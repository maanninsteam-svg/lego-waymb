<?php
// verifyPayment.php - MangoFy API
header('Content-Type: application/json');

// Lê o JSON do front
$input = json_decode(file_get_contents('php://input'), true);
$paymentCode = $input['id'] ?? null;

if (!$paymentCode) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Payment code não informado'
    ]);
    exit;
}

// ================================
// CONFIG MANGOFY
// ================================
$MANGOFY_API_TOKEN = '22a13f6f48064863af2f412e8c1877b2blegxppxapplkmktuswuo58iyb5gu6m'; // mesmo token do pagamento.php
$MANGOFY_STORE_CODE = '5cc977b5311cbbd3cb5cb2809912444e'; // mesmo store code

// Endpoint correto para buscar pagamento
$URL = "https://checkout.mangofy.com.br/api/v1/payment/" . urlencode($paymentCode);

// Requisição
$ch = curl_init($URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: ' . $MANGOFY_API_TOKEN,
    'Store-Code: ' . $MANGOFY_STORE_CODE
]);
$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Valida resposta
$data = json_decode($response, true);

if (!is_array($data) || $http !== 200) {
    echo json_encode([
        'status' => 'error',
        'raw'    => $response
    ]);
    exit;
}

// Pega o status do pagamento
// MangoFy usa: approved, pending, refunded, error
$status = strtolower($data['payment_status'] ?? '');

// Mapeia para o formato esperado pelo JS
// JS espera: completed, paid, approved
$mappedStatus = $status;
if ($status === 'approved') {
    $mappedStatus = 'approved'; // já está correto
}

// Devolve no formato esperado pelo JS
echo json_encode([
    'status' => $mappedStatus,
    'raw' => $data
]);