<?php
/** Customer order page, actions on it, and the printable receipt. */
$o = $ref ? order_by_ref($ref) : null;
if (!$o || !can_see_order($o)) { $trackMsg = $o ? 'Confirm the order email to view this order.' : null; $prefill = $ref; require __DIR__ . '/track.php'; return; }
$oid = (int)$o['id'];
$back = order_link($o);

require_once __DIR__ . '/order_parts.php';

/* ---------- actions ---------- */
if (is_post() && $action) {
    require_csrf();
    $unpaid = in_array($o['status'], UNPAID, true);
    try {
        switch ($action) {
            case 'pay':
                if (!$unpaid) throw new RuntimeException('This order no longer needs payment.');
                $prov = post('provider');
                if (!pay_enabled($prov) || $prov === 'BANK_TRANSFER') throw new RuntimeException('That payment method is not available.');
                db()->prepare("UPDATE payments SET status = 'ABANDONED' WHERE order_id = ? AND purpose = 'ORDER' AND status = 'INITIATED'")->execute([$oid]);
                set_order($oid, ['method' => $prov]);
                $pay = new_payment(order_by_id($oid), $prov);
                redirect(start_online_payment($o, $pay));
            case 'tobank':
                if (!$unpaid) throw new RuntimeException('This order no longer needs payment.');
                if (!pay_enabled('BANK_TRANSFER')) throw new RuntimeException('Bank transfer is not available.');
                db()->prepare("UPDATE payments SET status = 'ABANDONED' WHERE order_id = ? AND purpose = 'ORDER' AND status = 'INITIATED'")->execute([$oid]);
                $hold = date('c', max(strtotime($o['hold_until']), time() + (int)setting('transfer_hold_hours', 48) * 3600));
                set_order($oid, ['method' => 'BANK_TRANSFER', 'status' => 'PENDING_PAYMENT', 'hold_until' => $o['reservation_active'] ? $hold : $o['hold_until']]);
                $has = db()->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ? AND provider = 'BANK_TRANSFER' AND status = 'PENDING' AND purpose = 'ORDER'"); $has->execute([$oid]);
                if (!$has->fetchColumn()) new_payment(order_by_id($oid), 'BANK_TRANSFER');
                flash('Switched to bank transfer. Use the details below.');
                break;
            case 'ihavepaid':
                $s = db()->prepare("SELECT * FROM payments WHERE order_id = ? AND provider = 'BANK_TRANSFER' AND status = 'PENDING' AND purpose = 'ORDER' ORDER BY id DESC LIMIT 1"); $s->execute([$oid]);
                $p = $s->fetch(); if (!$p) throw new RuntimeException('No bank transfer is waiting on this order.');
                $sender = mb_substr(post('sender'), 0, 120);
                db()->prepare('UPDATE payments SET note = ? WHERE id = ?')->execute(['Customer reports transfer made ' . fmt_date(now(), true) . ($sender ? ' from "' . $sender . '"' : ''), $p['id']]);
                send_email(admin_email(), 'Transfer reported — ' . $o['ref'], "{$o['name']} says they've paid " . naira($p['amount']) . " by bank transfer for {$o['ref']}" . ($sender ? " (sender: $sender)" : '') . '. Verify against the bank statement.', '/admin/orders/' . $o['ref']);
                audit('payment.transfer_reported', $o['ref'], $p['ref']);
                flash('Thanks — our finance team has been notified and will confirm your transfer.');
                break;
            case 'cancel':
                cancel_unpaid($o, 'Cancelled by customer');
                send_email($o['email'], "Order {$o['ref']} cancelled", "Your order {$o['ref']} has been cancelled.", $back);
                flash('Order cancelled.');
                break;
            case 'dqaccept': case 'dqdecline': case 'dqpay':
                $s = db()->prepare('SELECT * FROM delivery_quotes WHERE id = ? AND order_id = ?'); $s->execute([(int)post('dq'), $oid]);
                $q = $s->fetch(); if (!$q) throw new RuntimeException('Delivery quote not found.');
                if ($action === 'dqdecline') {
                    if ($q['status'] !== 'SENT') throw new RuntimeException('This delivery quote can no longer be declined.');
                    db()->prepare("UPDATE delivery_quotes SET status = 'DECLINED' WHERE id = ?")->execute([$q['id']]);
                    set_order($oid, ['d_status' => 'QUOTE_PENDING']); d_event($oid, 'QUOTE_PENDING', 'Customer declined the delivery quote');
                    audit('delivery.quote_declined', $o['ref'], naira($q['amount']));
                    send_email(admin_email(), 'Delivery quote declined — ' . $o['ref'], "{$o['name']} declined the delivery quote of " . naira($q['amount']) . '.', '/admin/orders/' . $o['ref']);
                    flash('Delivery quote declined. Our team will be in touch.');
                    break;
                }
                if ($action === 'dqaccept') {
                    if ($q['status'] !== 'SENT') throw new RuntimeException('This delivery quote is no longer open.');
                    if (strtotime($q['valid_until'] . ' 23:59:59') < time()) throw new RuntimeException('This delivery quote has expired. Contact us for a new one.');
                    db()->prepare("UPDATE delivery_quotes SET status = 'ACCEPTED' WHERE id = ?")->execute([$q['id']]);
                    set_order($oid, ['d_status' => 'AWAITING_PAYMENT']); d_event($oid, 'AWAITING_PAYMENT', 'Customer accepted delivery quote ' . naira($q['amount']));
                    audit('delivery.quote_accepted', $o['ref'], naira($q['amount']));
                    send_email(admin_email(), 'Delivery quote accepted — ' . $o['ref'], "{$o['name']} accepted the delivery quote of " . naira($q['amount']) . '.', '/admin/orders/' . $o['ref']);
                }
                $prov = post('provider') ?: (online_provider() ?? 'BANK_TRANSFER');
                if ($prov !== 'BANK_TRANSFER' && pay_enabled($prov)) {
                    db()->prepare("UPDATE payments SET status = 'ABANDONED' WHERE order_id = ? AND purpose = 'DELIVERY' AND status = 'INITIATED'")->execute([$oid]);
                    $pay = new_payment(order_by_id($oid), $prov, 'DELIVERY', (int)$q['amount'], (int)$q['id']);
                    redirect(start_online_payment($o, $pay));
                }
                $has = db()->prepare("SELECT COUNT(*) FROM payments WHERE order_id = ? AND purpose = 'DELIVERY' AND status = 'PENDING' AND dq_id = ?"); $has->execute([$oid, $q['id']]);
                if (!$has->fetchColumn()) new_payment(order_by_id($oid), 'BANK_TRANSFER', 'DELIVERY', (int)$q['amount'], (int)$q['id']);
                flash('Delivery quote accepted. Pay the delivery charge by bank transfer using the details below.');
                break;
            default: not_found();
        }
    } catch (RuntimeException $e) { flash($e->getMessage(), 'warn'); }
    redirect($back);
}

