<?php
function _start_session(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // Guardar sessões no volume persistente (mesmo directório da BD)
    // para sobreviverem a restarts do container na Railway.
    $sessDir = '/var/www/html/db/sessions';
    if (!is_dir($sessDir)) @mkdir($sessDir, 0750, true);
    ini_set('session.save_path',      $sessDir);
    ini_set('session.gc_maxlifetime', 604800);   // 7 dias
    ini_set('session.cookie_lifetime', 604800);  // 7 dias
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure',   1);
    session_start();
}

function require_admin_auth(): void {
    _start_session();
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: /admin/index.php');
        exit;
    }
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: /admin/index.php');
        exit;
    }
}

function generate_csrf(): string {
    _start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    _start_session();
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
