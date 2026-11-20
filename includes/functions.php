<?php
/* ==============================================================
   General helpers
   ============================================================== */

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function now(): string { return date('Y-m-d H:i:s'); }

function base_path(): string
{
    static $b = null;
    if ($b !== null) return $b;
    if (SITE_URL !== '') return $b = rtrim(SITE_URL, '/');
    $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $doc  = str_replace('\\', '/', rtrim((string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
    $rel  = ($doc !== '' && strpos($root, $doc) === 0) ? substr($root, strlen($doc)) : '';
    return $b = rtrim($rel, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function full_url(string $path = ''): string
{
    if (SITE_URL !== '') return url($path);
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function asset(string $path): string
{
    if ($path === '') return '';
    if (preg_match('~^https?://~', $path)) return $path;
    return url($path);
}

function redirect(string $path): void
{
    if (!preg_match('~^https?://~', $path)) $path = url($path);
    header('Location: ' . $path);
    exit;
}

function setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $r) $cache[$r['skey']] = $r['svalue'];
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function save_setting(string $key, $value): void
{
    $pdo = db();
    $st = $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
    $st->execute([$value, $key]);
    if ($st->rowCount() === 0) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE skey = ?');
        $chk->execute([$key]);
        if (!$chk->fetchColumn()) $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)')->execute([$key, $value]);
    }
}

function money($amount): string
{
    return setting('currency', '₦') . number_format((float)$amount, 0);
}

function slugify(string $text): string
{
    $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
    return $s !== '' ? $s : 'item-' . time();
}

function unique_slug(string $table, string $text, int $ignoreId = 0): string
{
    $base = slugify($text);
    $slug = $base;
    $i = 2;
    $st = db()->prepare("SELECT COUNT(*) FROM $table WHERE slug = ? AND id != ?");
    while (true) {
        $st->execute([$slug, $ignoreId]);
        if (!$st->fetchColumn()) return $slug;
        $slug = $base . '-' . $i++;
    }
}

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }

function flashes(): string
{
    if (empty($_SESSION['flash'])) return '';
    $out = '';
    foreach ($_SESSION['flash'] as [$t, $m]) {
        $out .= '<div class="alert alert-' . e($t) . '"><span>' . e($m) . '</span><button type="button" class="alert-x" onclick="this.parentNode.remove()" aria-label="Close">&times;</button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        die('Session expired. Please go back, refresh the page and try again.');
    }
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }

function post(string $k, $d = '') { return isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $d; }
function get(string $k, $d = '') { return isset($_GET[$k]) ? (is_string($_GET[$k]) ? trim($_GET[$k]) : $_GET[$k]) : $d; }

function wa_number(): string { return preg_replace('/\D+/', '', (string)setting('whatsapp')); }

function wa_link(string $text = ''): string
{
    return 'https://wa.me/' . wa_number() . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function clean_html(string $html): string
{
    // allow simple formatting tags only
    $html = strip_tags($html, '<p><br><b><strong><i><em><u><ul><ol><li><h2><h3><h4><a><blockquote><table><thead><tbody><tr><th><td><hr><span>');
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*/i', '$1=$2#', $html);
    return $html;
}

/* ==============================================================
   Uploads
   ============================================================== */

function upload_image(array $file, string $folder = 'products', int $maxW = 1400): ?string
{
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 8 * 1024 * 1024) { flash('error', 'Image is larger than 8MB.'); return null; }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) { flash('error', 'Only JPG, PNG, WEBP or GIF images are allowed.'); return null; }
    $info = @getimagesize($file['tmp_name']);
    if (!$info) { flash('error', 'That file is not a valid image.'); return null; }

    $dir = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $dest = $dir . '/' . $name;

    // resize large photos when GD is available (keeps pages fast)
    if (function_exists('imagecreatetruecolor') && $info[0] > $maxW && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            default => false,
        };
        if ($src) {
            $h = (int)round($info[1] * $maxW / $info[0]);
            $dst = imagecreatetruecolor($maxW, $h);
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $h, $info[0], $info[1]);
            $ok = match ($info[2]) {
                IMAGETYPE_JPEG => imagejpeg($dst, $dest, 85),
                IMAGETYPE_PNG  => imagepng($dst, $dest, 7),
                IMAGETYPE_WEBP => imagewebp($dst, $dest, 85),
            };
            imagedestroy($src); imagedestroy($dst);
            if ($ok) return 'uploads/' . $folder . '/' . $name;
        }
    }
    if (!move_uploaded_file($file['tmp_name'], $dest)) { flash('error', 'Could not save the uploaded image. Check folder permissions on /uploads.'); return null; }
    return 'uploads/' . $folder . '/' . $name;
}

