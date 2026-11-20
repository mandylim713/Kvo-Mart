<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    if (post('action') === 'approve') $pdo->prepare('UPDATE reviews SET is_approved = 1 - is_approved WHERE id = ?')->execute([$id]);
    if (post('action') === 'delete') $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
    flash('success', 'Review updated.');
    redirect('admin/reviews.php');
}
$rows = $pdo->query('SELECT r.*, p.name pname, p.slug FROM reviews r LEFT JOIN products p ON p.id = r.product_id ORDER BY r.is_approved ASC, r.id DESC LIMIT 200')->fetchAll();

$adminTitle = 'Reviews';
require __DIR__ . '/_header.php';
?>
<div class="card">
<?php if (!$rows): ?><div class="empty"><i class="fa-regular fa-star"></i>No reviews yet.</div><?php endif; ?>
<?php foreach ($rows as $r): ?>
  <div class="list-row" style="align-items:flex-start;flex-wrap:wrap">
    <div style="flex:1;min-width:220px">
      <span style="color:#f59e0b"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
      <strong><?= e($r['name']) ?></strong> <span class="pill <?= $r['is_approved'] ? 'on' : 'warn' ?>"><?= $r['is_approved'] ? 'Published' : 'Pending' ?></span>
      <div class="t-sub">on <a href="<?= url('product.php?slug=' . urlencode((string)$r['slug'])) ?>" target="_blank" style="text-decoration:underline"><?= e($r['pname'] ?? 'deleted product') ?></a> · <?= e(date('j M Y', strtotime($r['created_at']))) ?></div>
      <p style="margin:6px 0 0"><?= nl2br(e($r['comment'])) ?></p>
    </div>
    <div class="btn-row">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn <?= $r['is_approved'] ? 'btn-outline' : 'btn-primary' ?> btn-sm"><?= $r['is_approved'] ? 'Unpublish' : 'Approve' ?></button></form>
      <form method="post" data-confirm="Delete this review?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-regular fa-trash-can"></i></button></form>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
