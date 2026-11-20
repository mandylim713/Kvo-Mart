<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

$id = (int)get('id');
$p = null;
if ($id) { $s = $pdo->prepare('SELECT * FROM pages WHERE id = ?'); $s->execute([$id]); $p = $s->fetch(); }

if (is_post()) {
    csrf_check();
    $title = mb_substr(post('title'), 0, 200);
    if ($title === '') { flash('error', 'Title is required.'); redirect('admin/page-edit.php' . ($id ? '?id=' . $id : '')); }
    $slug = unique_slug('pages', post('slug') ?: $title, $id);
    $data = [$title, $slug, clean_html((string)post('content')), post('in_footer') ? 1 : 0, post('is_active') ? 1 : 0, now()];
    if ($p) $pdo->prepare('UPDATE pages SET title=?, slug=?, content=?, in_footer=?, is_active=?, updated_at=? WHERE id=?')->execute([...$data, $id]);
    else { $pdo->prepare('INSERT INTO pages (title, slug, content, in_footer, is_active, updated_at) VALUES (?,?,?,?,?,?)')->execute($data); $id = (int)$pdo->lastInsertId(); }
    flash('success', 'Page saved.');
    redirect('admin/page-edit.php?id=' . $id);
}

$adminTitle = $p ? 'Edit page' : 'New page';
$useEditor = true;
require __DIR__ . '/_header.php';
?>
<div class="btn-row" style="margin-bottom:12px"><a class="btn btn-outline btn-sm" href="<?= url('admin/pages.php') ?>"><i class="fa-solid fa-arrow-left"></i> Pages</a><?php if ($p): ?><a class="btn btn-outline btn-sm" href="<?= url('page.php?slug=' . urlencode($p['slug'])) ?>" target="_blank"><i class="fa-solid fa-eye"></i> View</a><?php endif; ?></div>
<form method="post" class="card form-grid">
  <?= csrf_field() ?>
  <div class="form-grid two">
    <div class="field"><label>Title *</label><input name="title" required value="<?= e($p['title'] ?? '') ?>" data-slug-src></div>
    <div class="field"><label>Slug</label><input name="slug" value="<?= e($p['slug'] ?? '') ?>" data-slug <?= $p ? 'data-touched="1"' : '' ?>><?php if ($p && in_array($p['slug'], ['about', 'returns', 'terms', 'delivery', 'privacy', 'faq'], true)): ?><small>Linked from other pages — keep this slug.</small><?php endif; ?></div>
  </div>
  <div class="field"><label>Content</label><textarea name="content" data-editor><?= e($p['content'] ?? '') ?></textarea></div>
  <label class="check"><input type="checkbox" name="in_footer" value="1" <?= ($p['in_footer'] ?? 1) ? 'checked' : '' ?>> Show link in footer</label>
  <label class="check"><input type="checkbox" name="is_active" value="1" <?= ($p['is_active'] ?? 1) ? 'checked' : '' ?>> Published</label>
  <div><button class="btn btn-primary" type="submit">Save page</button></div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
