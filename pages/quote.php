<?php
/** Customer view of a quotation: accept, decline, pay. */
$qq = $number ? quote_by_number($number) : null;
$u = current_user();
$mine = $qq && (
    ($u && ((int)$qq['user_id'] === (int)$u['id'] || strtolower($qq['email']) === strtolower($u['email'])))
    || !empty($_SESSION['quote_access'][$qq['number']])
    || (isset($_GET['t']) && hash_equals((string)$qq['token'], (string)$_GET['t']))
    || can('quotations.view'));
if ($qq && $mine) $_SESSION['quote_access'][$qq['number']] = 1;
if (!$qq || $qq['status'] === 'DRAFT' || !$mine) {
    if ($u) not_found();
    page_open('Quotation', ['noindex' => true]);
    echo '<div class="page py" style="max-width:30rem"><h1>Sign in to view this quotation</h1><p class="muted mt2">Use the link in your quotation email, or sign in with the email the quotation was sent to.</p><a class="btn mt6" href="' . h(url('/account/login?next=/quotes/' . rawurlencode((string)$number))) . '">Sign in</a></div>';
    page_close(); return;
}
$back = '/quotes/' . $qq['number'];
if (is_post()) {
    require_csrf();
    $st = quote_status($qq);
    try {
        if (post('do') === 'accept') {
            if (!in_array($st, ['SENT', 'VIEWED'], true)) throw new RuntimeException($st === 'EXPIRED' ? 'This quotation has expired.' : 'This quotation can no longer be accepted.');
            db()->prepare("UPDATE quotations SET status = 'ACCEPTED', accepted_at = ? WHERE id = ?")->execute([now(), $qq['id']]);
            audit('quotation.accepted', $qq['number'], 'by customer');
            send_email(admin_email(), 'Quotation accepted — ' . $qq['number'], "{$qq['name']} accepted {$qq['number']} (" . naira($qq['total']) . ').', '/admin/quotations/' . $qq['id']);
            flash('Quotation accepted. Confirm delivery details and payment below.');
        } elseif (post('do') === 'decline') {
            if (!in_array($st, ['SENT', 'VIEWED'], true)) throw new RuntimeException('This quotation can no longer be declined.');
            db()->prepare("UPDATE quotations SET status = 'DECLINED' WHERE id = ?")->execute([$qq['id']]);
            audit('quotation.declined', $qq['number'], 'by customer');
            send_email(admin_email(), 'Quotation declined — ' . $qq['number'], "{$qq['name']} declined {$qq['number']}.", '/admin/quotations/' . $qq['id']);
            flash('You declined this quotation. Contact us any time if you need a revised offer.');
        } elseif (post('do') === 'pay') {
            if ($qq['status'] !== 'ACCEPTED') throw new RuntimeException('Accept the quotation first.');
            $e = [];
            if (mb_strlen(post('line1')) < 3) $e['line1'] = 'Enter the street address.';
            if (mb_strlen(post('city')) < 2) $e['city'] = 'Enter the city or town.';
            if (!in_array(post('state'), STATES, true)) $e['state'] = 'Select a state.';
            $pay = post('pay'); if (!isset(pay_methods()[$pay])) $e['pay'] = 'Choose a payment method.';
            if ($e) fail($e, $back);
            $o = quote_to_order($qq, ['line1' => post('line1'), 'city' => post('city'), 'state' => post('state'), 'instr' => ''], $pay);
            $p = new_payment($o, $pay);
            if ($pay === 'BANK_TRANSFER') redirect(order_link($o) . '&placed=1');
            try { redirect(start_online_payment($o, $p)); } catch (RuntimeException $ex) { flash($ex->getMessage(), 'warn'); redirect(order_link($o)); }
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'warn'); }
    redirect($back);
}
if ($qq['status'] === 'SENT' && !can('quotations.view')) { db()->prepare("UPDATE quotations SET status = 'VIEWED' WHERE id = ?")->execute([$qq['id']]); $qq['status'] = 'VIEWED'; }
$st = quote_status($qq);
$items = quote_items((int)$qq['id']);
$order = $qq['order_ref'] ? order_by_ref($qq['order_ref']) : null;
$addr = $u ? (user_addresses((int)$u['id'])[0] ?? []) : [];

