<?php
/** Flutterwave redirect after payment: ?status=successful&tx_ref=...&transaction_id=... */
require __DIR__ . '/../includes/bootstrap.php';

$txRef = get('tx_ref');
$txId  = get('transaction_id');
$status = get('status');

$st = db()->prepare('SELECT * FROM orders WHERE tx_ref = ?');
$st->execute([$txRef]);
$order = $st->fetch();
if (!$order) { flash('error', 'We could not find that payment reference.'); redirect('track.php'); }

if (in_array($status, ['successful', 'completed'], true) && $txId !== '' && flw_verify_and_mark($txId, $order)) {
    flash('success', 'Payment successful! Your order is confirmed.');
    if ($order['email']) {
        $o = db()->query('SELECT * FROM orders WHERE id = ' . (int)$order['id'])->fetch();
        send_mail($o['email'], 'Payment received for ' . $o['order_no'], '<p>We have received your payment. Your order is confirmed.</p>' . order_email_html($o, order_items((int)$o['id'])));
    }
} elseif ($status === 'cancelled') {
    flash('warning', 'Payment was cancelled. You can try again below.');
} else {
    db()->prepare("UPDATE orders SET payment_status = CASE WHEN payment_status = 'paid' THEN 'paid' ELSE 'failed' END, updated_at = ? WHERE id = ?")->execute([now(), $order['id']]);
    flash('error', 'We could not confirm your payment. If you were debited, contact us with your order number.');
}
redirect(order_view_url($order['order_no']) . '&new=1');
