<?php
/**
 * API: Criar cobrança PIX (Paradise Pags)
 * Contrato front: POST body { amount_cents, customer {}, product_hash?, utm_source?, preserved_query? }
 * - utm_source: obrigatório para XTracky (LeadId do script ou utm_source da URL).
 * - preserved_query: query string completa da URL (todos os parâmetros) para não perder nenhum no funil; devolvido na resposta.
 * Resposta 200: { qr_code, qr_code_base64, transaction_id, expires_at, preserved_query? }
 * amount = valor gerado pelo PIX (amount_cents). Envia waiting_payment para XTracky.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Config opcional: copie config.php.example para config.php e defina PARADISE_API_KEY e PARADISE_PRODUCT_HASH da sua loja
$configPath = __DIR__ . '/config.php';
if (is_file($configPath)) {
    require_once $configPath;
}

require_once __DIR__ . '/xtracky.php';

// Paradise Pags
define('PARADISE_API_URL', 'https://multi.paradisepags.com/api/v1/transaction.php');
if (!defined('PARADISE_API_KEY')) {
    define('PARADISE_API_KEY', 'sk_8a30d556e8cb4f1920f10e829d8b5485f38ad860bf7f4b6609c19af3d90c1964');
}
if (!defined('PARADISE_PRODUCT_HASH')) {
    define('PARADISE_PRODUCT_HASH', ''); // obrigatório: crie um produto no painel Paradise e defina em config.php
}

function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) $d += $cpf[$c] * (($t + 1) - $c);
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

function gerarCPF() {
    $n = array_fill(0, 9, 0);
    for ($i = 0; $i < 9; $i++) $n[$i] = rand(0, 9);
    $d1 = 11 - (($n[8]*2 + $n[7]*3 + $n[6]*4 + $n[5]*5 + $n[4]*6 + $n[3]*7 + $n[2]*8 + $n[1]*9 + $n[0]*10) % 11);
    if ($d1 >= 10) $d1 = 0;
    $d2 = 11 - (($d1*2 + $n[8]*3 + $n[7]*4 + $n[6]*5 + $n[5]*6 + $n[4]*7 + $n[3]*8 + $n[2]*9 + $n[1]*10 + $n[0]*11) % 11);
    if ($d2 >= 10) $d2 = 0;
    return implode('', $n) . $d1 . $d2;
}

function gerarQRCodeBase64($pixCode) {
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($pixCode);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && !empty($imageData)) {
        return 'data:image/png;base64,' . base64_encode($imageData);
    }
    return '';
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

$amount_cents = (int)($body['amount_cents'] ?? 0);
$customer = $body['customer'] ?? [];
$preserved_query = trim((string)($body['preserved_query'] ?? ''));
// utm_source: do body ou extraído da preserved_query (para nunca perder)
$utm_source = trim((string)($body['utm_source'] ?? ''));
if ($utm_source === '' && $preserved_query !== '') {
    parse_str($preserved_query, $q);
    $utm_source = trim((string)($q['utm_source'] ?? ''));
}
// Sempre priorizar config (evita front enviar hash de outra loja)
$product_hash = (defined('PARADISE_PRODUCT_HASH') && trim(PARADISE_PRODUCT_HASH) !== '') ? trim(PARADISE_PRODUCT_HASH) : (isset($body['product_hash']) && $body['product_hash'] !== '' ? trim($body['product_hash']) : '');

if ($amount_cents < 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Valor mínimo de R$ 1,00']);
    exit;
}

if ($product_hash === '') {
    http_response_code(400);
    echo json_encode([
        'error' => 'Produto PIX não configurado. Crie um produto no painel Paradise Pags (Produtos) e defina PARADISE_PRODUCT_HASH em ttk-clone/api/config.php (copie de config.php.example).',
    ]);
    exit;
}

// Mapeia customer do front para formato Paradise (nome, email, cpf, telefone)
$name = trim($customer['name'] ?? $customer['payerName'] ?? '');
if ($name === '') $name = 'Cliente';

$document = isset($customer['document']) ? preg_replace('/[^0-9]/', '', $customer['document']) : '';
if ($document === '' && !empty($customer['cpf'])) $document = preg_replace('/[^0-9]/', '', $customer['cpf']);
if ($document === '' || !validarCPF($document)) $document = gerarCPF();

$email = isset($customer['email']) ? filter_var(trim($customer['email']), FILTER_VALIDATE_EMAIL) : false;
if ($email === false) $email = 'cliente_' . uniqid() . '@ttk.online';

$phone = isset($customer['phone']) ? preg_replace('/[^0-9]/', '', $customer['phone']) : '';
if (strlen($phone) < 10) $phone = '11985232323';

$reference = 'TTK-' . time() . '-' . substr(uniqid(), -6);
$payload = [
    'amount' => $amount_cents,
    'description' => 'Contribuição de segurança',
    'reference' => $reference,
    'customer' => [
        'name' => $name,
        'email' => $email,
        'document' => $document,
        'phone' => $phone,
    ],
    'productHash' => $product_hash,
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => PARADISE_API_URL,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . PARADISE_API_KEY,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr !== '') {
    http_response_code(502);
    echo json_encode(['error' => 'Erro ao conectar ao gateway: ' . $curlErr]);
    exit;
}

$data = json_decode($response, true);
if ($httpCode !== 200 && $httpCode !== 201) {
    $msg = 'Erro ao gerar PIX';
    if (is_array($data)) {
        if (!empty($data['message'])) $msg = $data['message'];
        elseif (!empty($data['error'])) $msg = is_array($data['error']) ? json_encode($data['error']) : $data['error'];
    }
    // Dica quando o gateway rejeita o produto (hash de outra loja ou inválido)
    if (stripos($msg, 'produto') !== false && (stripos($msg, 'inválido') !== false || stripos($msg, 'bloqueado') !== false || stripos($msg, 'não pertencente') !== false)) {
        $msg .= ' Use um produto criado na SUA loja no painel Paradise Pags e defina PARADISE_PRODUCT_HASH em ttk-clone/api/config.php.';
    }
    http_response_code((int)$httpCode);
    echo json_encode(['error' => $msg]);
    exit;
}

if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['error' => 'Resposta inválida do gateway']);
    exit;
}

$pixCode = $data['qr_code'] ?? '';
$transaction_id = $data['transaction_id'] ?? $data['id'] ?? $reference;
$expires_at = $data['expires_at'] ?? date('c', time() + 10 * 60);

if ($pixCode === '') {
    http_response_code(502);
    echo json_encode(['error' => 'QR Code não retornado pelo gateway']);
    exit;
}

$qr_code_base64 = $data['qr_code_base64'] ?? '';
if ($qr_code_base64 === '' && $pixCode !== '') {
    $qr_code_base64 = gerarQRCodeBase64($pixCode);
}

// Formata CPF para leadDocument (XXX.XXX.XXX-XX) e telefone para leadPhone (+55...)
$docFormatted = strlen($document) === 11 ? substr($document, 0, 3) . '.' . substr($document, 3, 3) . '.' . substr($document, 6, 3) . '-' . substr($document, 9, 2) : $document;
$phoneFormatted = (strpos($phone, '+') !== 0 ? '+55' . $phone : $phone);

// XTracky: evento waiting_payment — amount = valor do PIX (centavos), demais campos conforme doc XTracky
$xtrackyPayload = [
    'orderId' => (string) $transaction_id,
    'amount' => (int) $amount_cents,
    'status' => 'waiting_payment',
    'platform' => 'CUSTOM',
    'utm_source' => $utm_source,
    'leadName' => $name,
    'leadEmail' => $email,
    'leadPhone' => $phoneFormatted,
    'leadDocument' => $docFormatted,
];
xtracky_send($xtrackyPayload);
xtracky_save_pending($transaction_id, array_merge($xtrackyPayload, ['preserved_query' => $preserved_query]));

// Resposta: preserved_query devolvido para o front manter na URL/redirect e não perder parâmetros
$response = [
    'qr_code' => $pixCode,
    'qr_code_base64' => $qr_code_base64,
    'transaction_id' => $transaction_id,
    'expires_at' => $expires_at,
];
if ($preserved_query !== '') {
    $response['preserved_query'] = $preserved_query;
}
http_response_code(200);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
