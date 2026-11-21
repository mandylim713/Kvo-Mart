<?php
$pdo = db();
if (q('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="kvo-subscribers-' . date('Ymd') . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, ['Email', 'Subscribed']);
    foreach ($pdo->query('SELECT email, created FROM newsletter ORDER BY id DESC') as $r) fputcsv($o, [$r['email'], $r['created']]);
    exit;
}
if (is_post()) { require_csrf(); $pdo->prepare('DELETE FROM newsletter WHERE id = ?')->execute([(int)post('id')]); flash('Removed.'); redirect('/admin/subscribers'); }
$rows = $pdo->query('SELECT * FROM newsletter ORDER BY id DESC')->fetchAll();
admin_open('Subscribers');
echo ahead('Stock update subscribers', count($rows) . ' email' . (count($rows) === 1 ? '' : 's') . ' from the “Stock updates by email” box.', '<a class="btn out sm" href="' . h(aurl('subscribers?export=csv')) . '">Export CSV</a>');
$c = csrf_field(); $t = '';
foreach ($rows as $r) $t .= '<div class="row between" style="border-bottom:1px solid var(--steel);padding:.5rem 0"><span class="sm">' . h($r['email']) . ' <span class="xs muted">· ' . fmt_date($r['created']) . '</span></span><form method="post" data-confirm="Remove this subscriber?">' . $c . '<input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn ghost sm" style="color:var(--danger)">Remove</button></form></div>';
echo panel('Subscribers', $t ?: '<p class="sm muted">No subscribers yet.</p>');
admin_close();
