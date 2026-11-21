<?php
$pdo = db();
$me = current_staff();
if (is_post()) {
    require_csrf();
    $sid = (int)post('id');
    if (post('do') === 'toggle') {
        if ($sid === (int)$me['id']) { flash("You can't disable your own account.", 'warn'); redirect('/admin/staff'); }
        $pdo->prepare('UPDATE staff SET active = 1 - active WHERE id = ?')->execute([$sid]); audit('staff.toggled', (string)$sid); flash('Staff account updated.'); redirect('/admin/staff');
    }
    $e = []; $em = strtolower(post('email'));
    if (mb_strlen(post('name')) < 2) $e['name'] = 'Enter a name.';
    if (!email_ok($em)) $e['email'] = 'Enter a valid email.';
    $dup = $pdo->prepare('SELECT COUNT(*) FROM staff WHERE email = ? AND id != ?'); $dup->execute([$em, $sid]); if ($dup->fetchColumn()) $e['email'] = 'That email already has a staff account.';
    if (!isset(ROLE_LABEL[post('role')])) $e['role'] = 'Choose a role.';
    if ($sid === (int)$me['id'] && post('role') !== 'SUPER_ADMIN') $e['role'] = "You can't remove your own super admin role.";
    if (!$sid || post('password') !== '') { if (!pw_ok((string)post('password'))) $e['password'] = PW_RULE; }
    if ($e) fail($e, '/admin/staff' . ($sid ? '?edit=' . $sid : ''));
    if ($sid) {
        $pdo->prepare('UPDATE staff SET name = ?, email = ?, role = ? WHERE id = ?')->execute([mb_substr(post('name'), 0, 150), $em, post('role'), $sid]);
        if (post('password') !== '') $pdo->prepare('UPDATE staff SET password = ? WHERE id = ?')->execute([password_hash((string)post('password'), PASSWORD_DEFAULT), $sid]);
    } else $pdo->prepare('INSERT INTO staff (name, email, password, role, active, created) VALUES (?,?,?,?,1,?)')->execute([mb_substr(post('name'), 0, 150), $em, password_hash((string)post('password'), PASSWORD_DEFAULT), post('role'), now()]);
    audit('staff.saved', $em, ROLE_LABEL[post('role')]);
    flash('Staff account saved. Share the password with them privately and ask them to change it.');
    redirect('/admin/staff');
}
$edit = q('edit') ? $pdo->query('SELECT * FROM staff WHERE id = ' . (int)q('edit'))->fetch() : null;
$rows = $pdo->query('SELECT * FROM staff ORDER BY id')->fetchAll();
admin_open('Staff & roles');
echo ahead('Staff & roles', 'Each role only sees the parts of the admin it needs.');
$c = csrf_field();
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Name</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead><tbody>';
foreach ($rows as $s) $t .= '<tr><td class="first"><b>' . h($s['name']) . '</b>' . ($s['id'] == $me['id'] ? ' ' . badge('You', 'gold') : '') . '<div class="xs muted">' . h($s['email']) . '</div></td><td data-l="Role">' . h(ROLE_LABEL[$s['role']] ?? $s['role']) . '</td><td data-l="Last sign-in" class="sm">' . fmt_date($s['last_login'], true) . '</td><td data-l="Status">' . badge($s['active'] ? 'Active' : 'Disabled', $s['active'] ? 'ok' : 'danger') . '</td><td><div class="row" style="gap:.3rem"><a class="btn out sm" href="' . h(aurl('staff?edit=' . $s['id'])) . '">Edit</a>' . ($s['id'] != $me['id'] ? '<form method="post">' . $c . '<input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="' . $s['id'] . '"><button class="btn ghost sm">' . ($s['active'] ? 'Disable' : 'Enable') . '</button></form>' : '') . '</div></td></tr>';
$roleHelp = ['SUPER_ADMIN' => 'Everything, including staff and settings.', 'STORE_MANAGER' => 'Everything except staff, verifying payments and refunds.', 'INVENTORY_MANAGER' => 'Products and stock.', 'SALES_REP' => 'Enquiries, quotations, customers; views orders and stock.', 'FINANCE_OFFICER' => 'Verifies transfers, refunds, cancels unpaid orders.', 'DELIVERY_MANAGER' => 'Delivery quotes and dispatch.', 'CUSTOMER_SUPPORT' => 'Enquiries; views orders, deliveries and customers.'];
$rh = '<dl>'; foreach ($roleHelp as $k => $v) $rh .= '<div class="pr"><dt>' . h(ROLE_LABEL[$k]) . '</dt><dd style="font-weight:400;text-align:left;max-width:60%">' . h($v) . '</dd></div>'; $rh .= '</dl>';
$f = '<form method="post" novalidate>' . $c . '<input type="hidden" name="id" value="' . (int)($edit['id'] ?? 0) . '">' . field('Name', 'sn', inp('name', $edit['name'] ?? '', '', 'sn'), ['name' => 'name']) . field('Work email', 'se', inp('email', $edit['email'] ?? '', 'type="email"', 'se'), ['name' => 'email'])
    . field('Role', 'sr', sel('role', array_map(fn($k, $l) => [$k, $l], array_keys(ROLE_LABEL), ROLE_LABEL), $edit['role'] ?? 'SALES_REP', '', 'sr'), ['name' => 'role'])
    . field($edit ? 'New password' : 'Password', 'sp', '<input class="in' . bad('password') . '" id="sp" name="password" type="text" autocomplete="new-password" placeholder="' . ($edit ? 'Leave blank to keep' : '') . '">', ['name' => 'password', 'hint' => PW_RULE])
    . '<div class="row mt4"><button class="btn">' . ($edit ? 'Save' : 'Add staff member') . '</button>' . ($edit ? '<a class="link" href="' . h(aurl('staff')) . '">Cancel</a>' : '') . '</div></form>';
echo '<div class="split admin" style="align-items:start"><div>' . panel('Staff', $t . '</tbody></table></div>') . panel('What each role can do', $rh) . '</div><div>' . panel($edit ? 'Edit ' . $edit['name'] : 'Add staff member', $f) . '</div></div>';
admin_close();
