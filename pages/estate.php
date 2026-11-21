<?php
$fmtPrice = fn($p, $long = false) => $p['price'] !== null ? naira($p['price']) . ($p['listing'] === 'Lease' ? ($long ? ' / year' : ' / yr') : '') : 'Price on request';
$ph = fn($p) => $p['image'] ? '<img src="' . h(asset($p['image'])) . '" alt="' . h($p['title']) . '" loading="lazy">' : '<div class="nophoto">' . ICON['nophoto'] . '<span>Photos on request</span></div>';
if ($slug) {
    $s = db()->prepare('SELECT * FROM properties WHERE slug = ? AND active = 1'); $s->execute([$slug]);
    $p = $s->fetch(); if (!$p) not_found();
    $_SESSION['form_for'] = 'enquiry';
    page_open($p['title'], ['desc' => mb_strimwidth($p['description'], 0, 158, '…'), 'image' => $p['image'], 'canonical' => '/real-estate/' . $p['slug']]);
    ?>
    <div class="page py"><?= crumbs([['Home', '/'], ['Real estate', '/real-estate'], [$p['title']]]) ?><div class="split mt6">
      <div><div class="gallery prop-ph"><?= $ph($p) ?></div><p class="mt6" style="white-space:pre-line"><?= h($p['description']) ?></p></div>
      <div><h1><?= h($p['title']) ?></h1><p class="price mt4" style="font-size:1.8rem"><?= $fmtPrice($p, true) ?></p>
        <div class="plate mt6" style="padding:0 1rem .5rem"><?= pl([['Type', h($p['type'])], ['Listing', h($p['listing'])], ['Location', h($p['location']) . ', ' . h($p['state'])], ['Size', $p['size'] ? number_format($p['size']) . ' m²' : ''], ['Bedrooms', $p['beds'] ? (int)$p['beds'] : ''], ['Features', h(implode(', ', json_decode((string)$p['features'], true) ?: []))]]) ?></div>
        <div class="box mt6"><h2 style="font-size:1.1rem">Book a viewing or enquire</h2><div class="mt4"><?= enquiry_form('PROPERTY', ['property' => $p, 'compact' => true]) ?></div></div></div></div></div>
    <?php page_close(); return;
}
$list = db()->query('SELECT * FROM properties WHERE active = 1 ORDER BY sort_order, id DESC')->fetchAll();
page_open('Real estate', ['canonical' => '/real-estate', 'desc' => 'Land, warehouses and commercial property for sale or lease from KVO Mart Ideal Services Ltd.']);
?>
<div class="page py"><?= crumbs([['Home', '/'], ['Real estate']]) ?><h1 class="mt4">Real estate</h1><p class="muted mt2">Land, warehouses and commercial property. Property is sold or leased by enquiry.</p>
<?php if ($list): ?><div class="grid g3 mt8"><?php foreach ($list as $p): ?>
  <article class="card"><a class="ph" href="<?= h(url('/real-estate/' . $p['slug'])) ?>" tabindex="-1"><?= $ph($p) ?><span class="tagl"><?= h($p['listing']) ?></span></a>
    <div class="bd plate"><p class="xs muted"><?= h($p['type']) ?> · <?= h($p['location']) ?>, <?= h($p['state']) ?></p><h3><a href="<?= h(url('/real-estate/' . $p['slug'])) ?>"><?= h($p['title']) ?></a></h3><p class="price mt3"><?= $fmtPrice($p) ?></p>
      <div class="act"><a class="btn out sm grow" href="<?= h(url('/real-estate/' . $p['slug'])) ?>">View details</a></div></div></article>
<?php endforeach; ?></div>
<?php else: ?><div class="mt8"><?= empty_state('No listings right now', 'Tell us what you are looking for and we will let you know when something suitable comes up.', '<a class="btn" href="' . h(url('/contact?subject=' . urlencode('Property enquiry'))) . '">Send an enquiry</a>') ?></div><?php endif; ?>
</div>
<?php page_close();