/* ---------- receipt ---------- */
if ($action === 'receipt') {
    page_open('Receipt ' . $o['ref'], ['noindex' => true]);
    echo '<div class="page py" style="max-width:48rem">';
    if (!$o['paid']) { echo note('No receipt yet', 'A receipt is available once payment is verified.', 'warn') . '<a class="btn mt6" href="' . h(url($back)) . '">Back to order</a></div>'; page_close(); return; }
    $pays = array_filter(order_payments($oid), fn($p) => $p['status'] === 'SUCCEEDED');
    echo '<div class="row between noprint"><a class="link" href="' . h(url($back)) . '">← Back to order</a><button class="btn dark" type="button" data-print>Print / save as PDF</button></div>'
        . '<div class="box mt6" style="border-top:4px solid var(--gold)"><div class="row between" style="align-items:flex-start"><div class="row"><img src="' . h(asset(setting('logo'))) . '" alt="" style="height:56px"><div><b style="font-family:var(--disp);font-size:1.1rem">' . h(setting('business_name')) . '</b><p class="xs muted">' . h(setting('tagline')) . '</p>' . (setting('address') ? '<p class="xs muted">' . h(setting('address')) . '</p>' : '') . (setting('phone') ? '<p class="xs muted">' . h(setting('phone')) . '</p>' : '') . '</div></div>'
        . '<div style="text-align:right"><h1 style="font-size:1.5rem">Receipt</h1><p class="sm tab">' . h($o['ref']) . '</p><p class="xs muted">' . fmt_date($o['paid_at'] ?: $o['created'], true) . '</p></div></div>'
        . '<div class="grid g2 mt6"><div><p class="xs muted">Billed to</p><p class="b">' . h($o['name']) . '</p>' . ($o['company'] ? '<p class="sm">' . h($o['company']) . '</p>' : '') . '<p class="sm">' . h($o['email']) . ' · ' . h($o['phone']) . '</p></div>'
        . '<div><p class="xs muted">Deliver to</p><p class="sm">' . ($o['delivery_method'] === 'PICKUP' ? 'Collection from KVO Mart' : h($o['addr_line1']) . '<br>' . h($o['addr_city']) . ', ' . h($o['addr_state'])) . '</p></div></div>'
        . '<div class="mt6">' . order_items_table($o) . '</div><div class="mt6"><p class="xs muted">Payments</p>';
    foreach ($pays as $p) echo '<p class="sm tab">' . fmt_date($p['verified_at'], true) . ' · ' . (PAY_LABEL[$p['provider']] ?? $p['provider']) . ' · ' . h($p['ref']) . ' · ' . naira($p['amount']) . '</p>';
    echo '</div><p class="xs muted mt6">Thank you for your business.</p></div></div>';
    page_close(); return;
}
if ($action) not_found();

