<?php
/** @var string $pageTitle  @var string $pageDesc  @var string $bodyClass */
$pageTitle = $pageTitle ?? '';
$siteName  = setting('site_name');
$fullTitle = $pageTitle ? $pageTitle . ' | ' . $siteName : $siteName . ' — ' . setting('tagline');
$pageDesc  = $pageDesc ?? setting('meta_description');
$cartCount = cart_count();
$cust      = customer();
$cats      = categories();
$current   = basename($_SERVER['SCRIPT_NAME'] ?? '');
$primary   = preg_match('/^#[0-9a-f]{6}$/i', setting('color_primary')) ? setting('color_primary') : '#0B6E4F';
$accent    = preg_match('/^#[0-9a-f]{6}$/i', setting('color_accent')) ? setting('color_accent') : '#F59E0B';
$logo      = setting('logo');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta name="theme-color" content="<?= e($primary) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(asset(setting('favicon'))) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= url('assets/vendor/jakarta/jakarta.css') ?>">
<link rel="stylesheet" href="<?= url('assets/vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=3">
<style>:root{--primary:<?= e($primary) ?>;--accent:<?= e($accent) ?>}</style>
</head>
<body class="<?= e($bodyClass ?? '') ?>">

<?php if (setting('topbar_text')): ?>
<div class="topbar">
  <div class="container topbar-in">
    <span><i class="fa-solid fa-truck-fast"></i> <?= e(setting('topbar_text')) ?></span>
    <span class="topbar-right hide-sm">
      <a href="tel:<?= e(preg_replace('/\s+/', '', setting('phone'))) ?>"><i class="fa-solid fa-phone"></i> <?= e(setting('phone')) ?></a>
      <a href="<?= url('track.php') ?>"><i class="fa-solid fa-location-dot"></i> Track order</a>
    </span>
  </div>
</div>
<?php endif; ?>

<header class="header" id="header">
  <div class="container header-in">
    <button class="icon-btn menu-btn" type="button" data-open="drawer" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
    <a class="logo" href="<?= url('index.php') ?>">
      <?php if ($logo): ?><img src="<?= e(asset($logo)) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?><span class="logo-mark"><?= e(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $siteName), 0, 3))) ?></span><span class="logo-text"><?= e($siteName) ?></span><?php endif; ?>
    </a>
    <form class="search" action="<?= url('shop.php') ?>" method="get" role="search">
      <input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Search products, brands…" aria-label="Search products">
      <button type="submit" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
    <div class="header-actions">
      <a class="icon-btn hide-sm" href="<?= url($cust ? 'account.php' : 'login.php') ?>" aria-label="Account"><i class="fa-regular fa-user"></i><span class="hide-md"><?= $cust ? e(explode(' ', $cust['name'])[0]) : 'Account' ?></span></a>
      <a class="icon-btn cart-btn" href="<?= url('cart.php') ?>" aria-label="Cart"><i class="fa-solid fa-cart-shopping"></i><span class="cart-count js-cart-count" <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span><span class="hide-md">Cart</span></a>
    </div>
  </div>
  <nav class="catnav hide-sm" aria-label="Categories">
    <div class="container catnav-in">
      <a href="<?= url('shop.php') ?>" class="<?= $current === 'shop.php' && !get('cat') ? 'active' : '' ?>"><i class="fa-solid fa-grip"></i> All products</a>
      <?php foreach ($cats as $c): ?>
        <a href="<?= url('shop.php?cat=' . urlencode($c['slug'])) ?>" class="<?= get('cat') === $c['slug'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= url('shop.php?deals=1') ?>" class="deal-link"><i class="fa-solid fa-fire"></i> Deals</a>
    </div>
  </nav>
</header>

<!-- mobile drawer -->
<div class="overlay" data-close="drawer"></div>
<aside class="drawer" id="drawer" aria-label="Menu">
  <div class="drawer-head">
    <strong><?= e($siteName) ?></strong>
    <button class="icon-btn" type="button" data-close="drawer" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <div class="drawer-user">
    <?php if ($cust): ?>
      <i class="fa-solid fa-circle-user"></i> <div><small>Signed in as</small><strong><?= e($cust['name']) ?></strong></div>
    <?php else: ?>
      <a class="btn btn-primary btn-sm" href="<?= url('login.php') ?>">Log in</a> <a class="btn btn-outline btn-sm" href="<?= url('register.php') ?>">Create account</a>
    <?php endif; ?>
  </div>
  <nav class="drawer-nav">
    <a href="<?= url('index.php') ?>"><i class="fa-solid fa-house"></i> Home</a>
    <a href="<?= url('shop.php') ?>"><i class="fa-solid fa-store"></i> Shop all</a>
    <a href="<?= url('shop.php?deals=1') ?>"><i class="fa-solid fa-fire"></i> Deals</a>
    <div class="drawer-label">Categories</div>
    <?php foreach ($cats as $c): ?>
      <a href="<?= url('shop.php?cat=' . urlencode($c['slug'])) ?>"><i class="fa-solid <?= e($c['icon'] ?: 'fa-tag') ?>"></i> <?= e($c['name']) ?></a>
    <?php endforeach; ?>
    <div class="drawer-label">Help</div>
    <a href="<?= url('track.php') ?>"><i class="fa-solid fa-location-dot"></i> Track order</a>
    <?php if ($cust): ?><a href="<?= url('account.php') ?>"><i class="fa-regular fa-user"></i> My account</a><?php endif; ?>
    <a href="<?= url('page.php?slug=about') ?>"><i class="fa-solid fa-circle-info"></i> About us</a>
    <a href="<?= url('contact.php') ?>"><i class="fa-solid fa-envelope"></i> Contact us</a>
    <a href="<?= e(wa_link('Hello ' . $siteName . ', I need help with an order.')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Chat on WhatsApp</a>
    <?php if ($cust): ?><a href="<?= url('logout.php') ?>"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</a><?php endif; ?>
  </nav>
</aside>

<main class="main">
<div class="container flash-wrap"><?= flashes() ?></div>
