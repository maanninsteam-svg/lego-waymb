<?php
/**
 * webhook/email-inbound.php
 *
 * Recebe emails enviados para support@legoworld2026.com via Resend Inbound.
 * Resend faz POST com JSON quando um email chega ao domínio.
 *
 * URL a configurar no Resend:
 *   https://legoworld2026.com/webhook/email-inbound.php?secret=SEU_WEBHOOK_SECRET
 */

// ── Verificar secret ──────────────────────────────────────────
$configPath = __DIR__ . '/../admin-config.json';
$config     = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];
$secret     = $config['webhook']['secret'] ?? '';

if ($secret !== '' && ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403);
    exit('Forbidden');
}

// ── Ler payload ───────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Log para debug (pode desativar em produção)
error_log('[email-inbound] payload: ' . substr($raw, 0, 500));

if (!$data) {
    http_response_code(400);
    exit('Bad payload');
}

// ── Normalizar payload do Resend ──────────────────────────────
// O Resend inbound pode enviar em dois formatos — normalizamos aqui.
// Formato A: { from, to, subject, text, html, ... }  (raiz)
// Formato B: { type: "email.received", data: { from, ... } }
$email = isset($data['data']) ? $data['data'] : $data;

// Extrair "from" (pode ser string "Nome <email>" ou objeto)
$fromRaw = $email['from'] ?? '';
if (is_array($fromRaw)) {
    $fromName  = $fromRaw['name']  ?? '';
    $fromEmail = $fromRaw['email'] ?? '';
} else {
    // Parsear "Nome <email@exemplo.com>"
    if (preg_match('/^(.*?)\s*<([^>]+)>$/', trim($fromRaw), $m)) {
        $fromName  = trim($m[1], ' "\'');
        $fromEmail = strtolower(trim($m[2]));
    } else {
        $fromName  = '';
        $fromEmail = strtolower(trim($fromRaw));
    }
}

$subject = $email['subject'] ?? '(sem assunto)';
$body    = $email['text']    ?? strip_tags($email['html'] ?? '');
$msgId   = $email['message_id'] ?? $email['messageId'] ?? md5($raw);

// Limpar corpo (remover quoted reply para ficar só a mensagem nova)
$body = trim(preg_replace('/\r\n/', "\n", $body));
// Remover tudo a partir de "Em ... escreveu:" / "On ... wrote:" (quoted text)
$body = preg_replace('/\n[-_]{2,}.*$/s', '', $body);
$body = preg_replace('/\nEm .{5,60} escreveu:.*$/si', '', $body);
$body = preg_replace('/\nOn .{5,60} wrote:.*$/si',     '', $body);
$body = trim($body);
if ($body === '') $body = '(sem conteúdo de texto)';

// Guardar contexto completo (HTML ou text) para a IA ter contexto do email original citado
$emailContext = $email['html'] ?? $email['text'] ?? '';

// Detectar assunto do pedido original a partir do subject "Re: ... Código: XXXXX"
$orderId = null;
if (preg_match('/[Cc]ódigo[:\s]+([A-Z0-9\-]{6,30})/u', $subject, $m)) {
    $orderId = $m[1];
}

// ── Guardar na BD ─────────────────────────────────────────────
require_once __DIR__ . '/../admin/includes/db.php';

$pdo = get_db();

// Verificar duplicado pelo message_id
$dup = $pdo->prepare("SELECT id FROM support_tickets WHERE email_message_id = :mid");
$dup->execute([':mid' => $msgId]);
if ($dup->fetch()) {
    http_response_code(200);
    exit('Already processed');
}

$stmt = $pdo->prepare("
    INSERT INTO support_tickets
        (order_id, name, email, subject, message, status, source, email_message_id, email_context, created_at)
    VALUES
        (:order_id, :name, :email, :subject, :message, 'open', 'email', :msg_id, :context, datetime('now'))
");
$stmt->execute([
    ':order_id' => $orderId,
    ':name'     => $fromName ?: $fromEmail,
    ':email'    => $fromEmail,
    ':subject'  => $subject,
    ':message'  => $body,
    ':msg_id'   => $msgId,
    ':context'  => $emailContext,
]);

http_response_code(200);
echo 'OK';