/* ---------- order page ---------- */
$pays = order_payments($oid);
$unpaid = in_array($o['status'], UNPAID, true);
$bankPay = null; foreach ($pays as $p) if ($p['provider'] === 'BANK_TRANSFER' && $p['purpose'] === 'ORDER' && $p['status'] === 'PENDING') { $bankPay = $p; break; }
$dqs = order_dquotes($oid);
$dq = null; $dqPay = null; foreach ($dqs as $x) { if ($x['status'] === 'SENT') $dq = $x; if ($x['status'] === 'ACCEPTED') $dqPay = $x; }
$dqBank = null; if ($dqPay) foreach ($pays as $p) if ($p['purpose'] === 'DELIVERY' && $p['status'] === 'PENDING' && (int)$p['dq_id'] === (int)$dqPay['id']) $dqBank = $p;
$online = array_filter(['PAYSTACK', 'FLUTTERWAVE'], 'pay_enabled');
$r = q('pay');
$banner = match (true) {
    in_array($r, ['paid', 'already'], true) => note('Payment confirmed', "We've verified your payment and emailed a confirmation to " . h($o['email']) . '.', 'ok'),
    $r === 'failed' => note('Payment not completed', "Your payment was declined. You haven't been charged. You can try again or pay by bank transfer.", 'danger'),
    $r === 'abandoned' => note('Payment cancelled', 'You left the payment page before paying. Your order is saved and stock is held until ' . fmt_date($o['hold_until'], true) . '.', 'warn'),
    $r === 'mismatch' => note('Payment held for review', "The amount received didn't match the order total, so the payment was not accepted automatically. Our finance team will review it.", 'warn'),
    $r === 'pending' => note('Waiting for confirmation', "We haven't received a final result from the payment provider yet. This page updates once it arrives — refresh in a minute.", 'warn'),
    q('placed') !== '' => note('Order placed', 'Order ' . h($o['ref']) . ' is reserved for you until ' . fmt_date($o['hold_until'], true) . '. Complete the bank transfer below.', 'ok'),
    default => '',
};
$csrf = csrf_field();
$act = fn($a) => h(url('/orders/' . $o['ref'] . '/' . $a));
page_open('Order ' . $o['ref'], ['noindex' => true]);
?>
<div class="page py"><div class="noprint"><?= crumbs([['Home', '/'], current_user() ? ['My orders', '/account/orders'] : ['Track order', '/track'], [$o['ref']]]) ?></div>
<?php if ($banner): ?><div class="mt4"><?= $banner ?></div><?php endif; ?>
<div class="row between mt6"><div><p class="sm muted">Order</p><h1 class="tab"><?= h($o['ref']) ?></h1><p class="sm muted">Placed <?= fmt_date($o['created'], true) ?></p></div>
  <div class="row"><?= badge(ORDER_STATUS[$o['status']] ?? $o['status'], ost_tone($o['status'])) ?><?= $unpaid || $o['status'] === 'CANCELLED' ? '' : badge(DELIVERY_STATUS[$o['d_status']] ?? '', $o['d_status'] === 'DELIVERED' ? 'ok' : '') ?></div></div>
