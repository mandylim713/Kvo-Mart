<?php
require __DIR__ . '/includes/bootstrap.php';

$c = require_customer();
$pdo = db();
$tab = get('tab', 'orders');

if (is_post()) {
    csrf_check();
    if (post('action') === 'profile') {
        $pdo->prepare('UPDATE customers SET name = ?, phone = ?, address = ?, city = ?, state = ? WHERE id = ?')
            ->execute([mb_substr(post('name'), 0, 150) ?: $c['name'], mb_substr(post('phone'), 0, 40), mb_substr(post('address'), 0, 500), mb_substr(post('city'), 0, 100), mb_substr(post('state'), 0, 100), $c['id']]);
        flash('success', 'Profile saved.');
        redirect('account.php?tab=profile');
    }
    if (post('action') === 'password') {
        if (!password_verify(post('current'), $c['password'])) flash('error', 'Current password is incorrect.');
        elseif (strlen(post('new')) < 6) flash('error', 'New password must be at least 6 characters.');
        elseif (post('new') !== post('new2')) flash('error', 'New passwords do not match.');
        else {
            $pdo->prepare('UPDATE customers SET password = ? WHERE id = ?')->execute([password_hash(post('new'), PASSWORD_DEFAULT), $c['id']]);
            flash('success', 'Password changed.');
        }
        redirect('account.php?tab=profile');
    }
}

$st = $pdo->prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC');
$st->execute([$c['id']]);
$orders = $st->fetchAll();

$pageTitle = 'My account';
require __DIR__ . '/includes/header.php';
?>
<div class="container" style="max-width:900px">
  <div class="page-head"><h1>Hi, <?= e(explode(' ', $c['name'])[0]) ?></h1><p class="muted"><?= e($c['email']) ?></p></div>
  <div class="acct-tabs">
    <a class="chip <?= $tab === 'orders' ? 'active' : '' ?>" href="<?= url('account.php') ?>">My orders (<?= count($orders) ?>)</a>
    <a class="chip <?= $tab === 'profile' ? 'active' : '' ?>" href="<?= url('account.php?tab=profile') ?>">Profile &amp; address</a>
    <a class="chip" href="<?= url('logout.php') ?>">Log out</a>
  </div>

  <?php if ($tab === 'profile'): ?>
    <div class="card" style="margin-bottom:14px">
      <h3>Profile &amp; delivery address</h3>
      <form method="post" class="form-grid two">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="field"><label>Full name</label><input name="name" value="<?= e($c['name']) ?>" required></div>
        <div class="field"><label>Phone</label><input name="phone" type="tel" value="<?= e($c['phone']) ?>"></div>
        <div class="field full"><label>Address</label><input name="address" value="<?= e($c['address']) ?>"></div>
        <div class="field"><label>City</label><input name="city" value="<?= e($c['city']) ?>"></div>
        <div class="field"><label>State</label><select name="state"><option value="">Select state</option><?php foreach (nigerian_states() as $s): ?><option <?= $c['state'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
        <div class="full"><button class="btn btn-primary" type="submit">Save profile</button></div>
      </form>
    </div>
    <div class="card">
      <h3>Change password</h3>
      <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="field"><label>Current password</label><input type="password" name="current" required autocomplete="current-password"></div>
        <div class="field"><label>New password</label><input type="password" name="new" required minlength="6" autocomplete="new-password"></div>
        <div class="field"><label>Confirm new password</label><input type="password" name="new2" required minlength="6" autocomplete="new-password"></div>
        <button class="btn btn-primary" type="submit">Change password</button>
      </form>
    </div>
  <?php else: ?>
    <div class="card">
      <?php if (!$orders): ?>
        <div class="empty"><i class="fa-solid fa-receipt"></i><h3>No orders yet</h3><a class="btn btn-primary" href="<?= url('shop.php') ?>">Start shopping</a></div>
      <?php else: foreach ($orders as $o): ?>
        <a class="order-row" href="<?= url(order_view_url($o['order_no'])) ?>">
          <div><strong><?= e($o['order_no']) ?></strong><div class="muted" style="font-size:13px"><?= e(date('j M Y', strtotime($o['created_at']))) ?> · <?= money($o['total']) ?></div></div>
          <div style="text-align:right"><?= status_badge($o['status']) ?><div style="margin-top:4px"><?= pay_badge($o['payment_status']) ?></div></div>
        </a>
      <?php endforeach; endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