function delete_upload(?string $path): void
{
    if (!$path || strpos($path, 'uploads/') !== 0) return;
    $f = realpath(__DIR__ . '/../' . $path);
    $base = realpath(__DIR__ . '/../uploads');
    if ($f && $base && strpos($f, $base) === 0 && is_file($f)) @unlink($f);
}

/* normalise $_FILES['x'] multiple upload into a list */
function files_list(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) return [];
    $out = [];
    foreach ($_FILES[$field]['name'] as $i => $n) {
        if ($n === '') continue;
        $out[] = ['name' => $n, 'type' => $_FILES[$field]['type'][$i], 'tmp_name' => $_FILES[$field]['tmp_name'][$i], 'error' => $_FILES[$field]['error'][$i], 'size' => $_FILES[$field]['size'][$i]];
    }
    return $out;
}

/* ==============================================================
   Catalogue
   ============================================================== */

function categories(bool $activeOnly = true): array
{
    static $c = [];
    $k = $activeOnly ? 1 : 0;
    if (!isset($c[$k])) {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS product_count FROM categories c'
             . ($activeOnly ? ' WHERE c.is_active = 1' : '') . ' ORDER BY c.sort_order, c.name';
        $c[$k] = db()->query($sql)->fetchAll();
    }
    return $c[$k];
}

function product_img(array $p): string
{
    return !empty($p['image']) ? asset($p['image']) : url('assets/img/no-image.svg');
}

function discount_pct(array $p): int
{
    if ((int)$p['old_price'] > (int)$p['price'] && (int)$p['old_price'] > 0) {
        return (int)round(((int)$p['old_price'] - (int)$p['price']) / (int)$p['old_price'] * 100);
    }
    return 0;
}

function product_card(array $p): string
{
    $link = url('product.php?slug=' . urlencode($p['slug']));
    $pct = discount_pct($p);
    $out = (int)$p['stock'] <= 0;
    $h  = '<article class="pcard">';
    $h .= '<a class="pcard-img" href="' . $link . '"><img src="' . e(product_img($p)) . '" alt="' . e($p['name']) . '" loading="lazy">';
    if ($pct) $h .= '<span class="badge badge-sale">-' . $pct . '%</span>';
    if ($out) $h .= '<span class="badge badge-out">Out of stock</span>';
    elseif ((int)$p['stock'] <= (int)$p['low_stock']) $h .= '<span class="badge badge-low">Only ' . (int)$p['stock'] . ' left</span>';
    $h .= '</a><div class="pcard-body">';
    if (!empty($p['brand'])) $h .= '<div class="pcard-brand">' . e($p['brand']) . '</div>';
    $h .= '<h3 class="pcard-title"><a href="' . $link . '">' . e($p['name']) . '</a></h3>';
    if (!empty($p['unit'])) $h .= '<div class="pcard-unit">' . e($p['unit']) . '</div>';
    $h .= '<div class="pcard-price"><strong>' . money($p['price']) . '</strong>';
    if ($pct) $h .= ' <del>' . money($p['old_price']) . '</del>';
    $h .= '</div></div><div class="pcard-actions">';
    if ($out) {
        $h .= '<a class="btn btn-outline btn-block" href="' . e(wa_link('Hello, I would like to know when this will be back in stock: ' . $p['name'])) . '" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Notify me</a>';
    } else {
        $h .= '<form method="post" action="' . url('cart.php') . '" class="js-add-cart">' . csrf_field()
            . '<input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="' . (int)$p['id'] . '"><input type="hidden" name="qty" value="1">'
            . '<button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-cart-plus"></i> Add to cart</button></form>';
    }
    $h .= '</div></article>';
    return $h;
}

