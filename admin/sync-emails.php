<?php
/**
 * admin/sync-emails.php
 * AJAX endpoint — vai buscar emails recebidos ao Resend e cria tickets.
 * Pode também ser chamado por cron: GET /admin/sync-emails.php?secret=...
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Aceitar tanto sessão admin como token de cron
$isCron = false;
if (empty($_SESSION['admin_logged_in'] ?? '')) {
    $config    = json_decode(file_get_contents(__DIR__ . '/../admin-config.json'), true);
    $secret    = $config['webhook']['secret'] ?? '';
    $isCron    = ($secret !== '' && ($_GET['secret'] ?? '') === $secret);
    if (!$isCron) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
} else {
    // Sessão admin → forçar sync (ignorar throttle)
    $flagFile = '/var/www/html/db/.last_email_sync';
    @file_put_contents($flagFile, 0); // reset throttle para forçar sync
}

header('Content-Type: application/json; charset=utf-8');

$pdo    = get_db();
$result = sync_received_emails($pdo);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
