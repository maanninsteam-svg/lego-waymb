<?php
/**
 * tracking.php
 *
 * Proxy server-side para 2 integrações independentes:
 *   1. UTMify  — apenas status `waiting_payment` e `paid` (dashboard de notificações de vendas)
 *   2. TikTok Events API v1.3 (Events API 2.0) — dispara `InitiateCheckout`, `AddPaymentInfo`,
 *      `PlaceAnOrder` (waiting_payment) e `CompletePayment` (paid) para alimentar otimização de anúncios
 *
 * Persistência inteligente:
 *   - Em `waiting_payment` salva o payload completo em .utmify_pending/{orderId}.json
 *   - Em `paid` recupera o pending, atualiza status/approvedDate e reenvia — não monta do zero
 *
 * Dedup TikTok: o `event_id` enviado é o mesmo que o browser disparou via `ttq.track(..., {event_id})`.
 * Para initiate_checkout/add_payment_info o browser usa `window.checkout_id` e o server recebe em $orderId.
 * Para waiting_payment/paid o browser usa `transactionId` e o server também.
 */

require_once __DIR__ . '/params_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ──────────────────────────────────────────────────────────────
// Configurações
// ──────────────────────────────────────────────────────────────

$config_path = __DIR__ . '/tracking_config.json';
$tracking_config = [];
if (file_exists($config_path)) {
    $tracking_config = json_decode(file_get_contents($config_path), true) ?: [];
}

// TikTok
$tt_pixel_id     = $tracking_config['tiktok']['pixel_id'] ?? '';
$tt_access_token = $tracking_config['tiktok']['access_token'] ?? '';
$tt_test_code    = $tracking_config['tiktok']['test_event_code'] ?? '';

// UTMify
$utmify_url      = $tracking_config['utmify']['api_url'] ?? 'https://api.utmify.com.br/api-credentials/orders';
$utmify_token    = $tracking_config['utmify']['api_token'] ?? '';
$utmify_platform = $tracking_config['utmify']['platform'] ?? 'Tiktok';

define('PENDING_DIR', __DIR__ . '/.utmify_pending');

// ──────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────

function utc_now_str(): string { return gmdate('Y-m-d H:i:s'); }

function pending_path(string $orderId): string {
    if (!is_dir(PENDING_DIR)) @mkdir(PENDING_DIR, 0755, true);
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $orderId);
    if ($safe === '') $safe = md5($orderId);
    return PENDING_DIR . '/' . $safe . '.json';
}

/**
 * Hash SHA256 lowercase+trim (padrão Meta/TikTok CAPI).
 * Retorna null quando vazio — nunca string vazia.
 */
function sha256_or_null($value): ?string {
    $v = strtolower(trim((string)$value));
    if ($v === '') return null;
    return hash('sha256', $v);
}

/**
 * Mapeia paymentMethod do gateway para valor aceito pela UTMify.
 * UTMify só aceita: credit_card | boleto | pix | paypal | free_price.
 * MBWay e Multibanco (europeus) → pix (equivalente instantâneo mais próximo).
 */
function utmify_payment_method(string $raw): string {
    $m = strtolower(trim($raw));
    $accepted = ['credit_card', 'boleto', 'pix', 'paypal', 'free_price'];
    if (in_array($m, $accepted, true)) return $m;
    if (in_array($m, ['mbway', 'multibanco'], true)) return 'pix';
    return 'pix';
}

/**
 * País ISO 3166-1 alfa-2. Tenta extrair do customer ou trackingParams; fallback PT (mercado europeu).
 */
function guess_country(array $customer, array $tp): ?string {
    $candidate = $customer['country'] ?? ($tp['country'] ?? null);
    if (!$candidate) return null;
    $c = strtoupper(trim((string)$candidate));
    return strlen($c) === 2 ? $c : null;
}

/**
 * Normaliza items recebidos do frontend em formato UTMify products[].
 */
function normalize_items_utmify(array $items): array {
    if (empty($items)) return [];
    $out = [];
    foreach ($items as $it) {
        $id    = (string)($it['id'] ?? 'item');
        $name  = (string)($it['name'] ?? 'Produto');
        $qty   = max(1, (int)($it['quantity'] ?? 1));
        $price = (float)($it['price'] ?? 0);
        $out[] = [
            'id'           => $id !== '' ? $id : 'item',
            'name'         => $name !== '' ? $name : 'Produto',
            'planId'       => null,
            'planName'     => null,
            'quantity'     => $qty,
            'priceInCents' => (int)round($price * 100),
        ];
    }
    return $out;
}

