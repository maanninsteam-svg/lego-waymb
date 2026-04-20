<?php
/**
 * create-test-order.php
 * Cria um pedido de teste na BD para testar o envio de email de rastreio.
 * Acesso: /admin/create-test-order.php?token=test_lego_2026
 * APAGAR após os testes.
 */

define('TEST_TOKEN', 'test_lego_2026');

if (($_GET['token'] ?? '') !== TEST_TOKEN) {
    http_response_code(403);
    echo '<h2>403 Forbidden</h2>';
    exit;
}

require_once __DIR__ . '/includes/db.php';

$pdo = get_db();

// Email de teste — altere para o seu email real para receber o teste
$testEmail = $_GET['email'] ?? 'seu-email@exemplo.com';

$orderId   = 'TEST-' . strtoupper(substr(md5(uniqid()), 0, 10));
$createdAt = date('Y-m-d H:i:s');

$items = json_encode([
    [
        'id'       => '1',
        'name'     => 'Troféu Oficial do Campeonato do Mundo™ da FIFA',
        'quantity' => 1,
        'price'    => 29.99,
    ],
    [
        'id'       => 'bump-messi',
        'name'     => 'Miniatura Messi',
        'quantity' => 1,
        'price'    => 9.90,
    ],
], JSON_UNESCAPED_UNICODE);

try {
    $stmt = $pdo->prepare("
        INSERT INTO orders
            (order_id, status, customer_name, customer_email, customer_phone,
             customer_address, customer_postal, customer_city, customer_district,
             customer_country, items_json, amount, method, created_at, paid_at, updated_at)
        VALUES
            (:order_id, 'paid', :name, :email, :phone,
             :address, :postal, :city, :district,
             'PT', :items, :amount, 'mbway', :created_at, :created_at, datetime('now'))
    ");
    $stmt->execute([
        ':order_id'   => $orderId,
        ':name'       => 'Cliente de Teste',
        ':email'      => $testEmail,
        ':phone'      => '+351912345678',
        ':address'    => 'Rua de Teste 123',
        ':postal'     => '1000-001',
        ':city'       => 'Lisboa',
        ':district'   => 'Lisboa',
        ':items'      => $items,
        ':amount'     => 39.89,
        ':created_at' => $createdAt,
    ]);

    $inserted = $pdo->lastInsertId();
    ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Pedido de Teste Criado</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 60px auto; padding: 20px; }
        .box { border-radius: 8px; padding: 20px; margin: 16px 0; }
        .success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }
        .info    { background: #dbeafe; border: 1px solid #93c5fd; color: #1e40af; font-size: 13px; }
        a { color: #2563eb; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
    </style>
</head>
<body>
    <h2>✓ Pedido de Teste Criado</h2>
    <div class="box success">
        <strong>Order ID:</strong> <code><?= htmlspecialchars($orderId) ?></code><br>
        <strong>Email:</strong> <?= htmlspecialchars($testEmail) ?><br>
        <strong>Total:</strong> 39,89 €
    </div>
    <div class="box info">
        Para testar com o seu email, aceda a:<br>
        <code>/admin/create-test-order.php?token=test_lego_2026&amp;email=SEU@EMAIL.COM</code>
    </div>
    <p>
        <a href="/admin/order.php?id=<?= urlencode($orderId) ?>">→ Abrir pedido e adicionar código de rastreio</a>
    </p>
    <p style="font-size:12px;color:#94a3b8;">Apague este ficheiro após os testes.</p>
</body>
</html>
    <?php
} catch (Throwable $e) {
    echo '<h2>Erro</h2><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
