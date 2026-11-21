<?php
/** Database connection, schema and first-run setup. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        if (DB_DRIVER === 'mysql') {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET time_zone = '+01:00'");
        } else {
            $dir = dirname(DB_SQLITE_PATH);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        exit('<h2 style="font-family:sans-serif">Database connection failed</h2><p style="font-family:sans-serif">Check includes/config.php.' . (DEBUG ? ' ' . htmlspecialchars($e->getMessage()) : '') . '</p>');
    }
    db_install($pdo);
    return $pdo;
}

function db_install(PDO $pdo): void
{
    try { $pdo->query('SELECT 1 FROM settings LIMIT 1'); return; } catch (PDOException $e) {}

    $my = DB_DRIVER === 'mysql';
    $pk = $my ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $T = $my ? 'LONGTEXT' : 'TEXT';
    $B = $my ? 'BIGINT' : 'INTEGER';
    $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $V = fn($n) => "VARCHAR($n)";

    $sql = [
        "settings (skey {$V(100)} PRIMARY KEY, svalue $T)",
        "users (id $pk, name {$V(150)}, email {$V(190)} UNIQUE, phone {$V(40)}, company {$V(150)}, password {$V(255)}, verified INT DEFAULT 0, active INT DEFAULT 1, created {$V(25)})",
        "addresses (id $pk, user_id INT, label {$V(80)}, name {$V(150)}, phone {$V(40)}, line1 {$V(255)}, city {$V(100)}, state {$V(60)}, is_default INT DEFAULT 0)",
        "staff (id $pk, name {$V(150)}, email {$V(190)} UNIQUE, password {$V(255)}, role {$V(40)}, active INT DEFAULT 1, last_login {$V(25)}, created {$V(25)})",
        "reset_tokens (token {$V(80)} PRIMARY KEY, user_id INT, expires $B)",
        "categories (id $pk, slug {$V(120)} UNIQUE, name {$V(150)}, kind {$V(20)}, description $T, sort_order INT DEFAULT 0, active INT DEFAULT 1)",
        "products (id $pk, sku {$V(60)} UNIQUE, slug {$V(200)} UNIQUE, name {$V(200)}, model {$V(120)}, kind {$V(20)}, cat {$V(120)}, brand {$V(120)}, cond {$V(20)}, mode {$V(20)}, price $B NULL, on_hand INT DEFAULT 0, reserved INT DEFAULT 0, low INT DEFAULT 3, inv {$V(20)}, dq INT DEFAULT 0, featured INT DEFAULT 0, status {$V(20)} DEFAULT 'PUBLISHED', description $T, specs $T, image {$V(255)}, credit $T, credit_url {$V(500)}, sales INT DEFAULT 0, views INT DEFAULT 0, created {$V(25)}, updated {$V(25)})",
        "product_images (id $pk, product_id INT, path {$V(255)}, sort_order INT DEFAULT 0)",
        "price_history (id $pk, product_id INT, price $B, at {$V(25)})",
        "movements (id $pk, product_id INT, sku {$V(60)}, type {$V(20)}, qty INT, on_hand INT, reserved INT, reason {$V(255)}, order_ref {$V(30)}, by_name {$V(150)}, at {$V(25)})",
        "zones (id $pk, name {$V(120)}, states $T, fee $B NULL, lead_days INT, active INT DEFAULT 1)",
        "promos (id $pk, code {$V(40)} UNIQUE, description {$V(255)}, type {$V(10)}, value $B, min_order $B DEFAULT 0, max_disc $B NULL, starts {$V(25)}, ends {$V(25)}, max_uses INT NULL, per_customer INT NULL, uses INT DEFAULT 0, active INT DEFAULT 1)",
        "orders (id $pk, ref {$V(30)} UNIQUE, token {$V(64)}, user_id INT NULL, email {$V(190)}, name {$V(150)}, phone {$V(40)}, company {$V(150)}, subtotal $B, discount $B DEFAULT 0, tax $B DEFAULT 0, total $B, delivery_charge $B DEFAULT 0, paid $B DEFAULT 0, refunded $B DEFAULT 0, status {$V(30)}, method {$V(30)}, delivery_method {$V(20)}, addr_line1 {$V(255)}, addr_city {$V(100)}, addr_state {$V(60)}, addr_instr $T, notes $T, promo {$V(40)}, quotation_id INT NULL, reservation_active INT DEFAULT 1, hold_until {$V(25)}, stock_committed INT DEFAULT 0, stock_issue $T, cancel_reason $T, created {$V(25)}, paid_at {$V(25)}, d_status {$V(40)}, d_fee $B NULL, d_basis $T, d_lead INT NULL, d_provider {$V(150)}, d_tracking {$V(150)}, d_expected {$V(25)}, d_assignee {$V(150)})",
        "order_items (id $pk, order_id INT, product_id INT NULL, name {$V(255)}, sku {$V(60)}, unit $B, qty INT, line_total $B)",
        "payments (id $pk, order_id INT, ref {$V(60)} UNIQUE, provider {$V(30)}, purpose {$V(20)}, amount $B, status {$V(20)}, at {$V(25)}, verified_at {$V(25)}, dq_id INT NULL, provider_ref {$V(120)}, note $T)",
        "refunds (id $pk, order_id INT, amount $B, reason $T, by_name {$V(150)}, at {$V(25)})",
        "delivery_events (id $pk, order_id INT, status {$V(40)}, note $T, at {$V(25)})",
        "delivery_quotes (id $pk, order_id INT, amount $B, provider {$V(150)}, days INT NULL, valid_until {$V(25)}, notes $T, status {$V(20)}, at {$V(25)})",
        "order_notes (id $pk, order_id INT, note $T, by_name {$V(150)}, at {$V(25)})",
        "enquiries (id $pk, code {$V(20)} UNIQUE, type {$V(30)}, status {$V(20)} DEFAULT 'NEW', name {$V(150)}, company {$V(150)}, email {$V(190)}, phone {$V(40)}, product_id INT NULL, property {$V(200)}, subject {$V(255)}, qty INT NULL, location {$V(200)}, requirements $T, message $T, assignee {$V(150)}, user_id INT NULL, created {$V(25)})",
        "quotations (id $pk, number {$V(30)} UNIQUE, token {$V(64)}, enquiry_id INT NULL, user_id INT NULL, name {$V(150)}, email {$V(190)}, phone {$V(40)}, company {$V(150)}, location {$V(200)}, status {$V(20)}, valid_until {$V(25)}, terms $T, notes $T, delivery $B DEFAULT 0, subtotal $B DEFAULT 0, tax $B DEFAULT 0, total $B DEFAULT 0, created {$V(25)}, sent_at {$V(25)}, accepted_at {$V(25)}, order_ref {$V(30)})",
        "quotation_items (id $pk, quotation_id INT, product_id INT NULL, description {$V(255)}, qty INT, unit $B, tax_bps INT DEFAULT 0)",
        "properties (id $pk, slug {$V(200)} UNIQUE, title {$V(200)}, type {$V(60)}, listing {$V(20)}, location {$V(150)}, state {$V(60)}, price $B NULL, size INT NULL, beds INT NULL, features $T, description $T, image {$V(255)}, active INT DEFAULT 1, sort_order INT DEFAULT 0, created {$V(25)})",
        "pages (id $pk, slug {$V(120)} UNIQUE, title {$V(200)}, content $T, updated {$V(25)})",
        "faqs (id $pk, question $T, answer $T, sort_order INT DEFAULT 0)",
        "newsletter (id $pk, email {$V(190)} UNIQUE, created {$V(25)})",
        "audit (id $pk, at {$V(25)}, actor {$V(150)}, action {$V(80)}, entity {$V(120)}, details $T)",
        "email_log (id $pk, at {$V(25)}, to_addr {$V(190)}, subject {$V(255)}, body $T, link {$V(500)}, sent INT DEFAULT 0)",
    ];
    foreach ($sql as $s) $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $s . $tail);
    foreach (['orders(email)', 'orders(user_id)', 'payments(order_id)', 'order_items(order_id)', 'movements(product_id)', 'products(cat)', 'delivery_events(order_id)'] as $i => $ix) {
        try { $pdo->exec("CREATE INDEX ix_$i ON $ix"); } catch (PDOException $e) {}
    }
    require_once __DIR__ . '/seed.php';
    seed_database($pdo);
}
