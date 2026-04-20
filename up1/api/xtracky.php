<?php
/**
 * XTracky – Envio de conversões via API
 * Doc: https://api.xtracky.com (Envio de Conversões via API)
 *
 * utm_source: deve ser enviado no body do create-pix e mantido em todo o funil (incluindo
 * back-redirect). Será preenchido pelo script/pixel da XTracky no <head>; até lá, o front
 * pode capturar da URL e repassar no create-pix.
 *
 * Uso: require_once __DIR__ . '/xtracky.php';
 * - xtracky_send($payload)  → envia JSON para XTracky e retorna true/false
 * - xtracky_save_pending($transactionId, $payload) → salva payload para evento paid
 * - xtracky_load_and_send_paid($transactionId) → carrega payload, envia status paid, remove arquivo
 */

define('XTRACKY_API_URL', 'https://api.xtracky.com/api/integrations/api');
define('XTRACKY_PENDING_DIR', __DIR__ . '/xtracky_pending');

/** Campos aceitos pela API XTracky (envio exato conforme documentação). */
$GLOBALS['xtracky_allowed_keys'] = ['orderId', 'amount', 'status', 'utm_source', 'platform', 'leadName', 'leadEmail', 'leadPhone', 'leadDocument'];

/**
 * Envia payload para a API XTracky.
 * Apenas os campos aceitos pela doc são enviados (orderId, amount, status, utm_source, platform, lead*).
 *
 * @param array $payload
 * @return bool true se HTTP 200, false caso contrário
 */
function xtracky_send(array $payload) {
    $allowed = $GLOBALS['xtracky_allowed_keys'];
    $payload = array_intersect_key($payload, array_flip($allowed));
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $ch = curl_init(XTRACKY_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 200;
}

/**
 * Garante que o diretório de pending existe e retorna o path do arquivo para um transaction_id.
 *
 * @param string $transactionId
 * @return string path do arquivo
 */
function xtracky_pending_path($transactionId) {
    $dir = XTRACKY_PENDING_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $transactionId);
    if ($safe === '') {
        $safe = md5($transactionId);
    }
    return $dir . '/' . $safe . '.json';
}

/**
 * Salva o payload (para envio do evento paid depois).
 *
 * @param string $transactionId
 * @param array $payload mesmo formato enviado em waiting_payment (orderId, amount, utm_source, lead*)
 * @return bool
 */
function xtracky_save_pending($transactionId, array $payload) {
    $path = xtracky_pending_path($transactionId);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    return $json !== false && @file_put_contents($path, $json) !== false;
}

/**
 * Carrega o payload salvo, envia evento paid para XTracky e remove o arquivo.
 *
 * @param string $transactionId
 * @return bool true se enviou paid com sucesso (e removeu arquivo), false se não havia arquivo ou envio falhou
 */
function xtracky_load_and_send_paid($transactionId) {
    $path = xtracky_pending_path($transactionId);
    if (!is_file($path)) {
        return false;
    }
    $json = @file_get_contents($path);
    if ($json === false) {
        return false;
    }
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        @unlink($path);
        return false;
    }
    $payload['status'] = 'paid';
    $ok = xtracky_send($payload); // xtracky_send envia só os campos aceitos pela API
    @unlink($path);
    return $ok;
}
