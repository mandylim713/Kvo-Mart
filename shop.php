<?php
require __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$catSlug  = get('cat');
$q        = get('q');
$min      = (int)get('min', 0);
$max      = (int)get('max', 0);
$deals    = get('deals') === '1';
$featured = get('featured') === '1';
$inStock  = get('instock') === '1';
$brand    = get('brand');
$sort     = get('sort', 'new');
$page     = max(1, (int)get('page', 1));
$perPage  = 20;

$where = ['p.is_active = 1'];
$args  = [];
$category = null;
if ($catSlug !== '') {
    $st = $pdo->prepare('SELECT * FROM categories WHERE slug = ? AND is_active = 1');
    $st->execute([$catSlug]);
    $category = $st->fetch();
    if ($category) { $where[] = 'p.category_id = ?'; $args[] = $category['id']; }
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.sku LIKE ? OR p.short_desc LIKE ?)';
    $like = '%' . $q . '%';
    array_push($args, $like, $like, $like, $like);
}
if ($min > 0) { $where[] = 'p.price >= ?'; $args[] = $min; }
if ($max > 0) { $where[] = 'p.price <= ?'; $args[] = $max; }
if ($deals) $where[] = '(p.is_deal = 1 OR p.old_price > p.price)';
if ($featured) $where[] = 'p.is_featured = 1';
if ($inStock) $where[] = 'p.stock > 0';
if ($brand !== '') { $where[] = 'p.brand = ?'; $args[] = $brand; }

