<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (is_post() && post('action') === 'delete') {
    csrf_check();
    $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([(int)post('id')]);
    flash('success', 'Page deleted.');
    redirect('admin/pages.php');
}
$rows = $pdo->query('SELECT * FROM pages ORDER BY id')->fetchAll();

$adminTitle = 'Pages';
require __DIR__ . '/_header.php';
?>
<div class="toolbar"><a class="btn btn-primary" href="<?= url('admin/page-edit.php') ?>"><i class="fa-solid fa-plus"></i> New page</a></div>
<div class="card flush-sm">
<table class="table cards">
  <thead><tr><th>Page</th><th>In footer</th><th>Status</th><th>Updated</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): ?>
    <tr>
      <td class="t-main"><div><a class="t-name" href="<?= url('admin/page-edit.php?id=' . $p['id']) ?>"><?= e($p['title']) ?></a><div class="t-sub">page.php?slug=<?= e($p['slug']) ?></div></div></td>
      <td data-label="In footer"><?= $p['in_footer'] ? 'Yes' : 'No' ?></td>
      <td data-label="Status"><span class="pill <?= $p['is_active'] ? 'on' : 'off' ?>"><?= $p['is_active'] ? 'Published' : 'Hidden' ?></span></td>
      <td data-label="Updated"><?= e(date('j M Y', strtotime($p['updated_at']))) ?></td>
      <td class="t-actions"><div class="btn-row">
        <a class="btn btn-outline btn-sm" href="<?= url('page.php?slug=' . urlencode($p['slug'])) ?>" target="_blank"><i class="fa-solid fa-eye"></i></a>
        <a class="btn btn-outline btn-sm" href="<?= url('admin/page-edit.php?id=' . $p['id']) ?>"><i class="fa-solid fa-pen"></i></a>
        <form method="post" data-confirm="Delete this page?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-regular fa-trash-can"></i></button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
