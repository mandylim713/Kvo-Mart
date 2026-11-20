<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Subscribed']);
    foreach ($pdo->query('SELECT email, created_at FROM subscribers ORDER BY id DESC') as $r) fputcsv($out, [$r['email'], $r['created_at']]);
    exit;
}
if (is_post()) {
    csrf_check();
    $pdo->prepare('DELETE FROM subscribers WHERE id = ?')->execute([(int)post('id')]);
    flash('success', 'Removed.');
    redirect('admin/subscribers.php');
}
$rows = $pdo->query('SELECT * FROM subscribers ORDER BY id DESC LIMIT 500')->fetchAll();

$adminTitle = 'Subscribers';
require __DIR__ . '/_header.php';
?>
<div class="toolbar"><span class="muted" style="flex:1"><?= count($rows) ?> subscriber<?= count($rows) === 1 ? '' : 's' ?></span><a class="btn btn-outline" href="?export=csv"><i class="fa-solid fa-file-csv"></i> Export CSV</a></div>
<div class="card">
  <?php if (!$rows): ?><div class="empty"><i class="fa-regular fa-paper-plane"></i>No subscribers yet. The newsletter box sits at the bottom of every page.</div><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <div class="list-row"><span><?= e($r['email']) ?> <small class="muted">· <?= e(date('j M Y', strtotime($r['created_at']))) ?></small></span>
      <form method="post" data-confirm="Remove this subscriber?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-regular fa-trash-can"></i></button></form></div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
