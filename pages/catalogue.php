<?php
$cfg = [
    'equipment'   => ['EQUIPMENT', '/equipment', 'Heavy equipment', 'Tractors, excavators, dozers, graders, rollers, generators and more. Buy listed machines online or ask for a written quotation.'],
    'spare-parts' => ['PART', '/spare-parts', 'Spare parts & implements', 'Filters, wear parts, implements and attachments. Ask us to confirm fitment before you buy.'],
    'supplies'    => ['SUPPLY', '/supplies', 'Supplies', 'Construction materials and commercial supplies. Bulk pricing on request.'],
    'search'      => [null, '/search', 'Search', 'Results across equipment, parts and supplies.'],
];
[$kind, $base, $title, $intro] = $cfg[$a];
$Q = array_map(fn($v) => is_string($v) ? trim($v) : '', $_GET);
$PER = 12;

$list = db()->query("SELECT * FROM products WHERE status = 'PUBLISHED'")->fetchAll();
if ($kind) $list = array_filter($list, fn($p) => $p['kind'] === $kind);
$catNames = []; foreach (categories(false) as $c) $catNames[$c['slug']] = $c['name'];
if (!empty($Q['q'])) foreach (preg_split('/\s+/', mb_strtolower($Q['q'])) as $t) if ($t !== '') $list = array_filter($list, fn($p) => str_contains(mb_strtolower(implode(' ', [$p['name'], $p['model'], $p['sku'], $p['brand'], $p['description'], $catNames[$p['cat']] ?? '', implode(' ', array_map(fn($s) => implode(' ', $s), specs($p)))])), $t));
if (($Q['category'] ?? '') === 'caterpillar-machinery') $list = array_filter($list, fn($p) => $p['brand'] === 'Caterpillar' || $p['cat'] === 'caterpillar-machinery');
elseif (!empty($Q['category'])) $list = array_filter($list, fn($p) => $p['cat'] === $Q['category']);
if (!empty($Q['brand'])) $list = array_filter($list, fn($p) => slugify($p['brand'] ?: '-') === $Q['brand']);
if (!empty($Q['condition'])) $list = array_filter($list, fn($p) => $p['cond'] === $Q['condition']);
$min = is_numeric($Q['min'] ?? '') ? (int)$Q['min'] * 100 : null;
$max = is_numeric($Q['max'] ?? '') ? (int)$Q['max'] * 100 : null;
if ($min !== null) $list = array_filter($list, fn($p) => price_of($p) !== null && price_of($p) >= $min);
if ($max !== null) $list = array_filter($list, fn($p) => price_of($p) !== null && price_of($p) <= $max);
if (($Q['availability'] ?? '') === 'in_stock') $list = array_filter($list, fn($p) => buyable($p)['ok']);
if (($Q['availability'] ?? '') === 'on_request') $list = array_filter($list, fn($p) => $p['mode'] === 'QUOTE_ONLY' || price_of($p) === null);
$list = array_values($list);
$byPrice = fn($dir) => fn($x, $y) => ((price_of($x) === null) <=> (price_of($y) === null)) ?: $dir * ((price_of($x) ?? 0) <=> (price_of($y) ?? 0));
$sorts = ['price_asc' => $byPrice(1), 'price_desc' => $byPrice(-1), 'newest' => fn($x, $y) => strcmp($y['created'], $x['created']), 'popular' => fn($x, $y) => ($y['sales'] <=> $x['sales']) ?: ($y['views'] <=> $x['views'])];
usort($list, $sorts[$Q['sort'] ?? ''] ?? fn($x, $y) => ($y['featured'] <=> $x['featured']) ?: strcmp($y['created'], $x['created']));

$total = count($list);
$pages = max(1, (int)ceil($total / $PER));
$page = min($pages, max(1, (int)($Q['page'] ?? 1)));
$items = array_slice($list, ($page - 1) * $PER, $PER);
$cats = array_values(array_filter(categories(), fn($c) => !$kind || $c['kind'] === $kind));
$brandsAll = db()->query("SELECT DISTINCT brand, kind FROM products WHERE status = 'PUBLISHED' AND brand <> '' ORDER BY brand")->fetchAll();
$brands = array_values(array_unique(array_map(fn($r) => $r['brand'], array_filter($brandsAll, fn($r) => !$kind || $r['kind'] === $kind))));
$active = count(array_filter(['q', 'category', 'brand', 'condition', 'min', 'max', 'availability'], fn($k) => !empty($Q[$k])));
$catName = !empty($Q['category']) ? ($catNames[$Q['category']] ?? null) : null;
$h1 = $catName ?: $title;
$catDesc = $catName ? (cat_by_slug($Q['category'])['description'] ?? '') : '';

