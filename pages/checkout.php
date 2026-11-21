<?php
/** Checkout: contact, address, delivery option, payment, promo. */

function co_draft(): array
{
    if (empty($_SESSION['co'])) {
        $u = current_user(); $a = $u ? (user_addresses((int)$u['id'])[0] ?? null) : null;
        $_SESSION['co'] = ['state' => $a['state'] ?? '', 'method' => '', 'pay' => array_key_first(pay_methods()), 'promo' => '', 'email' => $u['email'] ?? ''];
    }
    return $_SESSION['co'];
}
function co_compute(): array
{
    $d = co_draft();
    $lines = array_values(array_filter(cart_lines(), fn($l) => !$l['problem']));
    $heavy = (bool)array_filter($lines, fn($l) => $l['p']['dq']);
    $opts = delivery_options($heavy, $d['state']);
    $methods = array_column($opts, 'method');
    if (!in_array($d['method'], $methods, true)) {
        $d['method'] = '';
        foreach ($opts as $o) if ($o['method'] !== 'PICKUP') { $d['method'] = $o['method']; break; }
        if ($d['method'] === '' && $opts) $d['method'] = $opts[0]['method'];
    }
    $chosen = null; foreach ($opts as $o) if ($o['method'] === $d['method']) $chosen = $o;
    $sub = 0; foreach ($lines as $l) $sub += $l['unit'] * $l['qty'];
    $discount = 0; $promoMsg = '';
    if ($d['promo'] !== '') { $r = eval_promo($d['promo'], $sub, strtolower($d['email'])); if ($r['ok']) $discount = $r['discount']; else $promoMsg = $r['reason']; }
    $t = totals($lines, $discount, $chosen && $chosen['fee'] ? $chosen['fee'] : 0);
    $_SESSION['co'] = $d;
    return compact('lines', 'opts', 'chosen', 't', 'd', 'promoMsg', 'heavy');
}
function co_summary_html(array $c): string
{
    ['lines' => $lines, 'chosen' => $chosen, 't' => $t, 'd' => $d] = $c;
    $h = '<h2 style="font-size:1.2rem">Order summary</h2><ul style="list-style:none;padding:0;margin:1rem 0 0">';
    foreach ($lines as $l) $h .= '<li class="row between sm" style="flex-wrap:nowrap;padding:.4rem 0;border-bottom:1px solid var(--steel)"><span>' . h($l['p']['name']) . ' <span class="muted">× ' . $l['qty'] . '</span></span><span class="tab">' . naira($l['unit'] * $l['qty']) . '</span></li>';
    $h .= '</ul><div class="mt3">' . pl(array_filter([
        ['Subtotal', naira($t['subtotal'])],
        $t['discount'] ? ['Discount (' . h($d['promo']) . ')', '−' . naira($t['discount'])] : null,
        (int)setting('tax_bps') ? [h(setting('tax_label')) . (setting('prices_include_tax') === '1' ? ' (included)' : ''), naira($t['tax'])] : null,
        ['Delivery', !$chosen ? '<span class="muted" style="font-weight:400">Choose your state</span>' : ($chosen['fee'] === null ? '<span class="muted" style="font-weight:400">Quoted after order</span>' : ($chosen['fee'] === 0 ? 'Free (collection)' : naira($chosen['fee'])))],
    ])) . '</div><div class="row between mt3"><span class="b">Total due now</span><span class="price">' . naira($t['total']) . '</span></div>';
    if ($chosen && $chosen['fee'] === null) $h .= '<p class="xs muted mt2">You pay for the items now. We send a delivery quote for your approval before dispatch.</p>';
    return $h;
}
function co_delivery_html(array $c): string
{
    if ($c['d']['state'] === '') {
        $h = '<p class="sm muted">Select your state to see delivery options.</p>';
        if (setting('pickup_enabled') === '1') $h .= '<p class="sm muted mt1">Collection from KVO Mart is always available.</p>';
        return $h;
    }
    $h = '';
    foreach ($c['opts'] as $o) {
        $on = $o['method'] === $c['d']['method'];
        $h .= '<label class="radio' . ($on ? ' on' : '') . '"><input type="radio" name="delivery_method" value="' . h($o['method']) . '"' . ($on ? ' checked' : '') . ' data-co><span class="grow"><span class="row between"><span class="b">' . h($o['label']) . '</span><span class="tab b">' . ($o['fee'] === null ? 'Quoted' : ($o['fee'] === 0 ? 'Free' : naira($o['fee']))) . '</span></span><span class="sm muted" style="display:block">' . h($o['basis']) . ($o['lead'] ? ' · about ' . $o['lead'] . ' working days' : '') . '</span></span></label>';
    }
    return $h . ferr('delivery_method');
}

