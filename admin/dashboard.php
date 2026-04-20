<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_auth();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

// ── Stats ──────────────────────────────────────────────────────
$statsTotal   = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$statsPaid    = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('paid','shipped')")->fetchColumn();
$statsWaiting = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='waiting_payment'")->fetchColumn();
$statsRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','shipped')")->fetchColumn();

// ── Filters ────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($filterStatus !== '') {
    $where[]  = 'status = :status';
    $params[':status'] = $filterStatus;
}
if ($search !== '') {
    $where[]  = '(customer_name LIKE :search OR customer_email LIKE :search OR order_id LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders {$whereClause}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$stmt = $pdo->prepare("SELECT * FROM orders {$whereClause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

$pageTitle = 'Dashboard';

// Build query string helper
function buildQuery(array $override = []): string {
    $base = [
        'status' => $_GET['status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'page'   => $_GET['page']   ?? 1,
    ];
    $merged = array_merge($base, $override);
    $filtered = array_filter($merged, fn($v) => $v !== '' && $v !== 0 && $v !== '0');
    return $filtered ? '?' . http_build_query($filtered) : '';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p>Visão geral das encomendas</p>
    </div>
</div>

<!-- Stat cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Total Pedidos</div>
        <div class="stat-value"><?= $statsTotal ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Receita Total</div>
        <div class="stat-value accent"><?= format_money($statsRevenue) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pagos / Enviados</div>
        <div class="stat-value green"><?= $statsPaid ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Aguardando Pagamento</div>
        <div class="stat-value"><?= $statsWaiting ?></div>
    </div>
</div>

<!-- Filter bar -->
<form method="GET" action="/admin/dashboard.php">
    <div class="filter-bar">
        <input type="text" name="search" placeholder="Buscar por nome, email ou ID..." value="<?= h($search) ?>" style="min-width:260px;">
        <select name="status">
            <option value="">Todos os status</option>
            <option value="waiting_payment" <?= $filterStatus === 'waiting_payment' ? 'selected' : '' ?>>Aguardando</option>
            <option value="paid"            <?= $filterStatus === 'paid'            ? 'selected' : '' ?>>Pago</option>
            <option value="shipped"         <?= $filterStatus === 'shipped'         ? 'selected' : '' ?>>Enviado</option>
        </select>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($search !== '' || $filterStatus !== ''): ?>
            <a href="/admin/dashboard.php" class="btn btn-secondary">Limpar</a>
        <?php endif; ?>
    </div>
</form>

<!-- Orders table -->
<div class="card" style="padding:0;overflow:hidden;">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID Pedido</th>
                    <th>Data</th>
                    <th>Cliente</th>
                    <th>Email</th>
                    <th>Valor</th>
                    <th>Método</th>
                    <th>Status</th>
                    <th>Rastreio</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:32px;">Nenhum pedido encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <code style="font-size:11px;color:#475569;"><?= h(substr($order['order_id'], 0, 12)) ?>…</code>
                        </td>
                        <td style="white-space:nowrap;"><?= h(format_date($order['created_at'])) ?></td>
                        <td><?= h($order['customer_name'] ?? '—') ?></td>
                        <td style="font-size:13px;"><?= h($order['customer_email'] ?? '—') ?></td>
                        <td style="white-space:nowrap;font-weight:600;"><?= h(format_money((float)$order['amount'])) ?></td>
                        <td><?= h(method_label($order['method'] ?? '')) ?></td>
                        <td><?= status_badge($order['status']) ?></td>
                        <td>
                            <?php if ($order['tracking_code']): ?>
                                <code style="font-size:12px;"><?= h($order['tracking_code']) ?></code>
                            <?php else: ?>
                                <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/admin/order.php?id=<?= urlencode($order['order_id']) ?>" class="btn btn-info btn-sm">Ver detalhe</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="<?= h(buildQuery(['page' => $page - 1])) ?>">&laquo; Anterior</a>
    <?php else: ?>
        <span class="disabled">&laquo; Anterior</span>
    <?php endif; ?>

    <?php
    $rangeStart = max(1, $page - 2);
    $rangeEnd   = min($totalPages, $page + 2);
    for ($i = $rangeStart; $i <= $rangeEnd; $i++):
    ?>
        <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= h(buildQuery(['page' => $i])) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="<?= h(buildQuery(['page' => $page + 1])) ?>">Próxima &raquo;</a>
    <?php else: ?>
        <span class="disabled">Próxima &raquo;</span>
    <?php endif; ?>
    <span style="color:#94a3b8;font-size:13px;border:none;background:none;">
        <?= $totalRows ?> pedido<?= $totalRows !== 1 ? 's' : '' ?> no total
    </span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
