<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

$id = (int)get('id');
$p = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) { flash('error', 'Product not found.'); redirect('admin/products.php'); }
}
$gallery = [];
if ($p) {
    $g = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $g->execute([$id]);
    $gallery = $g->fetchAll();
}

if (is_post()) {
    csrf_check();
    $d = [
        'name'        => mb_substr(post('name'), 0, 200),
        'category_id' => (int)post('category_id') ?: null,
        'sku'         => mb_substr(post('sku'), 0, 80),
        'brand'       => mb_substr(post('brand'), 0, 120),
        'unit'        => mb_substr(post('unit'), 0, 60),
        'short_desc'  => mb_substr(post('short_desc'), 0, 500),
        'description' => clean_html((string)post('description')),
        'specs'       => mb_substr(post('specs'), 0, 4000),
        'price'       => max(0, (int)str_replace(',', '', post('price'))),
        'old_price'   => max(0, (int)str_replace(',', '', post('old_price'))),
        'low_stock'   => max(0, (int)post('low_stock', 5)),
        'is_featured' => post('is_featured') ? 1 : 0,
        'is_deal'     => post('is_deal') ? 1 : 0,
        'is_active'   => post('is_active') ? 1 : 0,
    ];
    if ($d['name'] === '' || $d['price'] <= 0) {
        flash('error', 'Product name and a price greater than 0 are required.');
        redirect('admin/product-edit.php' . ($id ? '?id=' . $id : ''));
    }
    $slugIn = post('slug') !== '' ? post('slug') : $d['name'];
    $d['slug'] = unique_slug('products', $slugIn, $id);

    $img = upload_image($_FILES['image'] ?? [], 'products');
    if ($img) { if ($p) delete_upload($p['image']); $d['image'] = $img; }

    if ($p) {
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($d)));
        $pdo->prepare("UPDATE products SET $sets, updated_at = ? WHERE id = ?")->execute([...array_values($d), now(), $id]);
        $newStock = max(0, (int)post('stock'));
        if ($newStock !== (int)$p['stock']) stock_adjust($id, $newStock - (int)$p['stock'], post('stock_reason') ?: 'Manual edit');
    } else {
        if ($d['sku'] === '') $d['sku'] = 'KVO-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $d['stock'] = 0;
        $d['created_at'] = now();
        $d['updated_at'] = now();
        $cols = implode(', ', array_keys($d));
        $ph = implode(', ', array_fill(0, count($d), '?'));
        $pdo->prepare("INSERT INTO products ($cols) VALUES ($ph)")->execute(array_values($d));
        $id = (int)$pdo->lastInsertId();
        $opening = max(0, (int)post('stock'));
        if ($opening) stock_adjust($id, $opening, 'Opening stock');
    }

    // remove selected gallery images
    foreach ((array)post('remove_img', []) as $gid) {
        $gp = $pdo->prepare('SELECT path FROM product_images WHERE id = ? AND product_id = ?');
        $gp->execute([(int)$gid, $id]);
        if ($path = $gp->fetchColumn()) { delete_upload($path); $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([(int)$gid]); }
    }
    // add gallery images
    $n = (int)$pdo->query('SELECT COUNT(*) FROM product_images WHERE product_id = ' . $id)->fetchColumn();
    foreach (files_list('gallery') as $f) {
        if ($n >= 8) { flash('info', 'Maximum of 8 extra images per product.'); break; }
        if ($path = upload_image($f, 'products')) {
            $pdo->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?,?,?)')->execute([$id, $path, $n++]);
        }
    }

    flash('success', 'Product saved.');
    redirect(post('save_new') ? 'admin/product-edit.php' : 'admin/product-edit.php?id=' . $id);
}

