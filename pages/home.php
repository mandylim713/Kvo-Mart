<?php
$pdo = db();
$live = $pdo->query("SELECT * FROM products WHERE status = 'PUBLISHED'")->fetchAll();
$featured = array_slice(array_values(array_filter($live, fn($p) => $p['featured'])), 0, 4);
$arrivals = array_values(array_filter($live, fn($p) => avail($p) > 0 && $p['kind'] === 'EQUIPMENT'));
usort($arrivals, fn($a, $b) => strcmp($b['created'], $a['created']));
$arrivals = array_slice($arrivals, 0, 4);
$eqCats = array_values(array_filter(categories(), fn($c) => $c['kind'] === 'EQUIPMENT'));
$_SESSION['form_for'] = $_SESSION['form_for'] ?? '';

page_open('', ['canonical' => '/']);
?>
<section class="hero"><div class="page hero-grid">
  <div><h1>Powering Projects. Supplying Progress.</h1>
    <p class="lead">KVO Mart supplies tractors, construction machinery, industrial equipment and commercial supplies — priced in Naira, with delivery arranged to your site.</p>
    <form class="hs" action="<?= h(url('/search')) ?>" role="search"><input name="q" type="search" placeholder="Excavator, 90hp tractor, oil filter…" aria-label="Search equipment"><button><?= ICON['search'] ?> Search</button></form>
    <div class="row mt6"><a class="btn" href="<?= h(url('/equipment')) ?>">Explore equipment</a><a class="btn out" style="border-color:#fff;color:#fff" href="<?= h(url('/request-quote')) ?>">Request a quote</a></div></div>
  <div class="browse"><h2 style="color:#fff;font-size:1rem">Browse by machine type</h2><div class="mt3">
    <?php foreach (array_slice($eqCats, 0, 9) as $c): ?><a href="<?= h(cat_link($c)) ?>"><span><?= h($c['name']) ?></span><span class="tab sm" style="color:var(--steel400)"><?= pub_count($c['slug']) ?: 'On request' ?></span></a><?php endforeach; ?>
  </div><a class="sm b mt3" style="display:inline-block;color:var(--gold3)" href="<?= h(url('/equipment')) ?>">All <?= count($live) ?> listings</a></div>
</div></section>

<?php if ($featured): ?>
<section class="page" style="padding-top:4rem;padding-bottom:4rem"><div class="sh"><div><h2>Featured equipment</h2><p class="muted mt2">Machines our team is highlighting right now.</p></div><a class="link" href="<?= h(url('/equipment')) ?>">See all equipment</a></div>
  <div class="cards-scroll"><?php foreach ($featured as $p) echo card($p); ?></div></section>
<?php endif; ?>

<section style="background:var(--steel50);padding:4rem 0"><div class="page"><div class="sh"><h2>Shop by category</h2></div><div class="cats">
  <?php foreach (categories() as $c): $n = pub_count($c['slug']); ?><a href="<?= h(cat_link($c)) ?>"><span class="b" style="color:var(--ink)"><?= h($c['name']) ?></span><span class="xs muted tab mt2"><?= $n ? $n . ' listed' : 'Ask for availability' ?></span></a><?php endforeach; ?>
</div></div></section>

<?php if ($arrivals): ?>
<section class="page" style="padding-top:4rem;padding-bottom:4rem"><div class="sh"><h2>New arrivals in stock</h2><a class="link" href="<?= h(url('/equipment?sort=newest&availability=in_stock')) ?>">All in-stock items</a></div>
  <div class="cards-scroll"><?php foreach ($arrivals as $p) echo card($p); ?></div></section>
<?php endif; ?>

<section style="border-top:1px solid var(--steel);border-bottom:1px solid var(--steel)"><div class="page grid g3" style="padding-top:4rem;padding-bottom:4rem">
  <div><?= ICON['truck'] ?><h2 class="mt3" style="font-size:1.2rem">How delivery works</h2><p class="muted mt2">Parts and supplies ship at a fixed rate where we cover your area. Heavy machinery is quoted for your approval based on size, weight, loading and route. You can also collect.</p><a class="link mt3" style="display:inline-block" href="<?= h(url('/delivery-policy')) ?>">Read the delivery policy</a></div>
  <div><?= ICON['doc'] ?><h2 class="mt3" style="font-size:1.2rem">Equipment financing enquiries</h2><p class="muted mt2">Planning a fleet purchase or staged payments? Tell us about the project. Enquiring doesn't commit you, and any financing is subject to separate approval.</p><a class="link mt3" style="display:inline-block" href="<?= h(url('/request-quote?type=FINANCING')) ?>">Make a financing enquiry</a></div>
  <div><?= ICON['shield'] ?><h2 class="mt3" style="font-size:1.2rem">Why buy from KVO Mart</h2><ul class="muted mt2" style="padding-left:1.1rem;margin-bottom:0"><li>Clear Naira pricing, or a written quotation.</li><li>Payments verified before your order is confirmed.</li><li>Order and delivery status you can track online.</li></ul></div>
</div></section>

<section class="page" style="padding-top:4rem;padding-bottom:4rem"><div class="sh"><h2>What we do</h2></div><div class="cats" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
  <?php foreach ([['Equipment sales and sourcing', 'New, used and refurbished machinery, or sourced to your specification.', '/equipment'], ['Parts and supplies', 'Spare parts, implements, construction materials and consumables.', '/spare-parts'], ['Real estate', 'Property listings and enquiries.', '/real-estate'], ['Contracts and procurement', 'Supply contracts and procurement for projects.', '/services']] as [$t, $bdy, $l]): ?>
    <a href="<?= h(url($l)) ?>"><span class="b" style="color:var(--ink);font-size:1.05rem"><?= h($t) ?></span><span class="sm muted mt2"><?= h($bdy) ?></span></a>
  <?php endforeach; ?>
</div></section>

<section style="background:var(--ink);padding:4rem 0" id="quote"><div class="page split"><div><h2 style="color:#fff;font-size:1.9rem;font-stretch:120%">Need a price for a specific machine?</h2><p class="mt4" style="color:var(--steel300)">Tell us the equipment, quantity and delivery location. Our sales team prepares a written quotation with pricing, delivery and validity terms.</p></div>
  <div class="box"><?= enquiry_form('QUOTE', ['compact' => true]) ?></div></div></section>

<section class="page" style="padding-top:3rem"><div class="box row between"><div><h2 style="font-size:1.2rem">Stock updates by email</h2><p class="sm muted">Occasional emails when new machines and parts arrive.</p></div>
  <form method="post" action="<?= h(url('/newsletter')) ?>" class="row" style="gap:0;min-width:min(100%,380px)"><?= csrf_field() ?><input type="hidden" name="back" value="/"><input class="in grow" name="email" type="email" required placeholder="you@company.com" aria-label="Email address" style="width:auto"><button class="btn dark">Subscribe</button></form></div></section>
<?php page_close();