/* ==============================================================
   Cart (stored in session: [product_id => qty])
   ============================================================== */

function cart_raw(): array { return $_SESSION['cart'] ?? []; }

function cart_count(): int { return array_sum(cart_raw()); }

function cart_add(int $id, int $qty = 1): array
{
    $st = db()->prepare('SELECT id, name, stock FROM products WHERE id = ? AND is_active = 1');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) return [false, 'Product not found.'];
    if ((int)$p['stock'] <= 0) return [false, $p['name'] . ' is out of stock.'];
    $cur = $_SESSION['cart'][$id] ?? 0;
    $new = min($cur + max(1, $qty), (int)$p['stock']);
    $_SESSION['cart'][$id] = $new;
    if ($cur + $qty > (int)$p['stock']) return [true, 'Only ' . (int)$p['stock'] . ' of ' . $p['name'] . ' available — cart updated.'];
    return [true, $p['name'] . ' added to cart.'];
}

function cart_set(int $id, int $qty): void
{
    if ($qty <= 0) { unset($_SESSION['cart'][$id]); return; }
    $st = db()->prepare('SELECT stock FROM products WHERE id = ? AND is_active = 1');
    $st->execute([$id]);
    $stock = $st->fetchColumn();
    if ($stock === false) { unset($_SESSION['cart'][$id]); return; }
    $_SESSION['cart'][$id] = min($qty, (int)$stock);
    if ($_SESSION['cart'][$id] <= 0) unset($_SESSION['cart'][$id]);
}

function cart_clear(): void { $_SESSION['cart'] = []; }

/** returns ['items'=>[...], 'subtotal'=>int, 'count'=>int] with live prices */
function cart_details(): array
{
    $raw = cart_raw();
    $items = [];
    $sub = 0;
    if ($raw) {
        $ids = array_map('intval', array_keys($raw));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT * FROM products WHERE is_active = 1 AND id IN ($in)");
        $st->execute($ids);
        foreach ($st->fetchAll() as $p) {
            $q = min((int)$raw[$p['id']], max(0, (int)$p['stock']));
            if ($q <= 0) { unset($_SESSION['cart'][$p['id']]); continue; }
            $line = $q * (int)$p['price'];
            $items[] = ['product' => $p, 'qty' => $q, 'total' => $line];
            $sub += $line;
        }
        foreach ($ids as $id) { // drop removed products
            $found = false;
            foreach ($items as $it) if ((int)$it['product']['id'] === $id) $found = true;
            if (!$found) unset($_SESSION['cart'][$id]);
        }
    }
    return ['items' => $items, 'subtotal' => $sub, 'count' => array_sum(array_column($items, 'qty'))];
}

/* ==============================================================
   Customers
   ============================================================== */

function customer(): ?array
{
    static $c = false;
    if ($c !== false) return $c;
    if (empty($_SESSION['customer_id'])) return $c = null;
    $st = db()->prepare('SELECT * FROM customers WHERE id = ? AND is_active = 1');
    $st->execute([$_SESSION['customer_id']]);
    return $c = ($st->fetch() ?: null);
}

