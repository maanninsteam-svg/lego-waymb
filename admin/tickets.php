<?php
require_once __DIR__ . '/includes/auth.php';
require_admin_auth();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = get_db();

$filterStatus = $_GET['status'] ?? 'open';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($filterStatus === 'open') {
    $where[]  = "status = 'open'";
} elseif ($filterStatus === 'resolved') {
    $where[]  = "status = 'resolved'";
}
// 'all' → no filter

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets {$whereClause}");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$stmt = $pdo->prepare("SELECT * FROM support_tickets {$whereClause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll();

$openCount = (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open'")->fetchColumn();

$pageTitle = 'Suporte';

function buildTicketQuery(array $override = []): string {
    $base = [
        'status' => $_GET['status'] ?? 'open',
        'page'   => $_GET['page']   ?? 1,
    ];
    $merged  = array_merge($base, $override);
    $filtered = array_filter($merged, fn($v) => $v !== '' && $v !== 0 && $v !== '0');
    return $filtered ? '?' . http_build_query($filtered) : '';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>Suporte — Tickets <?php if ($openCount > 0): ?><span style="background:#dc2626;color:#fff;font-size:14px;border-radius:999px;padding:2px 10px;margin-left:8px;"><?= $openCount ?></span><?php endif; ?></h2>
        <p>Mensagens recebidas pelo formulário de suporte</p>
    </div>
</div>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;">
    <a href="/admin/tickets.php?status=open"
       class="btn <?= $filterStatus === 'open' ? 'btn-primary' : 'btn-secondary' ?>">
        Abertos (<?= $openCount ?>)
    </a>
    <a href="/admin/tickets.php?status=resolved"
       class="btn <?= $filterStatus === 'resolved' ? 'btn-primary' : 'btn-secondary' ?>">
        Resolvidos
    </a>
    <a href="/admin/tickets.php?status=all"
       class="btn <?= $filterStatus === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
        Todos
    </a>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Data</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Pedido</th>
                    <th>Assunto</th>
                    <th>Status</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:32px;">Nenhum ticket encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td style="font-weight:600;">#<?= (int)$ticket['id'] ?></td>
                        <td style="white-space:nowrap;"><?= h(format_date($ticket['created_at'])) ?></td>
                        <td><?= h($ticket['name']) ?></td>
                        <td style="font-size:13px;"><?= h($ticket['email']) ?></td>
                        <td>
                            <?php if ($ticket['order_id']): ?>
                                <a href="/admin/order.php?id=<?= urlencode($ticket['order_id']) ?>" style="font-size:12px;color:#2563eb;">
                                    <?= h(substr($ticket['order_id'], 0, 10)) ?>…
                                </a>
                            <?php else: ?>
                                <span style="color:#94a3b8;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= h($ticket['subject']) ?>
                        </td>
                        <td>
                            <?php if ($ticket['status'] === 'open'): ?>
                                <span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Aberto</span>
                            <?php else: ?>
                                <span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Resolvido</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/admin/ticket.php?id=<?= (int)$ticket['id'] ?>" class="btn btn-info btn-sm">Ver</a>
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
        <a href="<?= h(buildTicketQuery(['page' => $page - 1])) ?>">&laquo; Anterior</a>
    <?php else: ?>
        <span class="disabled">&laquo; Anterior</span>
    <?php endif; ?>

    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= h(buildTicketQuery(['page' => $i])) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a href="<?= h(buildTicketQuery(['page' => $page + 1])) ?>">Próxima &raquo;</a>
    <?php else: ?>
        <span class="disabled">Próxima &raquo;</span>
    <?php endif; ?>
    <span style="color:#94a3b8;font-size:13px;border:none;background:none;">
        <?= $totalRows ?> ticket<?= $totalRows !== 1 ? 's' : '' ?> no total
    </span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
