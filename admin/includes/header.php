<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Count open tickets for sidebar badge
$openTickets = 0;
try {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE status='open'");
    $row = $stmt->fetch();
    $openTickets = (int)($row['cnt'] ?? 0);
} catch (Throwable $e) {
    // non-fatal
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?>Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --sidebar-active: #f59e0b;
            --accent: #f59e0b;
            --accent-dark: #d97706;
            --body-bg: #f1f5f9;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #059669;
            --danger: #dc2626;
            --info: #2563eb;
            --warning: #d97706;
            --sidebar-width: 240px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--body-bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .sidebar-logo {
            padding: 24px 20px 20px;
            border-bottom: 1px solid #334155;
        }

        .sidebar-logo h1 {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: 0.5px;
        }

        .sidebar-logo small {
            display: block;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 3px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 16px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 20px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.15s, color 0.15s;
            position: relative;
        }

        .sidebar-nav a:hover {
            background: var(--sidebar-hover);
            color: #fff;
        }

        .sidebar-nav a.active {
            background: rgba(245,158,11,0.15);
            color: var(--accent);
            border-left: 3px solid var(--accent);
        }

        .sidebar-nav a .nav-icon {
            font-size: 16px;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .badge {
            background: var(--danger);
            color: #fff;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            margin-left: auto;
        }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid #334155;
        }

        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
            font-size: 13px;
            text-decoration: none;
            transition: color 0.15s;
        }

        .sidebar-footer a:hover { color: #ef4444; }

        /* ── Main content ── */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 32px;
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .page-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
        }

        .page-header p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* ── Cards ── */
        .card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 24px;
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        /* ── Stat grid ── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 20px 24px;
        }

        .stat-card .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            margin: 6px 0 0;
        }

        .stat-card .stat-value.accent { color: var(--accent-dark); }
        .stat-card .stat-value.green  { color: var(--success); }
        .stat-card .stat-value.blue   { color: var(--info); }

        /* ── Table ── */
        .table-wrapper {
            overflow-x: auto;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table.data-table thead th {
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 11px 14px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            white-space: nowrap;
        }

        table.data-table tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text);
            vertical-align: middle;
        }

        table.data-table tbody tr:last-child td { border-bottom: none; }
        table.data-table tbody tr:hover { background: #f8fafc; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-primary  { background: var(--accent); color: #1e293b; }
        .btn-secondary{ background: #e2e8f0; color: var(--text); }
        .btn-sm       { padding: 5px 11px; font-size: 12px; }
        .btn-success  { background: var(--success); color: #fff; }
        .btn-danger   { background: var(--danger); color: #fff; }
        .btn-info     { background: var(--info); color: #fff; }

        /* ── Forms ── */
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 6px; }
        .form-control {
            width: 100%;
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            transition: border-color 0.15s;
            background: #fff;
        }
        .form-control:focus { outline: none; border-color: var(--accent); }
        textarea.form-control { resize: vertical; min-height: 100px; }

        /* ── Alert ── */
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .alert-info    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }

        /* ── Pagination ── */
        .pagination { display: flex; gap: 6px; margin-top: 20px; align-items: center; flex-wrap: wrap; }
        .pagination a, .pagination span {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text);
            background: #fff;
        }
        .pagination a:hover { background: #f1f5f9; }
        .pagination .current { background: var(--accent); color: #1e293b; border-color: var(--accent); }
        .pagination .disabled { color: #cbd5e1; pointer-events: none; }

        /* ── Filter bar ── */
        .filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-bar input, .filter-bar select {
            padding: 8px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background: #fff;
        }

        .filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: var(--accent); }

        /* ── Detail grid ── */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 768px) { .detail-grid { grid-template-columns: 1fr; } }
        .detail-row { display: flex; flex-direction: column; gap: 3px; margin-bottom: 14px; }
        .detail-row .label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-row .value { font-size: 14px; color: var(--text); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <h1>&#9917; Admin Panel</h1>
        <small>LEGO World Cup 2026</small>
    </div>
    <nav class="sidebar-nav">
        <a href="/admin/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">&#128202;</span> Dashboard
        </a>
        <a href="/admin/dashboard.php" class="<?= $currentPage === 'order.php' ? 'active' : '' ?>" style="<?= $currentPage === 'order.php' ? 'background:rgba(245,158,11,0.15);color:#f59e0b;border-left:3px solid #f59e0b;' : '' ?>">
            <span class="nav-icon">&#128230;</span> Pedidos
        </a>
        <a href="/admin/tickets.php" class="<?= $currentPage === 'tickets.php' || $currentPage === 'ticket.php' ? 'active' : '' ?>">
            <span class="nav-icon">&#128172;</span> Suporte
            <?php if ($openTickets > 0): ?>
                <span class="badge"><?= $openTickets ?></span>
            <?php endif; ?>
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="/admin/logout.php">
            <span>&#10006;</span> Terminar Sessão
        </a>
    </div>
</aside>

<div class="main-content">
