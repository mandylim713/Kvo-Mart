<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    switch (post('action')) {
        case 'toggle':
            $pdo->prepare('UPDATE products SET is_active = 1 - is_active, updated_at = ? WHERE id = ?')->execute([now(), $id]);
            flash('success', 'Product visibility updated.');
            break;
        case 'delete':
            $p = $pdo->query('SELECT image FROM products WHERE id = ' . $id)->fetch();
            $used = (int)$pdo->query('SELECT COUNT(*) FROM order_items WHERE product_id = ' . $id)->fetchColumn();
            if ($used) {
                $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$id]);
                flash('info', 'This product has past orders, so it was hidden instead of deleted.');
            } elseif ($p) {
                delete_upload($p['image']);
                foreach ($pdo->query('SELECT path FROM product_images WHERE product_id = ' . $id)->fetchAll(PDO::FETCH_COLUMN) as $g) delete_upload($g);
                $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM reviews WHERE product_id = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
                flash('success', 'Product deleted.');
            }
            break;
        case 'quick':
            $price = max(0, (int)post('price'));
            $newStock = max(0, (int)post('stock'));
            $cur = (int)$pdo->query('SELECT stock FROM products WHERE id = ' . $id)->fetchColumn();
            $pdo->prepare('UPDATE products SET price = ?, updated_at = ? WHERE id = ?')->execute([$price, now(), $id]);
            if ($newStock !== $cur) stock_adjust($id, $newStock - $cur, 'Quick edit');
            flash('success', 'Saved.');
            break;
    }
    redirect('admin/products.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'cat', 'show', 'page']))));
}

$qs = get('q');
$cat = (int)get('cat');
$show = get('show');
$page = max(1, (int)get('page', 1));
$per = 25;
$w = ['1=1']; $a = [];
if ($qs !== '') { $w[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)'; array_push($a, "%$qs%", "%$qs%", "%$qs%"); }
if ($cat) { $w[] = 'p.category_id = ?'; $a[] = $cat; }
if ($show === 'hidden') $w[] = 'p.is_active = 0';
if ($show === 'featured') $w[] = 'p.is_featured = 1';
if ($show === 'out') $w[] = 'p.stock <= 0';
$ws = implode(' AND ', $w);
$c = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $ws"); $c->execute($a); $total = (int)$c->fetchColumn();
$st = $pdo->prepare("SELECT p.*, c.name cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $ws ORDER BY p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($a);
$rows = $st->fetchAll();

$adminTitle = 'Products';
require __DIR__ . '/_header.php';
?>
<form class="toolbar" method="get">
  <div class="grow"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search name, SKU, brand"></div>
  <select name="cat" onchange="this.form.submit()"><option value="">All categories</option><?php foreach (categories(false) as $ct): ?><option value="<?= $ct['id'] ?>" <?= $cat == $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option><?php endforeach; ?></select>
  <select name="show" onchange="this.form.submit()"><option value="">All</option><option value="featured" <?= $show === 'featured' ? 'selected' : '' ?>>Featured</option><option value="out" <?= $show === 'out' ? 'selected' : '' ?>>Out of stock</option><option value="hidden" <?= $show === 'hidden' ? 'selected' : '' ?>>Hidden</option></select>
  <a class="btn btn-primary" href="<?= url('admin/product-edit.php') ?>"><i class="fa-solid fa-plus"></i> Add product</a>
</form>
<p class="muted" style="margin:0 0 10px;font-size:13px"><?= $total ?> product<?= $total === 1 ? '' : 's' ?> · edit price and stock inline, then tap <i class="fa-solid fa-check"></i></p>

<div class="card flush-sm">
<?php if ($rows): ?>
<table class="table cards">
  <thead><tr><th>Product</th><th>Category</th><th>Price (<?= e(setting('currency')) ?>)</th><th>Stock</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): $fid = 'q' . $p['id']; ?>
    <tr>
      <td class="t-main"><img class="thumb" src="<?= e(product_img($p)) ?>" alt=""><div><a class="t-name" href="<?= url('admin/product-edit.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a><div class="t-sub"><?= e($p['sku']) ?><?= $p['unit'] ? ' · ' . e($p['unit']) : '' ?><?= $p['is_featured'] ? ' · <i class="fa-solid fa-star" style="color:#f59e0b"></i>' : '' ?></div></div></td>
      <td data-label="Category"><?= e($p['cat_name'] ?? '—') ?></td>
      <td data-label="Price"><input form="<?= $fid ?>" class="input" style="width:110px;min-height:38px;font-size:14px" type="number" name="price" min="0" value="<?= (int)$p['price'] ?>" inputmode="numeric"></td>
      <td data-label="Stock"><input form="<?= $fid ?>" class="input" style="width:80px;min-height:38px;font-size:14px;<?= $p['stock'] <= $p['low_stock'] ? 'border-color:#fdba74' : '' ?>" type="number" name="stock" min="0" value="<?= (int)$p['stock'] ?>" inputmode="numeric"></td>
      <td data-label="Status"><span class="pill <?= $p['is_active'] ? 'on' : 'off' ?>"><?= $p['is_active'] ? 'Visible' : 'Hidden' ?></span></td>
      <td class="t-actions">
        <div class="btn-row">
          <form id="<?= $fid ?>" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="quick"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-outline btn-sm" title="Save price & stock"><i class="fa-solid fa-check"></i></button></form>
          <a class="btn btn-outline btn-sm" href="<?= url('admin/product-edit.php?id=' . $p['id']) ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-outline btn-sm" title="<?= $p['is_active'] ? 'Hide' : 'Show' ?>"><i class="fa-solid <?= $p['is_active'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i></button></form>
          <form method="post" data-confirm="Delete this product permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-danger btn-sm" title="Delete"><i class="fa-regular fa-trash-can"></i></button></form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?= paginate($total, $page, $per) ?>
<?php else: ?><div class="empty card"><i class="fa-solid fa-box-open"></i>No products found. <a href="<?= url('admin/product-edit.php') ?>" style="color:var(--primary);font-weight:700">Add your first product</a></div><?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
