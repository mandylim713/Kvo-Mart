<?php
$pdo = db();
$me = current_staff();
$revenue = (int)$pdo->query('SELECT COALESCE(SUM(paid - refunded),0) FROM orders WHERE paid > 0')->fetchColumn();
$month = (int)$pdo->query("SELECT COALESCE(SUM(paid - refunded),0) FROM orders WHERE paid > 0 AND paid_at >= '" . date('Y-m-01') . "'")->fetchColumn();
$nOrders = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pendingTx = $pdo->query("SELECT DISTINCT o.* FROM orders o JOIN payments p ON p.order_id = o.id WHERE p.provider = 'BANK_TRANSFER' AND p.status = 'PENDING' AND p.purpose = 'ORDER' AND o.status <> 'CANCELLED' ORDER BY o.id DESC")->fetchAll();
$needDq = $pdo->query("SELECT * FROM orders WHERE status = 'PAID' AND d_status IN ('AWAITING_ASSESSMENT','QUOTE_PENDING') ORDER BY id")->fetchAll();
$ready = $pdo->query("SELECT * FROM orders WHERE status = 'PAID' AND d_status = 'READY_FOR_DISPATCH' ORDER BY id")->fetchAll();
$issues = $pdo->query("SELECT * FROM orders WHERE stock_issue IS NOT NULL AND stock_issue <> '' AND status <> 'REFUNDED'")->fetchAll();
$low = array_filter($pdo->query("SELECT * FROM products WHERE status = 'PUBLISHED' AND price IS NOT NULL AND inv <> 'UNIQUE_UNIT'")->fetchAll(), fn($p) => avail($p) <= (int)$p['low']);
$newEnq = $pdo->query("SELECT * FROM enquiries WHERE status = 'NEW' ORDER BY id DESC")->fetchAll();
$accepted = $pdo->query("SELECT * FROM quotations WHERE status = 'ACCEPTED' ORDER BY id DESC")->fetchAll();
$recent = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 6')->fetchAll();

// 14-day sales
$days = []; for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = 0;
foreach ($pdo->query("SELECT paid_at, paid FROM orders WHERE paid > 0 AND paid_at >= '" . array_key_first($days) . "'") as $r) { $d = substr($r['paid_at'], 0, 10); if (isset($days[$d])) $days[$d] += (int)$r['paid']; }
$maxd = max(1, max($days));

$tile = fn($l, $v, $href, $perm) => can($perm) ? '<a href="' . h(aurl($href)) . '"><p class="sm muted">' . $l . '</p><p class="v tab">' . $v . '</p></a>' : '<div><p class="sm muted">' . $l . '</p><p class="v tab">—</p></div>';
$list = function ($title, $items, $fn, $perm, $empty = 'Nothing waiting.') { if (!can($perm)) return ''; $b = $items ? '<ul style="list-style:none;padding:0;margin:0">' . implode('', array_map(fn($x) => '<li style="padding:.45rem 0;border-bottom:1px solid var(--steel)" class="sm">' . $fn($x) . '</li>', array_slice($items, 0, 8))) . '</ul>' : '<p class="sm muted">' . $empty . '</p>'; return panel($title . ($items ? ' (' . count($items) . ')' : ''), $b); };
$oa = fn($o) => '<a class="b" href="' . h(aurl('orders/' . $o['ref'])) . '">' . h($o['ref']) . '</a>';

$setup = [];
if (can('settings.manage')) {
    if (!setting('phone') && !setting('email')) $setup[] = 'Add the business phone, email and address';
    if (!setting('whatsapp')) $setup[] = 'Add the WhatsApp number';
    if (str_contains(setting('bank_instructions'), '(add')) $setup[] = 'Enter the real bank account for transfers';
    if (!online_provider()) $setup[] = 'Connect Paystack or Flutterwave for card payments';
    if (!admin_email()) $setup[] = 'Set the email address that receives order notifications';
}
if (password_verify('KvoAdmin2026', $me['password'])) array_unshift($setup, '<a class="link" href="' . h(aurl('profile')) . '">Change the starting admin password</a>');

admin_open('Dashboard');
echo ahead('Dashboard', 'Signed in as ' . h(ROLE_LABEL[$me['role']] ?? '') . '.');
if ($setup) echo '<div style="margin-bottom:1.25rem">' . note('Finish setting up the store', '<ul style="margin:.4rem 0 0;padding-left:1.1rem">' . implode('', array_map(fn($s) => '<li>' . (str_contains($s, '<a') ? $s : '<a class="link" href="' . h(aurl('settings')) . '">' . h($s) . '</a>') . '</li>', $setup)) . '</ul>', 'gold') . '</div>';
echo '<div class="tiles">' . $tile('Revenue collected', naira($revenue), 'orders?status=PAID', 'orders.view') . $tile('This month', naira($month), 'orders?status=PAID', 'orders.view') . $tile('Transfers to verify', count($pendingTx), 'orders?attn=1', 'payments.verify') . $tile('New enquiries', count($newEnq), 'enquiries', 'enquiries.view') . '</div>';
if (can('orders.view')) {
    echo '<section class="panel mt6"><div class="ph"><h2>Payments received — last 14 days</h2><span class="sm muted tab">' . naira(array_sum($days)) . '</span></div><div class="pb"><div style="display:flex;align-items:flex-end;gap:4px;height:120px">';
    foreach ($days as $d => $v) echo '<div title="' . h(date('j M', strtotime($d)) . ': ' . naira($v)) . '" style="flex:1;background:' . ($v ? 'var(--gold)' : 'var(--steel)') . ';height:' . max(3, round($v / $maxd * 100)) . '%"></div>';
    echo '</div><div style="display:flex;gap:4px;margin-top:4px">'; foreach ($days as $d => $v) echo '<span class="xs muted" style="flex:1;text-align:center">' . date('j', strtotime($d)) . '</span>'; echo '</div></div></section>';
}
echo '<div class="grid g2" style="align-items:start"><div>'
    . $list('Stock issues to resolve', $issues, fn($o) => $oa($o) . ' — ' . h($o['name']), 'orders.view', 'None.')
    . $list('Bank transfers awaiting verification', $pendingTx, fn($o) => $oa($o) . ' — ' . h($o['name']) . ' · ' . naira($o['total']), 'payments.verify')
    . $list('Paid orders needing a delivery quote', $needDq, fn($o) => $oa($o) . ' — ' . h($o['addr_city']) . ', ' . h($o['addr_state']), 'deliveries.manage')
    . $list('Ready for dispatch', $ready, fn($o) => $oa($o) . ' — ' . h($o['name']) . ($o['delivery_method'] === 'PICKUP' ? ' (collection)' : ''), 'deliveries.manage')
    . '</div><div>'
    . $list('New enquiries', $newEnq, fn($e) => '<a class="b" href="' . h(aurl('enquiries/' . $e['code'])) . '">' . h($e['subject']) . '</a> — ' . h($e['name']), 'enquiries.view')
    . $list('Accepted quotations', $accepted, fn($q) => '<a class="b" href="' . h(aurl('quotations/' . $q['id'])) . '">' . h($q['number']) . '</a> — ' . h($q['name']) . ' · ' . naira($q['total']), 'quotations.view')
    . $list('Low stock', array_values($low), fn($p) => '<a class="b" href="' . h(aurl('products/' . $p['id'])) . '">' . h($p['name']) . '</a> — ' . avail($p) . ' available', 'inventory.view')
    . (can('orders.view') ? panel('Recent orders', admin_orders_table($recent), '<a class="link sm" href="' . h(aurl('orders')) . '">All orders</a>') : '')
    . '</div></div>';
admin_close();
