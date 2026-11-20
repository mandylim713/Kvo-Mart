<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

$id = (int)get('id');
$st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o) { flash('error', 'Order not found.'); redirect('admin/orders.php'); }

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'update') {
        $newStatus = post('status');
        $newPay = post('payment_status');
        $note = mb_substr(post('note'), 0, 500);
        if (!isset(order_statuses()[$newStatus])) $newStatus = $o['status'];
        if (!in_array($newPay, ['unpaid', 'awaiting', 'paid', 'failed', 'refunded'], true)) $newPay = $o['payment_status'];

        // stock follows cancellation
        if ($newStatus === 'cancelled' && $o['status'] !== 'cancelled') order_restock($id, admin()['id']);
        if ($o['status'] === 'cancelled' && $newStatus !== 'cancelled') {
            foreach (order_items($id) as $it) if ($it['product_id']) {
                stock_adjust((int)$it['product_id'], -(int)$it['qty'], 'Order ' . $o['order_no'] . ' re-opened');
                $pdo->prepare('UPDATE products SET sold = sold + ? WHERE id = ?')->execute([$it['qty'], $it['product_id']]);
            }
        }
        $pdo->prepare('UPDATE orders SET status = ?, payment_status = ?, admin_note = ?, updated_at = ? WHERE id = ?')
            ->execute([$newStatus, $newPay, mb_substr(post('admin_note'), 0, 2000), now(), $id]);
        if ($newStatus !== $o['status'] || $newPay !== $o['payment_status'] || $note !== '') {
            $hn = $note;
            if ($newPay !== $o['payment_status']) $hn = trim('Payment: ' . $newPay . '. ' . $hn);
            order_add_history($id, $newStatus, $hn);
            if ($o['email'] && $newStatus !== $o['status'] && post('notify')) {
                $o2 = $pdo->query('SELECT * FROM orders WHERE id = ' . $id)->fetch();
                send_mail($o['email'], 'Order ' . $o['order_no'] . ' is now ' . (order_statuses()[$newStatus] ?? $newStatus), '<p>Hi ' . e($o['name']) . ',</p><p>Your order status has been updated to <strong>' . e(order_statuses()[$newStatus] ?? $newStatus) . '</strong>.' . ($note ? '<br>' . e($note) : '') . '</p>' . order_email_html($o2, order_items($id)));
            }
        }
        flash('success', 'Order updated.');
    }
    if ($action === 'verify_flw') {
        $txId = post('tx_id') ?: $o['flw_tx_id'];
        if ($txId && flw_verify_and_mark($txId, $o)) flash('success', 'Payment verified with Flutterwave and marked as paid.');
        else flash('error', 'Could not verify a successful payment for this order. Check the transaction ID in your Flutterwave dashboard.');
    }
    if ($action === 'delete' && is_super()) {
        if ($o['status'] !== 'cancelled' && $o['status'] !== 'delivered') order_restock($id, admin()['id']);
        $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM order_history WHERE order_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
        flash('success', 'Order deleted.');
        redirect('admin/orders.php');
    }
    redirect('admin/order-view.php?id=' . $id);
}

$items = order_items($id);
$h = $pdo->prepare('SELECT * FROM order_history WHERE order_id = ? ORDER BY id DESC');
$h->execute([$id]);
$history = $h->fetchAll();
$phoneDigits = preg_replace('/\D+/', '', $o['phone']);
if (strpos($phoneDigits, '0') === 0) $phoneDigits = '234' . substr($phoneDigits, 1);
$custMsg = 'Hello ' . explode(' ', $o['name'])[0] . ', this is ' . setting('site_name') . ' regarding your order ' . $o['order_no'] . ' (' . money($o['total']) . '). ';
$trackLink = full_url(order_view_url($o['order_no']));

$adminTitle = 'Order ' . $o['order_no'];
require __DIR__ . '/_header.php';
?>
<div class="btn-row no-print" style="margin-bottom:12px">
  <a class="btn btn-outline btn-sm" href="<?= url('admin/orders.php') ?>"><i class="fa-solid fa-arrow-left"></i> Orders</a>
  <a class="btn btn-outline btn-sm" href="tel:<?= e($o['phone']) ?>"><i class="fa-solid fa-phone"></i> Call</a>
  <a class="btn btn-wa btn-sm" href="https://wa.me/<?= e($phoneDigits) ?>?text=<?= rawurlencode($custMsg . "\nTrack: " . $trackLink) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp customer</a>
  <button class="btn btn-outline btn-sm" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print invoice</button>
</div>

