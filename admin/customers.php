<?php
$pdo = db();
if (is_post()) {
    require_csrf(); need('settings.manage');
    $u = $pdo->query('SELECT * FROM users WHERE id = ' . (int)post('id'))->fetch();
    if ($u) { $pdo->prepare('UPDATE users SET active = ? WHERE id = ?')->execute([$u['active'] ? 0 : 1, $u['id']]); audit($u['active'] ? 'customer.disabled' : 'customer.enabled', $u['email']); flash($u['active'] ? 'Account disabled.' : 'Account enabled.'); }
    redirect(back_path('/admin/customers'));
}
if ($id) {
    $u = $pdo->query('SELECT * FROM users WHERE id = ' . (int)$id)->fetch(); if (!$u) { flash('Customer not found.', 'warn'); redirect('/admin/customers'); }
    $os = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? OR email = ? ORDER BY id DESC'); $os->execute([$u['id'], $u['email']]); $os = $os->fetchAll();
    $spent = array_sum(array_map(fn($o) => $o['paid'] - $o['refunded'], $os));
    admin_open($u['name']);
    echo ahead($u['name'], h($u['email']) . ' · joined ' . fmt_date($u['created']), can('settings.manage') ? '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . $u['id'] . '"><button class="btn out sm">' . ($u['active'] ? 'Disable account' : 'Enable account') . '</button></form>' : '');
    $ad = ''; foreach (user_addresses((int)$u['id']) as $a) $ad .= '<p class="sm" style="margin-bottom:.5rem"><b>' . h($a['label']) . '</b>' . ($a['is_default'] ? ' ' . badge('Default', 'gold') : '') . '<br>' . h($a['line1']) . ', ' . h($a['city']) . ', ' . h($a['state']) . '</p>';
    echo '<div class="split admin" style="align-items:start"><div>' . panel('Orders (' . count($os) . ')', admin_orders_table($os)) . '</div><div>' . panel('Contact', pl([['Phone', h($u['phone'])], ['Company', h($u['company'])], ['Status', $u['active'] ? 'Active' : 'Disabled'], ['Paid to date', naira($spent)]])) . panel('Addresses', $ad ?: '<p class="sm muted">None saved.</p>') . '</div></div>';
    admin_close(); return;
}
$qq = q('q');
$s = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS n, (SELECT COALESCE(SUM(paid - refunded),0) FROM orders o WHERE o.user_id = u.id) AS spent FROM users u WHERE (? = '' OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.company LIKE ?) ORDER BY u.id DESC LIMIT 500");
$l = "%$qq%"; $s->execute([$qq, $l, $l, $l, $l]); $rows = $s->fetchAll();
$guests = (int)$pdo->query('SELECT COUNT(DISTINCT email) FROM orders WHERE user_id IS NULL')->fetchColumn();
admin_open('Customers');
echo ahead('Customers', count($rows) . ' registered · ' . $guests . ' guest buyers (by email)');
echo '<form class="row" style="margin-bottom:1rem"><input class="in" name="q" value="' . h($qq) . '" placeholder="Name, email, phone, company" style="max-width:320px"><button class="btn dark sm">Search</button></form>';
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Customer</th><th>Phone</th><th class="r">Orders</th><th class="r">Paid</th><th>Joined</th><th>Status</th></tr></thead><tbody>';
foreach ($rows as $u) $t .= '<tr><td class="first"><a class="b" href="' . h(aurl('customers/' . $u['id'])) . '">' . h($u['name']) . '</a><div class="xs muted">' . h($u['email']) . ($u['company'] ? ' · ' . h($u['company']) : '') . '</div></td><td data-l="Phone">' . h($u['phone']) . '</td><td data-l="Orders" class="r tab">' . (int)$u['n'] . '</td><td data-l="Paid" class="r tab">' . naira($u['spent']) . '</td><td data-l="Joined">' . fmt_date($u['created']) . '</td><td data-l="Status">' . badge($u['active'] ? 'Active' : 'Disabled', $u['active'] ? 'ok' : 'danger') . '</td></tr>';
echo panel('Registered customers', $rows ? $t . '</tbody></table></div>' : '<p class="sm muted">No customer accounts yet. Guests can check out without one.</p>');
admin_close();
