<?php
/**
 * Database connection, automatic table creation and first-run seed data.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    try {
        if (DB_DRIVER === 'mysql') {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } else {
            $dir = dirname(DB_SQLITE_PATH);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        die('<h2 style="font-family:sans-serif">Database connection failed</h2><p style="font-family:sans-serif">Check includes/config.php. ' . (DEBUG ? htmlspecialchars($e->getMessage()) : '') . '</p>');
    }

    db_migrate($pdo);
    return $pdo;
}

function db_migrate(PDO $pdo): void
{
    $isMy = DB_DRIVER === 'mysql';
    $pk   = $isMy ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = $isMy ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    // quick check: already installed?
    try {
        $pdo->query('SELECT 1 FROM settings LIMIT 1');
        return;
    } catch (PDOException $e) { /* not installed yet */ }

    $tables = [
        "CREATE TABLE IF NOT EXISTS settings (skey VARCHAR(100) PRIMARY KEY, svalue TEXT)",
        "CREATE TABLE IF NOT EXISTS admins (id $pk, name VARCHAR(120), email VARCHAR(190) UNIQUE, password VARCHAR(255), role VARCHAR(20) DEFAULT 'admin', is_active INT DEFAULT 1, last_login VARCHAR(20), created_at VARCHAR(20))",
        "CREATE TABLE IF NOT EXISTS categories (id $pk, name VARCHAR(150), slug VARCHAR(170) UNIQUE, icon VARCHAR(60), image VARCHAR(255), description TEXT, sort_order INT DEFAULT 0, is_active INT DEFAULT 1)",
        "CREATE TABLE IF NOT EXISTS products (id $pk, category_id INT, name VARCHAR(200), slug VARCHAR(220) UNIQUE, sku VARCHAR(80), brand VARCHAR(120), unit VARCHAR(60), short_desc TEXT, description TEXT, specs TEXT, price INT DEFAULT 0, old_price INT DEFAULT 0, stock INT DEFAULT 0, low_stock INT DEFAULT 5, image VARCHAR(255), is_featured INT DEFAULT 0, is_deal INT DEFAULT 0, is_active INT DEFAULT 1, views INT DEFAULT 0, sold INT DEFAULT 0, created_at VARCHAR(20), updated_at VARCHAR(20))",
        "CREATE TABLE IF NOT EXISTS product_images (id $pk, product_id INT, path VARCHAR(255), sort_order INT DEFAULT 0)",
        "CREATE TABLE IF NOT EXISTS stock_log (id $pk, product_id INT, qty_change INT, stock_after INT, reason VARCHAR(255), admin_id INT, created_at VARCHAR(20))",
        "CREATE TABLE IF NOT EXISTS customers (id $pk, name VARCHAR(150), email VARCHAR(190) UNIQUE, phone VARCHAR(40), password VARCHAR(255), address TEXT, city VARCHAR(100), state VARCHAR(100), is_active INT DEFAULT 1, created_at VARCHAR(20))",
        "CREATE TABLE IF NOT EXISTS delivery_zones (id $pk, name VARCHAR(150), fee INT DEFAULT 0, eta VARCHAR(80), sort_order INT DEFAULT 0, is_active INT DEFAULT 1)",
        "CREATE TABLE IF NOT EXISTS orders (id $pk, order_no VARCHAR(30) UNIQUE, customer_id INT, name VARCHAR(150), email VARCHAR(190), phone VARCHAR(40), address TEXT, city VARCHAR(100), state VARCHAR(100), notes TEXT, delivery_method VARCHAR(20), zone_id INT, zone_name VARCHAR(150), subtotal INT, delivery_fee INT, total INT, payment_method VARCHAR(30), payment_status VARCHAR(20) DEFAULT 'unpaid', status VARCHAR(20) DEFAULT 'pending', tx_ref VARCHAR(80), flw_tx_id VARCHAR(80), admin_note TEXT, created_at VARCHAR(20), updated_at VARCHAR(20))",
        "CREATE TABLE IF NOT EXISTS order_items (id $pk, order_id INT, product_id INT, name VARCHAR(200), price INT, qty INT, total INT)",
        "CREATE TABLE IF NOT EXISTS order_history (id $pk, order_id INT, status VARCHAR(30), note TEXT, created_at VARCHAR(20))",
        "CREATE TABLE IF NOT EXISTS messages (id $pk, name VARCHAR(150), email VARCHAR(190), phone VARCHAR(40), subject VARCHAR(200), message TEXT, is_read INT DEFAULT 0, created_at VARCHAR(20))",
        "CREATE TABLE IF NOT EXISTS subscribers (id $pk, email VARCHAR(190) UNIQUE, created_at VARCHAR(20))",
        "CREATE TABLE IF NOT EXISTS slides (id $pk, title VARCHAR(200), subtitle TEXT, button_text VARCHAR(60), button_link VARCHAR(255), image VARCHAR(255), sort_order INT DEFAULT 0, is_active INT DEFAULT 1)",
        "CREATE TABLE IF NOT EXISTS pages (id $pk, title VARCHAR(200), slug VARCHAR(200) UNIQUE, content TEXT, in_footer INT DEFAULT 1, is_active INT DEFAULT 1, updated_at VARCHAR(20))",
        "CREATE TABLE IF NOT EXISTS reviews (id $pk, product_id INT, name VARCHAR(150), rating INT, comment TEXT, is_approved INT DEFAULT 0, created_at VARCHAR(20))",
    ];
    foreach ($tables as $sql) $pdo->exec($sql . $tail);

    require_once __DIR__ . '/seed.php';
    seed_database($pdo);
}