<?php if ($o['stock_issue']): ?><div class="mt4"><?= note("We're arranging stock for your order", 'Our team will contact you about supply or a refund.', 'warn') ?></div><?php endif; ?>
<?php if ($o['status'] === 'CANCELLED' && $o['cancel_reason']): ?><div class="mt4"><?= note('This order was cancelled', h($o['cancel_reason']) . '.', 'danger') ?></div><?php endif; ?>
<div class="split sum mt8"><div>
<?php if ($unpaid): ?>
  <section class="box" style="border-top:3px solid var(--gold)"><h2 style="font-size:1.15rem">Complete payment</h2>
    <p class="sm muted mt1">Amount due: <b class="tab" style="color:var(--ink)"><?= naira($o['total'] - $o['paid']) ?></b>. Stock is held until <?= fmt_date($o['hold_until'], true) ?>.</p>
    <?php if ($o['method'] === 'BANK_TRANSFER'): ?>
      <div class="note gold mt4"><strong>Bank transfer details</strong><p style="white-space:pre-line" class="sm mt1"><?= h(setting('bank_instructions')) ?></p><p class="sm mt2">Amount: <b class="tab"><?= naira($o['total'] - $o['paid']) ?></b> · Payment reference: <b class="tab"><?= h($bankPay['ref'] ?? $o['ref']) ?></b> <button type="button" class="link sm" data-copy="<?= h($bankPay['ref'] ?? $o['ref']) ?>">Copy</button></p></div>
      <?php if ($bankPay && !$bankPay['note']): ?>
        <form method="post" action="<?= $act('ihavepaid') ?>" class="mt4"><?= $csrf ?><div class="row" style="align-items:flex-end"><div class="grow" style="min-width:200px"><?= field('Name on the paying account', 'sender', '<input class="in" id="sender" name="sender" placeholder="Helps us match your transfer">', ['optional' => true]) ?></div><div class="f"><button class="btn dark">I've made the transfer</button></div></div></form>
      <?php elseif ($bankPay): ?><p class="sm mt4" style="color:var(--ok)">Thanks — our finance team has been notified and will confirm your transfer.</p><?php endif; ?>
      <?php foreach ($online as $prov): ?><form method="post" action="<?= $act('pay') ?>" style="display:inline-block" class="mt4 mr"><?= $csrf ?><input type="hidden" name="provider" value="<?= $prov ?>"><button class="link">Pay online with <?= PAY_LABEL[$prov] ?> instead</button></form> <?php endforeach; ?>
    <?php else: ?>
      <div class="row mt4"><?php foreach ($online as $prov): ?><form method="post" action="<?= $act('pay') ?>"><?= $csrf ?><input type="hidden" name="provider" value="<?= $prov ?>"><button class="btn<?= $prov === $o['method'] ? '' : ' out' ?>" data-busy="Opening payment…"><?= $o['status'] === 'PAYMENT_FAILED' ? 'Try again' : 'Pay now' ?> with <?= PAY_LABEL[$prov] ?> — <?= naira($o['total'] - $o['paid']) ?></button></form><?php endforeach; ?>
        <?php if (pay_enabled('BANK_TRANSFER')): ?><form method="post" action="<?= $act('tobank') ?>"><?= $csrf ?><button class="btn out">Pay by bank transfer instead</button></form><?php endif; ?></div>
    <?php endif; ?>
    <form method="post" action="<?= $act('cancel') ?>" class="mt4" data-confirm="Cancel this order? Held stock will be released."><?= $csrf ?><button class="btn ghost" style="color:var(--danger)">Cancel this order</button></form>
  </section>
<?php endif; ?>
<?php if ($dq): ?>
  <section class="box mt6" style="border-top:3px solid var(--gold)"><h2 style="font-size:1.15rem">Delivery quote ready</h2>
    <div class="mt3"><?= pl([['Delivery cost', naira($dq['amount'])], ['Provider', h($dq['provider'] ?: 'KVO logistics')], ['Estimated', $dq['days'] ? $dq['days'] . ' working days' : '—'], ['Valid until', fmt_date($dq['valid_until'])]]) ?></div>
    <?php if ($dq['notes']): ?><p class="sm mt3"><?= h($dq['notes']) ?></p><?php endif; ?>
    <div class="row mt4"><form method="post" action="<?= $act('dqaccept') ?>"><?= $csrf ?><input type="hidden" name="dq" value="<?= (int)$dq['id'] ?>"><?php if ($online): ?><input type="hidden" name="provider" value="<?= h(reset($online)) ?>"><?php endif; ?><button class="btn">Accept and pay <?= naira($dq['amount']) ?></button></form>
      <form method="post" action="<?= $act('dqdecline') ?>" data-confirm="Decline this delivery quote?"><?= $csrf ?><input type="hidden" name="dq" value="<?= (int)$dq['id'] ?>"><button class="btn out">Decline</button></form></div></section>
