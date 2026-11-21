<?php
$p = $slug ? prod_by_slug($slug) : null;
if (!$p || ($p['status'] === 'DRAFT' && !can('products.view'))) not_found();
if (empty($_SESSION['viewed'][$p['id']])) { db()->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$p['id']]); $_SESSION['viewed'][$p['id']] = 1; }

$b = buyable($p);
[$st, $tone] = stock_label($p);
$c = cat_by_slug($p['cat']);
$base = catalogue_base($p['kind']);
$showPrice = price_of($p) !== null && $p['mode'] !== 'QUOTE_ONLY';
$hist = db()->prepare('SELECT price FROM price_history WHERE product_id = ? ORDER BY id DESC LIMIT 2'); $hist->execute([$p['id']]); $hist = $hist->fetchAll(PDO::FETCH_COLUMN);
$was = ($showPrice && count($hist) > 1 && (int)$hist[1] > price_of($p)) ? (int)$hist[1] : null;
$rel = db()->prepare("SELECT * FROM products WHERE status = 'PUBLISHED' AND cat = ? AND id != ? ORDER BY featured DESC, sales DESC LIMIT 4"); $rel->execute([$p['cat'], $p['id']]); $related = $rel->fetchAll();
$gal = db()->prepare('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order, id'); $gal->execute([$p['id']]);
$imgs = array_values(array_filter(array_merge([$p['image']], $gal->fetchAll(PDO::FETCH_COLUMN))));
$inCompare = in_array($p['slug'], $_SESSION['compare'] ?? [], true);
$nCompare = count($_SESSION['compare'] ?? []);
$facts = [['Manufacturer', h($p['brand'])], ['Model', h($p['model'])], ['SKU', h($p['sku'])], ['Category', h($c['name'] ?? '')], ['Condition', COND[$p['cond']] ?? '']];
$taxBps = (int)setting('tax_bps');
$waText = 'Hello ' . setting('short_name', 'KVO Mart') . ", I'm interested in: " . $p['name'] . ' (' . $p['sku'] . ")\n" . full_url('/p/' . $p['slug']);
$crumbKind = $p['kind'] === 'PART' ? 'Spare parts' : ($p['kind'] === 'SUPPLY' ? 'Supplies' : 'Heavy equipment');
$_SESSION['form_for'] = 'enquiry';

$ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['name'], 'sku' => $p['sku'], 'description' => $p['description'], 'brand' => $p['brand'] ? ['@type' => 'Brand', 'name' => $p['brand']] : null, 'image' => $p['image'] ? full_url($p['image']) : null, 'itemCondition' => 'https://schema.org/' . ucfirst(strtolower($p['cond'])) . 'Condition'];
if ($showPrice) $ld['offers'] = ['@type' => 'Offer', 'priceCurrency' => 'NGN', 'price' => number_format(price_of($p) / 100, 2, '.', ''), 'availability' => 'https://schema.org/' . (avail($p) > 0 ? 'InStock' : 'OutOfStock'), 'url' => full_url('/p/' . $p['slug'])];

