<?php
require __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$slug = get('slug');
$st = $pdo->prepare('SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.is_active = 1');
$st->execute([$slug]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="card empty" style="margin-top:20px"><i class="fa-solid fa-box-open"></i><h3>Product not found</h3><p>It may have been removed or renamed.</p><a class="btn btn-primary" href="' . url('shop.php') . '">Continue shopping</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// review submission
if (is_post() && post('action') === 'review') {
    csrf_check();
    $name = mb_substr(post('name'), 0, 100);
    $rating = max(1, min(5, (int)post('rating', 5)));
    $comment = mb_substr(post('comment'), 0, 1500);
    if ($name === '' || $comment === '') flash('error', 'Please enter your name and review.');
    else {
        $pdo->prepare('INSERT INTO reviews (product_id, name, rating, comment, is_approved, created_at) VALUES (?,?,?,?,0,?)')->execute([$p['id'], $name, $rating, $comment, now()]);
        flash('success', 'Thank you! Your review will appear once approved.');
    }
    redirect('product.php?slug=' . urlencode($slug) . '#reviews');
}

if (empty($_SESSION['viewed'][$p['id']])) {
    $pdo->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$p['id']]);
    $_SESSION['viewed'][$p['id']] = 1;
}

$imgs = [product_img($p)];
$gi = $pdo->prepare('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
$gi->execute([$p['id']]);
foreach ($gi->fetchAll(PDO::FETCH_COLUMN) as $g) $imgs[] = asset($g);

$specs = [];
foreach (preg_split('/\r?\n/', (string)$p['specs']) as $line) {
    if (strpos($line, ':') !== false) { [$k, $v] = array_map('trim', explode(':', $line, 2)); if ($k !== '') $specs[] = [$k, $v]; }
}

$rv = $pdo->prepare('SELECT * FROM reviews WHERE product_id = ? AND is_approved = 1 ORDER BY id DESC');
$rv->execute([$p['id']]);
$reviews = $rv->fetchAll();
$avg = $reviews ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : 0;

$rel = $pdo->prepare('SELECT * FROM products WHERE is_active = 1 AND category_id = ? AND id != ? ORDER BY sold DESC, RANDOM() LIMIT 10');
if (DB_DRIVER === 'mysql') $rel = $pdo->prepare('SELECT * FROM products WHERE is_active = 1 AND category_id = ? AND id != ? ORDER BY sold DESC, RAND() LIMIT 10');
$rel->execute([$p['category_id'], $p['id']]);
$related = $rel->fetchAll();

$pct = discount_pct($p);
$stock = (int)$p['stock'];
$inCart = cart_raw()[$p['id']] ?? 0;
$maxQty = max(1, $stock - $inCart);

$pageTitle = $p['name'];
$pageDesc  = $p['short_desc'] ?: $p['name'];
$ogImage   = full_url(ltrim(str_replace(base_path(), '', $imgs[0]), '/'));
$bodyClass = 'has-sticky';
$waText = 'Hello ' . setting('site_name') . ", I'd like to order:\n\n" . $p['name'] . ($p['unit'] ? ' (' . $p['unit'] . ')' : '') . "\nPrice: " . money($p['price']) . "\nLink: " . full_url('product.php?slug=' . $p['slug']);
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="crumbs" style="padding-top:12px">
    <a href="<?= url('index.php') ?>">Home</a> /
    <?php if ($p['cat_slug']): ?><a href="<?= url('shop.php?cat=' . urlencode($p['cat_slug'])) ?>"><?= e($p['cat_name']) ?></a> /<?php endif; ?>
    <span><?= e($p['name']) ?></span>
  </div>

  <div class="product-wrap">
    <div class="gallery">
      <div class="gallery-main">
        <div class="gslides">
          <?php foreach ($imgs as $i => $src): ?><img src="<?= e($src) ?>" alt="<?= e($p['name']) ?><?= $i ? ' — image ' . ($i + 1) : '' ?>" <?= $i ? 'loading="lazy"' : '' ?>><?php endforeach; ?>
        </div>
        <?php if ($pct): ?><span class="badge badge-sale" style="top:12px;left:12px;font-size:12.5px">-<?= $pct ?>%</span><?php endif; ?>
        <?php if (count($imgs) > 1): ?><span class="gcount">1 / <?= count($imgs) ?></span><?php endif; ?>
      </div>
      <?php if (count($imgs) > 1): ?>
      <div class="thumbs">
        <?php foreach ($imgs as $i => $src): ?><button type="button" class="<?= $i ? '' : 'active' ?>" aria-label="Image <?= $i + 1 ?>"><img src="<?= e($src) ?>" alt=""></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="pinfo">
      <?php if ($p['brand']): ?><div class="pbrand"><?= e($p['brand']) ?></div><?php endif; ?>
      <h1><?= e($p['name']) ?></h1>
      <div class="pmeta">
        <?php if ($p['unit']): ?><span><i class="fa-solid fa-box"></i> <?= e($p['unit']) ?></span><?php endif; ?>
        <?php if ($p['sku']): ?><span>SKU: <?= e($p['sku']) ?></span><?php endif; ?>
        <?php if ($reviews): ?><a href="#reviews"><span class="stars"><?= str_repeat('★', (int)round($avg)) . str_repeat('☆', 5 - (int)round($avg)) ?></span> <?= $avg ?> (<?= count($reviews) ?>)</a><?php endif; ?>
      </div>

      <div class="pprice">
        <strong><?= money($p['price']) ?></strong>
        <?php if ($pct): ?><del><?= money($p['old_price']) ?></del><span class="save">Save <?= money($p['old_price'] - $p['price']) ?></span><?php endif; ?>
      </div>

      <?php if ($stock <= 0): ?>
        <div class="stock out"><i class="fa-solid fa-circle-xmark"></i> Out of stock</div>
      <?php elseif ($stock <= (int)$p['low_stock']): ?>
        <div class="stock low"><i class="fa-solid fa-triangle-exclamation"></i> Only <?= $stock ?> left — order soon</div>
      <?php else: ?>
        <div class="stock in"><i class="fa-solid fa-circle-check"></i> In stock</div>
      <?php endif; ?>

      <?php if ($p['short_desc']): ?><p class="pshort"><?= e($p['short_desc']) ?></p><?php endif; ?>

      <?php if ($stock > 0): ?>
      <form method="post" action="<?= url('cart.php') ?>" class="js-add-cart" id="main-buy">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
        <div class="buy-row">
          <div class="qty">
            <button type="button" data-step="-" aria-label="Decrease">−</button>
            <input type="number" name="qty" value="1" min="1" max="<?= $maxQty ?>" data-sync="pq" inputmode="numeric" aria-label="Quantity">
            <button type="button" data-step="+" aria-label="Increase">+</button>
          </div>
          <button class="btn btn-primary btn-lg" type="submit"><i class="fa-solid fa-cart-plus"></i> Add to cart</button>
        </div>
        <div class="buy-row" style="margin-top:0">
          <button class="btn btn-accent btn-lg" type="submit" name="buy_now" value="1"><i class="fa-solid fa-bolt"></i> Buy now</button>
          <a class="btn btn-wa btn-lg" href="<?= e(wa_link($waText)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
        </div>
      </form>
      <?php else: ?>
        <a class="btn btn-wa btn-lg btn-block" href="<?= e(wa_link('Hello, please let me know when this is back in stock: ' . $p['name'])) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Notify me when available</a>
      <?php endif; ?>

      <div class="trust card" style="padding:14px 16px">
        <div><i class="fa-solid fa-truck-fast"></i> Delivery fee calculated at checkout<?= (int)setting('free_delivery_min') ? ' · Free above ' . money(setting('free_delivery_min')) : '' ?></div>
        <?php if (setting('pickup_enabled') === '1'): ?><div><i class="fa-solid fa-store"></i> Free store pickup available</div><?php endif; ?>
        <div><i class="fa-solid fa-shield-halved"></i> Genuine product, checked before dispatch</div>
        <div><i class="fa-solid fa-rotate-left"></i> <a href="<?= url('page.php?slug=returns') ?>" style="text-decoration:underline">Returns &amp; refunds policy</a></div>
      </div>

      <div class="tabs">
        <div class="tab-btns">
          <button type="button" class="active">Description</button>
          <?php if ($specs): ?><button type="button">Specifications</button><?php endif; ?>
          <button type="button" id="reviews">Reviews (<?= count($reviews) ?>)</button>
        </div>
        <div class="tab-pane active prose"><?= $p['description'] ? clean_html($p['description']) : '<p class="muted">No description yet.</p>' ?></div>
        <?php if ($specs): ?>
        <div class="tab-pane"><table class="specs"><?php foreach ($specs as [$k, $v]): ?><tr><th><?= e($k) ?></th><td><?= e($v) ?></td></tr><?php endforeach; ?></table></div>
        <?php endif; ?>
        <div class="tab-pane">
          <?php if ($reviews): foreach ($reviews as $r): ?>
            <div class="review">
              <span class="stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
              <strong style="margin-left:6px"><?= e($r['name']) ?></strong> <small class="muted"><?= e(date('j M Y', strtotime($r['created_at']))) ?></small>
              <p style="margin:6px 0 0"><?= nl2br(e($r['comment'])) ?></p>
            </div>
          <?php endforeach; else: ?>
            <p class="muted">No reviews yet. Be the first to review this product.</p>
          <?php endif; ?>
          <form method="post" class="form-grid" style="margin-top:16px">
            <?= csrf_field() ?><input type="hidden" name="action" value="review">
            <div class="field"><label>Your name</label><input name="name" required maxlength="100" value="<?= e(customer()['name'] ?? '') ?>"></div>
            <div class="field"><label>Rating</label><select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('★', $i) ?> (<?= $i ?>)</option><?php endfor; ?></select></div>
            <div class="field"><label>Your review</label><textarea name="comment" required maxlength="1500"></textarea></div>
            <button class="btn btn-primary" type="submit">Submit review</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
  <section class="section">
    <div class="section-head"><h2>You may also like</h2><a href="<?= url('shop.php?cat=' . urlencode((string)$p['cat_slug'])) ?>">See more</a></div>
    <div class="hscroll"><?php foreach ($related as $r) echo product_card($r); ?></div>
  </section>
  <?php endif; ?>
</div>

<?php if ($stock > 0): ?>
<div class="sticky-buy">
  <div class="sb-price"><small><?= e(mb_strimwidth($p['name'], 0, 22, '…')) ?></small><strong><?= money($p['price']) ?></strong></div>
  <form method="post" action="<?= url('cart.php') ?>" class="js-add-cart">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
    <input type="hidden" name="qty" value="1" data-sync="pq">
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-cart-plus"></i> Add</button>
    <button class="btn btn-accent" type="submit" name="buy_now" value="1">Buy now</button>
  </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
