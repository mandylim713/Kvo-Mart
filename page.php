<?php
require __DIR__ . '/includes/bootstrap.php';

$st = db()->prepare('SELECT * FROM pages WHERE slug = ? AND is_active = 1');
$st->execute([get('slug')]);
$page = $st->fetch();
if (!$page) { http_response_code(404); }
$pageTitle = $page['title'] ?? 'Page not found';
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:860px">
  <?php if ($page): ?>
    <div class="page-head"><div class="crumbs"><a href="<?= url('index.php') ?>">Home</a> / <span><?= e($page['title']) ?></span></div><h1><?= e($page['title']) ?></h1></div>
    <div class="card prose"><?= clean_html($page['content']) ?></div>
  <?php else: ?>
    <div class="card empty" style="margin-top:20px"><i class="fa-regular fa-file"></i><h3>Page not found</h3><a class="btn btn-primary" href="<?= url('index.php') ?>">Go home</a></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
