<?php
/**
 * Webhook (postback) do gateway Paradise Pags.
 *
 * Configure no painel do gateway a URL de postback usando o domínio do seu site.
 * Exemplo (troque pelo seu domínio):
 *
 *   https://meusite.com/api/webhook-paradise.php
 *
 * Se o cliente trocar de domínio, altere apenas a URL no painel do gateway —
 * não é preciso mudar código.
 *
 * O gateway envia POST quando o status da transação muda. Este script responde 200 OK
 * e, em caso de pagamento aprovado, dispara o evento "paid" para a XTracky (mesmo
 * payload do waiting_payment, identificado por orderId = transaction_id).
 *
 * Polling (check-pix) continua ativo para o front atualizar a tela; o primeiro a
 * processar (webhook ou check-pix) envia o paid e remove o pending — o segundo não
 * reenvia (proteção contra duplicata).
 */
require_once __DIR__ . '/xtracky.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$transaction_id = isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : '';
$status = isset($payload['status']) ? strtolower(trim((string) $payload['status'])) : '';

// approved = pagamento confirmado → enviar evento paid para XTracky
if ($transaction_id !== '' && $status === 'approved') {
    xtracky_load_and_send_paid($transaction_id);
}

// Sempre 200 para o gateway não reenviar
http_response_code(200);
echo json_encode(['ok' => true]);