<?php endif; ?>
<?php if ($dqPay && !$dq): ?>
  <section class="box mt6" style="border-top:3px solid var(--gold)"><h2 style="font-size:1.15rem">Delivery payment due</h2><p class="sm mt2">You accepted a delivery quote of <?= naira($dqPay['amount']) ?>.</p>
    <?php if ($dqBank): ?><div class="note gold mt4"><strong>Pay by bank transfer</strong><p style="white-space:pre-line" class="sm mt1"><?= h(setting('bank_instructions')) ?></p><p class="sm mt2">Amount: <b><?= naira($dqPay['amount']) ?></b> · Reference: <b class="tab"><?= h($dqBank['ref']) ?></b></p></div><?php endif; ?>
    <div class="row mt4"><?php foreach ($online as $prov): ?><form method="post" action="<?= $act('dqpay') ?>"><?= $csrf ?><input type="hidden" name="dq" value="<?= (int)$dqPay['id'] ?>"><input type="hidden" name="provider" value="<?= $prov ?>"><button class="btn">Pay delivery with <?= PAY_LABEL[$prov] ?></button></form><?php endforeach; ?>
      <?php if (!$dqBank && pay_enabled('BANK_TRANSFER')): ?><form method="post" action="<?= $act('dqpay') ?>"><?= $csrf ?><input type="hidden" name="dq" value="<?= (int)$dqPay['id'] ?>"><input type="hidden" name="provider" value="BANK_TRANSFER"><button class="btn out">Pay delivery by bank transfer</button></form><?php endif; ?></div></section>
<?php endif; ?>
  <section class="box mt6"><h2 style="font-size:1.15rem">Items</h2><div class="mt3"><?= order_items_table($o) ?></div></section>
  <section class="box mt6"><h2 style="font-size:1.15rem">Delivery</h2><div class="mt3"><?= pl(array_filter([
    ['Method', $o['delivery_method'] === 'PICKUP' ? 'Collect from KVO Mart' : ($o['delivery_method'] === 'ZONE' ? 'Standard delivery' : 'Quoted delivery')],
    ['Status', DELIVERY_STATUS[$o['d_status']] ?? ''],
    $o['delivery_method'] === 'PICKUP' ? ['Collection', h(setting('pickup_note'))] : ['Address', h($o['addr_line1']) . ', ' . h($o['addr_city']) . ', ' . h($o['addr_state'])],
    ['Basis', h($o['d_basis'])],
    $o['d_provider'] ? ['Carrier', h($o['d_provider'])] : null, $o['d_tracking'] ? ['Tracking', h($o['d_tracking'])] : null, $o['d_expected'] ? ['Expected', fmt_date($o['d_expected'])] : null,
  ])) ?></div></section>
</div><aside>
  <section class="box"><h2 style="font-size:1.15rem">Timeline</h2><div class="mt4"><?= order_timeline($o) ?></div></section>
  <section class="box mt6"><h2 style="font-size:1.15rem">Customer</h2><div class="mt3"><?= pl(array_filter([['Name', h($o['name'])], ['Email', h($o['email'])], ['Phone', h($o['phone'])], $o['company'] ? ['Company', h($o['company'])] : null, ['Payment', PAY_LABEL[$o['method']] ?? $o['method']]])) ?></div></section>
  <div class="row mt6"><?php if ($o['paid']): ?><a class="btn dark" href="<?= h(url('/orders/' . $o['ref'] . '/receipt')) ?>">View receipt</a><?php endif; ?>
    <a class="btn out" href="<?= h(url('/contact?subject=' . urlencode('Order ' . $o['ref']))) ?>">Get help with this order</a>
    <?php if (wa_number()): ?><a class="btn out" style="border-color:var(--steel300)" href="<?= h(wa_link('Hello, I need help with order ' . $o['ref'])) ?>" target="_blank" rel="noopener"><?= ICON['wa'] ?> WhatsApp</a><?php endif; ?></div>
  <?php if (!current_user()): ?><p class="xs muted mt4">Keep this page's link to come back to your order, or use <a class="link" href="<?= h(url('/track')) ?>">Track an order</a> with your order number and email.</p><?php endif; ?>
</aside></div></div>
<?php page_close();
