<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    if (post('action') === 'delete') {
        delete_upload($pdo->query('SELECT image FROM slides WHERE id = ' . $id)->fetchColumn() ?: null);
        $pdo->prepare('DELETE FROM slides WHERE id = ?')->execute([$id]);
        flash('success', 'Slide deleted.');
        redirect('admin/slides.php');
    }
    $data = [mb_substr(post('title'), 0, 200), mb_substr(post('subtitle'), 0, 400), mb_substr(post('button_text'), 0, 60), mb_substr(post('button_link'), 0, 255), (int)post('sort_order'), post('is_active') ? 1 : 0];
    $img = upload_image($_FILES['image'] ?? [], 'branding', 1920);
    if ($id) {
        $pdo->prepare('UPDATE slides SET title=?, subtitle=?, button_text=?, button_link=?, sort_order=?, is_active=? WHERE id=?')->execute([...$data, $id]);
        if ($img) { delete_upload($pdo->query('SELECT image FROM slides WHERE id = ' . $id)->fetchColumn() ?: null); $pdo->prepare('UPDATE slides SET image=? WHERE id=?')->execute([$img, $id]); }
    } else {
        if (!$img) { flash('error', 'Please upload a slide image.'); redirect('admin/slides.php'); }
        $pdo->prepare('INSERT INTO slides (title, subtitle, button_text, button_link, sort_order, is_active, image) VALUES (?,?,?,?,?,?,?)')->execute([...$data, $img]);
    }
    flash('success', 'Slide saved.');
    redirect('admin/slides.php');
}

$edit = null;
if (get('edit')) { $s = $pdo->prepare('SELECT * FROM slides WHERE id = ?'); $s->execute([(int)get('edit')]); $edit = $s->fetch(); }
$rows = $pdo->query('SELECT * FROM slides ORDER BY sort_order, id')->fetchAll();

$adminTitle = 'Homepage slides';
require __DIR__ . '/_header.php';
?>
<div class="split">
  <div>
    <?php foreach ($rows as $s): ?>
    <div class="card" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <img src="<?= e(asset($s['image'])) ?>" alt="" style="width:150px;height:72px;object-fit:cover;border-radius:10px">
      <div style="flex:1;min-width:160px"><div class="t-name"><?= e($s['title'] ?: '(no title)') ?></div><div class="t-sub">Order <?= (int)$s['sort_order'] ?> · <span class="pill <?= $s['is_active'] ? 'on' : 'off' ?>"><?= $s['is_active'] ? 'Live' : 'Hidden' ?></span></div></div>
      <div class="btn-row">
        <a class="btn btn-outline btn-sm" href="?edit=<?= $s['id'] ?>#form"><i class="fa-solid fa-pen"></i></a>
        <form method="post" data-confirm="Delete this slide?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-regular fa-trash-can"></i></button></form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="card empty"><i class="fa-regular fa-images"></i>No slides. The homepage will start with categories.</div><?php endif; ?>
  </div>
  <div class="card" id="form">
    <h3><?= $edit ? 'Edit slide' : 'Add slide' ?></h3>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field"><label>Image <?= $edit ? '' : '*' ?></label><input type="file" name="image" accept="image/*" data-preview="pv-slide"><div class="previews" id="pv-slide"></div><small>Wide photo, about 1600×700. Text sits bottom-left, so keep that area simple.</small></div>
      <div class="field"><label>Headline</label><input name="title" value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="field"><label>Sub-text</label><textarea name="subtitle" style="min-height:70px"><?= e($edit['subtitle'] ?? '') ?></textarea></div>
      <div class="field"><label>Button text</label><input name="button_text" value="<?= e($edit['button_text'] ?? 'Shop now') ?>"></div>
      <div class="field"><label>Button link</label><input name="button_link" value="<?= e($edit['button_link'] ?? 'shop.php') ?>"><small>e.g. shop.php, shop.php?cat=beverages-drinks, shop.php?deals=1</small></div>
      <div class="field"><label>Order</label><input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? count($rows) + 1) ?>"></div>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Show on homepage</label>
      <button class="btn btn-primary" type="submit">Save slide</button>
      <?php if ($edit): ?><a class="btn btn-outline" href="<?= url('admin/slides.php') ?>">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