ob_start(); ?>
<form action="<?= h(url($base)) ?>">
  <?= field('Keyword', 'f-q', '<input class="in" id="f-q" name="q" type="search" value="' . h($Q['q'] ?? '') . '" placeholder="Model, SKU, brand…">') ?>
  <?= field('Category', 'f-cat', sel('category', array_merge([['', 'All categories']], array_map(fn($c) => [$c['slug'], $c['name'] . ' (' . pub_count($c['slug']) . ')'], $cats)), $Q['category'] ?? '', '', 'f-cat')) ?>
  <?php if ($brands): ?><?= field('Manufacturer', 'f-brand', sel('brand', array_merge([['', 'All manufacturers']], array_map(fn($b) => [slugify($b), $b], $brands)), $Q['brand'] ?? '', '', 'f-brand')) ?><?php endif; ?>
  <?php if ($kind !== 'SUPPLY'): ?><fieldset class="f" style="border:0;padding:0;margin:0 0 1rem"><legend class="l">Condition</legend>
    <?php foreach (['' => 'Any', 'NEW' => 'New', 'USED' => 'Used', 'REFURBISHED' => 'Refurbished'] as $v => $l): ?><label class="chk" style="margin-bottom:.35rem"><input type="radio" name="condition" value="<?= $v ?>" <?= ($Q['condition'] ?? '') === $v ? 'checked' : '' ?>> <?= $l ?></label><?php endforeach; ?></fieldset><?php endif; ?>
  <div class="f"><span class="l">Price (₦)</span><div class="row" style="flex-wrap:nowrap"><input class="in" name="min" type="number" min="0" placeholder="Min" value="<?= h($Q['min'] ?? '') ?>" aria-label="Minimum price" inputmode="numeric"><span class="muted">to</span><input class="in" name="max" type="number" min="0" placeholder="Max" value="<?= h($Q['max'] ?? '') ?>" aria-label="Maximum price" inputmode="numeric"></div></div>
  <?= field('Availability', 'f-av', sel('availability', [['', 'Everything'], ['in_stock', 'In stock, buy online'], ['on_request', 'Price on request']], $Q['availability'] ?? '', '', 'f-av')) ?>
  <input type="hidden" name="sort" value="<?= h($Q['sort'] ?? '') ?>">
  <div class="row"><button class="btn dark grow">Apply filters</button><?php if ($active): ?><a class="link" href="<?= h(url($base)) ?>">Clear</a><?php endif; ?></div>
</form>
<?php $filters = ob_get_clean();

page_open($h1, ['canonical' => $base . ($catName ? '?category=' . $Q['category'] : ''), 'desc' => $catDesc ?: $intro, 'noindex' => $a === 'search']);
?>
<div class="page py"><?= crumbs(array_merge([['Home', '/'], [$title, $base]], $catName ? [[$catName]] : [])) ?>
  <h1 class="mt4"><?= h($h1) ?></h1><p class="muted mt2" style="max-width:42rem"><?= h($catDesc ?: $intro) ?></p>
  <div class="split side mt8">
    <aside aria-label="Filters"><button class="btn out burger" type="button" data-open="filters" style="display:none">Filters<?= $active ? ' <span class="badge gold">' . $active . '</span>' : '' ?></button><div class="filters-desk"><?= $filters ?></div></aside>
    <section>
      <div class="row between" style="border-bottom:1px solid var(--steel);padding-bottom:.75rem;margin-bottom:1rem">
        <p class="sm muted tab"><?= $total ? $total . ' listing' . ($total === 1 ? '' : 's') : 'No matching listings' ?><?= !empty($Q['q']) ? ' for “' . h($Q['q']) . '”' : '' ?></p>
        <label class="row sm" style="gap:.5rem">Sort <select class="in" style="width:auto;min-height:38px" data-nav>
          <?php foreach (['' => 'Most relevant', 'newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'popular' => 'Most popular'] as $v => $l): ?><option value="<?= h(url($base . qsx($Q, ['sort' => $v, 'page' => '']))) ?>" <?= ($Q['sort'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select></label>
      </div>
      <?php if ($items): ?><div class="grid g3"><?php foreach ($items as $p) echo card($p); ?></div>
      <?php else: echo empty_state('Nothing matches those filters', 'We may still be able to source it. Try fewer filters, or send a quotation request.', '<a class="btn out" href="' . h(url($base)) . '">Clear filters</a> <a class="btn" href="' . h(url('/request-quote' . (!empty($Q['q']) ? '?subject=' . urlencode($Q['q']) : ''))) . '">Ask us to source it</a>'); endif; ?>
      <?php if ($pages > 1): ?><nav class="row between mt8" aria-label="Pagination"><?= $page > 1 ? '<a class="btn out" href="' . h(url($base . qsx($Q, ['page' => $page - 1]))) . '">Previous</a>' : '<span></span>' ?><span class="sm muted tab">Page <?= $page ?> of <?= $pages ?></span><?= $page < $pages ? '<a class="btn out" href="' . h(url($base . qsx($Q, ['page' => $page + 1]))) . '">Next</a>' : '<span></span>' ?></nav><?php endif; ?>
    </section>
  </div>
</div>
<div class="drawer" id="filters" role="dialog" aria-label="Filters" hidden><div style="padding:1.25rem"><div class="row between"><b style="font-family:var(--disp);font-size:1.1rem">Filters</b><button class="ib" type="button" data-close aria-label="Close filters"><?= ICON['x'] ?></button></div><div class="mt4"><?= str_replace(['id="f-', 'for="f-'], ['id="m-', 'for="m-'], $filters) ?></div></div></div>
<?php page_close();
