<?php
$qq = q('q');
$s = db()->prepare("SELECT * FROM audit WHERE (? = '' OR action LIKE ? OR entity LIKE ? OR actor LIKE ? OR details LIKE ?) ORDER BY id DESC LIMIT 500");
$l = "%$qq%"; $s->execute([$qq, $l, $l, $l, $l]);
admin_open('Audit log');
echo ahead('Audit log', 'Who did what, and when. The last 500 entries.');
echo '<form class="row" style="margin-bottom:1rem"><input class="in" name="q" value="' . h($qq) . '" placeholder="Order number, SKU, staff name, action" style="max-width:340px"><button class="btn dark sm">Search</button></form>';
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>';
foreach ($s as $a) $t .= '<tr><td class="first sm">' . fmt_date($a['at'], true) . '</td><td data-l="Who" class="sm">' . h($a['actor']) . '</td><td data-l="Action" class="sm">' . h($a['action']) . '</td><td data-l="Record" class="sm tab">' . (preg_match('/^KVO-[A-Z0-9]{8}$/', $a['entity']) ? '<a href="' . h(aurl('orders/' . $a['entity'])) . '">' . h($a['entity']) . '</a>' : h($a['entity'])) . '</td><td data-l="Details" class="sm">' . h($a['details']) . '</td></tr>';
echo panel('Entries', $t . '</tbody></table></div>');
admin_close();
