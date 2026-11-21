<?php
$pdo = db();
if ($id) {
    $o = order_by_ref($id); if (!$o) { flash('Order not found.', 'warn'); redirect('/admin/orders'); }
    $oid = (int)$o['id']; $back = '/admin/orders/' . $o['ref'];
    if (is_post()) {
        require_csrf();
        try {
            switch (post('do')) {
                case 'verify':
                    need('payments.verify');
                    $p = payment_by_ref(post('ref')); if (!$p || (int)$p['order_id'] !== $oid) throw new RuntimeException('Payment not found.');
                    $r = apply_payment($p['ref'], 'success', (int)$p['amount'], 'staff', post('bankref') ?: null);
                    audit('payment.verified_by_staff', $o['ref'], $p['ref'] . (post('bankref') ? ' bank ref ' . post('bankref') : ''));
                    flash($r === 'paid' ? 'Payment confirmed. The customer has been emailed.' : 'Payment was already confirmed.');
                    break;
                case 'reject':
                    need('payments.verify');
                    $p = payment_by_ref(post('ref')); if (!$p || (int)$p['order_id'] !== $oid) throw new RuntimeException('Payment not found.');
                    apply_payment($p['ref'], 'failed', 0, 'staff');
                    flash('Payment marked as not received.');
                    break;
                case 'recheck':
                    need('payments.verify');
                    $p = payment_by_ref(post('ref')); if (!$p || (int)$p['order_id'] !== $oid) throw new RuntimeException('Payment not found.');
                    $r = verify_online_payment($p, 'staff recheck');
                    flash(['paid' => 'The provider confirms this payment — order updated.', 'already' => 'Already confirmed.', 'failed' => 'The provider reports this payment failed.', 'mismatch' => 'The amount paid differs from the order — held for review.', 'pending' => 'The provider has no successful payment for this reference yet.'][$r] ?? 'Checked.', $r === 'paid' ? 'ok' : 'warn');
                    break;
                case 'manual':
                    need('payments.verify');
                    $amt = parse_naira(post('amount'));
                    if (!$amt || $amt <= 0) throw new RuntimeException('Enter the amount received.');
                    $purpose = post('purpose') === 'DELIVERY' ? 'DELIVERY' : 'ORDER';
                    if ($purpose === 'ORDER' && !in_array($o['status'], UNPAID, true) && $o['status'] !== 'CANCELLED') throw new RuntimeException('This order is already paid.');
                    if ($purpose === 'ORDER' && $amt !== (int)$o['total'] - (int)$o['paid']) throw new RuntimeException('The amount must match the balance due (' . naira($o['total'] - $o['paid']) . '). For part-payments, agree the balance with the customer first.');
                    $p = new_payment($o, 'MANUAL', $purpose, $amt);
                    db()->prepare('UPDATE payments SET note = ? WHERE id = ?')->execute([mb_substr('Recorded by ' . current_staff()['name'] . ': ' . post('note'), 0, 500), $p['id']]);
                    apply_payment($p['ref'], 'success', $amt, 'staff', post('bankref') ?: null);
                    flash('Payment recorded.');
                    break;
                case 'cancel':
                    need('orders.cancel');
                    cancel_unpaid($o, post('reason') ?: 'Cancelled by staff');
                    send_email($o['email'], "Order {$o['ref']} cancelled", "Your order {$o['ref']} has been cancelled." . (post('reason') ? ' Reason: ' . post('reason') : ''), order_link($o));
                    flash('Order cancelled and held stock released.');
                    break;
                case 'extend':
                    need('orders.update');
                    if (!in_array($o['status'], UNPAID, true)) throw new RuntimeException('Only unpaid orders have a stock hold.');
                    $hrs = max(1, min(720, (int)post('hours')));
                    set_order($oid, ['hold_until' => date('c', max(time(), strtotime($o['hold_until'])) + $hrs * 3600)]);
                    audit('order.hold_extended', $o['ref'], "+$hrs h");
                    flash("Stock hold extended by $hrs hours.");
                    break;
                case 'refund':
                    need('refunds.create');
                    $amt = parse_naira(post('amount'));
                    $left = (int)$o['paid'] - (int)$o['refunded'];
                    if (!$amt || $amt <= 0 || $amt > $left) throw new RuntimeException('Enter an amount up to ' . naira($left) . '.');
                    if (mb_strlen(post('reason')) < 3) throw new RuntimeException('Give a reason for the refund.');
                    $pdo->prepare('INSERT INTO refunds (order_id, amount, reason, by_name, at) VALUES (?,?,?,?,?)')->execute([$oid, $amt, post('reason'), current_staff()['name'], now()]);
                    $ref2 = (int)$o['refunded'] + $amt;
                    set_order($oid, ['refunded' => $ref2, 'status' => $ref2 >= (int)$o['paid'] ? 'REFUNDED' : 'PARTIALLY_REFUNDED']);
                    if (post('restock') && $o['stock_committed']) {
                        foreach (order_items($oid) as $l) if ($l['product_id']) { $pdo->prepare('UPDATE products SET on_hand = on_hand + ?, sales = CASE WHEN sales >= CAST(? AS INTEGER) THEN sales - ? ELSE 0 END WHERE id = ?')->execute([$l['qty'], $l['qty'], $l['qty'], $l['product_id']]); move(prod($l['product_id']), 'RETURN', (int)$l['qty'], 'Returned — refund on ' . $o['ref'], $o['ref']); }
                        set_order($oid, ['stock_committed' => 0]);
                    }
                    if ($ref2 >= (int)$o['paid'] && !in_array($o['d_status'], ['DELIVERED', 'CANCELLED'], true)) { set_order($oid, ['d_status' => 'CANCELLED']); d_event($oid, 'CANCELLED', 'Order refunded'); }
                    if (post('clear_issue')) set_order($oid, ['stock_issue' => null]);
                    audit('refund.created', $o['ref'], naira($amt) . ' — ' . post('reason'));
                    send_email($o['email'], 'Refund issued — ' . $o['ref'], 'We have issued a refund of ' . naira($amt) . " for order {$o['ref']}. Reason: " . post('reason') . '. It goes back to your original payment method or by bank transfer; banks can take a few working days to show it.', order_link($o));
                    flash('Refund recorded. Remember to send the money through the payment provider or your bank.');
                    break;
                case 'dquote':
                    need('deliveries.manage');
                    $amt = parse_naira(post('amount'));
                    if (!$amt || $amt <= 0) throw new RuntimeException('Enter the delivery cost.');
                    $valid = post('valid_until') ?: date('Y-m-d', time() + 7 * 86400);
                    $pdo->prepare("UPDATE delivery_quotes SET status = 'SUPERSEDED' WHERE order_id = ? AND status = 'SENT'")->execute([$oid]);
                    $pdo->prepare("INSERT INTO delivery_quotes (order_id, amount, provider, days, valid_until, notes, status, at) VALUES (?,?,?,?,?,?,'SENT',?)")->execute([$oid, $amt, mb_substr(post('provider'), 0, 150), post('days') !== '' ? (int)post('days') : null, $valid, mb_substr(post('notes'), 0, 1000), now()]);
                    set_order($oid, ['d_status' => 'QUOTE_AVAILABLE']); d_event($oid, 'QUOTE_AVAILABLE', 'Delivery quote ' . naira($amt));
                    audit('delivery.quote_sent', $o['ref'], naira($amt));
                    send_email($o['email'], 'Delivery quote for ' . $o['ref'], 'Your delivery quote is ready: ' . naira($amt) . (post('days') !== '' ? ', about ' . (int)post('days') . ' working days' : '') . '. Valid until ' . fmt_date($valid) . '. Open your order to accept and pay, or decline.', order_link($o));
                    flash('Delivery quote sent to the customer.');
                    break;
                case 'dstatus':
                    need('deliveries.manage');
                    $to = post('status');
                    if (!in_array($to, DELIVERY_NEXT[$o['d_status']] ?? [], true)) throw new RuntimeException('That delivery status change is not allowed from "' . (DELIVERY_STATUS[$o['d_status']] ?? '') . '".');
                    if (in_array($to, ['DISPATCHED', 'IN_TRANSIT', 'DELIVERED'], true) && $o['status'] !== 'PAID' && $o['status'] !== 'PARTIALLY_REFUNDED') throw new RuntimeException('Dispatch waits until the order is paid.');
                    $f = ['d_status' => $to];
                    foreach (['provider' => 'd_provider', 'tracking' => 'd_tracking', 'assignee' => 'd_assignee'] as $in => $col) if (post($in) !== '') $f[$col] = mb_substr(post($in), 0, 150);
                    if (post('expected') !== '') $f['d_expected'] = post('expected');
                    set_order($oid, $f); d_event($oid, $to, mb_substr(post('note'), 0, 500));
                    audit('delivery.status', $o['ref'], $to);
                    $msg = ['DISPATCHED' => 'Your order has been dispatched.', 'IN_TRANSIT' => 'Your order is on the way.', 'DELIVERED' => $o['delivery_method'] === 'PICKUP' ? 'Your order has been collected. Thank you for buying from us.' : 'Your order has been delivered. Thank you for buying from us.', 'EXCEPTION' => 'There is an issue with your delivery. Our team will contact you.', 'READY_FOR_DISPATCH' => $o['delivery_method'] === 'PICKUP' ? 'Your order is ready for collection.' : 'Your order is ready for dispatch.'][$to] ?? null;
                    if ($msg && post('notify')) send_email($o['email'], (DELIVERY_STATUS[$to] ?? 'Update') . ' — ' . $o['ref'], $msg . (post('tracking') ? ' Tracking: ' . post('tracking') . '.' : '') . (post('note') ? "\n\n" . post('note') : ''), order_link($o));
                    flash('Delivery updated to "' . DELIVERY_STATUS[$to] . '".');
                    break;
                case 'details':
                    need('deliveries.manage');
                    set_order($oid, ['d_provider' => mb_substr(post('provider'), 0, 150), 'd_tracking' => mb_substr(post('tracking'), 0, 150), 'd_assignee' => mb_substr(post('assignee'), 0, 150), 'd_expected' => post('expected') ?: null]);
                    flash('Delivery details saved.');
                    break;
                case 'note':
                    need('orders.update');
                    if (post('note') === '') throw new RuntimeException('Write a note first.');
                    $pdo->prepare('INSERT INTO order_notes (order_id, note, by_name, at) VALUES (?,?,?,?)')->execute([$oid, mb_substr(post('note'), 0, 2000), current_staff()['name'], now()]);
                    flash('Note added.');
                    break;
                case 'resolve':
                    need('orders.update');
                    set_order($oid, ['stock_issue' => null]); audit('order.stock_issue_resolved', $o['ref'], post('note'));
                    flash('Stock issue marked as resolved.');
                    break;
                default: throw new RuntimeException('Unknown action.');
            }
        } catch (InvalidArgumentException | RuntimeException $e) { flash($e->getMessage(), 'danger'); }
        redirect($back);
    }

    $o = order_by_id($oid);
    $pays = order_payments($oid); $dqs = order_dquotes($oid); $notes = $pdo->prepare('SELECT * FROM order_notes WHERE order_id = ? ORDER BY id DESC'); $notes->execute([$oid]); $notes = $notes->fetchAll();
    $unpaid = in_array($o['status'], UNPAID, true);
    $c = csrf_field();
    require_once __DIR__ . '/../pages/order_parts.php';

    admin_open('Order ' . $o['ref']);
    echo ahead('Order ' . $o['ref'], 'Placed ' . fmt_date($o['created'], true) . ' · ' . h(PAY_LABEL[$o['method']] ?? $o['method']) . ($unpaid ? ' · stock held until ' . fmt_date($o['hold_until'], true) : ''),
        '<div class="row">' . badge(ORDER_STATUS[$o['status']] ?? '', ost_tone($o['status'])) . badge(DELIVERY_STATUS[$o['d_status']] ?? '') . '<a class="btn out sm" href="' . h(url(order_link($o))) . '" target="_blank">Customer view</a><button class="btn out sm" type="button" data-print>Print</button></div>');
    if ($o['stock_issue']) echo '<div style="margin-bottom:1rem">' . note('Stock issue', h($o['stock_issue']) . (can('orders.update') ? '<form method="post" class="row mt3">' . $c . '<input type="hidden" name="do" value="resolve"><input class="in" name="note" placeholder="How it was resolved" style="max-width:320px"><button class="btn sm">Mark resolved</button></form>' : ''), 'danger') . '</div>';
    if ($o['status'] === 'CANCELLED' && $o['cancel_reason']) echo '<div style="margin-bottom:1rem">' . note('Cancelled', h($o['cancel_reason'])) . '</div>';

    // payments
    $pt = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Reference</th><th>Method</th><th class="r">Amount</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($pays as $p) {
        $btn = '';
        if (can('payments.verify') && $p['status'] === 'PENDING') {
            if ($p['provider'] === 'BANK_TRANSFER' && !str_starts_with((string)$p['note'], 'Amount mismatch'))
                $btn .= '<form method="post" class="row" style="gap:.4rem;margin-bottom:.4rem">' . $c . '<input type="hidden" name="do" value="verify"><input type="hidden" name="ref" value="' . h($p['ref']) . '"><input class="in" name="bankref" placeholder="Bank ref (optional)" style="width:150px;min-height:36px"><button class="btn sm">Confirm received</button></form>';
            if (str_starts_with((string)$p['note'], 'Amount mismatch')) $btn .= '<p class="xs muted" style="margin-bottom:.4rem">Settle with the customer, refund in the provider dashboard, then mark not received or record the correct payment.</p>';
            $btn .= '<form method="post" data-confirm="Mark this payment as not received?">' . $c . '<input type="hidden" name="do" value="reject"><input type="hidden" name="ref" value="' . h($p['ref']) . '"><button class="btn out sm">Not received</button></form>';
        } elseif (can('payments.verify') && in_array($p['status'], ['INITIATED', 'ABANDONED', 'FAILED'], true) && in_array($p['provider'], ['PAYSTACK', 'FLUTTERWAVE'], true) && pay_enabled($p['provider'])) {
            $btn = '<form method="post">' . $c . '<input type="hidden" name="do" value="recheck"><input type="hidden" name="ref" value="' . h($p['ref']) . '"><button class="btn out sm">Re-check with ' . PAY_LABEL[$p['provider']] . '</button></form>';
        }
        $pt .= '<tr><td class="first tab">' . h($p['ref']) . '<div class="xs muted">' . ($p['purpose'] === 'DELIVERY' ? 'Delivery' : 'Order') . ' · ' . fmt_date($p['at'], true) . ($p['provider_ref'] ? ' · ' . h($p['provider_ref']) : '') . '</div></td><td data-l="Method">' . h(PAY_LABEL[$p['provider']] ?? $p['provider']) . '</td><td data-l="Amount" class="tab r">' . naira($p['amount']) . '</td><td data-l="Status">' . pay_badge($p) . ($p['note'] ? '<div class="xs muted mt1">' . h($p['note']) . '</div>' : '') . '</td><td>' . $btn . '</td></tr>';
    }
    $pt .= '</tbody></table></div>';
    if (!$pays) $pt = '<p class="sm muted">No payments yet.</p>';
    if (can('payments.verify') && ($unpaid || $o['status'] === 'CANCELLED' || ($o['d_status'] === 'AWAITING_PAYMENT'))) {
        $pt .= '<details class="mt4"><summary class="b sm">Record a payment received another way</summary><form method="post" class="mt3 box" style="background:var(--steel50)" novalidate>' . $c . '<input type="hidden" name="do" value="manual"><div class="grid g2" style="gap:0 1rem">'
            . field('For', 'mp-p', '<select class="in" id="mp-p" name="purpose"><option value="ORDER">Order balance (' . naira(max(0, $o['total'] - $o['paid'])) . ')</option><option value="DELIVERY">Delivery charge</option></select>')
            . field('Amount (₦)', 'mp-a', '<input class="in" id="mp-a" name="amount" inputmode="decimal" value="' . h(naira_input(max(0, $o['total'] - $o['paid']))) . '">')
            . field('Bank / POS reference', 'mp-r', '<input class="in" id="mp-r" name="bankref">', ['optional' => true]) . field('Note', 'mp-n', '<input class="in" id="mp-n" name="note" placeholder="e.g. Cash at office">', ['optional' => true])
            . '</div><button class="btn sm">Record payment</button></form></details>';
    }

    // delivery
    $canQuote = $o['delivery_method'] === 'QUOTED' && in_array($o['d_status'], ['AWAITING_ASSESSMENT', 'QUOTE_PENDING', 'QUOTE_AVAILABLE'], true) && $o['status'] !== 'CANCELLED';
    $next = DELIVERY_NEXT[$o['d_status']] ?? [];
    if ($o['delivery_method'] === 'QUOTED' && $o['d_status'] === 'AWAITING_ASSESSMENT') $next = array_values(array_diff($next, ['READY_FOR_DISPATCH']));
    $db = pl(array_filter([
        ['Method', $o['delivery_method'] === 'PICKUP' ? 'Customer collection' : ($o['delivery_method'] === 'ZONE' ? 'Zone delivery' : 'Quoted delivery')],
        $o['delivery_method'] !== 'PICKUP' ? ['Address', h($o['addr_line1']) . ', ' . h($o['addr_city']) . ', ' . h($o['addr_state'])] : null,
        $o['addr_instr'] ? ['Instructions', h($o['addr_instr'])] : null, ['Basis', h($o['d_basis'])], ['Fee', $o['d_fee'] === null ? 'Not yet quoted' : naira($o['d_fee'])],
        $o['d_provider'] ? ['Carrier', h($o['d_provider'])] : null, $o['d_tracking'] ? ['Tracking', h($o['d_tracking'])] : null, $o['d_expected'] ? ['Expected', fmt_date($o['d_expected'])] : null, $o['d_assignee'] ? ['Assigned to', h($o['d_assignee'])] : null,
    ]));
    if ($dqs) { $db .= '<p class="sm b mt4">Delivery quotes</p>'; foreach ($dqs as $q) $db .= '<p class="sm">' . naira($q['amount']) . ' · ' . h($q['provider'] ?: 'KVO logistics') . ' · ' . badge(humanize($q['status'])) . ' <span class="xs muted">' . fmt_date($q['at'], true) . ' · valid to ' . fmt_date($q['valid_until']) . '</span></p>'; }
    if ($o['delivery_method'] !== 'PICKUP' && $o['addr_line1']) $db .= '<a class="link sm mt3" style="display:inline-block" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=' . rawurlencode($o['addr_line1'] . ', ' . $o['addr_city'] . ', ' . $o['addr_state'] . ', Nigeria') . '">Open address in Google Maps</a>';
    if (can('deliveries.manage') && $canQuote) {
        $db .= '<form method="post" class="mt6 box" style="background:var(--steel50)" novalidate>' . $c . '<input type="hidden" name="do" value="dquote"><p class="b">' . ($o['d_status'] === 'QUOTE_AVAILABLE' ? 'Replace the delivery quote' : 'Send delivery quote') . '</p>' . ($o['status'] !== 'PAID' ? '<p class="xs muted">The customer can accept now; dispatch waits until the order is paid.</p>' : '')
            . '<div class="grid g2 mt3" style="gap:0 1rem">' . field('Delivery cost (₦)', 'dq-amt', '<input class="in" id="dq-amt" name="amount" inputmode="decimal" required>') . field('Transport provider', 'dq-prov', '<input class="in" id="dq-prov" name="provider" placeholder="KVO logistics / haulage company">', ['optional' => true])
            . field('Working days', 'dq-days', '<input class="in" id="dq-days" name="days" type="number" min="1">', ['optional' => true]) . field('Valid until', 'dq-valid', '<input class="in" id="dq-valid" name="valid_until" type="date" value="' . date('Y-m-d', time() + 7 * 86400) . '">') . '</div>'
            . field('Notes for the customer', 'dq-notes', '<textarea class="in" id="dq-notes" name="notes" rows="2" placeholder="Low-bed trailer, offloading included…"></textarea>', ['optional' => true]) . '<button class="btn sm">Send to customer</button></form>';
    }
    if (can('deliveries.manage') && $next && $o['status'] !== 'CANCELLED') {
        $labels = $next; $opts = '';
        foreach ($next as $s) $opts .= '<option value="' . $s . '">' . h($o['delivery_method'] === 'PICKUP' && $s === 'DELIVERED' ? 'Collected by customer' : DELIVERY_STATUS[$s]) . '</option>';
        $db .= '<form method="post" class="mt6 box" style="background:var(--steel50)" novalidate>' . $c . '<input type="hidden" name="do" value="dstatus"><p class="b">Update delivery</p><div class="grid g2 mt3" style="gap:0 1rem">'
            . field('New status', 'ds-s', '<select class="in" id="ds-s" name="status">' . $opts . '</select>') . field('Carrier', 'ds-p', '<input class="in" id="ds-p" name="provider" value="' . h($o['d_provider']) . '">', ['optional' => true])
            . field('Tracking / waybill', 'ds-t', '<input class="in" id="ds-t" name="tracking" value="' . h($o['d_tracking']) . '">', ['optional' => true]) . field('Expected date', 'ds-e', '<input class="in" id="ds-e" name="expected" type="date" value="' . h(substr((string)$o['d_expected'], 0, 10)) . '">', ['optional' => true])
            . field('Assigned to', 'ds-a', '<input class="in" id="ds-a" name="assignee" value="' . h($o['d_assignee']) . '">', ['optional' => true]) . field('Note', 'ds-n', '<input class="in" id="ds-n" name="note">', ['optional' => true])
            . '</div>' . cb('notify', true, 'Email the customer about this update') . '<div class="mt3"><button class="btn sm">Update delivery</button></div></form>';
    }

    // side panel
    $side = panel('Customer', pl(array_filter([['Name', h($o['name'])], ['Email', '<a href="mailto:' . h($o['email']) . '">' . h($o['email']) . '</a>'], ['Phone', '<a href="tel:' . h(preg_replace('/[^\d+]/', '', $o['phone'])) . '">' . h($o['phone']) . '</a>'], $o['company'] ? ['Company', h($o['company'])] : null, ['Account', $o['user_id'] ? 'Registered' : 'Guest']]))
        . (function () use ($o) { $n = preg_replace('/\D+/', '', $o['phone']); if (str_starts_with($n, '0')) $n = '234' . substr($n, 1); return $n ? '<a class="btn wa sm mt3" target="_blank" rel="noopener" href="https://wa.me/' . h($n) . '?text=' . rawurlencode('Hello ' . explode(' ', $o['name'])[0] . ', this is ' . setting('short_name', 'KVO Mart') . ' about your order ' . $o['ref'] . '. ' . full_url(order_link($o))) . '">' . ICON['wa'] . ' WhatsApp customer</a>' : ''; })()
        . ($o['notes'] ? '<p class="sm mt3"><b>Order notes:</b> ' . nl2br(h($o['notes'])) . '</p>' : ''));
    $side .= panel('Timeline', order_timeline($o));
    if (can('orders.update') || $notes) {
        $nb = ''; foreach ($notes as $n) $nb .= '<div style="border-bottom:1px solid var(--steel);padding:.5rem 0" class="sm">' . nl2br(h($n['note'])) . '<div class="xs muted">' . h($n['by_name']) . ' · ' . fmt_date($n['at'], true) . '</div></div>';
        if (can('orders.update')) $nb .= '<form method="post" class="mt3">' . $c . '<input type="hidden" name="do" value="note"><textarea class="in" name="note" rows="2" placeholder="Internal note — customers never see this"></textarea><button class="btn sm mt2">Add note</button></form>';
        $side .= panel('Staff notes', $nb ?: '<p class="sm muted">No notes.</p>');
    }
    $actions = '';
    if ($unpaid && can('orders.update')) $actions .= '<form method="post" class="row">' . $c . '<input type="hidden" name="do" value="extend"><input class="in" name="hours" type="number" min="1" max="720" value="24" style="width:90px"><button class="btn out sm">Extend stock hold (hours)</button></form>';
    if ($unpaid && can('orders.cancel')) $actions .= '<form method="post" class="mt3" data-confirm="Cancel this unpaid order and release the stock?">' . $c . '<input type="hidden" name="do" value="cancel"><input class="in" name="reason" placeholder="Reason (sent to the customer)"><button class="btn danger sm mt2">Cancel order</button></form>';
    $left = (int)$o['paid'] - (int)$o['refunded'];
    if ($left > 0 && can('refunds.create')) $actions .= '<details class="mt3"' . ($o['stock_issue'] ? ' open' : '') . '><summary class="b sm">Issue a refund</summary><form method="post" class="mt3" data-confirm="Record this refund? Send the money through the provider or bank as well." novalidate>' . $c . '<input type="hidden" name="do" value="refund">'
        . field('Amount (₦)', 'rf-a', '<input class="in" id="rf-a" name="amount" inputmode="decimal" value="' . h(naira_input($left)) . '">', ['hint' => 'Up to ' . naira($left)]) . field('Reason', 'rf-r', '<input class="in" id="rf-r" name="reason">')
        . ($o['stock_committed'] ? cb('restock', false, 'Items came back — return them to stock') : '') . ($o['stock_issue'] ? cb('clear_issue', true, 'This resolves the stock issue') : '') . '<button class="btn danger sm mt3">Record refund</button></form></details>';
    if ($actions) $side .= panel('Actions', $actions);

    echo '<div class="split admin" style="align-items:start"><div>' . panel('Items and totals', order_items_table($o)) . panel('Payments', $pt) . panel('Delivery', $db) . '</div><div>' . $side . '</div></div>';
    admin_close();
    return;
}