// ---------- live refresh (state / delivery / promo changes) ----------
if ($action === 'summary') {
    $d = co_draft();
    foreach (['state', 'method', 'promo', 'email'] as $k) if (isset($_GET[$k])) $d[$k] = $k === 'promo' ? strtoupper(trim($_GET[$k])) : trim($_GET[$k]);
    if (isset($_GET['state']) && $_GET['state'] !== ($_SESSION['co']['state'] ?? '') && !isset($_GET['method'])) $d['method'] = '';
    if (isset($_GET['state']) && !in_array($d['state'], STATES, true)) $d['state'] = '';
    $_SESSION['co'] = $d;
    $c = co_compute();
    header('Content-Type: application/json');
    echo json_encode(['delivery' => co_delivery_html($c), 'summary' => co_summary_html($c), 'total' => $c['t']['total'],
        'promo' => $c['d']['promo'] === '' ? '' : ($c['promoMsg'] ?: 'Code ' . $c['d']['promo'] . ' applied: −' . naira($c['t']['discount'])), 'promoOk' => $c['d']['promo'] !== '' && !$c['promoMsg']]);
    exit;
}
if ($action) not_found();

$all = cart_lines();
if (is_post()) {
    require_csrf();
    if (!$all) redirect('/cart');
    $v = array_map(fn($x) => is_string($x) ? trim($x) : $x, $_POST);
    $d = co_draft();
    $d['state'] = in_array($v['state'] ?? '', STATES, true) ? $v['state'] : '';
    $d['method'] = $v['delivery_method'] ?? '';
    $d['pay'] = $v['pay'] ?? '';
    $d['email'] = $v['email'] ?? '';
    $d['promo'] = strtoupper($v['promo'] ?? '');
    $_SESSION['co'] = $d;
    $e = [];
    if (mb_strlen($v['name'] ?? '') < 2) $e['name'] = 'Enter your full name.';
    if (!email_ok($v['email'] ?? '')) $e['email'] = 'Enter a valid email address.';
    if (!phone_ok($v['phone'] ?? '')) $e['phone'] = 'Enter a phone number we can reach you on.';
    $pickup = ($v['delivery_method'] ?? '') === 'PICKUP';
    if (!$pickup && mb_strlen($v['line1'] ?? '') < 3) $e['line1'] = 'Enter the street address.';
    if (!$pickup && mb_strlen($v['city'] ?? '') < 2) $e['city'] = 'Enter the city or town.';
    if ($d['state'] === '') $e['state'] = 'Select a state.';
    if ($d['state'] !== '' && $d['method'] === '') $e['delivery_method'] = 'Choose a delivery method.';
    if (!isset(pay_methods()[$d['pay']])) $e['pay'] = "Choose how you'll pay.";
    if (empty($v['terms'])) $e['terms'] = 'Please accept the terms to continue.';
    $c = co_compute();
    if ($d['promo'] !== '' && $c['promoMsg']) $e['promo'] = $c['promoMsg'];
    if ($e) fail($e, '/checkout');
    try {
        $o = create_order(['lines' => array_map(fn($l) => ['pid' => $l['pid'], 'qty' => $l['qty']], $c['lines']), 'user_id' => current_user()['id'] ?? null,
            'email' => $v['email'], 'name' => $v['name'], 'phone' => $v['phone'], 'company' => $v['company'] ?? '', 'delivery_method' => $d['method'],
            'address' => ['line1' => $v['line1'] ?: 'Collection', 'city' => $v['city'] ?: '—', 'state' => $d['state'], 'instr' => $v['instr'] ?? ''],
            'method' => $d['pay'], 'promo' => $d['promo'] ?: null, 'notes' => $v['notes'] ?? '', 'expected_total' => (int)($v['expected_total'] ?? 0)]);
    } catch (DomainException $ex) {
        fail([], '/checkout', $ex->getMessage(), 'warn');
    } catch (RuntimeException $ex) {
        fail([], '/checkout', $ex->getMessage());
    }
    // save address for signed-in customers
    if (($u = current_user()) && !empty($v['save_addr']) && !$pickup) {
        $has = (bool)user_addresses((int)$u['id']);
        db()->prepare('INSERT INTO addresses (user_id, label, name, phone, line1, city, state, is_default) VALUES (?,?,?,?,?,?,?,?)')->execute([$u['id'], 'Delivery address', $v['name'], $v['phone'], $v['line1'], $v['city'], $d['state'], $has ? 0 : 1]);
    }
    $_SESSION['cart'] = []; unset($_SESSION['co']);
    $_SESSION['order_access'][$o['ref']] = 1;
    $link = order_link($o);
    send_email($o['email'], 'Order received — ' . $o['ref'], "Thanks, {$o['name']}. We've received order {$o['ref']} for " . naira($o['total']) . '. ' . ($o['method'] === 'BANK_TRANSFER' ? 'Please complete your bank transfer using the payment reference on your order page.' : 'Complete payment to confirm your order.') . ($o['delivery_method'] === 'QUOTED' ? " We'll send a delivery quote for your approval." : ''), $link);
    send_email(admin_email(), 'New order ' . $o['ref'], "{$o['name']} placed order {$o['ref']} for " . naira($o['total']) . ' (' . PAY_LABEL[$o['method']] . ').', '/admin/orders/' . $o['ref']);
    $pay = new_payment($o, $o['method']);
    if ($o['method'] === 'BANK_TRANSFER') redirect($link . '&placed=1');
    try { redirect(start_online_payment($o, $pay)); }
    catch (RuntimeException $ex) { flash($ex->getMessage() . ' Your order is saved — you can try again or switch to bank transfer below.', 'warn'); redirect($link); }
}

