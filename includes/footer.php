<?php
$footerPages = db()->query('SELECT title, slug FROM pages WHERE is_active = 1 AND in_footer = 1 ORDER BY id')->fetchAll();
$socials = ['facebook' => 'fa-facebook-f', 'instagram' => 'fa-instagram', 'twitter' => 'fa-x-twitter', 'tiktok' => 'fa-tiktok'];
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
</main>

<section class="newsletter">
  <div class="container newsletter-in">
    <div>
      <h3>Get deals before everyone else</h3>
      <p>Join our list for weekly offers and new arrivals. No spam.</p>
    </div>
    <form method="post" action="<?= url('subscribe.php') ?>" class="newsletter-form">
      <?= csrf_field() ?>
      <input type="email" name="email" required placeholder="Your email address" aria-label="Email address">
      <button class="btn btn-accent" type="submit">Subscribe</button>
    </form>
  </div>
</section>

<footer class="footer">
  <div class="container footer-grid">
    <div class="footer-about">
      <a class="logo logo-light" href="<?= url('index.php') ?>">
        <?php if (setting('logo')): ?><img src="<?= e(asset(setting('logo'))) ?>" alt="<?= e(setting('site_name')) ?>">
        <?php else: ?><span class="logo-mark"><?= e(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', setting('site_name')), 0, 3))) ?></span><span class="logo-text"><?= e(setting('site_name')) ?></span><?php endif; ?>
      </a>
      <p><?= e(setting('about_short')) ?></p>
      <div class="socials">
        <?php foreach ($socials as $k => $ic): if (setting($k)): ?>
          <a href="<?= e(setting($k)) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($k)) ?>"><i class="fa-brands <?= $ic ?>"></i></a>
        <?php endif; endforeach; ?>
        <a href="<?= e(wa_link()) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
      </div>
    </div>
    <details class="footer-col" open>
      <summary>Shop</summary>
      <a href="<?= url('shop.php') ?>">All products</a>
      <?php foreach (array_slice(categories(), 0, 6) as $c): ?><a href="<?= url('shop.php?cat=' . urlencode($c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
    </details>
    <details class="footer-col" open>
      <summary>Help</summary>
      <a href="<?= url('track.php') ?>">Track your order</a>
      <a href="<?= url('contact.php') ?>">Contact us</a>
      <?php foreach ($footerPages as $p): ?><a href="<?= url('page.php?slug=' . urlencode($p['slug'])) ?>"><?= e($p['title']) ?></a><?php endforeach; ?>
    </details>
    <div class="footer-col footer-contact">
      <h4>Contact</h4>
      <p><i class="fa-solid fa-location-dot"></i> <?= e(setting('address')) ?></p>
      <p><i class="fa-solid fa-phone"></i> <a href="tel:<?= e(preg_replace('/\s+/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></p>
      <p><i class="fa-solid fa-envelope"></i> <a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></p>
      <p><i class="fa-regular fa-clock"></i> <?= e(setting('opening_hours')) ?></p>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container footer-bottom-in">
      <span>&copy; <?= date('Y') ?> <?= e(setting('company_name')) ?>. All rights reserved.</span>
      <span class="pay-icons"><i class="fa-brands fa-cc-visa"></i><i class="fa-brands fa-cc-mastercard"></i><i class="fa-solid fa-building-columns"></i><i class="fa-solid fa-money-bill-wave"></i></span>
      <?php if (setting('show_powered_by', '1') === '1'): ?><span>Powered by <a href="https://digitalweboracleict.com" target="_blank" rel="noopener">DWO</a></span><?php endif; ?>
    </div>
  </div>
</footer>

<!-- floating WhatsApp (desktop) -->
<a class="wa-float" href="<?= e(wa_link('Hello ' . setting('site_name') . ', I have an enquiry.')) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>

<!-- mobile bottom tab bar -->
<nav class="tabbar" aria-label="Quick navigation">
  <a href="<?= url('index.php') ?>" class="<?= $current === 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i><span>Home</span></a>
  <button type="button" data-open="catsheet" class="<?= $current === 'shop.php' ? 'active' : '' ?>"><i class="fa-solid fa-grip"></i><span>Categories</span></button>
  <a href="<?= url('cart.php') ?>" class="tab-cart <?= $current === 'cart.php' ? 'active' : '' ?>"><span class="tab-ico"><i class="fa-solid fa-cart-shopping"></i><b class="js-cart-count" <?= cart_count() ? '' : 'hidden' ?>><?= cart_count() ?></b></span><span>Cart</span></a>
  <a href="<?= url(customer() ? 'account.php' : 'login.php') ?>" class="<?= in_array($current, ['account.php', 'login.php', 'register.php'], true) ? 'active' : '' ?>"><i class="fa-regular fa-user"></i><span>Account</span></a>
  <a href="<?= e(wa_link('Hello ' . setting('site_name') . ', I have an enquiry.')) ?>" target="_blank" rel="noopener" class="tab-wa"><i class="fa-brands fa-whatsapp"></i><span>Chat</span></a>
</nav>

<!-- mobile category bottom sheet -->
<div class="overlay" data-close="catsheet"></div>
<div class="sheet" id="catsheet" role="dialog" aria-label="Categories">
  <div class="sheet-grip"></div>
  <div class="sheet-head"><strong>Shop by category</strong><button class="icon-btn" type="button" data-close="catsheet" aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
  <div class="sheet-grid">
    <a href="<?= url('shop.php') ?>"><span><i class="fa-solid fa-store"></i></span>All products</a>
    <?php foreach (categories() as $c): ?>
      <a href="<?= url('shop.php?cat=' . urlencode($c['slug'])) ?>"><span><i class="fa-solid <?= e($c['icon'] ?: 'fa-tag') ?>"></i></span><?= e($c['name']) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('shop.php?deals=1') ?>"><span class="deal"><i class="fa-solid fa-fire"></i></span>Deals</a>
  </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>window.KVO = {cartUrl: <?= json_encode(url('cart.php')) ?>};</script>
<script src="<?= url('assets/js/app.js') ?>?v=3"></script>
</body>
</html>
