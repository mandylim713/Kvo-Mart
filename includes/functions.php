<?php
/* ==================================================================
   KVO Mart — helpers and business rules
   Money is stored in kobo (integers). ₦1 = 100 kobo.
   ================================================================== */

const STATES = ['Abia','Adamawa','Akwa Ibom','Anambra','Bauchi','Bayelsa','Benue','Borno','Cross River','Delta','Ebonyi','Edo','Ekiti','Enugu','FCT','Gombe','Imo','Jigawa','Kaduna','Kano','Katsina','Kebbi','Kogi','Kwara','Lagos','Nasarawa','Niger','Ogun','Ondo','Osun','Oyo','Plateau','Rivers','Sokoto','Taraba','Yobe','Zamfara'];
const PW_RULE = 'Use at least 10 characters, including a letter and a number.';
const COND = ['NEW' => 'New', 'USED' => 'Used', 'REFURBISHED' => 'Refurbished'];
const KINDS = ['EQUIPMENT' => 'Heavy equipment', 'PART' => 'Spare part / implement', 'SUPPLY' => 'Supply'];
const MODES = ['FIXED_PRICE' => 'Fixed price — buy online', 'HYBRID' => 'Buy online or request a quote', 'QUOTE_ONLY' => 'Quotation only'];

const ROLE_LABEL = ['SUPER_ADMIN' => 'Super admin', 'STORE_MANAGER' => 'Store manager', 'INVENTORY_MANAGER' => 'Inventory manager', 'SALES_REP' => 'Sales representative', 'FINANCE_OFFICER' => 'Finance officer', 'DELIVERY_MANAGER' => 'Delivery manager', 'CUSTOMER_SUPPORT' => 'Customer support'];
const ALL_PERMS = ['dashboard.view','products.view','products.edit','products.publish','inventory.view','inventory.adjust','orders.view','orders.update','orders.cancel','payments.verify','refunds.create','deliveries.view','deliveries.manage','quotations.view','quotations.manage','enquiries.view','enquiries.manage','customers.view','promotions.manage','content.manage','settings.manage','staff.manage','audit.view'];

const ORDER_STATUS = ['PENDING_PAYMENT' => 'Pending payment', 'PAYMENT_PROCESSING' => 'Payment processing', 'PAID' => 'Paid', 'PAYMENT_FAILED' => 'Payment failed', 'CANCELLED' => 'Cancelled', 'REFUNDED' => 'Refunded', 'PARTIALLY_REFUNDED' => 'Partially refunded'];
const UNPAID = ['PENDING_PAYMENT', 'PAYMENT_PROCESSING', 'PAYMENT_FAILED'];
const DELIVERY_STATUS = ['AWAITING_ASSESSMENT' => 'Awaiting delivery assessment', 'QUOTE_PENDING' => 'Delivery quote pending', 'QUOTE_AVAILABLE' => 'Delivery quote available', 'AWAITING_PAYMENT' => 'Awaiting delivery payment', 'READY_FOR_DISPATCH' => 'Ready for dispatch', 'DISPATCHED' => 'Dispatched', 'IN_TRANSIT' => 'In transit', 'DELIVERED' => 'Delivered', 'EXCEPTION' => 'Delivery exception', 'CANCELLED' => 'Delivery cancelled'];
const DELIVERY_NEXT = [
    'AWAITING_ASSESSMENT' => ['READY_FOR_DISPATCH', 'CANCELLED', 'EXCEPTION'], 'QUOTE_PENDING' => ['CANCELLED', 'EXCEPTION'],
    'QUOTE_AVAILABLE' => ['QUOTE_PENDING', 'CANCELLED'], 'AWAITING_PAYMENT' => ['QUOTE_PENDING', 'CANCELLED'],
    'READY_FOR_DISPATCH' => ['DISPATCHED', 'DELIVERED', 'EXCEPTION', 'CANCELLED'], 'DISPATCHED' => ['IN_TRANSIT', 'DELIVERED', 'EXCEPTION'], 'IN_TRANSIT' => ['DELIVERED', 'EXCEPTION'],
    'DELIVERED' => [], 'EXCEPTION' => ['READY_FOR_DISPATCH', 'DISPATCHED', 'IN_TRANSIT', 'DELIVERED', 'CANCELLED'], 'CANCELLED' => [],
];
const PAY_LABEL = ['PAYSTACK' => 'Paystack', 'FLUTTERWAVE' => 'Flutterwave', 'BANK_TRANSFER' => 'Bank transfer', 'MANUAL' => 'Recorded by staff'];
const ENQ_TYPES = ['QUOTE' => 'Quotation request', 'FINANCING' => 'Financing enquiry', 'PART_COMPATIBILITY' => 'Part compatibility', 'PROPERTY' => 'Property enquiry', 'GENERAL' => 'General message', 'SUPPORT' => 'Order support'];
const ENQ_STATUS = ['NEW' => 'New', 'IN_PROGRESS' => 'In progress', 'QUOTED' => 'Quoted', 'CLOSED' => 'Closed'];

function perms_for(string $role): array
{
    return match ($role) {
        'SUPER_ADMIN' => ALL_PERMS,
        'STORE_MANAGER' => array_values(array_diff(ALL_PERMS, ['staff.manage', 'payments.verify', 'refunds.create'])),
        'INVENTORY_MANAGER' => ['dashboard.view','products.view','products.edit','inventory.view','inventory.adjust'],
        'SALES_REP' => ['dashboard.view','products.view','inventory.view','orders.view','quotations.view','quotations.manage','enquiries.view','enquiries.manage','customers.view'],
        'FINANCE_OFFICER' => ['dashboard.view','orders.view','orders.cancel','payments.verify','refunds.create','quotations.view','customers.view','audit.view'],
        'DELIVERY_MANAGER' => ['dashboard.view','orders.view','deliveries.view','deliveries.manage'],
        'CUSTOMER_SUPPORT' => ['dashboard.view','products.view','orders.view','deliveries.view','enquiries.view','enquiries.manage','customers.view','quotations.view'],
        default => [],
    };
}

