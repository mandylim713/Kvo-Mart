<?php
require __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$no  = get('o');
$tok = get('t');
$st = $pdo->prepare('SELECT * FROM orders WHERE order_no = ?');
$st->execute([$no]);
$order = $st->fetch();
$c = customer();
$allowed = $order && (hash_equals(order_token($order['order_no']), (string)$tok) || ($c && (int)$order['customer_id'] === (int)$c['id']));
if (!$allowed) { flash('error', 'Order not found. Use Track Order with your order number and phone.'); redirect('track.php'); }

$items = order_items((int)$order['id']);
$self  = order_view_url($order['order_no']);

if (is_post()) {
    csrf_check();
    $a = post('action');
    if ($a === 'paid_transfer' && $order['payment_method'] === 'transfer' && $order['payment_status'] === 'unpaid') {
        $pdo->prepare("UPDATE orders SET payment_status = 'awaiting', updated_at = ? WHERE id = ?")->execute([now(), $order['id']]);
        order_add_history((int)$order['id'], $order['status'], 'Customer reported bank transfer' . (post('sender') ? ' from ' . mb_substr(post('sender'), 0, 120) : ''));
        send_mail(setting('notify_email'), 'Transfer reported for ' . $order['order_no'], '<p>' . e($order['name']) . ' says they have paid ' . money($order['total']) . ' by bank transfer for order ' . e($order['order_no']) . '. Sender name: ' . e(post('sender')) . '</p>');
        flash('success', 'Thanks! We will confirm your payment shortly.');
    }
    if ($a === 'retry_flw' && $order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled' && setting('flw_secret_key')) {
        $txRef = $order['order_no'] . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
        $pdo->prepare('UPDATE orders SET tx_ref = ?, payment_method = ?, updated_at = ? WHERE id = ?')->execute([$txRef, 'flutterwave', now(), $order['id']]);
        $res = flw_request('POST', 'payments', [
            'tx_ref' => $txRef, 'amount' => (int)$order['total'], 'currency' => 'NGN',
            'redirect_url' => full_url('pay/flutterwave.php'),
            'customer' => ['email' => $order['email'] ?: setting('email'), 'phonenumber' => $order['phone'], 'name' => $order['name']],
            'customizations' => ['title' => setting('site_name'), 'description' => 'Order ' . $order['order_no']],
        ]);
        if ($res && ($res['status'] ?? '') === 'success' && !empty($res['data']['link'])) { header('Location: ' . $res['data']['link']); exit; }
        flash('error', 'Online payment is not available right now. Please use bank transfer or contact us.');
    }
    redirect($self);
}

$hist = $pdo->prepare('SELECT * FROM order_history WHERE order_id = ? ORDER BY id DESC');
$hist->execute([$order['id']]);
$history = $hist->fetchAll();

$isNew = get('new') === '1';
$waText = order_whatsapp_text($order, $items);
$steps = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'On the way', 'delivered' => 'Delivered'];
$keys = array_keys($steps);
$pos = array_search($order['status'], $keys, true);