$orderBy = match ($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular'    => 'p.sold DESC, p.views DESC',
    'name'       => 'p.name ASC',
    default      => 'p.created_at DESC, p.id DESC',
};
$whereSql = implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$cnt->execute($args);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$st = $pdo->prepare("SELECT p.* FROM products p WHERE $whereSql ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$st->execute($args);
$products = $st->fetchAll();

// brands for filter (within category if chosen)
$bq = "SELECT DISTINCT brand FROM products WHERE is_active = 1 AND brand IS NOT NULL AND brand <> ''" .($category ? ' AND category_id = ' . (int)$category['id'] : '') . ' ORDER BY brand';
$brands = $pdo->query($bq)->fetchAll(PDO::FETCH_COLUMN);

function qs(array $change): string
{
    $p = array_merge($_GET, $change);
    foreach ($p as $k => $v) if ($v === '' || $v === null) unset($p[$k]);
    return url('shop.php' . ($p ? '?' . http_build_query($p) : ''));
}

if ($category) $pageTitle = $category['name'];
elseif ($q !== '') $pageTitle = 'Search: ' . $q;
elseif ($deals) $pageTitle = 'Hot deals';
elseif ($featured) $pageTitle = 'Featured products';
else $pageTitle = 'Shop all products';
$pageDesc = $category['description'] ?? null;

require __DIR__ . '/includes/header.php';
$activeFilters = ($min || $max || $inStock || $brand !== '' || $deals) ? 1 : 0;
?>

<div class="container">
  <div class="page-head">
    <div class="crumbs"><a href="<?= url('index.php') ?>">Home</a> / <a href="<?= url('shop.php') ?>">Shop</a><?php if ($category): ?> / <span><?= e($category['name']) ?></span><?php endif; ?></div>
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($category && $category['description']): ?><p class="muted"><?= e($category['description']) ?></p><?php endif; ?>
  </div>

  <div class="chips" style="margin-top:6px">
    <a class="chip <?= !$category ? 'active' : '' ?>" href="<?= qs(['cat' => '', 'brand' => '', 'page' => '']) ?>">All</a>
    <?php foreach (categories() as $c): ?>
      <a class="chip <?= $category && $category['id'] == $c['id'] ? 'active' : '' ?>" href="<?= qs(['cat' => $c['slug'], 'brand' => '', 'page' => '']) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="shop-layout">
    <div class="overlay" data-close="filters"></div>
    <form class="filters" id="filters" method="get" action="<?= url('shop.php') ?>">
      <div class="sheet-grip"></div>
      <div class="sheet-head filters-close"><strong>Filter products</strong><button class="icon-btn" type="button" data-close="filters" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <input type="hidden" name="sort" value="<?= e($sort) ?>">
      <div class="filter-group">
        <h4>Category</h4>
        <label><input type="radio" name="cat" value="" <?= !$category ? 'checked' : '' ?>> All categories</label>
        <?php foreach (categories() as $c): ?>
          <label><input type="radio" name="cat" value="<?= e($c['slug']) ?>" <?= $category && $category['id'] == $c['id'] ? 'checked' : '' ?>> <?= e($c['name']) ?> <span class="muted">(<?= (int)$c['product_count'] ?>)</span></label>
        <?php endforeach; ?>
      </div>
      <div class="filter-group">
        <h4>Price (<?= e(setting('currency')) ?>)</h4>
        <div class="price-row field">
          <input type="number" name="min" min="0" placeholder="Min" value="<?= $min ?: '' ?>" inputmode="numeric">
          <input type="number" name="max" min="0" placeholder="Max" value="<?= $max ?: '' ?>" inputmode="numeric">
        </div>
      </div>
      <?php if ($brands): ?>
      <div class="filter-group">
        <h4>Brand</h4>
        <label><input type="radio" name="brand" value="" <?= $brand === '' ? 'checked' : '' ?>> Any brand</label>
        <?php foreach ($brands as $b): ?><label><input type="radio" name="brand" value="<?= e($b) ?>" <?= $brand === $b ? 'checked' : '' ?>> <?= e($b) ?></label><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="filter-group">
        <h4>Availability</h4>
        <label><input type="checkbox" name="instock" value="1" <?= $inStock ? 'checked' : '' ?>> In stock only</label>
        <label><input type="checkbox" name="deals" value="1" <?= $deals ? 'checked' : '' ?>> On sale / deals</label>
      </div>
      <div style="display:flex;gap:10px;padding-top:14px">
        <a class="btn btn-outline" style="flex:1" href="<?= url('shop.php') ?>">Reset</a>
        <button class="btn btn-primary" style="flex:2" type="submit">Show results</button>
      </div>
    </form>

    <div>
      <div class="shop-bar">
        <button class="btn btn-outline btn-sm filter-btn" type="button" data-open="filters"><i class="fa-solid fa-sliders"></i> Filters<?= $activeFilters ? ' •' : '' ?></button>
        <span class="count hide-sm"><?= number_format($total) ?> product<?= $total === 1 ? '' : 's' ?></span>
        <select aria-label="Sort products" onchange="location.href=this.value">
          <?php foreach (['new' => 'Newest', 'popular' => 'Most popular', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'name' => 'Name A–Z'] as $k => $v): ?>
            <option value="<?= e(qs(['sort' => $k, 'page' => ''])) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($products): ?>
        <div class="grid"><?php foreach ($products as $p) echo product_card($p); ?></div>
        <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pages">
          <?php if ($page > 1): ?><a href="<?= qs(['page' => $page - 1]) ?>" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
          <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?><span class="active"><?= $i ?></span><?php else: ?><a href="<?= qs(['page' => $i]) ?>"><?= $i ?></a><?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $pages): ?><a href="<?= qs(['page' => $page + 1]) ?>" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
        </nav>
        <?php endif; ?>
      <?php else: ?>
        <div class="card empty">
          <i class="fa-solid fa-box-open"></i>
          <h3>No products found</h3>
          <p>Try a different search or remove some filters.</p>
          <a class="btn btn-primary" href="<?= url('shop.php') ?>">Browse all products</a>
          <p style="margin-top:14px"><a class="btn btn-wa" href="<?= e(wa_link('Hello, do you have ' . ($q ?: 'this product') . ' in stock?')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Ask us on WhatsApp</a></p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
