<?php
/**
 * API: Verificar pagamento PIX (Paradise Pags)
 * Contrato front: POST body { transaction_id }
 * Resposta 200: { status: "pending" | "paid" | "approved" | "confirmed" }
 * Ao confirmar pagamento, envia evento paid para XTracky (payload salvo no create-pix).
 */
require_once __DIR__ . '/xtracky.php';
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

define('PARADISE_QUERY_URL', 'https://multi.paradisepags.com/api/v1/query.php');
define('PARADISE_API_KEY', 'sk_8a30d556e8cb4f1920f10e829d8b5485f38ad860bf7f4b6609c19af3d90c1964');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

$transaction_id = trim($body['transaction_id'] ?? '');

if ($transaction_id === '') {
    http_response_code(200);
    echo json_encode(['status' => 'pending', 'transaction_id' => '']);
    exit;
}

$url = PARADISE_QUERY_URL . '?action=get_transaction&id=' . urlencode($transaction_id);
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-API-Key: ' . PARADISE_API_KEY,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || $response === false || $response === '') {
    http_response_code(200);
    echo json_encode(['status' => 'pending', 'transaction_id' => $transaction_id]);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    http_response_code(200);
    echo json_encode(['status' => 'pending', 'transaction_id' => $transaction_id]);
    exit;
}

// Paradise: pending | approved | failed | refunded → front: pending | paid | approved | confirmed
$paradiseStatus = strtolower(trim($data['status'] ?? 'pending'));
$status = 'pending';
if ($paradiseStatus === 'approved') {
    $status = 'paid';
} elseif (in_array($paradiseStatus, ['paid', 'confirmed'], true)) {
    $status = $paradiseStatus;
}

// XTracky: evento paid (mesmo payload do waiting_payment, identificado por orderId)
if (in_array($status, ['paid', 'approved', 'confirmed'], true)) {
    xtracky_load_and_send_paid($transaction_id);
}

http_response_code(200);
echo json_encode([
    'status' => $status,
    'transaction_id' => $transaction_id,
], JSON_UNESCAPED_UNICODE);