$pageTitle = 'Order ' . $order['order_no'];
require __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width:860px">
  <?php if ($isNew): ?>
  <div class="success-head">
    <div class="success-icon"><i class="fa-solid fa-check"></i></div>
    <h1 style="font-size:24px">Thank you, <?= e(explode(' ', $order['name'])[0]) ?>!</h1>
    <p class="muted">Your order <strong style="color:var(--ink)"><?= e($order['order_no']) ?></strong> has been received.<?= $order['email'] ? ' A copy has been sent to ' . e($order['email']) . '.' : '' ?></p>
  </div>
  <?php else: ?>
  <div class="page-head"><div class="crumbs"><a href="<?= url($c ? 'account.php' : 'track.php') ?>"><?= $c ? 'My account' : 'Track order' ?></a> / <span><?= e($order['order_no']) ?></span></div><h1>Order <?= e($order['order_no']) ?></h1></div>
  <?php endif; ?>

  <?php if ($order['payment_method'] === 'whatsapp'): ?>
  <div class="card" style="margin-bottom:14px;text-align:center">
    <h3 style="margin-bottom:6px">Finish your order on WhatsApp</h3>
    <p class="muted" style="font-size:14px">Tap below to send your order details to us. We'll confirm availability, delivery time and payment.</p>
    <a class="btn btn-wa btn-lg btn-block" id="wa-go" href="<?= e(wa_link($waText)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Send order on WhatsApp</a>
  </div>
  <?php endif; ?>

  <?php if ($order['payment_method'] === 'transfer' && $order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled'): ?>
  <div class="card" style="margin-bottom:14px">
    <h3 style="margin-bottom:4px">Complete your bank transfer</h3>
    <p class="muted" style="font-size:14px;margin:0">Transfer the exact amount and use your order number as the narration.</p>
    <div class="bank-box">
      <div class="row"><span class="muted">Amount</span><strong><?= money($order['total']) ?></strong></div>
      <div class="row"><span class="muted">Bank</span><strong><?= e(setting('bank_name')) ?></strong></div>
      <div class="row"><span class="muted">Account name</span><strong><?= e(setting('bank_account_name')) ?></strong></div>
      <div class="row"><span class="muted">Account number</span><span><strong style="font-size:17px;letter-spacing:.5px"><?= e(setting('bank_account_no')) ?></strong> <button class="copy-btn" type="button" data-copy="<?= e(setting('bank_account_no')) ?>"><i class="fa-regular fa-copy"></i> Copy</button></span></div>
      <div class="row"><span class="muted">Narration</span><span><strong><?= e($order['order_no']) ?></strong> <button class="copy-btn" type="button" data-copy="<?= e($order['order_no']) ?>"><i class="fa-regular fa-copy"></i></button></span></div>
    </div>
    <?php if ($order['payment_status'] === 'unpaid'): ?>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="action" value="paid_transfer">
      <div class="field"><label>Sender / account name used <small>(helps us confirm faster)</small></label><input name="sender" maxlength="120"></div>
      <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-check"></i> I have made the transfer</button>
    </form>
    <?php else: ?>
      <div class="alert alert-warning mb-0"><span><i class="fa-regular fa-clock"></i> Transfer reported — we'll confirm shortly.</span></div>
    <?php endif; ?>
    <a class="btn btn-wa btn-block" style="margin-top:10px" href="<?= e(wa_link('Hello, I have paid for order ' . $order['order_no'] . ' (' . money($order['total']) . '). Here is my receipt:')) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Send receipt on WhatsApp</a>
  </div>
  <?php endif; ?>

  <?php if ($order['payment_method'] === 'flutterwave' && $order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled'): ?>
  <div class="card" style="margin-bottom:14px">
    <div class="alert alert-warning"><span>Payment not completed yet.</span></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="retry_flw"><button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fa-solid fa-credit-card"></i> Pay <?= money($order['total']) ?> now</button></form>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
      <div><small class="muted">Status</small><div><?= status_badge($order['status']) ?> <?= pay_badge($order['payment_status']) ?></div></div>
      <div style="text-align:right"><small class="muted">Placed</small><div style="font-weight:600;font-size:14px"><?= e(date('j M Y, g:ia', strtotime($order['created_at']))) ?></div></div>
    </div>
    <?php if ($order['status'] !== 'cancelled'): ?>
    <div class="progress-steps"><?php foreach ($keys as $i => $k): ?><div class="<?= $pos !== false && $i <= $pos ? 'done' : '' ?>"><?= e($steps[$k]) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-bottom:14px">
    <h3>Items</h3>
    <?php foreach ($items as $it): ?>
      <div class="summary"><div class="row"><span><?= e($it['name']) ?> <span class="muted">× <?= (int)$it['qty'] ?></span></span><strong><?= money($it['total']) ?></strong></div></div>
    <?php endforeach; ?>
    <div class="summary">
      <div class="row" style="border-top:1px solid var(--line);margin-top:6px;padding-top:10px"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
      <div class="row"><span>Delivery <?= $order['delivery_method'] === 'pickup' ? '(store pickup)' : '(' . e($order['zone_name']) . ')' ?></span><span><?= $order['delivery_fee'] ? money($order['delivery_fee']) : 'Free' ?></span></div>
      <div class="row total"><span>Total</span><span><?= money($order['total']) ?></span></div>
    </div>
  </div>

  <div class="card" style="margin-bottom:14px">
    <h3>Delivery details</h3>
    <p style="margin:0;font-size:14.5px"><strong><?= e($order['name']) ?></strong><br><?= e($order['phone']) ?><?= $order['email'] ? '<br>' . e($order['email']) : '' ?>
    <?php if ($order['delivery_method'] === 'delivery'): ?><br><?= e($order['address']) ?>, <?= e($order['city']) ?>, <?= e($order['state']) ?><?php else: ?><br><em><?= e(setting('pickup_address')) ?></em><?php endif; ?></p>
    <p class="muted" style="margin:10px 0 0;font-size:13.5px">Payment: <?= e(payment_methods()[$order['payment_method']] ?? $order['payment_method']) ?></p>
  </div>

  <?php if ($history): ?>
  <div class="card" style="margin-bottom:14px">
    <h3>Order updates</h3>
    <ul class="timeline">
      <?php foreach ($history as $h): ?><li class="done"><strong><?= e(order_statuses()[$h['status']] ?? ucfirst($h['status'])) ?></strong><?= $h['note'] ? ' — ' . e($h['note']) : '' ?><small><?= e(date('j M Y, g:ia', strtotime($h['created_at']))) ?></small></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="no-print" style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn btn-primary" href="<?= url('shop.php') ?>">Continue shopping</a>
    <button class="btn btn-outline" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print receipt</button>
    <a class="btn btn-outline" href="<?= e(wa_link('Hello, I have a question about order ' . $order['order_no'])) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Need help?</a>
  </div>
  <?php if ($isNew): ?><p class="muted" style="font-size:13px;margin-top:14px">Bookmark this page to check your order status anytime, or use <a href="<?= url('track.php') ?>" style="text-decoration:underline">Track Order</a> with your order number.</p><?php endif; ?>
</div>

<?php if ($isNew && $order['payment_method'] === 'whatsapp'): ?>
<script>setTimeout(function(){var a=document.getElementById('wa-go');if(a)window.location.href=a.href;},1500);</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