page_open('Checkout', ['noindex' => true]);
echo '<div class="page py">';
if (!$all) { echo '<h1>Checkout</h1><div class="mt8">' . empty_state('Your cart is empty', '', '<a class="btn" href="' . h(url('/equipment')) . '">Browse equipment</a>') . '</div></div>'; page_close(); return; }
if (array_filter($all, fn($l) => $l['problem'])) { echo '<h1>Checkout</h1><div class="mt6">' . note('Some items in your cart need attention', 'Go back to your cart to fix them.', 'warn') . '</div><a class="btn mt6" href="' . h(url('/cart')) . '">Back to cart</a></div>'; page_close(); return; }

$u = current_user() ?: [];
$addrs = $u ? user_addresses((int)$u['id']) : [];
$a = $addrs[0] ?? [];
$c = co_compute(); $d = $c['d'];
$pm = pay_methods();
?>
<?= crumbs([['Cart', '/cart'], ['Checkout']]) ?><h1 class="mt4">Checkout</h1>
<?php if (!$u): ?><p class="sm muted mt2">Checking out as a guest. <a class="link" href="<?= h(url('/account/login?next=/checkout')) ?>">Sign in</a> to use saved addresses and see orders in your account.</p><?php endif; ?>
<?= form_msg() ?>
<form method="post" novalidate class="split sum mt8" id="co" data-summary="<?= h(url('/checkout/summary')) ?>"><?= csrf_field() ?>
<div>
  <section class="box"><h2 style="font-size:1.15rem">Contact details</h2><div class="grid g2 mt4" style="gap:0 1rem">
    <?= field('Full name', 'name', inp('name', $u['name'] ?? '', 'autocomplete="name"')) ?>
    <?= field('Email', 'email', inp('email', $u['email'] ?? $d['email'], 'type="email" autocomplete="email" data-co-email'), ['hint' => 'Your order confirmation goes here.']) ?>
    <?= field('Phone', 'phone', inp('phone', $u['phone'] ?? '', 'type="tel" autocomplete="tel"')) ?>
    <?= field('Company', 'company', inp('company', $u['company'] ?? ''), ['optional' => true]) ?></div></section>
  <section class="box mt6"><h2 style="font-size:1.15rem">Delivery address</h2><div class="mt4">
    <?php if ($addrs): ?><div class="f"><label class="l" for="co-saved">Saved address</label><select class="in" id="co-saved" data-co-saved><option value="">Enter a new address</option>
      <?php foreach ($addrs as $x): ?><option value="<?= (int)$x['id'] ?>" data-line1="<?= h($x['line1']) ?>" data-city="<?= h($x['city']) ?>" data-state="<?= h($x['state']) ?>" <?= $x['is_default'] ? 'selected' : '' ?>><?= h($x['label']) ?> — <?= h($x['line1']) ?>, <?= h($x['city']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <?= field('Street address', 'line1', inp('line1', $a['line1'] ?? '', 'autocomplete="address-line1"')) ?>
    <div class="grid g2" style="gap:0 1rem"><?= field('City / town', 'city', inp('city', $a['city'] ?? '', 'autocomplete="address-level2"')) ?><?= field('State', 'state', sel('state', states_opts(), $d['state'], 'data-co-state autocomplete="address-level1"')) ?></div>
    <?= field('Delivery instructions', 'instr', ta('instr', '', 'rows="2" placeholder="Site access, contact on arrival, offloading equipment available…"'), ['optional' => true]) ?>
    <?php if ($u): ?><label class="chk"><input type="checkbox" name="save_addr" value="1" <?= $addrs ? '' : 'checked' ?>> Save this address to my account</label><?php endif; ?>
  </div></section>
  <section class="box mt6"><h2 style="font-size:1.15rem">Delivery method</h2><div id="co-delivery" class="radio-group mt4"><?= co_delivery_html($c) ?></div></section>
  <section class="box mt6"><h2 style="font-size:1.15rem">Payment</h2><div class="radio-group mt4">
    <?php foreach ($pm as $k => [$l, $bdy]): $on = $d['pay'] === $k; ?><label class="radio<?= $on ? ' on' : '' ?>"><input type="radio" name="pay" value="<?= $k ?>"<?= $on ? ' checked' : '' ?> data-co-pay><span><span class="b" style="display:block"><?= h($l) ?></span><span class="sm muted"><?= h($bdy) ?></span></span></label><?php endforeach; ?>
    <?= ferr('pay') ?></div>
    <?php if (!online_provider()): ?><p class="xs muted mt2">Online card payment will appear here once it is switched on.</p><?php endif; ?></section>
  <section class="box mt6">
    <div class="f"><label class="l" for="promo">Promo code</label><div class="row" style="flex-wrap:nowrap;gap:0"><input class="in<?= bad('promo') ?>" id="promo" name="promo" value="<?= h($d['promo']) ?>" placeholder="e.g. KVO10" style="text-transform:uppercase" autocomplete="off"><button type="button" class="btn dark" data-co-promo>Apply</button></div><p id="co-promomsg" class="hint" aria-live="polite"><?= $d['promo'] !== '' ? h($c['promoMsg'] ?: 'Code ' . $d['promo'] . ' applied: −' . naira($c['t']['discount'])) : '' ?></p><?= ferr('promo') ?></div>
    <?= field('Order notes', 'notes', ta('notes', '', 'rows="2"'), ['optional' => true]) ?>
    <label class="chk"><input type="checkbox" name="terms" value="1"> <span>I agree to the <a href="<?= h(url('/terms')) ?>" target="_blank">terms and conditions</a> and the <a href="<?= h(url('/delivery-policy')) ?>" target="_blank">delivery policy</a>.</span></label><?= ferr('terms') ?>
  </section>
</div>
<aside><div class="plate box" style="position:sticky;top:120px"><div id="co-summary"><?= co_summary_html($c) ?></div><input type="hidden" name="expected_total" id="co-expected" value="<?= (int)$c['t']['total'] ?>">
  <div class="mt6"><button class="btn full" style="min-height:52px" data-busy="Placing order…">Place order</button></div><p class="xs muted mt2" style="text-align:center">Prices and stock are confirmed when you place the order.</p></div></aside>
</form></div>
<?php page_close();
