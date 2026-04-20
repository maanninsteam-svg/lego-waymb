<?php
/**
 * webhook/waymb-paid.php
 *
 * Recebe o webhook da WayMB quando um depósito é confirmado como PAGO.
 * Dispara o evento "paid" no UTMify + TikTok CAPI, lendo os dados de
 * tracking guardados em .utmify_pending/{orderId}.json pelo browser.
 *
 * Configurar na WayMB → Configurações → Integrações:
 *   Webhook — Depósito Pago:
 *   https://legoworld2026.com/webhook/waymb-paid.php
 */

// Log do payload recebido (útil para debug inicial)
$raw = file_get_contents('php://input');
error_log('[waymb-paid] payload: ' . substr($raw, 0, 500));

// Tentar parse JSON ou form-encoded
$data = json_decode($raw, true);
if (!$data && !empty($_POST)) {
    $data = $_POST;
}
if (!$data) {
    http_response_code(400);
    exit('Bad payload');
}

// Extrair transaction ID — WayMB pode usar 'id' ou 'transaction_id'
$txnId = trim((string)(
    $data['id']             ??
    $data['transaction_id'] ??
    $data['order_id']       ??
    ''
));

if ($txnId === '') {
    http_response_code(400);
    error_log('[waymb-paid] transaction ID ausente. Keys: ' . implode(',', array_keys($data)));
    exit('Missing transaction ID');
}

// Verificar se existe ficheiro pending para este pedido
$pendingPath = '/var/www/html/.utmify_pending/' . preg_replace('/[^A-Za-z0-9\-_]/', '', $txnId) . '.json';
if (!file_exists($pendingPath)) {
    // Sem dados de tracking (talvez já tenha sido processado pelo browser)
    error_log('[waymb-paid] sem pending para ' . $txnId . ' — provavelmente já processado');
    http_response_code(200);
    exit('Already processed');
}

// Chamar o nosso próprio tracking.php com status=paid via cURL interno
// tracking.php lê o ficheiro pending para obter UTMs, customer, items, etc.
$trackingUrl = 'http://127.0.0.1/tracking.php';

// Construir payload mínimo — tracking.php completa com dados do pending
$payload = json_encode([
    'status'         => 'paid',
    'order_id'       => $txnId,
    'transaction_id' => $txnId,
    'amount'         => (float)($data['amount'] ?? 0),
    'method'         => strtolower((string)($data['method'] ?? $data['payment_method'] ?? 'mbway')),
    'source'         => 'waymb_webhook',
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($trackingUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Forwarded-For: ' . ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? ''),
        'X-WayMB-Webhook: 1',
    ],
    CURLOPT_TIMEOUT        => 20,
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    error_log('[waymb-paid] curl error calling tracking.php: ' . $curlErr);
} else {
    error_log('[waymb-paid] tracking.php responded HTTP ' . $httpCode . ': ' . substr($response, 0, 200));
}

http_response_code(200);
echo 'OK';