/**
 * Normaliza items para formato TikTok contents[].
 */
function normalize_items_tiktok(array $items): array {
    if (empty($items)) return [];
    $out = [];
    foreach ($items as $it) {
        $out[] = [
            'content_id'   => (string)($it['id'] ?? 'item'),
            'content_type' => 'product',
            'content_name' => (string)($it['name'] ?? 'Produto'),
            'quantity'     => max(1, (int)($it['quantity'] ?? 1)),
            'price'        => (float)($it['price'] ?? 0),
        ];
    }
    return $out;
}

// ──────────────────────────────────────────────────────────────
// UTMify sender
// ──────────────────────────────────────────────────────────────

function utmify_send(array $payload, string $url, string $token): array {
    if ($token === '') return [false, 0, null, 'MISSING_TOKEN'];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) return [false, 0, null, 'JSON_ENCODE_FAILED'];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-token: ' . $token,
        ],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $respJson = null;
    if (is_string($response) && $response !== '') {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) $respJson = $decoded;
    }
    $ok = ($err === '' && $http >= 200 && $http < 300);
    return [$ok, $http, $respJson, $err];
}

// ──────────────────────────────────────────────────────────────
// TikTok Events API 2.0 sender — schema v1.3 correto
// ──────────────────────────────────────────────────────────────

function tiktok_send_event(string $eventName, array $ctx): array {
    global $tt_pixel_id, $tt_access_token, $tt_test_code;

    if ($tt_pixel_id === '' || $tt_access_token === '') {
        return [false, 0, null, 'MISSING_CREDENTIALS'];
    }

    $customer = is_array($ctx['customer'] ?? null) ? $ctx['customer'] : [];
    $tp       = is_array($ctx['trackingParams'] ?? null) ? $ctx['trackingParams'] : [];
    $items    = is_array($ctx['items'] ?? null) ? $ctx['items'] : [];

    $user = [
        'email'       => sha256_or_null($customer['email'] ?? ''),
        'phone'       => sha256_or_null($customer['phone'] ?? ''),
        'external_id' => !empty($ctx['event_id']) ? hash('sha256', (string)$ctx['event_id']) : null,
        'ttclid'      => !empty($tp['ttclid']) ? (string)$tp['ttclid'] : ($_COOKIE['ttclid'] ?? null),
        'ttp'         => !empty($tp['ttp']) ? (string)$tp['ttp'] : ($_COOKIE['_ttp'] ?? null),
        'ip'          => client_ip(),
        'user_agent'  => client_user_agent($tp),
    ];
    // Remove chaves nulas/vazias
    $user = array_filter($user, fn($v) => $v !== null && $v !== '');

    $page = array_filter([
        'url'      => page_url($tp),
        'referrer' => page_referrer($tp),
    ], fn($v) => $v !== null && $v !== '');

    $contents = normalize_items_tiktok($items);
    if (empty($contents)) {
        $contents = [[
            'content_id'   => (string)($ctx['product_id'] ?? 'front-checkout'),
            'content_type' => 'product',
            'content_name' => (string)($ctx['product_name'] ?? 'Produto'),
            'quantity'     => 1,
            'price'        => (float)($ctx['amount'] ?? 0),
        ]];
    }

    $eventId = (string)($ctx['event_id'] ?? '');
    $dataEntry = [
        'event'      => $eventName,
        'event_time' => time(),
        'event_id'   => $eventId,
        'user'       => $user,
        'page'       => $page,
        'properties' => [
            'currency'     => 'EUR',
            'value'        => (float)($ctx['amount'] ?? 0),
            'content_type' => 'product',
            'contents'     => $contents,
            'order_id'     => $eventId,
        ],
    ];

    $payload = [
        'event_source'    => 'WEB',
        'event_source_id' => $tt_pixel_id,
        'data'            => [$dataEntry],
    ];
    if (!empty($tt_test_code)) {
        $payload['test_event_code'] = $tt_test_code;
    }

    $ch = curl_init('https://business-api.tiktok.com/open_api/v1.3/event/track/');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Access-Token: ' . $tt_access_token,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $respJson = null;
    if (is_string($response) && $response !== '') {
        $respJson = json_decode($response, true);
    }
    return [$http >= 200 && $http < 300, $http, $respJson, $err];
}

// ──────────────────────────────────────────────────────────────
// Parse request
// ──────────────────────────────────────────────────────────────

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$status = strtolower(trim((string)($body['status'] ?? 'waiting_payment')));
if (!in_array($status, ['initiate_checkout', 'add_payment_info', 'waiting_payment', 'paid'], true)) {
    $status = 'waiting_payment';
}

