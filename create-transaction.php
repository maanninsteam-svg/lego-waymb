<?php
/**
 * Proxy local para WayMB (criar transacao e consultar status).
 * - Default de criacao: multibanco (mais estavel neste funil)
 * - Sem tracking aqui (xtracky permanece separado no projeto)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/central.php';

$_wcfg = [];
$_wcfgFile = __DIR__ . '/waymb-config.json';
if (file_exists($_wcfgFile)) {
    $_wcfg = json_decode(file_get_contents($_wcfgFile), true) ?: [];
}

$client_id = $_wcfg['client_id'] ?? 'batman_e20135bb';
$client_secret = $_wcfg['secret'] ?? '2e42298b-1d55-46c5-8db0-77c904a451ae';
$account_email = $_wcfg['email'] ?? 'empreendimentodigitalmexico@gmail.com';

$action = $_GET['action'] ?? 'create';
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

function json_out($code, $payload) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function waymb_post($url, $payload) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    return [$httpCode, $response, $curlErr];
}

if ($action === 'status' || $action === 'info') {
    $transaction_id = trim((string)($data['id'] ?? $_GET['id'] ?? ''));
    if ($transaction_id === '') {
        json_out(400, ['error' => 'Missing transaction ID']);
    }

    [$httpCode, $response, $curlErr] = waymb_post(
        'https://api.waymb.com/transactions/info',
        ['id' => $transaction_id]
    );

    if ($curlErr !== '') {
        json_out(502, ['error' => 'Erro ao consultar status no gateway', 'details' => $curlErr]);
    }

    $resp = json_decode((string)$response, true);
    if (!is_array($resp)) {
        json_out($httpCode > 0 ? $httpCode : 502, ['error' => 'Resposta invalida do gateway']);
    }

    $rawStatus = strtoupper(trim((string)($resp['status'] ?? '')));
    $status = 'pending';
    if (in_array($rawStatus, ['PAID', 'APPROVED', 'COMPLETED', 'CONFIRMED'], true)) {
        $status = 'paid';
    } elseif (in_array($rawStatus, ['FAILED', 'CANCELED', 'CANCELLED', 'EXPIRED', 'REFUNDED'], true)) {
        $status = 'failed';
    }

    json_out(200, [
        'status' => $status,
        'raw_status' => $rawStatus,
        'transaction_id' => $transaction_id,
        'gateway_response' => $resp
    ]);
}

$stage = strtolower(trim((string)($data['stage'] ?? '')));
$amount = (float)($data['amount'] ?? 0);
if ($stage !== '') {
    $amount = central_price($stage, $amount);
}
if ($amount <= 0) {
    $amount = central_price('front', 13.97);
}

$method = strtolower(trim((string)($data['method'] ?? 'multibanco')));
if ($method === '') {
    $method = 'multibanco';
}

$payer = $data['payer'] ?? [];
if (!is_array($payer)) {
    $payer = [];
}

// Não forçar CPF/document: deixa o gateway validar apenas os dados enviados.
if (array_key_exists('document', $payer)) {
    $doc = trim((string)$payer['document']);
    if ($doc === '') {
        unset($payer['document']);
    }
}

$payload = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'account_email' => $account_email,
    'amount' => $amount,
    'method' => $method,
    'payer' => $payer,
    'currency' => 'EUR',
    'callbackUrl' => $data['callbackUrl'] ?? '',
    'success_url' => $data['success_url'] ?? '',
    'failed_url' => $data['failed_url'] ?? ''
];

[$httpCode, $response, $curlErr] = waymb_post('https://api.waymb.com/transactions/create', $payload);

if ($curlErr !== '') {
    json_out(502, ['error' => 'Erro ao criar transacao no gateway', 'details' => $curlErr]);
}

$resp = json_decode((string)$response, true);
if (!is_array($resp)) {
    json_out($httpCode > 0 ? $httpCode : 502, ['error' => 'Resposta invalida do gateway']);
}

$id = $resp['id'] ?? $resp['transaction_id'] ?? null;
if (!$id) {
    json_out($httpCode > 0 ? $httpCode : 502, ['error' => 'Gateway nao retornou ID da transacao', 'gateway_response' => $resp]);
}

json_out(200, $resp);

