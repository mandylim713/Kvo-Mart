<?php
$me = admin();
$cnt = admin_counts();
$cur = basename($_SERVER['SCRIPT_NAME']);
$primary = preg_match('/^#[0-9a-f]{6}$/i', setting('color_primary')) ? setting('color_primary') : '#0B6E4F';
$nav = [
    ['index.php', 'fa-gauge-high', 'Dashboard', 0, ''],
    ['orders.php', 'fa-receipt', 'Orders', $cnt['orders'], 'order-view.php'],
    ['products.php', 'fa-box', 'Products', 0, 'product-edit.php'],
    ['stock.php', 'fa-warehouse', 'Inventory', $cnt['lowstock'], ''],
    ['categories.php', 'fa-tags', 'Categories', 0, ''],
    ['customers.php', 'fa-users', 'Customers', 0, ''],
    ['messages.php', 'fa-envelope', 'Messages', $cnt['messages'], ''],
    ['reviews.php', 'fa-star', 'Reviews', $cnt['reviews'], ''],
    ['slides.php', 'fa-images', 'Homepage slides', 0, ''],
    ['pages.php', 'fa-file-lines', 'Pages', 0, 'page-edit.php'],
    ['zones.php', 'fa-truck', 'Delivery zones', 0, ''],
    ['subscribers.php', 'fa-paper-plane', 'Subscribers', 0, ''],
];
if (is_super()) {
    $nav[] = ['settings.php', 'fa-gear', 'Settings', 0, ''];
    $nav[] = ['users.php', 'fa-user-shield', 'Admin users', 0, ''];
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0f172a">
<title><?= e(($adminTitle ?? 'Admin') . ' · ' . setting('site_name')) ?></title>
<link rel="stylesheet" href="<?= url('assets/vendor/jakarta/jakarta.css') ?>">
<link rel="stylesheet" href="<?= url('assets/vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>?v=3">
<style>:root{--primary:<?= e($primary) ?>}</style>
</head>
<body>
<div class="a-overlay" data-a-close></div>
<aside class="a-side" id="aside">
  <div class="a-brand">
    <span class="a-mark"><?= e(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', setting('site_name')), 0, 3))) ?></span>
    <div><strong><?= e(setting('site_name')) ?></strong><small>Admin dashboard</small></div>
  </div>
  <nav class="a-nav">
    <?php foreach ($nav as [$file, $ic, $label, $badge, $alt]): ?>
      <a href="<?= url('admin/' . $file) ?>" class="<?= $cur === $file || ($alt && $cur === $alt) ? 'active' : '' ?>"><i class="fa-solid <?= $ic ?>"></i><span><?= e($label) ?></span><?php if ($badge): ?><b><?= $badge ?></b><?php endif; ?></a>
    <?php endforeach; ?>
    <a href="<?= url('index.php') ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>View website</span></a>
  </nav>
</aside>

<div class="a-main">
  <header class="a-top">
    <button class="a-icon a-menu" type="button" data-a-open aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    <h1><?= e($adminTitle ?? 'Dashboard') ?></h1>
    <div class="a-top-r">
      <a class="a-icon" href="<?= url('admin/orders.php?status=pending') ?>" aria-label="Pending orders"><i class="fa-regular fa-bell"></i><?php if ($cnt['orders']): ?><b><?= $cnt['orders'] ?></b><?php endif; ?></a>
      <details class="a-user">
        <summary><span class="a-avatar"><?= e(strtoupper(substr($me['name'], 0, 1))) ?></span><span class="a-hide-sm"><?= e($me['name']) ?></span></summary>
        <div class="a-dd">
          <div class="muted" style="padding:8px 12px;font-size:12px"><?= e($me['email']) ?> · <?= e(ucfirst($me['role'])) ?></div>
          <a href="<?= url('admin/profile.php') ?>"><i class="fa-solid fa-key"></i> My password</a>
          <a href="<?= url('admin/logout.php') ?>"><i class="fa-solid fa-arrow-right-from-bracket"></i> Log out</a>
        </div>
      </details>
    </div>
  </header>
  <div class="a-content">
    <?= flashes() ?>