/* ---------- list ---------- */
$Q = array_map(fn($v) => is_string($v) ? trim($v) : '', $_GET);
$w = ['1=1']; $args = [];
if (!empty($Q['status']) && isset(ORDER_STATUS[$Q['status']])) { $w[] = 'o.status = ?'; $args[] = $Q['status']; }
if (!empty($Q['attn'])) $w[] = "o.status <> 'CANCELLED' AND ((o.stock_issue IS NOT NULL AND o.stock_issue <> '') OR EXISTS (SELECT 1 FROM payments p WHERE p.order_id = o.id AND p.status = 'PENDING' AND p.purpose = 'ORDER'))";
if (!empty($Q['q'])) { $w[] = '(o.ref LIKE ? OR o.name LIKE ? OR o.email LIKE ? OR o.phone LIKE ? OR o.company LIKE ?)'; $like = '%' . $Q['q'] . '%'; array_push($args, $like, $like, $like, $like, $like); }
if (!empty($Q['from'])) { $w[] = 'o.created >= ?'; $args[] = $Q['from']; }
if (!empty($Q['to'])) { $w[] = 'o.created <= ?'; $args[] = $Q['to'] . 'T23:59:59'; }
$where = implode(' AND ', $w);
if (($Q['export'] ?? '') === 'csv') {
    $s = $pdo->prepare("SELECT * FROM orders o WHERE $where ORDER BY o.id DESC"); $s->execute($args);
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="kvo-orders-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Order', 'Date', 'Customer', 'Company', 'Email', 'Phone', 'State', 'Items', 'Subtotal (NGN)', 'Discount', 'Delivery', 'Total', 'Paid', 'Refunded', 'Payment status', 'Method', 'Delivery status']);
    foreach ($s as $o) { $it = implode('; ', array_map(fn($i) => $i['name'] . ' x' . $i['qty'], order_items((int)$o['id']))); fputcsv($out, [$o['ref'], $o['created'], $o['name'], $o['company'], $o['email'], $o['phone'], $o['addr_state'], $it, $o['subtotal'] / 100, $o['discount'] / 100, $o['delivery_charge'] / 100, $o['total'] / 100, $o['paid'] / 100, $o['refunded'] / 100, ORDER_STATUS[$o['status']] ?? $o['status'], PAY_LABEL[$o['method']] ?? $o['method'], DELIVERY_STATUS[$o['d_status']] ?? '']); }
    exit;
}
$per = 30; $page = max(1, (int)($Q['page'] ?? 1));
$cnt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total),0) FROM orders o WHERE $where"); $cnt->execute($args); [$total, $sum] = $cnt->fetch(PDO::FETCH_NUM);
$s = $pdo->prepare("SELECT * FROM orders o WHERE $where ORDER BY o.id DESC LIMIT $per OFFSET " . (($page - 1) * $per)); $s->execute($args);
$counts = []; foreach ($pdo->query('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $counts[$r['status']] = $r['n'];
admin_open('Orders');
echo ahead('Orders', (int)$total . ' order' . ((int)$total === 1 ? '' : 's') . ' · ' . naira($sum), '<a class="btn out sm" href="' . h(aurl('orders' . qsx($Q, ['export' => 'csv', 'page' => '']))) . '">Export CSV</a>');
echo '<div class="chip-row" style="margin-bottom:1rem"><a class="chip' . (empty($Q['status']) && empty($Q['attn']) ? ' on' : '') . '" href="' . h(aurl('orders')) . '">All</a><a class="chip' . (!empty($Q['attn']) ? ' on' : '') . '" href="' . h(aurl('orders?attn=1')) . '">Needs attention (' . attn_count() . ')</a>';
foreach (ORDER_STATUS as $k => $l) echo '<a class="chip' . (($Q['status'] ?? '') === $k ? ' on' : '') . '" href="' . h(aurl('orders?status=' . $k)) . '">' . h($l) . ' (' . (int)($counts[$k] ?? 0) . ')</a>';
echo '</div><form class="row" style="margin-bottom:1rem"><input type="hidden" name="status" value="' . h($Q['status'] ?? '') . '"><input class="in grow" name="q" value="' . h($Q['q'] ?? '') . '" placeholder="Order number, name, email, phone, company" style="max-width:360px"><input class="in" type="date" name="from" value="' . h($Q['from'] ?? '') . '" style="width:auto" aria-label="From"><input class="in" type="date" name="to" value="' . h($Q['to'] ?? '') . '" style="width:auto" aria-label="To"><button class="btn dark sm">Search</button></form>';
echo panel('Orders', admin_orders_table($s->fetchAll()) . admin_pager((int)$total, $page, $per, $Q));
admin_close();