function require_customer(): array
{
    $c = customer();
    if (!$c) {
        flash('info', 'Please log in to continue.');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $c;
}

/* ==============================================================
   Orders
   ============================================================== */

function order_statuses(): array
{
    return [
        'pending'    => 'Pending',
        'confirmed'  => 'Confirmed',
        'processing' => 'Processing',
        'shipped'    => 'Out for delivery',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled',
    ];
}

function payment_methods(): array
{
    return [
        'flutterwave' => 'Card / Bank (Flutterwave)',
        'transfer'    => 'Bank transfer',
        'pod'         => 'Pay on delivery',
        'whatsapp'    => 'WhatsApp order',
    ];
}

function status_badge(string $s): string
{
    $label = order_statuses()[$s] ?? ucfirst($s);
    return '<span class="status status-' . e($s) . '">' . e($label) . '</span>';
}

function pay_badge(string $s): string
{
    $map = ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded', 'awaiting' => 'Awaiting confirmation'];
    return '<span class="pay pay-' . e($s) . '">' . e($map[$s] ?? ucfirst($s)) . '</span>';
}

function new_order_no(): string
{
    $st = db()->prepare('SELECT COUNT(*) FROM orders WHERE order_no = ?');
    do {
        $no = 'KVO' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $st->execute([$no]);
    } while ($st->fetchColumn());
    return $no;
}

function app_secret(): string
{
    static $s = null;
    if ($s !== null) return $s;
    $s = (string)setting('app_secret');
    if ($s === '') { $s = bin2hex(random_bytes(24)); save_setting('app_secret', $s); }
    return $s;
}

function order_token(string $orderNo): string
{
    return substr(hash_hmac('sha256', $orderNo, app_secret()), 0, 20);
}

function order_view_url(string $orderNo): string
{
    return 'order.php?o=' . urlencode($orderNo) . '&t=' . order_token($orderNo);
}

function order_add_history(int $orderId, string $status, string $note = ''): void
{
    db()->prepare('INSERT INTO order_history (order_id, status, note, created_at) VALUES (?,?,?,?)')
        ->execute([$orderId, $status, $note, now()]);
}

function order_items(int $orderId): array
{
    $st = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $st->execute([$orderId]);
    return $st->fetchAll();
}

/** put stock back when an order is cancelled */
function order_restock(int $orderId, ?int $adminId = null): void
{
    $pdo = db();
    foreach (order_items($orderId) as $it) {
        if (!$it['product_id']) continue;
        $pdo->prepare('UPDATE products SET stock = stock + ?, sold = CASE WHEN sold >= ? THEN sold - ? ELSE 0 END WHERE id = ?')
            ->execute([$it['qty'], $it['qty'], $it['qty'], $it['product_id']]);
        $after = $pdo->query('SELECT stock FROM products WHERE id = ' . (int)$it['product_id'])->fetchColumn();
        $pdo->prepare('INSERT INTO stock_log (product_id, qty_change, stock_after, reason, admin_id, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$it['product_id'], $it['qty'], (int)$after, 'Order cancelled', $adminId, now()]);
    }
}

function order_whatsapp_text(array $order, array $items): string
{
    $t = "Hello " . setting('site_name') . ", I just placed an order.\n\n*Order:* {$order['order_no']}\n";
    foreach ($items as $it) $t .= "• {$it['name']} x{$it['qty']} — " . money($it['total']) . "\n";
    $t .= "\nSubtotal: " . money($order['subtotal']);
    $t .= "\nDelivery: " . ($order['delivery_method'] === 'pickup' ? 'Store pickup' : money($order['delivery_fee']) . " ({$order['zone_name']})");
    $t .= "\n*Total: " . money($order['total']) . "*";
    $t .= "\n\nName: {$order['name']}\nPhone: {$order['phone']}";
    if ($order['delivery_method'] !== 'pickup') $t .= "\nAddress: {$order['address']}, {$order['city']}, {$order['state']}";
    $t .= "\nPayment: " . (payment_methods()[$order['payment_method']] ?? $order['payment_method']);
    return $t;
}

/* ==============================================================
   Email (uses PHP mail(), works on most cPanel hosts)
   ============================================================== */

function send_mail(string $to, string $subject, string $html): bool
{
    if (setting('send_emails') !== '1' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = setting('email', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $name = setting('site_name');
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . '=?UTF-8?B?' . base64_encode($name) . '?=' . " <$from>\r\nReply-To: $from\r\n";
    $body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #eee;border-radius:10px;overflow:hidden">'
          . '<div style="background:' . e(setting('color_primary')) . ';color:#fff;padding:18px 22px;font-size:20px;font-weight:bold">' . e($name) . '</div>'
          . '<div style="padding:22px;color:#222;line-height:1.6">' . $html . '</div>'
          . '<div style="background:#f6f6f6;padding:12px 22px;font-size:12px;color:#777">' . e(setting('company_name')) . ' · ' . e(setting('phone')) . '</div></div>';
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function order_email_html(array $order, array $items): string
{
    $rows = '';
    foreach ($items as $it) $rows .= '<tr><td style="padding:6px;border-bottom:1px solid #eee">' . e($it['name']) . ' × ' . (int)$it['qty'] . '</td><td style="padding:6px;border-bottom:1px solid #eee;text-align:right">' . money($it['total']) . '</td></tr>';
    return '<p>Order <strong>' . e($order['order_no']) . '</strong></p><table style="width:100%;border-collapse:collapse">' . $rows
        . '<tr><td style="padding:6px">Delivery</td><td style="padding:6px;text-align:right">' . money($order['delivery_fee']) . '</td></tr>'
        . '<tr><td style="padding:6px"><strong>Total</strong></td><td style="padding:6px;text-align:right"><strong>' . money($order['total']) . '</strong></td></tr></table>'
        . '<p>Payment: ' . e(payment_methods()[$order['payment_method']] ?? $order['payment_method']) . '<br>Status: ' . e(order_statuses()[$order['status']] ?? $order['status']) . '</p>'
        . '<p><a href="' . e(full_url('track.php?order=' . urlencode($order['order_no']) . '&phone=' . urlencode($order['phone']))) . '">Track this order</a></p>';
}

/* ==============================================================
   Flutterwave
   ============================================================== */

function flw_request(string $method, string $endpoint, ?array $payload = null): ?array
{
    $secret = trim((string)setting('flw_secret_key'));
    if ($secret === '' || !function_exists('curl_init')) return null;
    $ch = curl_init('https://api.flutterwave.com/v3/' . ltrim($endpoint, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $secret, 'Content-Type: application/json'],
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return null;
    $j = json_decode($res, true);
    return is_array($j) ? $j : null;
}

/** marks an order paid if Flutterwave verifies the transaction */
function flw_verify_and_mark(string $transactionId, array $order): bool
{
    $r = flw_request('GET', 'transactions/' . rawurlencode($transactionId) . '/verify');
    if (!$r || ($r['status'] ?? '') !== 'success') return false;
    $d = $r['data'] ?? [];
    $ok = ($d['status'] ?? '') === 'successful'
        && ($d['tx_ref'] ?? '') === $order['tx_ref']
        && (float)($d['amount'] ?? 0) >= (float)$order['total']
        && strtoupper($d['currency'] ?? '') === 'NGN';
    if (!$ok) return false;
    if ($order['payment_status'] !== 'paid') {
        db()->prepare("UPDATE orders SET payment_status = 'paid', flw_tx_id = ?, status = CASE WHEN status = 'pending' THEN 'confirmed' ELSE status END, updated_at = ? WHERE id = ?")
            ->execute([(string)$transactionId, now(), $order['id']]);
        order_add_history((int)$order['id'], 'confirmed', 'Payment received via Flutterwave (ID ' . $transactionId . ')');
    }
    return true;
}

function nigerian_states(): array
{
    return ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT - Abuja','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba','Yobe','Zamfara'];
}