$orderId = trim((string)($body['transaction_id'] ?? $body['orderId'] ?? ''));
if ($orderId === '') $orderId = 'tmp-' . time();

$amount       = (float)($body['amount'] ?? 0);
$amountCents  = max(0, (int)round($amount * 100));
$customer     = is_array($body['customer'] ?? null) ? $body['customer'] : [];
$items        = is_array($body['items'] ?? null) ? $body['items'] : [];
$method       = (string)($body['method'] ?? 'pix');
$trackingP    = extract_tracking_params($body);

$createdAt    = utc_now_str();
$approvedDate = null;

// ──────────────────────────────────────────────────────────────
// Persistência inteligente — em 'paid' recupera o pending salvo em 'waiting_payment'
// ──────────────────────────────────────────────────────────────
$savedPending = null;
if ($status === 'paid') {
    $savedRaw = @file_get_contents(pending_path($orderId));
    $savedPending = is_string($savedRaw) ? json_decode($savedRaw, true) : null;
    if (is_array($savedPending)) {
        $createdAt   = (string)($savedPending['createdAt'] ?? $createdAt);
        if (!empty($savedPending['items']))        $items      = $savedPending['items'];
        if (!empty($savedPending['trackingP']))    $trackingP  = array_merge($savedPending['trackingP'] ?? [], $trackingP);
        if (!empty($savedPending['amount_cents'])) $amountCents = (int)$savedPending['amount_cents'];
        if (!empty($savedPending['amount']))       $amount     = (float)$savedPending['amount'];
        if (!empty($savedPending['method']))       $method     = (string)$savedPending['method'];
        // Preferir dados do pedido em waiting_payment; o body atual (paid) sobrescreve campos iguais
        $customer = array_merge($savedPending['customer'] ?? [], $customer);
    }
    $approvedDate = utc_now_str();
}

// ──────────────────────────────────────────────────────────────
// UTMify — dispara APENAS em waiting_payment ou paid (conforme doc)
// ──────────────────────────────────────────────────────────────
$utmifyResult = null;

if (in_array($status, ['waiting_payment', 'paid'], true)) {
    $utmifyProducts = normalize_items_utmify($items);
    if (empty($utmifyProducts)) {
        $utmifyProducts = [[
            'id' => 'front-checkout',
            'name' => 'Produto',
            'planId' => null,
            'planName' => null,
            'quantity' => 1,
            'priceInCents' => $amountCents,
        ]];
    }

    $customerPayload = [
        'name'     => !empty($customer['name'])  ? (string)$customer['name']  : 'Cliente',
        'email'    => !empty($customer['email']) ? (string)$customer['email'] : '',
        'phone'    => !empty($customer['phone']) ? (string)$customer['phone'] : null,
        'document' => !empty($customer['document']) ? (string)$customer['document'] : null,
    ];
    $country = guess_country($customer, $trackingP);
    if ($country) $customerPayload['country'] = $country;
    $ip = client_ip();
    if ($ip)      $customerPayload['ip'] = $ip;

    $utmifyPayload = [
        'orderId'            => $orderId,
        'platform'           => $utmify_platform,
        'paymentMethod'      => utmify_payment_method($method),
        'status'             => $status,
        'createdAt'          => $createdAt,
        'approvedDate'       => $approvedDate,
        'refundedAt'         => null,
        'customer'           => $customerPayload,
        'products'           => $utmifyProducts,
        'trackingParameters' => utmify_tracking_parameters($trackingP),
        'commission'         => [
            'totalPriceInCents'     => $amountCents,
            'gatewayFeeInCents'     => 0,
            'userCommissionInCents' => $amountCents,
            'currency'              => 'EUR',
        ],
        'isTest' => false,
    ];

    $utmifyResult = utmify_send($utmifyPayload, $utmify_url, $utmify_token);
}

// ──────────────────────────────────────────────────────────────
// TikTok CAPI — dispara em todos os 4 status (4 eventos distintos)
// ──────────────────────────────────────────────────────────────
$eventMap = [
    'initiate_checkout' => 'InitiateCheckout',
    'add_payment_info'  => 'AddPaymentInfo',
    'waiting_payment'   => 'PlaceAnOrder',
    'paid'              => 'CompletePayment',
];
$ttEvent = $eventMap[$status] ?? null;
$ttResult = null;

