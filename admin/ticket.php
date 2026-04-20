<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_auth();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

$ticketId = (int)($_GET['id'] ?? 0);
if ($ticketId <= 0) {
    header('Location: /admin/tickets.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = :id");
$stmt->execute([':id' => $ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: /admin/tickets.php?error=not_found');
    exit;
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($postToken)) {
        $error = 'Token de segurança inválido. Tente novamente.';
    } else {
        $reply     = trim($_POST['admin_reply'] ?? '');
        $markResolved = !empty($_POST['mark_resolved']);

        $newStatus  = $ticket['status'];
        $resolvedAt = $ticket['resolved_at'];

        if ($markResolved) {
            $newStatus  = 'resolved';
            $resolvedAt = date('Y-m-d H:i:s');
        }

        $upd = $pdo->prepare("
            UPDATE support_tickets
            SET admin_reply  = :reply,
                status       = :status,
                resolved_at  = :resolved_at
            WHERE id = :id
        ");
        $upd->execute([
            ':reply'       => $reply !== '' ? $reply : null,
            ':status'      => $newStatus,
            ':resolved_at' => $resolvedAt,
            ':id'          => $ticketId,
        ]);

        // Reload
        $stmt->execute([':id' => $ticketId]);
        $ticket = $stmt->fetch();

        $success = $markResolved
            ? 'Ticket marcado como resolvido e resposta guardada.'
            : 'Resposta guardada com sucesso.';
    }
}

$csrfToken = generate_csrf();
$pageTitle = 'Ticket #' . $ticketId;

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Ticket #<?= (int)$ticketId ?></h2>
        <p>
            <a href="/admin/tickets.php" style="color:#64748b;text-decoration:none;">&#8592; Voltar aos Tickets</a>
        </p>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <?php if (($ticket['source'] ?? 'form') === 'email'): ?>
            <span style="background:#ede9fe;color:#5b21b6;padding:5px 12px;border-radius:12px;font-size:12px;font-weight:600;">&#9993; Via Email</span>
        <?php endif; ?>
        <?php if ($ticket['status'] === 'open'): ?>
            <span style="background:#fef3c7;color:#92400e;padding:5px 14px;border-radius:12px;font-size:13px;font-weight:600;">Aberto</span>
        <?php else: ?>
            <span style="background:#d1fae5;color:#065f46;padding:5px 14px;border-radius:12px;font-size:13px;font-weight:600;">Resolvido</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<!-- Ticket info -->
<div class="detail-grid">
    <div class="card">
        <div class="card-title">&#128100; Informações do Contacto</div>
        <div class="detail-row">
            <span class="label">Nome</span>
            <span class="value"><?= h($ticket['name']) ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Email</span>
            <span class="value"><?= h($ticket['email']) ?></span>
        </div>
        <div class="detail-row">
            <span class="label">ID do Pedido</span>
            <span class="value">
                <?php if ($ticket['order_id']): ?>
                    <a href="/admin/order.php?id=<?= urlencode($ticket['order_id']) ?>" style="color:#2563eb;">
                        <?= h($ticket['order_id']) ?>
                    </a>
                <?php else: ?>
                    —
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-row">
            <span class="label">Data</span>
            <span class="value"><?= h(format_date($ticket['created_at'])) ?></span>
        </div>
        <?php if ($ticket['resolved_at']): ?>
        <div class="detail-row">
            <span class="label">Resolvido em</span>
            <span class="value"><?= h(format_date($ticket['resolved_at'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title">&#128172; Mensagem</div>
        <div class="detail-row">
            <span class="label">Assunto</span>
            <span class="value" style="font-weight:600;"><?= h($ticket['subject']) ?></span>
        </div>
        <div class="detail-row" style="margin-top:8px;">
            <span class="label">Mensagem</span>
            <div class="value" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-top:6px;line-height:1.7;white-space:pre-wrap;font-size:14px;">
                <?= h($ticket['message']) ?>
            </div>
        </div>
    </div>
</div>

<!-- Reply / resolve form -->
<div class="card">
    <div class="card-title">
        <?= $ticket['admin_reply'] ? '&#9998; Editar Resposta' : '&#128231; Responder' ?>
    </div>

    <form method="POST" action="/admin/ticket.php?id=<?= $ticketId ?>">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

        <div class="form-group">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <label for="admin_reply" style="margin:0;">Resposta (nota interna / resposta ao cliente)</label>
                <button type="button" id="btnAiReply"
                        onclick="generateAiReply(<?= $ticketId ?>)"
                        style="background:#6366f1;color:#fff;border:none;border-radius:8px;padding:7px 14px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
                    <span id="aiReplyIcon">&#10024;</span>
                    <span id="aiReplyLabel">Gerar com IA</span>
                </button>
            </div>
            <textarea id="admin_reply" name="admin_reply" class="form-control" rows="6"
                      placeholder="Escreva aqui a sua resposta ou nota interna..."><?= h($ticket['admin_reply'] ?? '') ?></textarea>
            <div id="aiReplyError" style="color:#dc2626;font-size:13px;margin-top:6px;display:none;"></div>
        </div>

        <?php if ($ticket['status'] === 'open'): ?>
        <div class="form-group" style="display:flex;align-items:center;gap:10px;">
            <input type="checkbox" id="mark_resolved" name="mark_resolved" value="1" style="width:18px;height:18px;cursor:pointer;">
            <label for="mark_resolved" style="margin:0;cursor:pointer;font-size:14px;font-weight:500;">
                Marcar como resolvido
            </label>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>

<script>
async function generateAiReply(ticketId) {
    const btn   = document.getElementById('btnAiReply');
    const label = document.getElementById('aiReplyLabel');
    const icon  = document.getElementById('aiReplyIcon');
    const errEl = document.getElementById('aiReplyError');
    const ta    = document.getElementById('admin_reply');

    btn.disabled = true;
    label.textContent = 'A gerar…';
    icon.textContent  = '⏳';
    errEl.style.display = 'none';

    try {
        const res  = await fetch('/admin/ai-reply.php?id=' + ticketId);
        const data = await res.json();

        if (data.error) {
            errEl.textContent   = data.error;
            errEl.style.display = 'block';
        } else {
            ta.value = data.reply;
            ta.focus();
        }
    } catch (e) {
        errEl.textContent   = 'Erro de ligação ao servidor.';
        errEl.style.display = 'block';
    } finally {
        btn.disabled      = false;
        label.textContent = 'Gerar com IA';
        icon.textContent  = '✨';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