$v = fn($k, $def = '') => e($p[$k] ?? $def);
$adminTitle = $p ? 'Edit product' : 'Add product';
$useEditor = true;
require __DIR__ . '/_header.php';
?>
<?php if ($p): ?>
<div class="btn-row" style="margin-bottom:12px">
  <a class="btn btn-outline btn-sm" href="<?= url('admin/products.php') ?>"><i class="fa-solid fa-arrow-left"></i> Products</a>
  <a class="btn btn-outline btn-sm" href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
  <a class="btn btn-outline btn-sm" href="<?= url('admin/stock.php?product=' . $p['id']) ?>"><i class="fa-solid fa-clock-rotate-left"></i> Stock history</a>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="split">
  <?= csrf_field() ?>
  <div>
    <div class="card">
      <div class="form-grid two">
        <div class="field full"><label>Product name *</label><input name="name" required maxlength="200" value="<?= $v('name') ?>" data-slug-src></div>
        <div class="field"><label>Brand</label><input name="brand" value="<?= $v('brand') ?>"></div>
        <div class="field"><label>Pack size / unit</label><input name="unit" value="<?= $v('unit') ?>" placeholder="e.g. 5 litres, Pack of 12"></div>
        <div class="field full"><label>Short description</label><textarea name="short_desc" style="min-height:70px" maxlength="500" placeholder="One or two lines shown near the price"><?= $v('short_desc') ?></textarea></div>
        <div class="field full"><label>Full description</label><textarea name="description" data-editor><?= e($p['description'] ?? '') ?></textarea></div>
        <div class="field full"><label>Specifications</label><textarea name="specs" placeholder="One per line, e.g.&#10;Brand: Binatone&#10;Power: 500W&#10;Warranty: 1 year"><?= $v('specs') ?></textarea><small>Each line as <strong>Name: Value</strong> — shown as a table on the product page.</small></div>
      </div>
    </div>

    <div class="card">
      <h3>Images</h3>
      <div class="field"><label>Main image</label>
        <?php if (!empty($p['image'])): ?><img src="<?= e(asset($p['image'])) ?>" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:12px;margin-bottom:8px;border:1px solid var(--line)"><?php endif; ?>
        <input type="file" name="image" accept="image/*" data-preview="pv-main"><div class="previews" id="pv-main"></div><small>Square photos look best (at least 800×800). JPG, PNG or WEBP, max 8MB.</small>
      </div>
      <div class="field" style="margin-top:14px"><label>Extra images (up to 8)</label>
        <?php if ($gallery): ?><div class="gallery-edit" style="margin-bottom:10px"><?php foreach ($gallery as $g): ?><div class="gi"><img src="<?= e(asset($g['path'])) ?>" alt=""><label><input type="checkbox" name="remove_img[]" value="<?= $g['id'] ?>"> Remove</label></div><?php endforeach; ?></div><?php endif; ?>
        <input type="file" name="gallery[]" accept="image/*" multiple data-preview="pv-gal"><div class="previews" id="pv-gal"></div>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <h3>Price &amp; stock</h3>
      <div class="form-grid">
        <div class="field"><label>Selling price (<?= e(setting('currency')) ?>) *</label><input type="number" name="price" min="1" required value="<?= $v('price') ?>" inputmode="numeric"></div>
        <div class="field"><label>Old price (<?= e(setting('currency')) ?>)</label><input type="number" name="old_price" min="0" value="<?= ($p['old_price'] ?? 0) ?: '' ?>" inputmode="numeric"><small>Shown crossed out to display a discount.</small></div>
        <div class="field"><label><?= $p ? 'Quantity in stock' : 'Opening stock' ?></label><input type="number" name="stock" min="0" value="<?= (int)($p['stock'] ?? 0) ?>" inputmode="numeric"></div>
        <?php if ($p): ?><div class="field"><label>Reason for stock change</label><input name="stock_reason" placeholder="e.g. New delivery, damaged, recount"></div><?php endif; ?>
        <div class="field"><label>Low-stock alert at</label><input type="number" name="low_stock" min="0" value="<?= (int)($p['low_stock'] ?? 5) ?>" inputmode="numeric"></div>
      </div>
    </div>
    <div class="card">
      <h3>Organise</h3>
      <div class="form-grid">
        <div class="field"><label>Category</label><select name="category_id"><option value="">— None —</option><?php foreach (categories(false) as $ct): ?><option value="<?= $ct['id'] ?>" <?= ($p['category_id'] ?? 0) == $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>SKU / code</label><input name="sku" value="<?= $v('sku') ?>" placeholder="Auto if empty"></div>
        <div class="field"><label>URL slug</label><input name="slug" value="<?= $v('slug') ?>" data-slug <?= $p ? 'data-touched="1"' : '' ?>></div>
        <label class="check"><input type="checkbox" name="is_active" value="1" <?= ($p['is_active'] ?? 1) ? 'checked' : '' ?>> Visible on website</label>
        <label class="check"><input type="checkbox" name="is_featured" value="1" <?= !empty($p['is_featured']) ? 'checked' : '' ?>> Featured on homepage</label>
        <label class="check"><input type="checkbox" name="is_deal" value="1" <?= !empty($p['is_deal']) ? 'checked' : '' ?>> Show in Hot deals</label>
      </div>
    </div>
    <div class="card">
      <div class="form-grid">
        <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save product</button>
        <?php if (!$p): ?><button class="btn btn-outline btn-block" type="submit" name="save_new" value="1">Save &amp; add another</button><?php endif; ?>
      </div>
    </div>
  </div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
