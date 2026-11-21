<?php
$pdo = db();
if (is_post()) {
    require_csrf();
    $zid = (int)post('id');
    if (post('do') === 'delete') { $pdo->prepare('DELETE FROM zones WHERE id = ?')->execute([$zid]); audit('zone.deleted', (string)$zid); flash('Zone deleted.'); redirect('/admin/zones'); }
    $states = array_values(array_intersect(STATES, (array)($_POST['states'] ?? [])));
    $e = [];
    if (mb_strlen(post('name')) < 2) $e['name'] = 'Name the zone.';
    if (!$states) $e['states'] = 'Pick at least one state.';
    try { $fee = parse_naira(post('fee')); } catch (InvalidArgumentException $ex) { $e['fee'] = $ex->getMessage(); $fee = null; }
    foreach (zones(false) as $z) if ((int)$z['id'] !== $zid && $z['active']) foreach (array_intersect($z['states'], $states) as $dupS) $e['states'] = "$dupS is already in the \"{$z['name']}\" zone.";
    if ($e) fail($e, '/admin/zones' . ($zid ? '?edit=' . $zid : ''));
    $vals = [mb_substr(post('name'), 0, 120), json_encode($states), $fee, max(0, (int)post('lead_days')), post('active') ? 1 : 0];
    if ($zid) $pdo->prepare('UPDATE zones SET name=?, states=?, fee=?, lead_days=?, active=? WHERE id=?')->execute([...$vals, $zid]);
    else $pdo->prepare('INSERT INTO zones (name, states, fee, lead_days, active) VALUES (?,?,?,?,?)')->execute($vals);
    audit('zone.saved', post('name')); flash('Delivery zone saved.'); redirect('/admin/zones');
}
$edit = null; if (q('edit')) foreach (zones(false) as $z) if ((int)$z['id'] === (int)q('edit')) $edit = $z;
$zs = zones(false);
$covered = []; foreach ($zs as $z) if ($z['active']) $covered = array_merge($covered, $z['states']);
admin_open('Delivery zones');
echo ahead('Delivery zones', 'Flat delivery fees for parts, supplies and light equipment. Heavy items and states outside every zone get a delivery quote after the order.');
$c = csrf_field();
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Zone</th><th>States</th><th class="r">Fee</th><th class="r">Days</th><th>Status</th><th></th></tr></thead><tbody>';
foreach ($zs as $z) $t .= '<tr><td class="first"><b>' . h($z['name']) . '</b></td><td data-l="States" class="sm">' . h(implode(', ', $z['states'])) . '</td><td data-l="Fee" class="r tab">' . ($z['fee'] === null ? 'Quoted' : naira($z['fee'])) . '</td><td data-l="Days" class="r tab">' . (int)$z['lead_days'] . '</td><td data-l="Status">' . badge($z['active'] ? 'Active' : 'Off', $z['active'] ? 'ok' : '') . '</td><td><a class="btn out sm" href="' . h(aurl('zones?edit=' . $z['id'])) . '">Edit</a></td></tr>';
$sel = $_SESSION['old']['states'] ?? ($edit['states'] ?? []);
$boxes = '<div class="grid g3" style="gap:.1rem .75rem;max-height:280px;overflow:auto;border:1px solid var(--steel);padding:.5rem">';
foreach (STATES as $s) $boxes .= '<label class="chk"><input type="checkbox" name="states[]" value="' . h($s) . '"' . (in_array($s, $sel, true) ? ' checked' : '') . '> ' . h($s) . (in_array($s, $covered, true) && !in_array($s, $edit['states'] ?? [], true) ? ' <span class="xs muted">(zoned)</span>' : '') . '</label>';
$boxes .= '</div>' . ferr('states');
$f = '<form method="post" novalidate>' . $c . '<input type="hidden" name="id" value="' . (int)($edit['id'] ?? 0) . '">' . field('Zone name', 'zn', inp('name', $edit['name'] ?? '', '', 'zn'), ['name' => 'name'])
    . '<div class="grid g2" style="gap:0 1rem">' . field('Flat fee (₦)', 'zf', inp('fee', naira_input($edit['fee'] ?? null), 'inputmode="decimal" placeholder="Blank = always quote"', 'zf'), ['name' => 'fee']) . field('Working days', 'zl', inp('lead_days', $edit['lead_days'] ?? 3, 'type="number" min="0"', 'zl'), ['name' => 'lead_days']) . '</div>'
    . '<p class="l">States in this zone</p>' . $boxes . cb('active', (bool)($edit['active'] ?? 1), 'Active') . '<div class="row mt4"><button class="btn">' . ($edit ? 'Save zone' : 'Add zone') . '</button>' . ($edit ? '<a class="link" href="' . h(aurl('zones')) . '">Cancel</a>' : '') . '</div></form>'
    . ($edit ? '<form method="post" class="mt4" data-confirm="Delete this zone?">' . $c . '<input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . (int)$edit['id'] . '"><button class="btn ghost sm" style="color:var(--danger)">Delete zone</button></form>' : '');
echo '<div class="split admin" style="align-items:start"><div>' . panel('Zones', $t . '</tbody></table></div>') . note('Collection', 'Customers can always choose to collect from your yard when collection is switched on in Settings.') . '</div><div>' . panel($edit ? 'Edit zone' : 'Add a zone', $f) . '</div></div>';
admin_close();
