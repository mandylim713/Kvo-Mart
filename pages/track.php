<?php
if (is_post() && ($ROUTE_PATH ?? '') === '/track') {
    require_csrf();
    if (!throttle('track', 12, 600)) fail([], '/track', 'Too many attempts. Wait a few minutes and try again.');
    $o = order_by_ref(post('ref'));
    if (!$o || strtolower($o['email']) !== strtolower(post('email'))) fail([], '/track', "We couldn't find an order with that number and email.");
    $_SESSION['order_access'][$o['ref']] = 1;
    redirect('/orders/' . $o['ref']);
}
page_open('Track an order', ['noindex' => !empty($prefill)]);
?>
<div class="page py" style="max-width:36rem"><h1>Track an order</h1><p class="muted mt2"><?= h($trackMsg ?? 'Enter your order number and the email used at checkout.') ?></p>
  <form method="post" action="<?= h(url('/track')) ?>" class="box mt6" novalidate><?= csrf_field() ?>
    <?= field('Order number', 't-ref', '<input class="in" id="t-ref" name="ref" value="' . h($prefill ?? old('ref')) . '" placeholder="KVO-XXXXXXXX" autocapitalize="characters" required>') ?>
    <?= field('Email', 't-email', '<input class="in" id="t-email" name="email" type="email" value="' . h(old('email')) . '" required>') ?>
    <?= form_msg() ?><div class="mt4"><button class="btn">Find order</button></div></form>
  <?php if (!current_user()): ?><p class="sm mt4">Have an account? <a class="link" href="<?= h(url('/account/login')) ?>">Sign in</a> to see all your orders.</p><?php endif; ?>
</div>
<?php page_close();
