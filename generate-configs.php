<?php
/**
 * generate-configs.php
 *
 * Executado pelo entrypoint do Docker ANTES de arrancar o Apache.
 * Gera todos os ficheiros de configuração JSON a partir das variáveis
 * de ambiente da Railway — nunca armazena credenciais no repositório.
 */

$base = '/var/www/html';

// ── waymb-config.json (raiz) ──────────────────────────────────
file_put_contents("{$base}/waymb-config.json", json_encode([
    'client_id' => getenv('WAYMB_CLIENT_ID') ?: '',
    'secret'    => getenv('WAYMB_SECRET')    ?: '',
    'email'     => getenv('WAYMB_EMAIL')     ?: '',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ── up1/waymb-config.json ─────────────────────────────────────
@mkdir("{$base}/up1", 0755, true);
file_put_contents("{$base}/up1/waymb-config.json", json_encode([
    'client_id' => getenv('WAYMB_CLIENT_ID') ?: '',
    'secret'    => getenv('WAYMB_SECRET')    ?: '',
    'email'     => getenv('WAYMB_EMAIL')     ?: '',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ── tracking_config.json ──────────────────────────────────────
file_put_contents("{$base}/tracking_config.json", json_encode([
    'tiktok' => [
        'pixel_id'        => getenv('TIKTOK_PIXEL_ID')     ?: '',
        'access_token'    => getenv('TIKTOK_ACCESS_TOKEN') ?: '',
        'test_event_code' => getenv('TIKTOK_TEST_CODE')    ?: '',
    ],
    'utmify' => [
        'api_url'   => 'https://api.utmify.com.br/api-credentials/orders',
        'api_token' => getenv('UTMIFY_API_TOKEN') ?: '',
        'platform'  => getenv('UTMIFY_PLATFORM')  ?: 'Tiktok',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// ── admin-config.json ─────────────────────────────────────────
// Preserva o password_hash existente (gravado após o primeiro login)
$adminPath = "{$base}/admin-config.json";
$existingHash = '';
if (file_exists($adminPath)) {
    $existing = json_decode(file_get_contents($adminPath), true);
    $existingHash = $existing['admin']['password_hash'] ?? '';
}

file_put_contents($adminPath, json_encode([
    'admin' => [
        'username'      => getenv('ADMIN_USERNAME') ?: 'admin',
        'password'      => '',        // sempre vazio — plaintext vem da env var
        'password_hash' => $existingHash,
    ],
    'resend' => [
        'api_key'    => getenv('RESEND_API_KEY')    ?: '',
        'from_name'  => getenv('RESEND_FROM_NAME')  ?: 'LEGO World Cup 2026',
        'from_email' => getenv('RESEND_FROM_EMAIL') ?: '',
    ],
    'anthropic' => [
        'api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
    ],
    'db' => [
        'path' => getenv('DB_PATH') ?: 'db/lego_store.db',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "[OK] Configurações geradas com sucesso.\n";
