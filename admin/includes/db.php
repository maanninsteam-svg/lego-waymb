<?php
function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $config = json_decode(file_get_contents(__DIR__ . '/../../admin-config.json'), true);
    $dbRelPath = $config['db']['path'] ?? 'db/lego_store.db';
    $dbPath = __DIR__ . '/../../' . $dbRelPath;

    $dir = dirname($dbPath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    run_migrations($pdo);
    return $pdo;
}

function run_migrations(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id        TEXT    NOT NULL UNIQUE,
            status          TEXT    NOT NULL DEFAULT 'waiting_payment',
            customer_name   TEXT,
            customer_email  TEXT,
            customer_phone  TEXT,
            customer_address TEXT,
            customer_postal  TEXT,
            customer_city    TEXT,
            customer_district TEXT,
            customer_country TEXT DEFAULT 'PT',
            items_json      TEXT,
            amount          REAL    NOT NULL DEFAULT 0,
            method          TEXT,
            tracking_code   TEXT,
            tracking_sent_at DATETIME,
            created_at      DATETIME NOT NULL,
            paid_at         DATETIME,
            updated_at      DATETIME NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX IF NOT EXISTS idx_orders_status  ON orders(status);
        CREATE INDEX IF NOT EXISTS idx_orders_email   ON orders(customer_email);
        CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at DESC);

        CREATE TABLE IF NOT EXISTS support_tickets (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id    TEXT,
            name        TEXT    NOT NULL,
            email       TEXT    NOT NULL,
            subject     TEXT    NOT NULL,
            message     TEXT    NOT NULL,
            status      TEXT    NOT NULL DEFAULT 'open',
            admin_reply TEXT,
            resolved_at DATETIME,
            created_at  DATETIME NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX IF NOT EXISTS idx_tickets_status ON support_tickets(status);
        CREATE INDEX IF NOT EXISTS idx_tickets_email  ON support_tickets(email);
    ");

    // Colunas adicionadas em migrações posteriores (falha silenciosa se já existirem)
    $extras = [
        "ALTER TABLE support_tickets ADD COLUMN source           TEXT    DEFAULT 'form'",
        "ALTER TABLE support_tickets ADD COLUMN email_message_id TEXT",
        "ALTER TABLE support_tickets ADD COLUMN email_context    TEXT",
    ];
    foreach ($extras as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) { /* coluna já existe */ }
    }
}
