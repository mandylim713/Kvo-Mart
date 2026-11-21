<?php
$pdo = db();
if (is_post()) {
    require_csrf();
    $pid = (int)post('id');
    if (post('do') === 'toggle') { $pdo->prepare('UPDATE promos SET active = 1 - active WHERE id = ?')->execute([$pid]); audit('promo.toggled', (string)$pid); flash('Promotion updated.'); redirect('/admin/promotions'); }
    if (post('do') === 'delete') { $pdo->prepare('DELETE FROM promos WHERE id = ? AND uses = 0')->execute([$pid]); flash('Deleted (codes already used are kept — switch them off instead).'); redirect('/admin/promotions'); }
    $e = []; $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', post('code')));
    if (strlen($code) < 3) $e['code'] = 'Use at least 3 letters or numbers.';
    $dup = $pdo->prepare('SELECT COUNT(*) FROM promos WHERE code = ? AND id != ?'); $dup->execute([$code, $pid]); if ($dup->fetchColumn()) $e['code'] = 'That code exists.';
    $type = post('type') === 'FIXED' ? 'FIXED' : 'PERCENT';
    try {
        $value = $type === 'PERCENT' ? (int)round((float)post('value') * 100) : (parse_naira(post('value')) ?? 0);
        $min = parse_naira(post('min_order')) ?? 0; $maxd = parse_naira(post('max_disc'));
    } catch (InvalidArgumentException $ex) { $e['value'] = $ex->getMessage(); $value = 0; $min = 0; $maxd = null; }
    if ($value <= 0 || ($type === 'PERCENT' && $value > 10000)) $e['value'] = $type === 'PERCENT' ? 'Enter a percentage between 0 and 100.' : 'Enter the amount off.';
    if (!post('starts') || !post('ends') || post('ends') < post('starts')) $e['ends'] = 'Choose a start and an end date after it.';
    if ($e) fail($e, '/admin/promotions' . ($pid ? '?edit=' . $pid : ''));
    $vals = [$code, mb_substr(post('description'), 0, 255), $type, $value, $min, $maxd, post('starts') . 'T00:00:00', post('ends') . 'T23:59:59', post('max_uses') !== '' ? (int)post('max_uses') : null, post('per_customer') !== '' ? (int)post('per_customer') : null, post('active') ? 1 : 0];
    if ($pid) $pdo->prepare('UPDATE promos SET code=?, description=?, type=?, value=?, min_order=?, max_disc=?, starts=?, ends=?, max_uses=?, per_customer=?, active=? WHERE id=?')->execute([...$vals, $pid]);
    else $pdo->prepare('INSERT INTO promos (code, description, type, value, min_order, max_disc, starts, ends, max_uses, per_customer, active, uses) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)')->execute($vals);
    audit('promo.saved', $code); flash('Promotion saved.'); redirect('/admin/promotions');
}
$edit = q('edit') ? $pdo->query('SELECT * FROM promos WHERE id = ' . (int)q('edit'))->fetch() : null;
$rows = $pdo->query('SELECT * FROM promos ORDER BY id DESC')->fetchAll();
admin_open('Promotions');
echo ahead('Promotions', 'Discount codes customers enter at checkout. Quotations and delivery charges are never discounted.');
$c = csrf_field();
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Code</th><th>Discount</th><th>Dates</th><th class="r">Used</th><th>Status</th><th></th></tr></thead><tbody>';
foreach ($rows as $p) {
    $live = $p['active'] && time() >= strtotime($p['starts']) && time() <= strtotime($p['ends']);
    $t .= '<tr><td class="first"><b class="tab">' . h($p['code']) . '</b><div class="xs muted">' . h($p['description']) . '</div></td><td data-l="Discount">' . ($p['type'] === 'PERCENT' ? ($p['value'] / 100) . '%' : naira($p['value'])) . ($p['min_order'] ? '<div class="xs muted">min ' . naira($p['min_order']) . '</div>' : '') . ($p['max_disc'] ? '<div class="xs muted">max ' . naira($p['max_disc']) . ' off</div>' : '') . '</td><td data-l="Dates" class="sm">' . fmt_date($p['starts']) . ' – ' . fmt_date($p['ends']) . '</td><td data-l="Used" class="r tab">' . (int)$p['uses'] . ($p['max_uses'] ? ' / ' . (int)$p['max_uses'] : '') . '</td><td data-l="Status">' . badge($live ? 'Live' : ($p['active'] ? 'Scheduled / ended' : 'Off'), $live ? 'ok' : '') . '</td>'
        . '<td><div class="row" style="gap:.3rem"><a class="btn out sm" href="' . h(aurl('promotions?edit=' . $p['id'])) . '">Edit</a><form method="post">' . $c . '<input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="' . $p['id'] . '"><button class="btn ghost sm">' . ($p['active'] ? 'Switch off' : 'Switch on') . '</button></form>' . (!$p['uses'] ? '<form method="post" data-confirm="Delete this code?">' . $c . '<input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . $p['id'] . '"><button class="btn ghost sm" style="color:var(--danger)">Delete</button></form>' : '') . '</div></td></tr>';
}
$f = '<form method="post" novalidate>' . $c . '<input type="hidden" name="id" value="' . (int)($edit['id'] ?? 0) . '">' . field('Code', 'pc', inp('code', $edit['code'] ?? '', 'style="text-transform:uppercase"', 'pc'), ['name' => 'code']) . field('Description', 'pd', inp('description', $edit['description'] ?? '', '', 'pd'), ['optional' => true, 'name' => 'description'])
    . '<div class="grid g2" style="gap:0 1rem">' . field('Type', 'pt', sel('type', [['PERCENT', 'Percentage off'], ['FIXED', 'Fixed amount off (₦)']], $edit['type'] ?? 'PERCENT', '', 'pt'), ['name' => 'type']) . field('Value', 'pv', inp('value', $edit ? ($edit['type'] === 'PERCENT' ? $edit['value'] / 100 : naira_input($edit['value'])) : '', 'inputmode="decimal"', 'pv'), ['name' => 'value'])
    . field('Minimum order (₦)', 'pmin', inp('min_order', naira_input($edit['min_order'] ?? null), 'inputmode="decimal"', 'pmin'), ['optional' => true, 'name' => 'min_order']) . field('Maximum discount (₦)', 'pmx', inp('max_disc', naira_input($edit['max_disc'] ?? null), 'inputmode="decimal"', 'pmx'), ['optional' => true, 'name' => 'max_disc'])
    . field('Starts', 'ps', inp('starts', substr($edit['starts'] ?? date('Y-m-d'), 0, 10), 'type="date"', 'ps'), ['name' => 'starts']) . field('Ends', 'pe', inp('ends', substr($edit['ends'] ?? date('Y-m-d', time() + 30 * 86400), 0, 10), 'type="date"', 'pe'), ['name' => 'ends'])
    . field('Total uses allowed', 'pmu', inp('max_uses', $edit['max_uses'] ?? '', 'type="number" min="1"', 'pmu'), ['optional' => true, 'name' => 'max_uses']) . field('Uses per customer', 'ppc', inp('per_customer', $edit['per_customer'] ?? '1', 'type="number" min="1"', 'ppc'), ['optional' => true, 'name' => 'per_customer']) . '</div>'
    . cb('active', (bool)($edit['active'] ?? 1), 'Active') . '<div class="row mt4"><button class="btn">' . ($edit ? 'Save' : 'Create code') . '</button>' . ($edit ? '<a class="link" href="' . h(aurl('promotions')) . '">Cancel</a>' : '') . '</div></form>';
echo '<div class="split admin" style="align-items:start"><div>' . panel('Codes', $rows ? $t . '</tbody></table></div>' : '<p class="sm muted">No codes yet.</p>') . '</div><div>' . panel($edit ? 'Edit code' : 'New code', $f) . '</div></div>';
admin_close();