require_once __DIR__ . '/order_parts.php';
page_open('Quotation ' . $qq['number'], ['noindex' => true]);
?>
<div class="page py" style="max-width:56rem"><div class="noprint"><?= crumbs($u ? [['My account', '/account'], ['Quotations', '/account/quotes'], [$qq['number']]] : [['Home', '/'], [$qq['number']]]) ?></div>
  <div class="box mt6" style="border-top:4px solid var(--gold)"><div class="row between" style="align-items:flex-start">
    <div class="row"><img src="<?= h(asset(setting('logo'))) ?>" alt="" style="height:52px"><div><b style="font-family:var(--disp)"><?= h(setting('business_name')) ?></b><p class="xs muted"><?= h(setting('tagline')) ?></p><?= setting('phone') ? '<p class="xs muted">' . h(setting('phone')) . '</p>' : '' ?></div></div>
    <div style="text-align:right"><h1 style="font-size:1.4rem">Quotation</h1><p class="tab sm"><?= h($qq['number']) ?></p><p class="xs muted">Issued <?= fmt_date($qq['sent_at'] ?: $qq['created']) ?> · Valid until <?= fmt_date($qq['valid_until']) ?></p><div class="mt2"><?= badge(humanize($st), quote_tone($qq)) ?></div></div></div>
    <p class="sm mt6"><span class="muted">Prepared for</span><br><b><?= h($qq['name']) ?></b><?= $qq['company'] ? ' · ' . h($qq['company']) : '' ?><br><?= h($qq['email']) ?><?= $qq['location'] ? ' · ' . h($qq['location']) : '' ?></p>
    <div class="mt6"><?= quote_doc($qq, $items) ?></div></div>
  <div class="noprint mt6">
    <?php if (in_array($st, ['SENT', 'VIEWED'], true)): ?><div class="row">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="accept"><button class="btn">Accept quotation</button></form>
      <form method="post" data-confirm="Decline this quotation?"><?= csrf_field() ?><input type="hidden" name="do" value="decline"><button class="btn out">Decline</button></form>
      <button class="btn ghost" type="button" data-print>Print / save as PDF</button></div><?php endif; ?>
    <?= $st === 'EXPIRED' ? note('This quotation has expired', 'Contact us for an updated quotation. <a class="link" href="' . h(url('/request-quote')) . '">Request a new quote</a>', 'warn') : '' ?>
    <?= $st === 'DECLINED' ? note('You declined this quotation', '') : '' ?>
    <?php if ($st === 'ACCEPTED'): ?><div class="box" style="border-top:3px solid var(--gold)"><h2 style="font-size:1.15rem">Pay for this quotation</h2><p class="sm muted mt1">Confirm where we should deliver and how you'll pay. Prices come from the quotation.</p>
      <form method="post" class="mt4" novalidate><?= csrf_field() ?><input type="hidden" name="do" value="pay"><div class="grid g2" style="gap:0 1rem">
        <?= field('Street address', 'qp-line1', inp('line1', $addr['line1'] ?? '', '', 'qp-line1'), ['name' => 'line1']) ?><?= field('City / town', 'qp-city', inp('city', $addr['city'] ?? '', '', 'qp-city'), ['name' => 'city']) ?>
        <?= field('State', 'qp-state', sel('state', states_opts(), $addr['state'] ?? '', '', 'qp-state'), ['name' => 'state']) ?>
        <?= field('Payment method', 'qp-pay', sel('pay', array_map(fn($k, $v) => [$k, $v[0]], array_keys(pay_methods()), pay_methods()), '', '', 'qp-pay'), ['name' => 'pay']) ?></div>
        <?= form_msg() ?><button class="btn mt4">Continue to payment — <?= naira($qq['total']) ?></button></form></div><?php endif; ?>
    <?= $st === 'CONVERTED' && $order ? note('Order created', 'This quotation became order <a class="link" href="' . h(url(order_link($order))) . '">' . h($order['ref']) . '</a>.', 'ok') : '' ?>
  </div></div>
<?php page_close();
