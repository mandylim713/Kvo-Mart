<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    if (post('action') === 'delete') {
        $n = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE category_id = ' . $id)->fetchColumn();
        if ($n) flash('error', "This category has $n product(s). Move them to another category first.");
        else {
            $img = $pdo->query('SELECT image FROM categories WHERE id = ' . $id)->fetchColumn();
            delete_upload($img ?: null);
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }
    $name = mb_substr(post('name'), 0, 150);
    if ($name === '') { flash('error', 'Category name is required.'); redirect('admin/categories.php'); }
    $icon = preg_replace('/[^a-z0-9-]/', '', strtolower(post('icon'))) ?: 'fa-tag';
    if (strpos($icon, 'fa-') !== 0) $icon = 'fa-' . $icon;
    $slug = unique_slug('categories', post('slug') ?: $name, $id);
    $img = upload_image($_FILES['image'] ?? [], 'branding', 900);
    $data = [$name, $slug, $icon, mb_substr(post('description'), 0, 500), (int)post('sort_order'), post('is_active') ? 1 : 0];
    if ($id) {
        $pdo->prepare('UPDATE categories SET name=?, slug=?, icon=?, description=?, sort_order=?, is_active=? WHERE id=?')->execute([...$data, $id]);
        if ($img) { delete_upload($pdo->query('SELECT image FROM categories WHERE id = ' . $id)->fetchColumn() ?: null); $pdo->prepare('UPDATE categories SET image=? WHERE id=?')->execute([$img, $id]); }
        flash('success', 'Category updated.');
    } else {
        $pdo->prepare('INSERT INTO categories (name, slug, icon, description, sort_order, is_active, image) VALUES (?,?,?,?,?,?,?)')->execute([...$data, $img]);
        flash('success', 'Category added.');
    }
    redirect('admin/categories.php');
}

$edit = null;
if (get('edit')) { $s = $pdo->prepare('SELECT * FROM categories WHERE id = ?'); $s->execute([(int)get('edit')]); $edit = $s->fetch(); }
$cats = categories(false);
$icons = ['fa-basket-shopping','fa-mug-hot','fa-spray-can-sparkles','fa-pump-soap','fa-plug','fa-blender','fa-utensils','fa-baby','fa-shirt','fa-mobile-screen','fa-laptop','fa-tv','fa-couch','fa-bottle-water','fa-apple-whole','fa-drumstick-bite','fa-bread-slice','fa-cookie','fa-wine-bottle','fa-pills','fa-paw','fa-pen','fa-gift','fa-screwdriver-wrench','fa-car','fa-gem','fa-shoe-prints','fa-football','fa-lightbulb','fa-fan','fa-box','fa-tag'];

$adminTitle = 'Categories';
require __DIR__ . '/_header.php';
?>
<div class="split">
  <div class="card flush-sm">
    <table class="table cards">
      <thead><tr><th>Category</th><th>Products</th><th>Order</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($cats as $c): ?>
        <tr>
          <td class="t-main"><span class="thumb" style="display:grid;place-items:center;font-size:20px;color:var(--primary);background:var(--soft)"><i class="fa-solid <?= e($c['icon']) ?>"></i></span><div><div class="t-name"><?= e($c['name']) ?></div><div class="t-sub">/<?= e($c['slug']) ?></div></div></td>
          <td data-label="Products"><?= (int)$c['product_count'] ?></td>
          <td data-label="Order"><?= (int)$c['sort_order'] ?></td>
          <td data-label="Status"><span class="pill <?= $c['is_active'] ? 'on' : 'off' ?>"><?= $c['is_active'] ? 'Visible' : 'Hidden' ?></span></td>
          <td class="t-actions"><div class="btn-row">
            <a class="btn btn-outline btn-sm" href="?edit=<?= $c['id'] ?>#form"><i class="fa-solid fa-pen"></i></a>
            <form method="post" data-confirm="Delete this category?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-regular fa-trash-can"></i></button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card" id="form">
    <h3><?= $edit ? 'Edit category' : 'Add category' ?></h3>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field"><label>Name *</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>" data-slug-src></div>
      <div class="field"><label>Slug</label><input name="slug" value="<?= e($edit['slug'] ?? '') ?>" data-slug <?= $edit ? 'data-touched="1"' : '' ?>></div>
      <div class="field"><label>Icon</label>
        <select name="icon"><?php foreach ($icons as $ic): ?><option value="<?= $ic ?>" <?= ($edit['icon'] ?? '') === $ic ? 'selected' : '' ?>><?= ucwords(str_replace(['fa-', '-'], ['', ' '], $ic)) ?></option><?php endforeach; ?></select>
        <small>Icons come from Font Awesome.</small></div>
      <div class="field"><label>Description</label><textarea name="description" style="min-height:70px"><?= e($edit['description'] ?? '') ?></textarea></div>
      <div class="field"><label>Image (optional)</label><input type="file" name="image" accept="image/*"></div>
      <div class="field"><label>Display order</label><input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? count($cats) + 1) ?>"></div>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Visible</label>
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Add category' ?></button>
      <?php if ($edit): ?><a class="btn btn-outline" href="<?= url('admin/categories.php') ?>">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
