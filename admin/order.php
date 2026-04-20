<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_auth();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = get_db();

$orderId = trim($_GET['id'] ?? '');
if ($orderId === '') {
    header('Location: /admin/dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = :id");
$stmt->execute([':id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /admin/dashboard.php?error=not_found');
    exit;
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($postToken)) {
        $error = 'Token de segurança inválido. Tente novamente.';
    } else {
        $trackingCode = trim($_POST['tracking_code'] ?? '');
        if ($trackingCode === '') {
            $error = 'O código de rastreio não pode estar vazio.';
        } else {
            $emailSent   = false;
            $sentAt      = null;

            // Try to send email
            $items = [];
            if (!empty($order['items_json'])) {
                $items = json_decode($order['items_json'], true) ?: [];
            }
            $emailSent = send_tracking_email(
                $order['customer_email'] ?? '',
                $order['customer_name']  ?? 'Cliente',
                $trackingCode,
                $items,
                (float)$order['amount']
            );
            if ($emailSent) {
                $sentAt = date('Y-m-d H:i:s');
            }

            // Save to DB
            $upd = $pdo->prepare("
                UPDATE orders
                SET tracking_code    = :code,
                    status           = 'shipped',
                    tracking_sent_at = :sent_at,
                    updated_at       = datetime('now')
                WHERE order_id = :id
            ");
            $upd->execute([
                ':code'    => $trackingCode,
                ':sent_at' => $sentAt,
                ':id'      => $orderId,
            ]);

            // Reload
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch();

            if ($emailSent) {
                $success = 'Código de rastreio guardado e email enviado com sucesso!';
            } else {
                $success = 'Código de rastreio guardado. Nota: o email não pôde ser enviado (verifique os logs).';
            }
        }
    }
}

$items = [];
if (!empty($order['items_json'])) {
    $items = json_decode($order['items_json'], true) ?: [];
}

$csrfToken = generate_csrf();
$pageTitle = 'Pedido ' . substr($orderId, 0, 10) . '…';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Detalhe do Pedido</h2>
        <p>
            <a href="/admin/dashboard.php" style="color:#64748b;text-decoration:none;">&#8592; Voltar ao Dashboard</a>
        </p>
    </div>
    <div><?= status_badge($order['status']) ?></div>
</div>

<?php if ($success !== ''): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="detail-grid">
    <!-- Customer info -->
    <div class="card">
        <div class="card-title">&#128100; Informações do Cliente</div>
        <div class="detail-row">
            <span class="label">Nome</span>
            <span class="value"><?= h($order['customer_name'] ?? '—') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Email</span>
            <span class="value"><?= h($order['customer_email'] ?? '—') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Telefone</span>
            <span class="value"><?= h($order['customer_phone'] ?? '—') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Morada</span>
            <span class="value"><?= h($order['customer_address'] ?? '—') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Código Postal</span>
            <span class="value"><?= h($order['customer_postal'] ?? '—') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Cidade</span>
            <span class="value"><?= h($order['customer_city'] ?? '—') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Distrito</span>
            <span class="value"><?= h($order['customer_district'] ?? '—') ?></span>
        </div>
        <div class="detail-row">
            <span class="label">País</span>
            <span class="value"><?= h($order['customer_country'] ?? 'PT') ?></span>
        </div>
    </div>

    <!-- Payment info -->
    <div class="card">
        <div class="card-title">&#128179; Informações do Pagamento</div>
        <div class="detail-row">
            <span class="label">ID do Pedido</span>
            <span class="value"><code style="font-size:12px;"><?= h($order['order_id']) ?></code></span>
        </div>
        <div class="detail-row">
            <span class="label">Data de Criação</span>
            <span class="value"><?= h(format_date($order['created_at'])) ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Data de Pagamento</span>
            <span class="value"><?= h(format_date($order['paid_at'])) ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Método de Pagamento</span>
            <span class="value"><?= h(method_label($order['method'] ?? '')) ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Total</span>
            <span class="value" style="font-size:18px;font-weight:700;color:#059669;"><?= h(format_money((float)$order['amount'])) ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Status</span>
            <span class="value"><?= status_badge($order['status']) ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Última Atualização</span>
            <span class="value"><?= h(format_date($order['updated_at'])) ?></span>
        </div>
    </div>
</div>

<!-- Items table -->
<div class="card">
    <div class="card-title">&#128722; Itens do Pedido</div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Preço Unit.</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4" style="color:#94a3b8;text-align:center;padding:20px;">Sem itens registados.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= h($item['name'] ?? '—') ?></td>
                        <td><?= (int)($item['quantity'] ?? 1) ?></td>
                        <td><?= h(format_money((float)($item['price'] ?? 0))) ?></td>
                        <td style="font-weight:600;"><?= h(format_money((float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;font-weight:700;padding:12px 14px;">Total</td>
                    <td style="font-weight:700;font-size:16px;padding:12px 14px;"><?= h(format_money((float)$order['amount'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Tracking -->
<div class="card">
    <div class="card-title">&#128230; Rastreio de Envio</div>

    <?php if ($order['tracking_code']): ?>
        <div class="alert alert-info" style="margin-bottom:20px;">
            <strong>Código atual:</strong> <?= h($order['tracking_code']) ?><br>
            <?php if ($order['tracking_sent_at']): ?>
                <span style="font-size:13px;">&#10003; Email enviado em <?= h(format_date($order['tracking_sent_at'])) ?></span>
            <?php else: ?>
                <span style="font-size:13px;color:#92400e;">&#10007; Email de rastreio não enviado</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/order.php?id=<?= urlencode($orderId) ?>">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="form-group">
            <label for="tracking_code">
                <?= $order['tracking_code'] ? 'Atualizar Código de Rastreio' : 'Código de Rastreio' ?>
            </label>
            <input type="text" id="tracking_code" name="tracking_code" class="form-control"
                   placeholder="Ex: RR123456789PT"
                   value="<?= h($order['tracking_code'] ?? '') ?>"
                   style="max-width:360px;">
        </div>
        <button type="submit" class="btn btn-primary">
            &#128231; Salvar e Enviar Email
        </button>
        <?php if ($order['tracking_code']): ?>
            <p style="font-size:12px;color:#64748b;margin-top:8px;">
                Ao salvar, o status será marcado como <strong>Enviado</strong> e um email será enviado ao cliente.
            </p>
        <?php endif; ?>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