if ($ttEvent) {
    $ttResult = tiktok_send_event($ttEvent, [
        'event_id'       => $orderId,
        'amount'         => $amount,
        'customer'       => $customer,
        'items'          => $items,
        'trackingParams' => $trackingP,
        'product_id'     => !empty($items[0]['id']) ? (string)$items[0]['id'] : 'front-checkout',
        'product_name'   => !empty($items[0]['name']) ? (string)$items[0]['name'] : 'Produto',
    ]);
}

// ──────────────────────────────────────────────────────────────
// Persistência: em waiting_payment salva payload completo
// ──────────────────────────────────────────────────────────────
if ($status === 'waiting_payment') {
    $pending = [
        'createdAt'    => $createdAt,
        'customer'     => $customer,
        'items'        => $items,
        'trackingP'    => $trackingP,
        'amount'       => $amount,
        'amount_cents' => $amountCents,
        'method'       => $method,
    ];
    @file_put_contents(pending_path($orderId), json_encode($pending, JSON_UNESCAPED_UNICODE));
}

if ($status === 'paid' && $utmifyResult && $utmifyResult[0]) {
    @unlink(pending_path($orderId));
}

// ──────────────────────────────────────────────────────────────
// Admin DB persistence — upsert order into SQLite
// ──────────────────────────────────────────────────────────────
if (in_array($status, ['waiting_payment', 'paid'], true) && $orderId !== '') {
    try {
        require_once __DIR__ . '/admin/includes/db.php';
        $pdo = get_db();
        $pdo->prepare("
            INSERT INTO orders
                (order_id, status, customer_name, customer_email, customer_phone,
                 customer_address, customer_postal, customer_city, customer_district,
                 customer_country, items_json, amount, method, created_at, paid_at, updated_at)
            VALUES
                (:order_id, :status, :name, :email, :phone,
                 :address, :postal, :city, :district,
                 :country, :items_json, :amount, :method, :created_at, :paid_at, datetime('now'))
            ON CONFLICT(order_id) DO UPDATE SET
                status     = CASE WHEN orders.status = 'shipped' THEN 'shipped' ELSE excluded.status END,
                paid_at    = CASE WHEN excluded.status = 'paid' THEN excluded.paid_at ELSE orders.paid_at END,
                updated_at = datetime('now')
        ")->execute([
            ':order_id'  => $orderId,
            ':status'    => $status,
            ':name'      => $customer['name'] ?? null,
            ':email'     => $customer['email'] ?? null,
            ':phone'     => $customer['phone'] ?? null,
            ':address'   => $customer['address'] ?? null,
            ':postal'    => $customer['postal'] ?? null,
            ':city'      => $customer['city'] ?? null,
            ':district'  => $customer['district'] ?? null,
            ':country'   => $customer['country'] ?? 'PT',
            ':items_json'=> json_encode($items, JSON_UNESCAPED_UNICODE),
            ':amount'    => $amount,
            ':method'    => $method,
            ':created_at'=> $createdAt,
            ':paid_at'   => $approvedDate,
        ]);
    } catch (Throwable $e) {
        // DB errors are non-fatal — tracking continues regardless
        error_log('Admin DB upsert failed: ' . $e->getMessage());
    }
}

// ──────────────────────────────────────────────────────────────
// Resposta
// ──────────────────────────────────────────────────────────────
$utmifyOk = $utmifyResult ? $utmifyResult[0] : null;
$ttOk     = $ttResult ? $ttResult[0] : null;

$ttApiMsg = null;
if ($ttResult && is_array($ttResult[2])) {
    $ttApiMsg = $ttResult[2]['message'] ?? ($ttResult[2]['msg'] ?? null);
}

$integrationsOk = true;
if ($utmifyResult !== null && !$utmifyResult[0]) {
    $integrationsOk = false;
}
if ($ttResult !== null && !$ttResult[0]) {
    $integrationsOk = false;
}

echo json_encode([
    'ok'                       => $integrationsOk,
    'status'                   => $status,
    'utmify'                   => $utmifyResult ? [
        'ok'    => $utmifyResult[0],
        'http'  => $utmifyResult[1],
        'error' => $utmifyResult[3] !== '' ? $utmifyResult[3] : null,
    ] : 'skipped',
    'tiktok'                   => $ttResult ? [
        'ok'         => $ttResult[0],
        'http'       => $ttResult[1],
        'event'      => $ttEvent,
        'error'      => $ttResult[3] !== '' ? $ttResult[3] : null,
        'api_message'=> $ttApiMsg,
    ] : 'skipped',
    'recovered_from_pending'   => $status === 'paid' && is_array($savedPending),
], JSON_UNESCAPED_UNICODE);
exit;
