<?php
// proxy_mangofy.php
// Fazer upload para: newsbenefits.site/upup/1/proxy_mangofy.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['token']) || !isset($input['store']) || !isset($input['action'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Required: token, store, action']));
}

$endpoints = [
    'balance' => '/balance',
    'transactions' => '/all',
    'stats' => '/stats',
    'company' => '/company'
];

if (!isset($endpoints[$input['action']])) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid action']));
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://checkout.mangofy.com.br/api/v1/payment' . $endpoints[$input['action']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $input['token'],
        'Store-Code: ' . $input['store'],
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;
