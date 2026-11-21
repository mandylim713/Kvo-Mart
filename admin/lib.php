<?php
/** Admin layout and helpers (same look as the approved demo admin). */

const ANAV = [
    ['', 'Dashboard', 'dashboard.view'], ['orders', 'Orders', 'orders.view'], ['deliveries', 'Deliveries', 'deliveries.view'],
    ['products', 'Products', 'products.view'], ['categories', 'Categories', 'products.edit'], ['inventory', 'Inventory', 'inventory.view'],
    ['enquiries', 'Enquiries', 'enquiries.view'], ['quotations', 'Quotations', 'quotations.view'], ['customers', 'Customers', 'customers.view'],
    ['promotions', 'Promotions', 'promotions.manage'], ['zones', 'Delivery zones', 'settings.manage'], ['estate', 'Real estate', 'content.manage'],
    ['content', 'Pages & FAQs', 'content.manage'], ['subscribers', 'Subscribers', 'customers.view'], ['settings', 'Settings', 'settings.manage'],
    ['staff', 'Staff & roles', 'staff.manage'], ['emails', 'Email log', 'audit.view'], ['audit', 'Audit log', 'audit.view'],
];

function need(string $perm): void { if (!can($perm)) { flash("Your role doesn't have permission to do that.", 'danger'); redirect(back_path('/admin')); } }
function panel(string $title, string $body, string $action = ''): string { return '<section class="panel"><div class="ph"><h2>' . h($title) . '</h2>' . $action . '</div><div class="pb">' . $body . '</div></section>'; }
function ahead(string $title, string $sub = '', string $action = ''): string { return '<div class="row between" style="margin-bottom:1.25rem"><div><h1 style="font-size:1.6rem">' . h($title) . '</h1>' . ($sub ? '<p class="sm muted mt1">' . $sub . '</p>' : '') . '</div>' . $action . '</div>'; }
function aurl(string $p = ''): string { return url('/admin' . ($p !== '' ? '/' . ltrim($p, '/') : '')); }
function attn_count(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM orders o WHERE status <> 'CANCELLED' AND (stock_issue IS NOT NULL AND stock_issue <> '' OR EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.status = 'PENDING' AND p.purpose = 'ORDER'))")->fetchColumn();
}
function new_enq_count(): int { return (int)db()->query("SELECT COUNT(*) FROM enquiries WHERE status = 'NEW'")->fetchColumn(); }
function pay_badge(array $p): string { return badge(humanize($p['status']), $p['status'] === 'SUCCEEDED' ? 'ok' : ($p['status'] === 'FAILED' ? 'danger' : ($p['status'] === 'PENDING' ? 'warn' : ''))); }
function cb(string $name, bool $on, string $label): string { return '<label class="chk mt2"><input type="checkbox" name="' . h($name) . '" value="1"' . ($on ? ' checked' : '') . '> ' . $label . '</label>'; }
function admin_orders_table(array $list): string
{
    if (!$list) return '<p class="sm muted">No orders.</p>';
    $h = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Payment</th><th>Delivery</th><th class="r">Total</th></tr></thead><tbody>';
    foreach ($list as $o) $h .= '<tr><td class="first"><a class="b" href="' . h(aurl('orders/' . $o['ref'])) . '">' . h($o['ref']) . '</a></td><td data-l="Customer">' . h($o['name']) . '<div class="xs muted">' . h($o['addr_city']) . ', ' . h($o['addr_state']) . '</div></td><td data-l="Date">' . fmt_date($o['created']) . '</td><td data-l="Payment">' . badge(ORDER_STATUS[$o['status']] ?? '', ost_tone($o['status'])) . '<div class="xs muted">' . h(PAY_LABEL[$o['method']] ?? '') . '</div></td><td data-l="Delivery" class="sm">' . h(DELIVERY_STATUS[$o['d_status']] ?? '') . '</td><td data-l="Total" class="r tab">' . naira($o['total']) . '</td></tr>';
    return $h . '</tbody></table></div>';
}
function admin_pager(int $total, int $page, int $per, array $q): string
{
    $pages = (int)ceil($total / $per); if ($pages <= 1) return '';
    global $ROUTE_PATH;
    return '<nav class="row between mt4">' . ($page > 1 ? '<a class="btn out sm" href="' . h(url($ROUTE_PATH . qsx($q, ['page' => $page - 1]))) . '">Previous</a>' : '<span></span>') . '<span class="sm muted">Page ' . $page . ' of ' . $pages . '</span>' . ($page < $pages ? '<a class="btn out sm" href="' . h(url($ROUTE_PATH . qsx($q, ['page' => $page + 1]))) . '">Next</a>' : '<span></span>') . '</nav>';
}

