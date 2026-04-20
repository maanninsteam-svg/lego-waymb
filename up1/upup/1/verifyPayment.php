<?php
// verifyPayment.php - Paradise API Integration
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$transactionId = $input['id'] ?? null;

if (!$transactionId) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID da transação não fornecido'
    ]);
    exit;
}

$API_KEY = 'sk_8a30d556e8cb4f1920f10e829d8b5485f38ad860bf7f4b6609c19af3d90c1964';
$API_URL = "https://multi.paradisepags.com/api/v1/query.php?action=get_transaction&id=" . urlencode($transactionId);

$ch = curl_init($API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "X-API-Key: {$API_KEY}"
    ],
    CURLOPT_TIMEOUT        => 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

if ($error || $httpCode !== 200) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao verificar pagamento na Paradise',
        'raw' => $response,
        'curl_error' => $error
    ]);
    exit;
}

$data = json_decode($response, true);
$status = $data['status'] ?? 'pending';

// A Paradise API retorna os status: pending, approved, failed, refunded.
// Nós retornamos o mesmo formato pro frontend.
echo json_encode([
    'status' => $status,
    'raw' => $data
]);