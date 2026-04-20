<?php
/**
 * seed_pending.php
 *
 * One-time import script: reads all .utmify_pending/*.json files and
 * upserts them into the orders SQLite database.
 *
 * Usage: access via browser with ?token=seed_lego_2026
 * or run from CLI: php seed_pending.php
 */

define('SEED_TOKEN', 'seed_lego_2026');

// Token protection (skip when running from CLI)
if (PHP_SAPI !== 'cli') {
    $token = $_GET['token'] ?? '';
    if ($token !== SEED_TOKEN) {
        http_response_code(403);
        echo '<h2>403 Forbidden</h2><p>Missing or invalid token.</p>';
        exit;
    }
}

require_once __DIR__ . '/../admin/includes/db.php';

$pendingDir = __DIR__ . '/../.utmify_pending';
if (!is_dir($pendingDir)) {
    die("Pending directory not found: {$pendingDir}\n");
}

$pdo = get_db();

$files    = glob($pendingDir . '/*.json');
$imported = 0;
$skipped  = 0;
$errors   = [];

foreach ($files as $file) {
    $raw = file_get_contents($file);
    if ($raw === false) {
        $errors[] = basename($file) . ': failed to read';
        continue;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $errors[] = basename($file) . ': invalid JSON';
        continue;
    }

    // Derive order_id from filename (without extension)
    $orderId = pathinfo($file, PATHINFO_FILENAME);

    $customer  = $data['customer']  ?? [];
    $items     = $data['items']     ?? [];
    $amount    = (float)($data['amount'] ?? 0);
    $method    = (string)($data['method'] ?? '');
    $createdAt = (string)($data['createdAt'] ?? date('Y-m-d H:i:s'));

    try {
        $stmt = $pdo->prepare("
            INSERT INTO orders
                (order_id, status, customer_name, customer_email, customer_phone,
                 customer_address, customer_postal, customer_city, customer_district,
                 customer_country, items_json, amount, method, created_at, paid_at, updated_at)
            VALUES
                (:order_id, :status, :name, :email, :phone,
                 :address, :postal, :city, :district,
                 :country, :items_json, :amount, :method, :created_at, :paid_at, datetime('now'))
            ON CONFLICT(order_id) DO UPDATE SET
                updated_at = datetime('now')
        ");
        $stmt->execute([
            ':order_id'   => $orderId,
            ':status'     => 'waiting_payment',
            ':name'       => $customer['name']     ?? null,
            ':email'      => $customer['email']    ?? null,
            ':phone'      => $customer['phone']    ?? null,
            ':address'    => $customer['address']  ?? null,
            ':postal'     => $customer['postal']   ?? null,
            ':city'       => $customer['city']     ?? null,
            ':district'   => $customer['district'] ?? null,
            ':country'    => $customer['country']  ?? 'PT',
            ':items_json' => json_encode($items, JSON_UNESCAPED_UNICODE),
            ':amount'     => $amount,
            ':method'     => $method,
            ':created_at' => $createdAt,
            ':paid_at'    => null,
        ]);
        $imported++;
    } catch (Throwable $e) {
        $errors[] = basename($file) . ': ' . $e->getMessage();
        $skipped++;
    }
}

// Output
if (PHP_SAPI === 'cli') {
    echo "Imported: {$imported}\n";
    echo "Skipped:  {$skipped}\n";
    if ($errors) {
        echo "Errors:\n";
        foreach ($errors as $e) echo "  - {$e}\n";
    }
} else {
    ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Seed Pending Orders</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 60px auto; padding: 20px; }
        .box { border-radius: 8px; padding: 20px; margin: 16px 0; }
        .success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
        .info    { background: #dbeafe; border: 1px solid #93c5fd; color: #1e40af; }
        .error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
        ul { margin: 8px 0 0; padding-left: 20px; }
    </style>
</head>
<body>
    <h2>&#127795; Seed Pending Orders</h2>
    <div class="box success">
        <strong>&#10003; Concluído!</strong><br>
        <strong><?= $imported ?></strong> pedido<?= $imported !== 1 ? 's' : '' ?> importado<?= $imported !== 1 ? 's' : '' ?> com sucesso.
    </div>
    <?php if ($skipped > 0): ?>
    <div class="box info">
        <strong><?= $skipped ?></strong> pedido<?= $skipped !== 1 ? 's ignorados' : ' ignorado' ?> (já existentes ou erros).
    </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="box error">
        <strong>Erros encontrados:</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <p style="margin-top:24px;">
        <a href="/admin/dashboard.php" style="color:#2563eb;">&#8594; Ir para o Dashboard</a>
    </p>
</body>
</html>
<?php
}