page_open($p['name'], ['desc' => mb_strimwidth($p['description'], 0, 158, '…'), 'image' => $p['image'], 'canonical' => '/p/' . $p['slug'], 'body' => $b['ok'] ? 'has-sticky' : '',
    'head' => '<script type="application/ld+json">' . json_encode(array_filter($ld), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>']);
?>
<div class="page py"><?= crumbs([['Home', '/'], [$crumbKind, $base], [$c['name'] ?? '', $base . '?category=' . $p['cat']], [$p['name']]]) ?>
  <?php if ($p['status'] !== 'PUBLISHED'): ?><div class="mt4"><?= note('This listing is no longer available', 'It may have been sold or withdrawn.', 'warn') ?></div><?php endif; ?>
  <div class="split mt6">
    <div>
      <div class="gallery"><?php if (count($imgs) > 1): ?><div class="slides" data-gallery><?php foreach ($imgs as $i => $src): ?><img src="<?= h(asset($src)) ?>" alt="<?= h($p['name']) ?><?= $i ? ' — photo ' . ($i + 1) : '' ?>"<?= $i ? ' loading="lazy"' : '' ?>><?php endforeach; ?></div><span class="gcount" data-gcount>1 / <?= count($imgs) ?></span><?php else: echo photo($p); endif; ?></div>
      <?php if (count($imgs) > 1): ?><div class="thumbs"><?php foreach ($imgs as $i => $src): ?><button type="button" class="<?= $i ? '' : 'on' ?>" data-thumb="<?= $i ?>" aria-label="Photo <?= $i + 1 ?>"><img src="<?= h(asset($src)) ?>" alt=""></button><?php endforeach; ?></div><?php endif; ?>
      <?php if ($p['image'] && $p['credit']): ?><p class="xs muted mt2"><strong style="color:var(--char)">Representative photo — the unit for sale may differ.</strong> <?= $p['credit_url'] ? '<a href="' . h($p['credit_url']) . '" target="_blank" rel="noopener noreferrer license">' . h($p['credit']) . '</a>' : h($p['credit']) ?></p><?php endif; ?>
    </div>
    <div><p class="sm muted"><?= h($c['name'] ?? '') ?></p><h1 class="mt1"><?= h($p['name']) ?></h1>
      <div class="row mt3" style="gap:.4rem"><?= badge(COND[$p['cond']] ?? $p['cond']) . badge($st, $tone) . ($p['inv'] === 'UNIQUE_UNIT' ? badge('Single unit', 'dark') : '') ?></div>
      <div class="mt6" style="border-top:1px solid var(--steel);border-bottom:1px solid var(--steel);padding:1.25rem 0">
        <?php if ($showPrice): ?><p class="price" style="font-size:2.2rem"><?= naira($p['price']) ?></p><?php if ($was): ?><p class="sm muted tab">Previously <?= naira($was) ?></p><?php endif; ?>
          <p class="sm muted mt1"><?= $taxBps ? (setting('prices_include_tax') === '1' ? 'Includes ' . h(setting('tax_label')) . '.' : 'Excludes ' . h(setting('tax_label')) . '.') : 'Price in Naira.' ?> Delivery is calculated at checkout.</p>
        <?php else: ?><p class="price" style="font-size:1.9rem">Request price</p><p class="sm muted mt1">Pricing depends on specification and availability. Request a quote and our sales team will respond in writing.</p><?php endif; ?>
      </div>
      <?php if ($b['ok']): ?>
      <form method="post" action="<?= h(url('/cart/add')) ?>" class="mt6" id="buybox"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>">
        <?php if ($b['max'] > 1): ?><div class="row"><label class="l" for="qty" style="margin:0">Quantity</label><div class="row" style="gap:0;border:1px solid var(--steel300)"><button type="button" class="ib" data-qty="-1" aria-label="Decrease">−</button><input id="qty" name="qty" type="number" min="1" max="<?= $b['max'] ?>" value="1" class="tab" inputmode="numeric" style="width:64px;height:44px;border:0;border-left:1px solid var(--steel300);border-right:1px solid var(--steel300);text-align:center"><button type="button" class="ib" data-qty="1" aria-label="Increase">+</button></div><span class="sm muted"><?= $b['max'] ?> available</span></div>
        <?php else: ?><input type="hidden" name="qty" value="1"><?php endif; ?>
        <div class="row mt4" style="flex-wrap:nowrap"><button class="btn grow" name="go" value="cart" style="min-height:48px"><?= ICON['cart'] ?> Add to cart</button><button class="btn dark grow" name="go" value="buy" style="min-height:48px">Buy now</button></div>
      </form>
      <?php endif; ?>
      <div class="row mt4" style="flex-wrap:nowrap">
        <?php if ($p['mode'] !== 'FIXED_PRICE' || !$b['ok']): ?><a class="btn out grow" href="#quote" style="min-height:48px">Request a quote</a><?php endif; ?>
        <?php if (wa_number()): ?><a class="btn out grow" style="min-height:48px;border-color:var(--steel300)" href="<?= h(wa_link($waText)) ?>" target="_blank" rel="noopener"><?= ICON['wa'] ?> Ask on WhatsApp</a>
        <?php else: ?><a class="btn out grow" style="min-height:48px;border-color:var(--steel300)" href="<?= h(url('/contact?subject=' . urlencode($p['name']))) ?>">Ask a question</a><?php endif; ?>
      </div>
      <form method="post" action="<?= h(url('/compare')) ?>" class="mt4" style="display:inline"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= h($p['slug']) ?>"><input type="hidden" name="back" value="<?= h('/p/' . $p['slug']) ?>"><button class="link"><?= $inCompare ? 'Remove from comparison' : 'Add to comparison' ?></button></form>
      <?php if ($nCompare > 1): ?> <a class="sm b" style="color:var(--gold6);margin-left:.75rem" href="<?= h(url('/compare')) ?>">Compare <?= $nCompare ?> items</a><?php endif; ?>
      <div class="row mt6" style="align-items:flex-start;flex-wrap:nowrap"><?= ICON['truck'] ?><p class="sm"><?= $p['dq'] ? 'Delivery for this item is quoted separately after your order, based on size, weight, loading and route. You approve the delivery cost before dispatch, or you can collect.' : 'Fixed-rate delivery is available to some states; the charge for your address is shown at checkout. Other locations are quoted.' ?></p></div>
    </div>
  </div>
  <div class="split mt12">
    <div><h2>Description</h2><p class="mt4" style="white-space:pre-line"><?= h($p['description']) ?></p>
      <?php if ($p['kind'] === 'PART'): ?><div class="mt6"><?= note('Not sure it fits?', "We only list compatibility where it has been verified. Send a compatibility enquiry with your machine's make, model and serial number and we'll confirm before you buy.") ?></div><?php endif; ?></div>
    <div><h2>Specifications</h2><div class="plate mt4" style="padding:0 1rem .5rem"><?= pl(array_merge($facts, array_map(fn($s) => [h($s[0]), h($s[1] ?? '')], specs($p)))) ?></div></div>
  </div>
  <section id="quote" class="mt12" style="background:var(--steel50);padding:1.5rem;scroll-margin-top:120px"><h2><?= $p['kind'] === 'PART' ? 'Request a quote or check compatibility' : 'Request a quote' ?></h2><p class="muted mt2">Tell us quantity, delivery location and any requirements. We'll reply with a written quotation.</p>
    <div class="box mt6" style="max-width:48rem"><?= enquiry_form($p['kind'] === 'PART' ? 'PART_COMPATIBILITY' : 'QUOTE', ['product' => $p]) ?></div></section>
  <?php if ($related): ?><section class="mt12"><h2>Related <?= $p['kind'] === 'EQUIPMENT' ? 'equipment' : 'products' ?></h2><div class="cards-scroll mt6"><?php foreach ($related as $r) echo card($r); ?></div></section><?php endif; ?>
</div>
<?php if ($b['ok']): ?>
<div class="sticky-buy" data-sticky><div class="grow" style="min-width:0"><p class="xs muted" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($p['name']) ?></p><p class="price"><?= naira($p['price']) ?></p></div>
  <form method="post" action="<?= h(url('/cart/add')) ?>" class="row" style="gap:.4rem;flex-wrap:nowrap"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><input type="hidden" name="qty" value="1" data-qty-mirror><button class="btn sm" name="go" value="cart" aria-label="Add to cart"><?= ICON['cart'] ?></button><button class="btn dark sm" name="go" value="buy">Buy now</button></form></div>
<?php endif; ?>
<?php page_close();