<div class="split">
  <div>
    <div class="card">
      <div class="card-head">
        <div><h2 style="font-size:18px;margin:0"><?= e($o['order_no']) ?></h2><span class="muted" style="font-size:13px"><?= e(date('l j M Y, g:ia', strtotime($o['created_at']))) ?></span></div>
        <div><?= status_badge($o['status']) ?> <?= pay_badge($o['payment_status']) ?></div>
      </div>
      <table class="table">
        <thead><tr><th>Item</th><th style="text-align:right">Qty</th><th style="text-align:right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr><td><?= $it['product_id'] ? '<a href="' . url('admin/product-edit.php?id=' . $it['product_id']) . '" class="t-name">' . e($it['name']) . '</a>' : e($it['name']) ?><div class="t-sub"><?= money($it['price']) ?> each</div></td><td style="text-align:right"><?= (int)$it['qty'] ?></td><td style="text-align:right"><strong><?= money($it['total']) ?></strong></td></tr>
        <?php endforeach; ?>
          <tr><td colspan="2" style="text-align:right">Subtotal</td><td style="text-align:right"><?= money($o['subtotal']) ?></td></tr>
          <tr><td colspan="2" style="text-align:right">Delivery (<?= $o['delivery_method'] === 'pickup' ? 'pickup' : e($o['zone_name']) ?>)</td><td style="text-align:right"><?= money($o['delivery_fee']) ?></td></tr>
          <tr><td colspan="2" style="text-align:right"><strong>Total</strong></td><td style="text-align:right;font-size:17px"><strong><?= money($o['total']) ?></strong></td></tr>
        </tbody>
      </table>
    </div>

    <div class="split-even">
      <div class="card">
        <h3>Customer</h3>
        <p style="margin:0;line-height:1.7"><strong><?= e($o['name']) ?></strong><br><a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a><?= $o['email'] ? '<br><a href="mailto:' . e($o['email']) . '">' . e($o['email']) . '</a>' : '' ?>
        <?= $o['customer_id'] ? '<br><span class="pill on">Registered customer</span>' : '<br><span class="pill">Guest</span>' ?></p>
      </div>
      <div class="card">
        <h3><?= $o['delivery_method'] === 'pickup' ? 'Store pickup' : 'Delivery address' ?></h3>
        <?php if ($o['delivery_method'] === 'pickup'): ?><p class="muted" style="margin:0">Customer will pick up from the store.</p>
        <?php else: ?><p style="margin:0;line-height:1.7"><?= e($o['address']) ?><br><?= e($o['city']) ?>, <?= e($o['state']) ?><br><span class="muted">Zone: <?= e($o['zone_name']) ?></span></p>
          <a class="btn btn-outline btn-sm no-print" style="margin-top:10px" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode($o['address'] . ', ' . $o['city'] . ', ' . $o['state'] . ', Nigeria') ?>"><i class="fa-solid fa-map-location-dot"></i> Open in Maps</a>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($o['notes']): ?><div class="card"><h3>Customer note</h3><p style="margin:0"><?= nl2br(e($o['notes'])) ?></p></div><?php endif; ?>

    <div class="card no-print">
      <h3>History</h3>
      <ul class="timeline"><?php foreach ($history as $hh): ?><li><strong><?= e(order_statuses()[$hh['status']] ?? $hh['status']) ?></strong><?= $hh['note'] ? ' — ' . e($hh['note']) : '' ?><small><?= e(date('j M Y, g:ia', strtotime($hh['created_at']))) ?></small></li><?php endforeach; ?></ul>
    </div>
  </div>

  <div class="no-print">
    <div class="card">
      <h3>Update order</h3>
      <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="update">
        <div class="field"><label>Order status</label><select name="status"><?php foreach (order_statuses() as $k => $v): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select><small>Cancelling returns items to stock automatically.</small></div>
        <div class="field"><label>Payment status</label><select name="payment_status"><?php foreach (['unpaid' => 'Unpaid', 'awaiting' => 'Awaiting confirmation', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $k => $v): ?><option value="<?= $k ?>" <?= $o['payment_status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Update note (visible to customer)</label><input name="note" placeholder="e.g. Rider is on the way"></div>
        <?php if ($o['email']): ?><label class="check"><input type="checkbox" name="notify" value="1" checked> Email customer about status change</label><?php endif; ?>
        <div class="field"><label>Private admin note</label><textarea name="admin_note" style="min-height:70px"><?= e($o['admin_note']) ?></textarea></div>
        <button class="btn btn-primary" type="submit">Save changes</button>
      </form>
    </div>
    <div class="card">
      <h3>Payment</h3>
      <dl class="kv">
        <dt>Method</dt><dd><?= e(payment_methods()[$o['payment_method']] ?? $o['payment_method']) ?></dd>
        <dt>Reference</dt><dd style="word-break:break-all"><?= e($o['tx_ref']) ?></dd>
        <?php if ($o['flw_tx_id']): ?><dt>Flutterwave ID</dt><dd><?= e($o['flw_tx_id']) ?></dd><?php endif; ?>
      </dl>
      <?php if ($o['payment_method'] === 'flutterwave' && $o['payment_status'] !== 'paid' && setting('flw_secret_key')): ?>
      <form method="post" class="form-grid" style="margin-top:12px">
        <?= csrf_field() ?><input type="hidden" name="action" value="verify_flw">
        <div class="field"><label>Flutterwave transaction ID</label><input name="tx_id" value="<?= e($o['flw_tx_id']) ?>" placeholder="From your Flutterwave dashboard"></div>
        <button class="btn btn-outline" type="submit"><i class="fa-solid fa-rotate"></i> Verify payment</button>
      </form>
      <?php endif; ?>
      <p class="muted" style="font-size:12.5px;margin:12px 0 0">Customer order link:<br><a href="<?= e($trackLink) ?>" target="_blank" style="word-break:break-all;color:var(--primary)"><?= e($trackLink) ?></a></p>
    </div>
    <?php if (is_super()): ?>
    <form method="post" data-confirm="Delete this order permanently? Stock will be returned if it was not delivered."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-block" type="submit"><i class="fa-regular fa-trash-can"></i> Delete order</button></form>
    <?php endif; ?>
  </div>
</div>


<?php require __DIR__ . '/_footer.php'; ?>