function admin_open(string $title): void
{
    global $sec;
    $s = current_staff();
    $flashes = take_flashes();
    $attn = can('orders.view') ? attn_count() : 0; $ne = can('enquiries.view') ? new_enq_count() : 0;
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">'
        . '<title>' . h($title) . ' — ' . h(setting('short_name', 'KVO Mart')) . ' Admin</title><link rel="icon" href="' . h(asset(setting('logo'))) . '">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . h(url('/assets/css/site.css')) . '?v=7"></head><body>'
        . '<div class="admin"><aside class="aside" id="aside"><div class="amenu"><b style="color:#fff;font-family:var(--disp)">KVO Admin</b><button class="ib" type="button" style="color:#fff" data-aside aria-label="Menu" aria-expanded="false">' . ICON['menu'] . '</button></div>'
        . '<a class="abrand" href="' . h(url('/')) . '"><span style="background:#fff;padding:3px"><img src="' . h(asset(setting('logo'))) . '" alt="" style="height:30px"></span><b style="color:#fff;font-family:var(--disp)">KVO Admin</b></a>'
        . '<div class="anav"><p class="xs" style="padding:0 1rem .75rem;color:var(--steel400)">' . h($s['name']) . '<br><span style="color:var(--gold3)">' . h(ROLE_LABEL[$s['role']] ?? $s['role']) . '</span></p><nav>';
    foreach (ANAV as [$k, $l, $p]) {
        if (!can($p)) continue;
        $badge = $k === 'orders' && $attn ? ' <span class="badge gold" style="margin-left:.25rem">' . $attn . '</span>' : ($k === 'enquiries' && $ne ? ' <span class="badge gold" style="margin-left:.25rem">' . $ne . '</span>' : '');
        echo '<a href="' . h(aurl($k)) . '" class="' . (($sec ?? '') === $k ? 'on' : '') . '">' . h($l) . $badge . '</a>';
    }
    echo '<a href="' . h(aurl('profile')) . '" class="' . (($sec ?? '') === 'profile' ? 'on' : '') . '">My password</a></nav>'
        . '<div style="padding:1rem"><a href="' . h(url('/')) . '" class="sm" style="padding:0;border:0" target="_blank">← View store</a><form method="post" action="' . h(aurl('logout')) . '">' . csrf_field() . '<button class="btn out sm mt3" style="border-color:var(--steel400);color:#fff">Sign out</button></form></div></div></aside>'
        . '<main class="amain" id="main">';
    foreach ($flashes as [$t, $m]) echo '<div class="note ' . h($t) . '" style="margin-bottom:1rem" role="status">' . h($m) . '</div>';
    if (($s['must_change'] ?? false)) {}
}
function admin_close(string $foot = ''): void
{
    echo '</main></div><div class="toast" role="status" hidden></div><script>window.KVO=' . json_encode(['cart' => url('/cart')]) . '</script><script src="' . h(url('/assets/js/site.js')) . '?v=7" defer></script><script src="' . h(url('/assets/js/admin.js')) . '?v=7" defer></script>' . $foot . '</body></html>';
    clear_form_state();
}
