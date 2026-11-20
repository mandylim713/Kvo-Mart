<?php
require __DIR__ . '/includes/bootstrap.php';

$no = strtoupper(trim(get('order')));
$phone = get('phone');
if ($no !== '' && $phone !== '') {
    $st = db()->prepare('SELECT order_no, phone, email FROM orders WHERE order_no = ?');
    $st->execute([$no]);
    $o = $st->fetch();
    $digits = fn($s) => substr(preg_replace('/\D+/', '', (string)$s), -10);
    if ($o && ($digits($o['phone']) === $digits($phone) || strcasecmp((string)$o['email'], $phone) === 0)) {
        redirect(order_view_url($o['order_no']));
    }
    flash('error', 'No order matches that order number and phone/email.');
}

$pageTitle = 'Track your order';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="card auth-card">
    <div class="text-center"><div class="success-icon" style="background:var(--soft);color:var(--primary)"><i class="fa-solid fa-location-dot"></i></div></div>
    <h1 class="text-center">Track your order</h1>
    <p class="muted text-center" style="font-size:14px">Enter the order number from your confirmation and the phone number (or email) you used.</p>
    <form method="get" class="form-grid">
      <div class="field"><label>Order number</label><input name="order" required placeholder="e.g. KVO2610A1B2C" value="<?= e($no) ?>" autocapitalize="characters"></div>
      <div class="field"><label>Phone number or email</label><input name="phone" required value="<?= e($phone) ?>"></div>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Track order</button>
    </form>
    <?php if (!empty($_SESSION['my_orders'])): ?>
      <div style="margin-top:18px"><small class="muted">Recent orders on this device</small>
        <?php foreach (array_reverse(array_slice($_SESSION['my_orders'], -5)) as $mo): ?><a class="order-row" href="<?= url(order_view_url($mo)) ?>"><strong><?= e($mo) ?></strong><i class="fa-solid fa-chevron-right muted"></i></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