/* ---------------- basics ---------------- */
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function now(): string { return date('c'); }
function naira($k): string
{
    if ($k === null || $k === '') return 'Request price';
    $k = (int)$k;
    $neg = $k < 0; $k = abs($k);
    $s = '₦' . number_format(intdiv($k, 100)) . ($k % 100 ? '.' . str_pad((string)($k % 100), 2, '0', STR_PAD_LEFT) : '');
    return $neg ? '−' . $s : $s;
}
function naira_input($k): string { return $k === null || $k === '' ? '' : (($k % 100) ? number_format($k / 100, 2, '.', '') : (string)intdiv((int)$k, 100)); }
function parse_naira($v): ?int
{
    $c = preg_replace('/[₦,\s]/u', '', (string)$v);
    if ($c === '') return null;
    if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $c)) throw new InvalidArgumentException('Enter an amount in Naira, e.g. 1250000 or 1250000.50');
    [$w, $f] = array_pad(explode('.', $c), 2, '');
    return (int)$w * 100 + (int)substr($f . '00', 0, 2);
}
function pct(int $amount, int $bps): int { return intdiv($amount * $bps + 5000, 10000); }
function incl_tax(int $gross, int $bps): int { return $bps ? intdiv($gross * $bps * 2 + (10000 + $bps), (10000 + $bps) * 2) : 0; }
function fmt_date($d, bool $time = false): string { if (!$d) return '—'; $t = strtotime((string)$d); return $t ? date($time ? 'j M Y, H:i' : 'j M Y', $t) : '—'; }
function humanize(string $s): string { $s = strtolower(str_replace('_', ' ', $s)); return ucfirst($s); }
function code(int $n): string { $a = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; $o = ''; for ($i = 0; $i < $n; $i++) $o .= $a[random_int(0, 31)]; return $o; }
function token(): string { return bin2hex(random_bytes(20)); }
function slugify(string $s): string { $s = strtolower(preg_replace('/\(.*?\)/', '', $s)); $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-'); return $s !== '' ? $s : 'item-' . strtolower(code(5)); }
function email_ok($e): bool { return (bool)preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', (string)$e); }
function phone_ok($p): bool { return (bool)preg_match('/^[+\d][\d\s()-]{6,}$/', (string)$p); }
function pw_ok($p): bool { return strlen((string)$p) >= 10 && preg_match('/[A-Za-z]/', $p) && preg_match('/\d/', $p); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function post(string $k, $d = '') { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $v; }
function q(string $k, $d = '') { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }

/* ---------------- settings ---------------- */
function setting(string $k, $d = '')
{
    global $__SETTINGS;
    if ($__SETTINGS === null) { $__SETTINGS = []; foreach (db()->query('SELECT skey, svalue FROM settings') as $r) $__SETTINGS[$r['skey']] = $r['svalue']; }
    return array_key_exists($k, $__SETTINGS) ? $__SETTINGS[$k] : $d;
}
function save_setting(string $k, $v): void
{
    global $__SETTINGS;
    $pdo = db();
    $ex = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE skey = ?'); $ex->execute([$k]);
    if ($ex->fetchColumn()) $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?')->execute([(string)$v, $k]);
    else $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)')->execute([$k, (string)$v]);
    if (is_array($__SETTINGS)) $__SETTINGS[$k] = (string)$v;
}

/* ---------------- URLs ---------------- */
function base_path(): string
{
    static $b = null;
    if ($b !== null) return $b;
    if (SITE_URL !== '') return $b = rtrim((string)parse_url(SITE_URL, PHP_URL_PATH), '/');
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    return $b = rtrim(str_replace('\\', '/', dirname($script)), '/.');
}
function url(string $p = '/'): string { return base_path() . '/' . ltrim($p, '/'); }
function full_url(string $p = '/'): string
{
    if (SITE_URL !== '') return rtrim(SITE_URL, '/') . '/' . ltrim($p, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($p);
}
function asset(?string $p): string { if (!$p) return ''; return preg_match('~^https?://~', $p) ? $p : url($p); }
function redirect(string $p): never { header('Location: ' . (preg_match('~^https?://~', $p) ? $p : url($p))); exit; }
function qsx(array $q, array $patch = []): string
{
    $o = [];
    foreach (array_merge($q, $patch) as $k => $v) if ($v !== null && $v !== '' && !is_array($v)) $o[$k] = $v;
    return $o ? '?' . http_build_query($o) : '';
}
function back_path(string $def = '/'): string
{
    $r = $_SERVER['HTTP_REFERER'] ?? '';
    if (!$r || parse_url($r, PHP_URL_HOST) !== strtok($_SERVER['HTTP_HOST'] ?? '', ':')) return $def;
    $p = (string)parse_url($r, PHP_URL_PATH); $bp = base_path();
    if ($bp !== '' && str_starts_with($p, $bp)) $p = substr($p, strlen($bp));
    $qs = parse_url($r, PHP_URL_QUERY);
    return safe_next(($p ?: '/') . ($qs ? '?' . $qs : ''), $def);
}
function safe_next(string $n, string $def = '/account'): string { return ($n !== '' && $n[0] === '/' && !str_starts_with($n, '//')) ? $n : $def; }

/* ---------------- session helpers ---------------- */
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf() . '">'; }
function csrf_ok(): bool { $t = (string)($_SESSION['csrf'] ?? ''); return $t !== '' && hash_equals($t, (string)($_POST['_csrf'] ?? '')); }
function require_csrf(): void { if (!csrf_ok()) { flash('Your session expired or the page was open too long. Please try again.', 'warn'); redirect(back_path('/')); } }
function flash(string $msg, string $tone = 'ok'): void { $_SESSION['flash'][] = [$tone, $msg]; }
function take_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function old(string $k, $d = '') { return $_SESSION['old'][$k] ?? $d; }
function form_errors(): array { $e = $_SESSION['errors'] ?? []; return $e; }
function fail(array $errors, string $back, string $msg = 'Check the highlighted fields.', string $tone = 'danger'): never
{
    $_SESSION['errors'] = $errors; $_SESSION['old'] = $_POST; unset($_SESSION['old']['_csrf'], $_SESSION['old']['password'], $_SESSION['old']['password2'], $_SESSION['old']['old']);
    $_SESSION['form_msg'] = [$tone, $msg];
    redirect($back);
}
function clear_form_state(): void { unset($_SESSION['errors'], $_SESSION['old'], $_SESSION['form_msg']); }
function throttle(string $key, int $max, int $window): bool
{
    $k = 'thr_' . $key; $now = time();
    $_SESSION[$k] = array_values(array_filter($_SESSION[$k] ?? [], fn($t) => $t > $now - $window));
    if (count($_SESSION[$k]) >= $max) return false;
    $_SESSION[$k][] = $now; return true;
}

/* ---------------- people ---------------- */
function current_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    if (empty($_SESSION['uid'])) return $u = null;
    $s = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1'); $s->execute([$_SESSION['uid']]);
    return $u = ($s->fetch() ?: null);
}
function user_addresses(int $uid): array { $s = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id'); $s->execute([$uid]); return $s->fetchAll(); }
function current_staff(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    if (empty($_SESSION['sid'])) return $u = null;
    $s = db()->prepare('SELECT * FROM staff WHERE id = ? AND active = 1'); $s->execute([$_SESSION['sid']]);
    return $u = ($s->fetch() ?: null);
}
function can(string $perm): bool { $s = current_staff(); return $s && in_array($perm, perms_for($s['role']), true); }
function actor(): string { $s = current_staff(); if ($s) return $s['name']; $u = current_user(); return $u ? $u['name'] : 'System'; }

/* ---------------- records ---------------- */
function audit(string $action, string $entity, string $details = ''): void
{
    db()->prepare('INSERT INTO audit (at, actor, action, entity, details) VALUES (?,?,?,?,?)')->execute([now(), actor(), $action, $entity, $details]);
}
function send_email(string $to, string $subject, string $body, ?string $link = null): void
{
    if (!$to) return;
    $sent = 0;
    if (setting('send_emails') === '1' && email_ok($to)) {
        $name = setting('short_name', 'KVO Mart');
        $from = setting('mail_from') ?: (setting('email') ?: 'no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
        $btn = $link ? '<p style="margin:24px 0"><a href="' . h(full_url($link)) . '" style="background:#B8892B;color:#000;padding:12px 20px;text-decoration:none;font-weight:bold;display:inline-block">Open</a></p>' : '';
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;border-top:4px solid #B8892B">'
            . '<div style="background:#000;color:#fff;padding:16px 22px;font-weight:bold;font-size:18px">' . h($name) . '<div style="font-size:12px;color:#CED2D6;font-weight:normal">' . h(setting('tagline')) . '</div></div>'
            . '<div style="padding:22px;color:#1E2023;line-height:1.6;font-size:15px">' . nl2br(h($body)) . $btn . '</div>'
            . '<div style="padding:14px 22px;background:#F5F6F7;color:#666B72;font-size:12px">' . h(setting('business_name')) . (setting('phone') ? ' · ' . h(setting('phone')) : '') . '</div></div>';
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: =?UTF-8?B?" . base64_encode($name) . "?= <$from>\r\nReply-To: " . (setting('email') ?: $from) . "\r\n";
        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers) ? 1 : 0;
    }
    db()->prepare('INSERT INTO email_log (at, to_addr, subject, body, link, sent) VALUES (?,?,?,?,?,?)')->execute([now(), $to, $subject, $body, $link, $sent]);
}
function admin_email(): string { return setting('admin_email') ?: setting('email'); }

/* ---------------- catalogue ---------------- */
function categories(bool $activeOnly = true): array
{
    static $c = [];
    $k = (int)$activeOnly;
    if (!isset($c[$k])) $c[$k] = db()->query('SELECT * FROM categories' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, name')->fetchAll();
    return $c[$k];
}
function cat_by_slug(?string $slug): ?array { foreach (categories(false) as $c) if ($c['slug'] === $slug) return $c; return null; }
function prod($id): ?array { if (!$id) return null; $s = db()->prepare('SELECT * FROM products WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; }
function prod_by_slug(string $slug): ?array { $s = db()->prepare('SELECT * FROM products WHERE slug = ?'); $s->execute([$slug]); return $s->fetch() ?: null; }
function specs(array $p): array { $s = json_decode((string)$p['specs'], true); return is_array($s) ? $s : []; }
function avail(array $p): int { return max(0, (int)$p['on_hand'] - (int)$p['reserved']); }
function price_of(array $p): ?int { return $p['price'] === null || $p['price'] === '' ? null : (int)$p['price']; }
function buyable(array $p): array
{
    if ($p['status'] !== 'PUBLISHED') return ['ok' => false, 'reason' => 'This item is no longer available.'];
    if ($p['mode'] === 'QUOTE_ONLY') return ['ok' => false, 'reason' => 'This item is sold by quotation only.'];
    if (price_of($p) === null) return ['ok' => false, 'reason' => 'Price on request — ask for a quotation.'];
    if (avail($p) <= 0) return ['ok' => false, 'reason' => 'Out of stock.'];
    return ['ok' => true, 'max' => $p['inv'] === 'UNIQUE_UNIT' ? 1 : min(999, avail($p))];
}
function stock_label(array $p): array
{
    if ($p['status'] !== 'PUBLISHED') return ['No longer available', ''];
    $a = avail($p);
    if ($p['mode'] === 'QUOTE_ONLY' || price_of($p) === null) return [$a > 0 ? 'Available — price on request' : 'Sourced on request', 'gold'];
    if ($a <= 0) return ['Out of stock', 'danger'];
    if ($p['inv'] === 'UNIQUE_UNIT') return ['Available', 'ok'];
    if ($a <= 3) return ["Only $a left", 'warn'];
    return ['In stock', 'ok'];
}
function catalogue_base(string $kind): string { return $kind === 'PART' ? '/spare-parts' : ($kind === 'SUPPLY' ? '/supplies' : '/equipment'); }
function cat_link(array $c): string { return url(catalogue_base($c['kind']) . '?category=' . urlencode($c['slug'])); }
function pub_counts(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    foreach (db()->query("SELECT cat, brand FROM products WHERE status = 'PUBLISHED'") as $r) {
        $m[$r['cat']] = ($m[$r['cat']] ?? 0) + 1;
        if ($r['brand'] === 'Caterpillar' && $r['cat'] !== 'caterpillar-machinery') $m['caterpillar-machinery'] = ($m['caterpillar-machinery'] ?? 0) + 1;
    }
    return $m;
}
function pub_count(string $slug): int { return pub_counts()[$slug] ?? 0; }

/* ---------------- money rules ---------------- */
function totals(array $lines, int $discount = 0, int $delivery = 0): array
{
    $bps = (int)setting('tax_bps'); $incl = setting('prices_include_tax') === '1';
    $sub = 0; foreach ($lines as $l) $sub += $l['unit'] * $l['qty'];
    $disc = min($discount, $sub); $taxable = $sub - $disc;
    $tax = $incl ? incl_tax($taxable, $bps) : pct($taxable, $bps);
    return ['subtotal' => $sub, 'discount' => $disc, 'delivery' => $delivery, 'tax' => $tax, 'total' => $taxable + ($incl ? 0 : $tax) + $delivery];
}
function zones(bool $activeOnly = true): array
{
    $r = db()->query('SELECT * FROM zones' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY id')->fetchAll();
    foreach ($r as &$z) { $z['states'] = json_decode($z['states'], true) ?: []; $z['fee'] = $z['fee'] === null ? null : (int)$z['fee']; }
    return $r;
}
/** $heavy = any line needs a delivery quote */
function delivery_options(bool $heavy, string $state): array
{
    $o = [];
    if (setting('pickup_enabled') === '1') $o[] = ['method' => 'PICKUP', 'label' => 'Collect from KVO Mart', 'fee' => 0, 'basis' => 'Customer collection — no delivery charge', 'lead' => null, 'initial' => 'READY_FOR_DISPATCH'];
    if ($state !== '') {
        $zone = null; foreach (zones() as $z) if (in_array($state, $z['states'], true)) { $zone = $z; break; }
        if ($zone && !$heavy && $zone['fee'] !== null) $o[] = ['method' => 'ZONE', 'label' => 'Standard delivery — ' . $zone['name'], 'fee' => $zone['fee'], 'basis' => 'Flat fee for delivery zone "' . $zone['name'] . '" (' . $state . ')', 'lead' => (int)$zone['lead_days'], 'initial' => 'READY_FOR_DISPATCH'];
        $o[] = ['method' => 'QUOTED', 'label' => $heavy ? 'Heavy equipment delivery (quoted)' : 'Delivery quoted after order', 'fee' => null, 'basis' => $heavy ? 'Transport cost depends on machine size, weight, loading and route — quoted separately for your approval' : 'No fixed rate for this location — quoted separately for your approval', 'lead' => null, 'initial' => 'AWAITING_ASSESSMENT'];
    }
    return $o;
}
function promo_by_code(string $c): ?array { $s = db()->prepare('SELECT * FROM promos WHERE code = ?'); $s->execute([strtoupper(trim($c))]); return $s->fetch() ?: null; }
function eval_promo(string $codeStr, int $subtotal, string $email): array
{
    $p = promo_by_code($codeStr);
    $t = time();
    if (!$p || !$p['active']) return ['ok' => false, 'reason' => "That code isn't valid."];
    if ($p['starts'] && $t < strtotime($p['starts'])) return ['ok' => false, 'reason' => "That code isn't active yet."];
    if ($p['ends'] && $t > strtotime($p['ends'])) return ['ok' => false, 'reason' => 'That code has expired.'];
    if ($p['max_uses'] !== null && (int)$p['uses'] >= (int)$p['max_uses']) return ['ok' => false, 'reason' => 'That code has reached its usage limit.'];
    if ($p['per_customer'] && $email !== '') {
        $s = db()->prepare("SELECT COUNT(*) FROM orders WHERE promo = ? AND email = ? AND status != 'CANCELLED'"); $s->execute([$p['code'], strtolower($email)]);
        if ((int)$s->fetchColumn() >= (int)$p['per_customer']) return ['ok' => false, 'reason' => "You've already used this code."];
    }
    if ($subtotal < (int)$p['min_order']) return ['ok' => false, 'reason' => 'This code needs a minimum order of ' . naira($p['min_order']) . '.'];
    $d = $p['type'] === 'PERCENT' ? pct($subtotal, (int)$p['value']) : (int)$p['value'];
    if ($p['max_disc'] !== null) $d = min($d, (int)$p['max_disc']);
    return ['ok' => true, 'discount' => min($d, $subtotal), 'promo' => $p];
}

/* ---------------- cart (session: [pid => qty]) ---------------- */
function cart_raw(): array { return $_SESSION['cart'] ?? []; }
function cart_count(): int { return array_sum(cart_raw()); }
function cart_lines(): array
{
    $out = [];
    foreach (cart_raw() as $pid => $qty) {
        $p = prod($pid);
        if (!$p) { unset($_SESSION['cart'][$pid]); continue; }
        $b = buyable($p);
        $out[] = ['pid' => (int)$pid, 'qty' => (int)$qty, 'p' => $p, 'unit' => $b['ok'] ? price_of($p) : null, 'max' => $b['ok'] ? $b['max'] : 0,
            'problem' => !$b['ok'] ? $b['reason'] : ($qty > $b['max'] ? 'Only ' . $b['max'] . ' available — reduce the quantity.' : null)];
    }
    return $out;
}
function add_to_cart(int $pid, int $qty): void
{
    $p = prod($pid);
    if (!$p) throw new RuntimeException('Product not found.');
    $b = buyable($p);
    if (!$b['ok']) throw new RuntimeException($b['reason']);
    if ($qty < 1) throw new RuntimeException('Choose a valid quantity.');
    $next = ($_SESSION['cart'][$pid] ?? 0) + $qty;
    if ($next > $b['max']) throw new RuntimeException($p['inv'] === 'UNIQUE_UNIT' ? "This is a single unit and it's already in your cart." : 'Only ' . $b['max'] . ' available.');
    $_SESSION['cart'][$pid] = $next;
}

/* ---------------- stock ---------------- */
function move(array $p, string $type, int $qty, string $reason, ?string $ref = null): void
{
    $f = prod($p['id']);
    db()->prepare('INSERT INTO movements (product_id, sku, type, qty, on_hand, reserved, reason, order_ref, by_name, at) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$f['id'], $f['sku'], $type, $qty, $f['on_hand'], $f['reserved'], $reason, $ref, current_staff()['name'] ?? 'System', now()]);
}
function order_by_ref(string $ref): ?array { $s = db()->prepare('SELECT * FROM orders WHERE ref = ?'); $s->execute([strtoupper(trim($ref))]); return $s->fetch() ?: null; }
function order_by_id($id): ?array { $s = db()->prepare('SELECT * FROM orders WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; }
function order_items(int $oid): array { $s = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id'); $s->execute([$oid]); return $s->fetchAll(); }
function order_payments(int $oid): array { $s = db()->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC'); $s->execute([$oid]); return $s->fetchAll(); }
function order_dquotes(int $oid): array { $s = db()->prepare('SELECT * FROM delivery_quotes WHERE order_id = ? ORDER BY id'); $s->execute([$oid]); return $s->fetchAll(); }
function order_events(int $oid): array { $s = db()->prepare('SELECT * FROM delivery_events WHERE order_id = ? ORDER BY id'); $s->execute([$oid]); return $s->fetchAll(); }
function order_refunds(int $oid): array { $s = db()->prepare('SELECT * FROM refunds WHERE order_id = ? ORDER BY id'); $s->execute([$oid]); return $s->fetchAll(); }
function order_link(array $o): string { return '/orders/' . $o['ref'] . '?t=' . $o['token']; }
function d_event(int $oid, string $status, string $note = ''): void { db()->prepare('INSERT INTO delivery_events (order_id, status, note, at) VALUES (?,?,?,?)')->execute([$oid, $status, $note, now()]); }
function set_order(int $oid, array $f): void
{
    $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($f)));
    db()->prepare("UPDATE orders SET $sets WHERE id = ?")->execute([...array_values($f), $oid]);
}

function release(array $o, string $reason): void
{
    if (!$o['reservation_active']) return;
    foreach (order_items((int)$o['id']) as $l) {
        if (!$l['product_id']) continue;
        db()->prepare('UPDATE products SET reserved = CASE WHEN reserved >= CAST(? AS INTEGER) THEN reserved - ? ELSE 0 END WHERE id = ?')->execute([$l['qty'], $l['qty'], $l['product_id']]);
        if ($p = prod($l['product_id'])) move($p, 'RELEASE', (int)$l['qty'], $reason, $o['ref']);
    }
    set_order((int)$o['id'], ['reservation_active' => 0]);
}

/**
 * Create an order. Prices always come from the catalogue (or an accepted quotation).
 * $in: lines[[pid,qty]] | quotation(array with items), user_id, email, name, phone, company,
 *      delivery_method, address[line1,city,state,instr], method, promo, notes, expected_total
 */
function create_order(array $in): array
{
    $pdo = db();
    $q = $in['quotation'] ?? null;
    if ($q) {
        $lines = []; $heavy = false;
        foreach ($q['items'] as $i) {
            $p = $i['product_id'] ? prod($i['product_id']) : null;
            if ($p && $p['dq']) $heavy = true;
            $lines[] = ['pid' => $p ? (int)$p['id'] : null, 'name' => $i['description'], 'sku' => $p['sku'] ?? 'QUOTE', 'unit' => (int)$i['unit'], 'qty' => (int)$i['qty'], 'stock' => (bool)$p];
        }
    } else {
        if (empty($in['lines'])) throw new RuntimeException('Your cart is empty.');
        $lines = []; $heavy = false;
        foreach ($in['lines'] as $l) {
            $p = prod($l['pid']);
            if (!$p) throw new RuntimeException('An item in your cart is no longer available.');
            $b = buyable($p);
            if (!$b['ok']) throw new RuntimeException($p['name'] . ': ' . $b['reason']);
            if ((int)$l['qty'] < 1) throw new RuntimeException($p['name'] . ': quantity must be a whole number of at least 1.');
            if ((int)$l['qty'] > $b['max']) throw new RuntimeException($p['name'] . ': only ' . $b['max'] . ' available.');
            if ($p['dq']) $heavy = true;
            $lines[] = ['pid' => (int)$p['id'], 'name' => $p['name'], 'sku' => $p['sku'], 'unit' => price_of($p), 'qty' => (int)$l['qty'], 'stock' => true];
        }
    }
    $opts = delivery_options($heavy, $in['address']['state']);
    $chosen = null; foreach ($opts as $o) if ($o['method'] === $in['delivery_method']) $chosen = $o;
    if (!$chosen) throw new RuntimeException("That delivery option isn't available for your location.");
    $sub = 0; foreach ($lines as $l) $sub += $l['unit'] * $l['qty'];
    $discount = 0; $promo = null;
    if (!empty($in['promo']) && !$q) {
        $r = eval_promo($in['promo'], $sub, $in['email']);
        if (!$r['ok']) throw new RuntimeException($r['reason']);
        $discount = $r['discount']; $promo = $r['promo'];
    }
    $deliveryFee = $q ? (int)$q['delivery'] : (int)($chosen['fee'] ?? 0);
    if ($q && $chosen['method'] === 'PICKUP') $deliveryFee = 0;
    $t = totals($lines, $discount, $deliveryFee);
    if (isset($in['expected_total']) && $in['expected_total'] !== null && (int)$in['expected_total'] !== $t['total'])
        throw new DomainException('Prices or charges changed since you loaded this page. Review the updated total and confirm again.');

    $ref = 'KVO-' . code(8);
    $holdMin = $in['method'] === 'BANK_TRANSFER' ? (int)setting('transfer_hold_hours', 48) * 60 : (int)setting('card_hold_min', 60);
    $dStatus = ($q && $q['delivery'] && $chosen['method'] !== 'PICKUP') ? 'READY_FOR_DISPATCH' : $chosen['initial'];
    $dFee = ($chosen['fee'] === null && !$q) ? null : $deliveryFee;
    $basis = ($q && $q['delivery'] && $chosen['method'] !== 'PICKUP') ? 'Delivery as quoted in ' . $q['number'] : $chosen['basis'];

    $pdo->beginTransaction();
    try {
        // reserve every line or none (prevents overselling)
        $res = $pdo->prepare("UPDATE products SET reserved = reserved + ? WHERE id = ? AND status = 'PUBLISHED' AND on_hand - reserved >= CAST(? AS INTEGER)");
        foreach ($lines as $l) {
            if (!$l['pid'] || !$l['stock']) continue;
            $res->execute([$l['qty'], $l['pid'], $l['qty']]);
            if ($res->rowCount() === 0) throw new RuntimeException($l['name'] . ' no longer has enough stock for this order.');
        }
        $pdo->prepare('INSERT INTO orders (ref, token, user_id, email, name, phone, company, subtotal, discount, tax, total, delivery_charge, paid, refunded, status, method, delivery_method, addr_line1, addr_city, addr_state, addr_instr, notes, promo, quotation_id, reservation_active, hold_until, stock_committed, created, d_status, d_fee, d_basis, d_lead) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,0,?,?,?,?,?,?,?,?,?,?,1,?,0,?,?,?,?,?)')
            ->execute([$ref, token(), $in['user_id'] ?? null, strtolower($in['email']), $in['name'], $in['phone'], $in['company'] ?? '', $t['subtotal'], $t['discount'], $t['tax'], $t['total'], $t['delivery'], 'PENDING_PAYMENT', $in['method'], $chosen['method'], $in['address']['line1'], $in['address']['city'], $in['address']['state'], $in['address']['instr'] ?? '', $in['notes'] ?? '', $promo['code'] ?? null, $q['id'] ?? null, date('c', time() + $holdMin * 60), now(), $dStatus, $dFee, $basis, $chosen['lead']]);
        $oid = (int)$pdo->lastInsertId();
        $oi = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, sku, unit, qty, line_total) VALUES (?,?,?,?,?,?,?)');
        foreach ($lines as $l) $oi->execute([$oid, $l['pid'], $l['name'], $l['sku'], $l['unit'], $l['qty'], $l['unit'] * $l['qty']]);
        if ($promo) $pdo->prepare('UPDATE promos SET uses = uses + 1 WHERE id = ?')->execute([$promo['id']]);
        $pdo->prepare('INSERT INTO delivery_events (order_id, status, note, at) VALUES (?,?,?,?)')->execute([$oid, $dStatus, 'Order placed', now()]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

    $o = order_by_id($oid);
    foreach ($lines as $l) if ($l['pid'] && $l['stock'] && ($p = prod($l['pid']))) move($p, 'RESERVATION', $l['qty'], 'Reserved for unpaid order', $ref);
    audit('order.created', $ref, 'Total ' . naira($t['total']));
    return $o;
}

function new_payment(array $o, string $provider, string $purpose = 'ORDER', ?int $amount = null, ?int $dqId = null): array
{
    $amount ??= (int)$o['total'] - (int)$o['paid'];
    $ref = str_replace('-', '', $o['ref']) . 'P' . code(6);
    db()->prepare('INSERT INTO payments (order_id, ref, provider, purpose, amount, status, at, dq_id, note) VALUES (?,?,?,?,?,?,?,?,\'\')')
        ->execute([$o['id'], $ref, $provider, $purpose, $amount, $provider === 'BANK_TRANSFER' ? 'PENDING' : 'INITIATED', now(), $dqId]);
    if ($purpose === 'ORDER' && $provider !== 'BANK_TRANSFER' && in_array($o['status'], ['PENDING_PAYMENT', 'PAYMENT_FAILED'], true)) set_order((int)$o['id'], ['status' => 'PAYMENT_PROCESSING']);
    return payment_by_ref($ref);
}
function payment_by_ref(string $ref): ?array { $s = db()->prepare('SELECT * FROM payments WHERE ref = ?'); $s->execute([$ref]); return $s->fetch() ?: null; }

/**
 * Apply a verified payment result. Idempotent — a succeeded payment is never applied twice.
 * $result: success | failed | abandoned. Returns paid|already|failed|abandoned|mismatch.
 */
function apply_payment(string $ref, string $result, int $amount, string $source, ?string $providerRef = null): string
{
    $pdo = db();
    $p = payment_by_ref($ref);
    if (!$p) throw new RuntimeException('Unknown payment');
    $o = order_by_id($p['order_id']);
    if ($p['status'] === 'SUCCEEDED') return 'already';
    if ($result === 'failed' || $result === 'abandoned') {
        if ($p['status'] === 'PENDING' && $p['provider'] === 'BANK_TRANSFER' && $source !== 'staff') return 'ignored';
        $st = $result === 'failed' ? 'FAILED' : 'ABANDONED';
        $pdo->prepare('UPDATE payments SET status = ? WHERE id = ? AND status != \'SUCCEEDED\'')->execute([$st, $p['id']]);
        if ($p['purpose'] === 'ORDER' && in_array($o['status'], ['PENDING_PAYMENT', 'PAYMENT_PROCESSING'], true)) set_order((int)$o['id'], ['status' => $result === 'failed' ? 'PAYMENT_FAILED' : 'PENDING_PAYMENT']);
        audit('payment.' . strtolower($st), $o['ref'], $p['ref'] . " ($source)");
        if ($result === 'failed') send_email($o['email'], 'Payment not completed — ' . $o['ref'], "We couldn't confirm payment for order {$o['ref']}. You haven't been charged for this attempt. You can try again from your order page.", order_link($o));
        return strtolower($st);
    }
    if ($amount !== (int)$p['amount']) {
        $note = 'Amount mismatch: expected ' . naira($p['amount']) . ', got ' . naira($amount) . ' — held for review';
        $pdo->prepare("UPDATE payments SET status = 'PENDING', note = ?, provider_ref = COALESCE(provider_ref, ?) WHERE id = ?")->execute([$note, $providerRef, $p['id']]);
        audit('payment.mismatch', $o['ref'], $note);
        send_email(admin_email(), 'Payment held for review — ' . $o['ref'], $note, '/admin/orders/' . $o['ref']);
        return 'mismatch';
    }
    $pdo->beginTransaction();
    try {
        $u = $pdo->prepare("UPDATE payments SET status = 'SUCCEEDED', verified_at = ?, provider_ref = ? WHERE id = ? AND status != 'SUCCEEDED'");
        $u->execute([now(), $providerRef ?: ($p['provider_ref'] ?: 'TX' . code(8)), $p['id']]);
        if ($u->rowCount() === 0) { $pdo->rollBack(); return 'already'; }
        $o = order_by_id($p['order_id']);
        if ($p['purpose'] === 'DELIVERY') {
            set_order((int)$o['id'], ['paid' => $o['paid'] + $amount, 'delivery_charge' => $o['delivery_charge'] + $amount, 'total' => $o['total'] + $amount, 'd_fee' => $amount]);
            if ($p['dq_id']) $pdo->prepare("UPDATE delivery_quotes SET status = 'PAID' WHERE id = ?")->execute([$p['dq_id']]);
            if (in_array($o['d_status'], ['AWAITING_PAYMENT', 'QUOTE_AVAILABLE'], true)) { set_order((int)$o['id'], ['d_status' => 'READY_FOR_DISPATCH']); d_event((int)$o['id'], 'READY_FOR_DISPATCH', 'Delivery payment verified'); }
        } else {
            $issue = null; $committed = (int)$o['stock_committed'];
            if (!$committed) {
                $items = order_items((int)$o['id']);
                if ($o['reservation_active']) {
                    foreach ($items as $l) if ($l['product_id']) $pdo->prepare('UPDATE products SET on_hand = on_hand - ?, reserved = CASE WHEN reserved >= CAST(? AS INTEGER) THEN reserved - ? ELSE 0 END, sales = sales + ? WHERE id = ?')->execute([$l['qty'], $l['qty'], $l['qty'], $l['qty'], $l['product_id']]);
                    $committed = 1; $reason = 'Sold — payment verified';
                } else {
                    $okAll = true; foreach ($items as $l) if ($l['product_id'] && ($pp = prod($l['product_id'])) && avail($pp) < $l['qty']) $okAll = false;
                    if ($okAll) { foreach ($items as $l) if ($l['product_id']) $pdo->prepare('UPDATE products SET on_hand = on_hand - ?, sales = sales + ? WHERE id = ?')->execute([$l['qty'], $l['qty'], $l['product_id']]); $committed = 1; $reason = 'Sold — payment verified after hold lapsed'; }
                    else $issue = 'Payment verified after the stock hold lapsed and the stock is no longer available. Arrange supply or refund.';
                }
            }
            $status = $o['status'] === 'CANCELLED' || in_array($o['status'], UNPAID, true) ? 'PAID' : $o['status'];
            set_order((int)$o['id'], ['status' => $status, 'paid_at' => now(), 'paid' => $o['paid'] + $amount, 'reservation_active' => 0, 'stock_committed' => $committed, 'stock_issue' => $issue, 'method' => $p['provider'] === 'MANUAL' ? $o['method'] : $p['provider']]);
            if ($o['status'] === 'CANCELLED' && !in_array($o['d_status'], ['DELIVERED'], true)) { set_order((int)$o['id'], ['d_status' => $o['delivery_method'] === 'QUOTED' ? 'AWAITING_ASSESSMENT' : 'READY_FOR_DISPATCH']); }
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    if ($p['purpose'] === 'ORDER' && isset($reason)) foreach (order_items((int)$o['id']) as $l) if ($l['product_id'] && ($pp = prod($l['product_id']))) move($pp, 'SALE', -(int)$l['qty'], $reason, $o['ref']);
    audit('payment.succeeded', $o['ref'], $p['ref'] . ' ' . naira($amount) . " ($source)");
    $o = order_by_id($p['order_id']);
    send_email($o['email'], 'Payment confirmed — ' . $o['ref'], "We've verified your payment of " . naira($amount) . ' for ' . ($p['purpose'] === 'DELIVERY' ? 'delivery of' : '') . " order {$o['ref']}.", order_link($o));
    send_email(admin_email(), 'Payment verified — ' . $o['ref'], 'A payment of ' . naira($amount) . " was verified for order {$o['ref']} ({$o['name']}).", '/admin/orders/' . $o['ref']);
    return 'paid';
}

function cancel_unpaid(array $o, string $reason): void
{
    if (!in_array($o['status'], UNPAID, true)) throw new RuntimeException('Only unpaid orders can be cancelled this way.');
    release($o, 'Order cancelled: ' . $reason);
    if ($o['promo']) db()->prepare('UPDATE promos SET uses = CASE WHEN uses > 0 THEN uses - 1 ELSE 0 END WHERE code = ?')->execute([$o['promo']]);
    db()->prepare("UPDATE payments SET status = 'ABANDONED' WHERE order_id = ? AND status IN ('INITIATED','PENDING')")->execute([$o['id']]);
    $f = ['status' => 'CANCELLED', 'cancel_reason' => $reason];
    if (!in_array($o['d_status'], ['DELIVERED', 'CANCELLED'], true)) { $f['d_status'] = 'CANCELLED'; d_event((int)$o['id'], 'CANCELLED', $reason); }
    set_order((int)$o['id'], $f);
    audit('order.cancelled', $o['ref'], $reason);
}

/** Cancel unpaid orders whose stock hold has expired. Runs at most once a minute. */
function expire_holds(bool $force = false): int
{
    $last = (int)setting('last_expiry_run', 0);
    if (!$force && time() - $last < 60) return 0;
    save_setting('last_expiry_run', time());
    $s = db()->prepare("SELECT * FROM orders WHERE reservation_active = 1 AND status IN ('PENDING_PAYMENT','PAYMENT_PROCESSING','PAYMENT_FAILED') AND hold_until < ?");
    $s->execute([now()]);
    $n = 0;
    foreach ($s->fetchAll() as $o) {
        // a bank transfer the customer reported is never auto-cancelled
        $rep = db()->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ? AND provider = 'BANK_TRANSFER' AND status = 'PENDING' AND note != ''");
        $rep->execute([$o['id']]);
        if ($rep->fetchColumn()) continue;
        cancel_unpaid($o, 'Payment not received before the stock hold expired');
        send_email($o['email'], "Order {$o['ref']} cancelled", "Order {$o['ref']} was cancelled because payment wasn't received before the stock hold expired. You're welcome to place a new order.", order_link($o));
        $n++;
    }
    return $n;
}

function can_see_order(array $o): bool
{
    $u = current_user();
    if ($u && (int)$o['user_id'] === (int)$u['id']) return true;
    if (!empty($_SESSION['order_access'][$o['ref']])) return true;
    if (isset($_GET['t']) && hash_equals((string)$o['token'], (string)$_GET['t'])) { $_SESSION['order_access'][$o['ref']] = 1; return true; }
    return can('orders.view');
}

/* ---------------- quotations ---------------- */
function quote_by_number(string $n): ?array { $s = db()->prepare('SELECT * FROM quotations WHERE number = ?'); $s->execute([$n]); return $s->fetch() ?: null; }
function quote_by_id($id): ?array { $s = db()->prepare('SELECT * FROM quotations WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; }
function quote_items(int $qid): array { $s = db()->prepare('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id'); $s->execute([$qid]); return $s->fetchAll(); }
function quote_status(array $q): string { return in_array($q['status'], ['SENT', 'VIEWED'], true) && $q['valid_until'] && strtotime($q['valid_until'] . ' 23:59:59') < time() ? 'EXPIRED' : $q['status']; }
function quote_tone(array $q): string { return ['ACCEPTED' => 'ok', 'CONVERTED' => 'ok', 'DECLINED' => 'danger', 'EXPIRED' => 'danger', 'SENT' => 'gold', 'VIEWED' => 'gold'][quote_status($q)] ?? ''; }
function recalc_quote(int $qid): void
{
    $q = quote_by_id($qid); $sub = 0; $tax = 0;
    foreach (quote_items($qid) as $i) { $sub += $i['unit'] * $i['qty']; $tax += pct($i['unit'] * $i['qty'], (int)$i['tax_bps']); }
    db()->prepare('UPDATE quotations SET subtotal = ?, tax = ?, total = ? WHERE id = ?')->execute([$sub, $tax, $sub + $tax + (int)$q['delivery'], $qid]);
}
function quote_link(array $q): string { return '/quotes/' . $q['number'] . '?t=' . $q['token']; }
function quote_to_order(array $q, array $addr, string $pay): array
{
    $q['items'] = quote_items((int)$q['id']);
    $o = create_order(['quotation' => $q, 'user_id' => $q['user_id'], 'email' => $q['email'], 'name' => $q['name'], 'phone' => $q['phone'] ?: '—', 'company' => $q['company'], 'delivery_method' => (int)$q['delivery'] ? 'QUOTED' : 'PICKUP', 'address' => $addr, 'method' => $pay]);
    db()->prepare("UPDATE quotations SET status = 'CONVERTED', order_ref = ? WHERE id = ?")->execute([$o['ref'], $q['id']]);
    $_SESSION['order_access'][$o['ref']] = 1;
    audit('quotation.converted', $q['number'], $o['ref']);
    send_email($o['email'], "Order created from {$q['number']} — {$o['ref']}", "Your accepted quotation {$q['number']} is now order {$o['ref']} for " . naira($o['total']) . '.', order_link($o));
    return $o;
}

/* ---------------- uploads ---------------- */
function upload_image(?array $file, string $folder, int $maxW = 1600): ?string
{
    if (!$file || empty($file['tmp_name']) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('Image is larger than 10MB.');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) throw new RuntimeException('Upload a JPG, PNG, WEBP or GIF image.');
    $dir = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $types[$info[2]];
    $dest = $dir . '/' . $name;
    if (function_exists('imagecreatetruecolor') && $info[0] > $maxW && $info[2] !== IMAGETYPE_GIF) {
        $src = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']), IMAGETYPE_PNG => @imagecreatefrompng($file['tmp_name']), IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false, default => false };
        if ($src) {
            $hgt = (int)round($info[1] * $maxW / $info[0]);
            $dst = imagecreatetruecolor($maxW, $hgt); imagealphablending($dst, false); imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $hgt, $info[0], $info[1]);
            $ok = match ($info[2]) { IMAGETYPE_JPEG => imagejpeg($dst, $dest, 84), IMAGETYPE_PNG => imagepng($dst, $dest, 7), IMAGETYPE_WEBP => imagewebp($dst, $dest, 84) };
            if ($ok) return 'uploads/' . $folder . '/' . $name;
        }
    }
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Could not save the image. Check that the uploads folder is writable.');
    return 'uploads/' . $folder . '/' . $name;
}
function delete_upload(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) return;
    $f = realpath(__DIR__ . '/../' . $path); $base = realpath(__DIR__ . '/../uploads');
    if ($f && $base && str_starts_with($f, $base) && is_file($f)) @unlink($f);
}
function files_list(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) return [];
    $o = [];
    foreach ($_FILES[$field]['name'] as $i => $n) if ($n !== '') $o[] = ['name' => $n, 'tmp_name' => $_FILES[$field]['tmp_name'][$i], 'error' => $_FILES[$field]['error'][$i], 'size' => $_FILES[$field]['size'][$i]];
    return $o;
}
function clean_html(string $html): string
{
    $html = strip_tags($html, '<p><br><b><strong><i><em><u><ul><ol><li><h2><h3><h4><a><blockquote><table><thead><tbody><tr><th><td><hr>');
    $html = preg_replace('/\s(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    return preg_replace('/href\s*=\s*(["\']?)\s*(javascript|data|vbscript):[^"\'>\s]*/i', 'href=$1#', $html);
}
function wa_number(): string { $n = preg_replace('/\D+/', '', (string)setting('whatsapp')); if (str_starts_with($n, '0')) $n = '234' . substr($n, 1); return $n; }
function wa_link(string $text = ''): string { return 'https://wa.me/' . wa_number() . ($text !== '' ? '?text=' . rawurlencode($text) : ''); }
