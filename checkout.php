<?php
require __DIR__ . '/includes/bootstrap.php';

$pdo  = db();
$cart = cart_details();
if (!$cart['items']) { flash('info', 'Your cart is empty.'); redirect('cart.php'); }

$minOrder = (int)setting('min_order');
if ($minOrder > 0 && $cart['subtotal'] < $minOrder) { flash('warning', 'Minimum order is ' . money($minOrder) . '.'); redirect('cart.php'); }

$zones   = $pdo->query('SELECT * FROM delivery_zones WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$freeMin = (int)setting('free_delivery_min');
$pickup  = setting('pickup_enabled') === '1';
$methods = [];
if (setting('pay_flutterwave') === '1' && setting('flw_public_key') && setting('flw_secret_key')) $methods['flutterwave'] = ['Pay online (card, transfer, USSD)', 'Secured by Flutterwave', 'fa-credit-card'];
if (setting('pay_transfer') === '1') $methods['transfer'] = ['Bank transfer', 'Transfer to our account; we confirm and dispatch', 'fa-building-columns'];
if (setting('pay_pod') === '1') $methods['pod'] = ['Pay on delivery', 'Cash or transfer when your order arrives', 'fa-money-bill-wave'];
if (setting('pay_whatsapp') === '1') $methods['whatsapp'] = ['Order via WhatsApp', 'Send your order to us and finish on WhatsApp', 'fa-brands fa-whatsapp'];
if (!$methods) $methods['pod'] = ['Pay on delivery', 'Cash or transfer when your order arrives', 'fa-money-bill-wave'];

$c = customer();
$old = $_SESSION['checkout_old'] ?? [];
unset($_SESSION['checkout_old']);
$val = fn($k, $d = '') => e($old[$k] ?? ($c[$k] ?? $d));

if (is_post()) {
    csrf_check();
    $d = [
        'name'    => mb_substr(post('name'), 0, 150),
        'email'   => mb_substr(post('email'), 0, 190),
        'phone'   => mb_substr(post('phone'), 0, 40),
        'address' => mb_substr(post('address'), 0, 500),
        'city'    => mb_substr(post('city'), 0, 100),
        'state'   => mb_substr(post('state'), 0, 100),
        'notes'   => mb_substr(post('notes'), 0, 1000),
        'delivery_method' => post('delivery_method') === 'pickup' && $pickup ? 'pickup' : 'delivery',
        'zone_id' => (int)post('zone_id'),
        'payment_method' => post('payment_method'),
    ];
    $errors = [];
    if ($d['name'] === '') $errors[] = 'Enter your full name.';
    if (!preg_match('/^\+?[0-9 ()-]{7,20}$/', $d['phone'])) $errors[] = 'Enter a valid phone number.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!isset($methods[$d['payment_method']])) $errors[] = 'Choose a payment method.';
    if ($d['payment_method'] === 'flutterwave' && $d['email'] === '') $errors[] = 'An email address is required for online payment.';

    $zone = null;
    if ($d['delivery_method'] === 'delivery') {
        if ($d['address'] === '' || $d['city'] === '' || $d['state'] === '') $errors[] = 'Enter your delivery address, city and state.';
        foreach ($zones as $z) if ((int)$z['id'] === $d['zone_id']) $zone = $z;
        if (!$zone) $errors[] = 'Choose your delivery zone.';
    }

    if ($errors) {
        foreach ($errors as $er) flash('error', $er);
        $_SESSION['checkout_old'] = $d;
        redirect('checkout.php');
    }

    $fee = 0;
    if ($d['delivery_method'] === 'delivery') {
        $fee = (int)$zone['fee'];
        if ($freeMin > 0 && $cart['subtotal'] >= $freeMin) $fee = 0;
    }

    $pdo->beginTransaction();
    try {
        // reserve stock atomically
        $dec = $pdo->prepare('UPDATE products SET stock = stock - ?, sold = sold + ? WHERE id = ? AND stock >= ?');
        $log = $pdo->prepare('INSERT INTO stock_log (product_id, qty_change, stock_after, reason, admin_id, created_at) VALUES (?,?,?,?,NULL,?)');
        $orderNo = new_order_no();
        $subtotal = 0;
        foreach ($cart['items'] as $it) {
            $dec->execute([$it['qty'], $it['qty'], $it['product']['id'], $it['qty']]);
            if ($dec->rowCount() === 0) throw new RuntimeException('Sorry, "' . $it['product']['name'] . '" just sold out or has less stock than requested. Please review your cart.');
            $after = $pdo->query('SELECT stock FROM products WHERE id = ' . (int)$it['product']['id'])->fetchColumn();
            $log->execute([$it['product']['id'], -$it['qty'], (int)$after, 'Order ' . $orderNo, now()]);
            $subtotal += $it['total'];
        }
        $total = $subtotal + $fee;
        $txRef = $orderNo . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

        $pdo->prepare('INSERT INTO orders (order_no, customer_id, name, email, phone, address, city, state, notes, delivery_method, zone_id, zone_name, subtotal, delivery_fee, total, payment_method, payment_status, status, tx_ref, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$orderNo, $c['id'] ?? null, $d['name'], $d['email'], $d['phone'], $d['address'], $d['city'], $d['state'], $d['notes'], $d['delivery_method'], $zone['id'] ?? null, $zone['name'] ?? 'Store pickup', $subtotal, $fee, $total, $d['payment_method'], 'unpaid', 'pending', $txRef, now(), now()]);
        $orderId = (int)$pdo->lastInsertId();
        $oi = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, price, qty, total) VALUES (?,?,?,?,?,?)');
        foreach ($cart['items'] as $it) {
            $nm = $it['product']['name'] . ($it['product']['unit'] ? ' (' . $it['product']['unit'] . ')' : '');
            $oi->execute([$orderId, $it['product']['id'], $nm, $it['product']['price'], $it['qty'], $it['total']]);
        }
        order_add_history($orderId, 'pending', 'Order placed (' . (payment_methods()[$d['payment_method']] ?? $d['payment_method']) . ')');
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash('error', $ex instanceof RuntimeException ? $ex->getMessage() : 'Could not place your order. Please try again.');
        if (DEBUG && !($ex instanceof RuntimeException)) flash('error', $ex->getMessage());
        $_SESSION['checkout_old'] = $d;
        redirect('cart.php');
    }

    // remember details for next time
    if ($c && (!$c['address'] || !$c['phone'])) {
        $pdo->prepare('UPDATE customers SET phone = COALESCE(NULLIF(phone, \'\'), ?), address = COALESCE(NULLIF(address, \'\'), ?), city = COALESCE(NULLIF(city, \'\'), ?), state = COALESCE(NULLIF(state, \'\'), ?) WHERE id = ?')
            ->execute([$d['phone'], $d['address'], $d['city'], $d['state'], $c['id']]);
    }

    cart_clear();
    $_SESSION['my_orders'][] = $orderNo;

    $order = $pdo->query('SELECT * FROM orders WHERE id = ' . $orderId)->fetch();
    $items = order_items($orderId);
    send_mail(setting('notify_email'), 'New order ' . $orderNo . ' — ' . money($order['total']), '<p>A new order was placed by ' . e($order['name']) . ' (' . e($order['phone']) . ').</p>' . order_email_html($order, $items));
    if ($order['email']) send_mail($order['email'], 'We received your order ' . $orderNo, '<p>Hi ' . e($order['name']) . ', thank you for shopping with us!</p>' . order_email_html($order, $items));

    if ($d['payment_method'] === 'flutterwave') {
        $logo = setting('logo') ? full_url(setting('logo')) : null;
        $res = flw_request('POST', 'payments', [
            'tx_ref'          => $txRef,
            'amount'          => $total,
            'currency'        => 'NGN',
            'redirect_url'    => full_url('pay/flutterwave.php'),
            'payment_options' => 'card,banktransfer,ussd',
            'customer'        => ['email' => $d['email'], 'phonenumber' => $d['phone'], 'name' => $d['name']],
            'customizations'  => array_filter(['title' => setting('site_name'), 'description' => 'Order ' . $orderNo, 'logo' => $logo]),
            'meta'            => ['order_no' => $orderNo],
        ]);
        if ($res && ($res['status'] ?? '') === 'success' && !empty($res['data']['link'])) {
            header('Location: ' . $res['data']['link']);
            exit;
        }
        flash('warning', 'Your order was saved but we could not start the online payment. You can retry payment below or choose another option.');
    }

    redirect(order_view_url($orderNo) . '&new=1');
}

$pageTitle = 'Checkout';
$bodyClass = 'has-sticky';
$selZone   = (int)($old['zone_id'] ?? 0);
$selMethod = $old['delivery_method'] ?? 'delivery';
$selPay    = $old['payment_method'] ?? array_key_first($methods);
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="page-head">
    <div class="crumbs"><a href="<?= url('cart.php') ?>">Cart</a> / <span>Checkout</span></div>
    <h1>Checkout</h1>
    <?php if (!$c): ?><p class="muted" style="font-size:14px">Have an account? <a href="<?= url('login.php?next=' . urlencode(url('checkout.php'))) ?>" style="color:var(--primary);font-weight:700">Log in</a> for faster checkout — or continue as a guest.</p><?php endif; ?>
  </div>

  <form method="post" id="checkout-form" class="checkout-layout" data-subtotal="<?= $cart['subtotal'] ?>" data-free="<?= $freeMin ?>" data-currency="<?= e(setting('currency')) ?>" novalidate>
    <?= csrf_field() ?>
    <div style="display:grid;gap:16px">
      <section class="card">
        <h2 class="step-title"><span>1</span> Contact details</h2>
        <div class="form-grid two">
          <div class="field full"><label>Full name <span class="req">*</span></label><input name="name" required autocomplete="name" value="<?= $val('name') ?>"></div>
          <div class="field"><label>Phone number <span class="req">*</span></label><input name="phone" type="tel" required autocomplete="tel" inputmode="tel" placeholder="080..." value="<?= $val('phone') ?>"></div>
          <div class="field"><label>Email <small>(for receipt<?= isset($methods['flutterwave']) ? '; required for online payment' : '' ?>)</small></label><input name="email" type="email" autocomplete="email" inputmode="email" value="<?= $val('email') ?>"></div>
        </div>
      </section>

      <section class="card">
        <h2 class="step-title"><span>2</span> Delivery</h2>
        <div class="options" style="margin-bottom:14px">
          <label class="option"><input type="radio" name="delivery_method" value="delivery" <?= $selMethod !== 'pickup' ? 'checked' : '' ?>><div><strong>Home / office delivery</strong><small>We bring it to your address</small></div><i class="fa-solid fa-truck" style="margin-left:auto;color:var(--primary)"></i></label>
          <?php if ($pickup): ?>
          <label class="option"><input type="radio" name="delivery_method" value="pickup" <?= $selMethod === 'pickup' ? 'checked' : '' ?>><div><strong>Store pickup — free</strong><small><?= e(setting('pickup_address')) ?></small></div><i class="fa-solid fa-store" style="margin-left:auto;color:var(--primary)"></i></label>
          <?php endif; ?>
        </div>
        <div class="js-delivery-only">
          <div class="form-grid two">
            <div class="field full"><label>Street address <span class="req">*</span></label><input name="address" data-required autocomplete="street-address" placeholder="House number, street, landmark" value="<?= $val('address') ?>"></div>
            <div class="field"><label>City / area <span class="req">*</span></label><input name="city" data-required autocomplete="address-level2" value="<?= $val('city') ?>"></div>
            <div class="field"><label>State <span class="req">*</span></label>
              <select name="state" data-required>
                <option value="">Select state</option>
                <?php $cs = $old['state'] ?? ($c['state'] ?? ''); foreach (nigerian_states() as $s): ?><option <?= $cs === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php if ($zones): ?>
          <div class="field" style="margin-top:14px"><label>Delivery zone <span class="req">*</span></label></div>
          <div class="options">
            <?php foreach ($zones as $i => $z): ?>
            <label class="option"><input type="radio" name="zone_id" value="<?= (int)$z['id'] ?>" data-fee="<?= (int)$z['fee'] ?>" <?= $selZone === (int)$z['id'] ? 'checked' : '' ?>>
              <div><strong><?= e($z['name']) ?></strong><?php if ($z['eta']): ?><small><?= e($z['eta']) ?></small><?php endif; ?></div>
              <span class="opt-price"><?= ($freeMin > 0 && $cart['subtotal'] >= $freeMin) ? 'Free' : money($z['fee']) ?></span></label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="field" style="margin-top:14px"><label>Order notes <small>(optional)</small></label><textarea name="notes" placeholder="Delivery instructions, preferred time…" style="min-height:80px"><?= e($old['notes'] ?? '') ?></textarea></div>
      </section>

      <section class="card">
        <h2 class="step-title"><span>3</span> Payment</h2>
        <div class="options">
          <?php foreach ($methods as $k => [$t, $s, $ic]): ?>
          <label class="option"><input type="radio" name="payment_method" value="<?= $k ?>" <?= $selPay === $k ? 'checked' : '' ?>><div><strong><?= e($t) ?></strong><small><?= e($s) ?></small></div><i class="<?= strpos($ic, 'fa-brands') === 0 ? $ic : 'fa-solid ' . $ic ?>" style="margin-left:auto;color:var(--primary);font-size:18px"></i></label>
          <?php endforeach; ?>
        </div>
        <p class="muted" style="font-size:12.5px;margin:12px 0 0">By placing your order you agree to our <a href="<?= url('page.php?slug=terms') ?>" style="text-decoration:underline">terms</a> and <a href="<?= url('page.php?slug=returns') ?>" style="text-decoration:underline">returns policy</a>.</p>
      </section>
    </div>

    <aside class="card summary">
      <h3>Your order (<?= $cart['count'] ?>)</h3>
      <div style="max-height:300px;overflow-y:auto;margin-bottom:8px">
        <?php foreach ($cart['items'] as $it): ?>
        <div class="mini-item"><img src="<?= e(product_img($it['product'])) ?>" alt=""><div class="mi-name"><?= e($it['product']['name']) ?><div class="mi-qty">Qty <?= $it['qty'] ?> × <?= money($it['product']['price']) ?></div></div><strong><?= money($it['total']) ?></strong></div>
        <?php endforeach; ?>
      </div>
      <div class="row"><span>Subtotal</span><strong><?= money($cart['subtotal']) ?></strong></div>
      <div class="row"><span>Delivery</span><strong class="js-fee">Select zone</strong></div>
      <div class="row total"><span>Total</span><span class="js-total"><?= money($cart['subtotal']) ?></span></div>
      <button class="btn btn-primary btn-lg btn-block js-place-btn hide-sm" style="margin-top:12px" type="submit"><i class="fa-solid fa-lock"></i> <span class="js-place-label">Place order</span></button>
      <div class="muted text-center" style="font-size:12.5px;margin-top:10px"><i class="fa-solid fa-shield-halved"></i> Your details are safe with us</div>
    </aside>
  </form>
</div>

<div class="sticky-total">
  <div><small>Total</small><strong class="js-total"><?= money($cart['subtotal']) ?></strong></div>
  <button class="btn btn-primary js-place-btn" type="submit" form="checkout-form"><i class="fa-solid fa-lock"></i> <span class="js-place-label">Place order</span></button>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
