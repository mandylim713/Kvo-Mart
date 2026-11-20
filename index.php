<?php
require __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$slides   = $pdo->query('SELECT * FROM slides WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$featured = $pdo->query('SELECT * FROM products WHERE is_active = 1 AND is_featured = 1 ORDER BY updated_at DESC LIMIT 10')->fetchAll();
$deals    = $pdo->query('SELECT * FROM products WHERE is_active = 1 AND (is_deal = 1 OR old_price > price) ORDER BY is_deal DESC, (old_price - price) DESC LIMIT 10')->fetchAll();
$newest   = $pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 10')->fetchAll();
$best     = $pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY sold DESC, views DESC LIMIT 10')->fetchAll();
$cats     = categories();

$bodyClass = 'home';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <?php if ($slides): ?>
    <div class="slider">
      <div class="slides">
        <?php foreach ($slides as $i => $s): ?>
        <div class="slide">
          <?php if ($s['image']): ?><img src="<?= e(asset($s['image'])) ?>" alt="" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>><?php endif; ?>
          <div class="slide-body">
            <h2><?= e($s['title']) ?></h2>
            <?php if ($s['subtitle']): ?><p><?= e($s['subtitle']) ?></p><?php endif; ?>
            <?php if ($s['button_text']): ?><a class="btn btn-accent" href="<?= e(preg_match('~^https?://~', $s['button_link']) ? $s['button_link'] : url($s['button_link'])) ?>"><?= e($s['button_text']) ?> <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($slides) > 1): ?>
      <div class="dots"><?php foreach ($slides as $i => $s): ?><button type="button" class="<?= $i ? '' : 'active' ?>" aria-label="Slide <?= $i + 1 ?>"></button><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="perks">
      <div class="perk"><i class="fa-solid fa-truck-fast"></i><div><strong>Fast delivery</strong>To your doorstep</div></div>
      <div class="perk"><i class="fa-solid fa-shield-halved"></i><div><strong>100% genuine</strong>Trusted brands</div></div>
      <div class="perk"><i class="fa-solid fa-lock"></i><div><strong>Secure payment</strong>Card, transfer, POD</div></div>
      <div class="perk"><i class="fa-brands fa-whatsapp"></i><div><strong>Real support</strong>Chat with us anytime</div></div>
    </div>
  </div>
</section>

<?php if ($cats): ?>
<section class="section">
  <div class="container">
    <div class="section-head"><h2>Shop by category</h2><a href="<?= url('shop.php') ?>">View all</a></div>
    <div class="cat-scroll">
      <?php foreach ($cats as $c): ?>
      <a class="cat-tile" href="<?= url('shop.php?cat=' . urlencode($c['slug'])) ?>">
        <span class="ci"><i class="fa-solid <?= e($c['icon'] ?: 'fa-tag') ?>"></i></span><?= e($c['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($deals): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head"><h2><i class="fa-solid fa-fire" style="color:#ea580c"></i> Hot deals</h2><a href="<?= url('shop.php?deals=1') ?>">See all</a></div>
    <div class="hscroll"><?php foreach ($deals as $p) echo product_card($p); ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($featured): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head"><h2>Featured products</h2><a href="<?= url('shop.php?featured=1') ?>">See all</a></div>
    <div class="grid grid-5"><?php foreach ($featured as $p) echo product_card($p); ?></div>
  </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0">
  <div class="container promo">
    <div class="promo-card" style="background:linear-gradient(135deg,var(--primary),#064e3b)">
      <h3>Buying in bulk?</h3>
      <p>Get special prices for offices, events, shops and wholesale orders.</p>
      <a class="btn btn-accent" href="<?= e(wa_link('Hello, I would like a bulk/wholesale quote.')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Request a quote</a>
    </div>
    <div class="promo-card" style="background:linear-gradient(135deg,#1e293b,#334155)">
      <h3>Track your order</h3>
      <p>Know exactly where your order is — from confirmation to delivery.</p>
      <a class="btn btn-outline" href="<?= url('track.php') ?>"><i class="fa-solid fa-location-dot"></i> Track now</a>
    </div>
  </div>
</section>

<?php if ($newest): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head"><h2>New arrivals</h2><a href="<?= url('shop.php?sort=new') ?>">See all</a></div>
    <div class="hscroll"><?php foreach ($newest as $p) echo product_card($p); ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($best): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head"><h2>Popular right now</h2><a href="<?= url('shop.php?sort=popular') ?>">See all</a></div>
    <div class="grid grid-5"><?php foreach (array_slice($best, 0, 10) as $p) echo product_card($p); ?></div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
