<?php
require __DIR__ . '/includes/bootstrap.php';

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

if (is_post()) {
    csrf_check();
    $action = post('action');
    $msg = ''; $ok = true;
    switch ($action) {
        case 'add':
            [$ok, $msg] = cart_add((int)post('product_id'), max(1, (int)post('qty', 1)));
            if ($ok && post('buy_now')) {
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'redirect' => url('checkout.php')]); exit; }
                redirect('checkout.php');
            }
            break;
        case 'update':
            foreach ((array)post('qty', []) as $id => $q) cart_set((int)$id, (int)$q);
            $msg = 'Cart updated.';
            break;
        case 'remove':
            cart_set((int)post('product_id'), 0);
            $msg = 'Item removed.';
            break;
        case 'clear':
            cart_clear();
            $msg = 'Cart cleared.';
            break;
    }
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $msg, 'count' => cart_count()]);
        exit;
    }
    if ($msg) flash($ok ? 'success' : 'error', $msg);
    $back = $action === 'add' ? ($_SERVER['HTTP_REFERER'] ?? url('cart.php')) : url('cart.php');
    header('Location: ' . $back);
    exit;
}

$cart = cart_details();
$freeMin = (int)setting('free_delivery_min');
$minOrder = (int)setting('min_order');
$pageTitle = 'Your cart';
$bodyClass = $cart['items'] ? 'has-sticky' : '';
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="page-head"><h1>Your cart <?php if ($cart['count']): ?><span class="muted" style="font-size:.6em;font-weight:600">(<?= $cart['count'] ?> item<?= $cart['count'] > 1 ? 's' : '' ?>)</span><?php endif; ?></h1></div>

  <?php if (!$cart['items']): ?>
    <div class="card empty">
      <i class="fa-solid fa-cart-shopping"></i>
      <h3>Your cart is empty</h3>
      <p>Browse our products and add items you love.</p>
      <a class="btn btn-primary" href="<?= url('shop.php') ?>">Start shopping</a>
    </div>
  <?php else: ?>
  <div class="cart-layout">
    <div class="card">
      <form method="post" id="cart-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="update">
        <?php foreach ($cart['items'] as $it): $p = $it['product']; ?>
        <div class="cart-item">
          <a href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>"><img src="<?= e(product_img($p)) ?>" alt="<?= e($p['name']) ?>"></a>
          <div>
            <a class="ci-name" href="<?= url('product.php?slug=' . urlencode($p['slug'])) ?>"><?= e($p['name']) ?></a>
            <div class="ci-unit"><?= e($p['unit']) ?> · <?= money($p['price']) ?> each</div>
            <div class="ci-row">
              <div class="qty qty-sm">
                <button type="button" data-step="-" aria-label="Decrease">−</button>
                <input type="number" class="js-autosubmit" name="qty[<?= (int)$p['id'] ?>]" value="<?= $it['qty'] ?>" min="0" max="<?= (int)$p['stock'] ?>" inputmode="numeric" aria-label="Quantity">
                <button type="button" data-step="+" aria-label="Increase">+</button>
              </div>
              <span class="ci-price"><?= money($it['total']) ?></span>
            </div>
            <button class="ci-remove" type="submit" form="rm-<?= (int)$p['id'] ?>"><i class="fa-regular fa-trash-can"></i> Remove</button>
          </div>
        </div>
        <?php endforeach; ?>
        <noscript><button class="btn btn-outline btn-sm" type="submit">Update cart</button></noscript>
      </form>
      <?php foreach ($cart['items'] as $it): ?>
        <form method="post" id="rm-<?= (int)$it['product']['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= (int)$it['product']['id'] ?>"></form>
      <?php endforeach; ?>
      <div style="display:flex;justify-content:space-between;gap:10px;margin-top:12px;flex-wrap:wrap">
        <a class="btn btn-ghost" href="<?= url('shop.php') ?>"><i class="fa-solid fa-arrow-left"></i> Continue shopping</a>
        <form method="post" onsubmit="return confirm('Remove all items from your cart?')"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn btn-ghost" style="color:var(--muted)" type="submit">Clear cart</button></form>
      </div>
    </div>

    <aside class="card summary">
      <h3>Order summary</h3>
      <div class="row"><span>Subtotal</span><strong><?= money($cart['subtotal']) ?></strong></div>
      <div class="row"><span>Delivery</span><span class="muted">Calculated at checkout</span></div>
      <?php if ($freeMin > 0): ?>
        <?php if ($cart['subtotal'] >= $freeMin): ?>
          <div class="alert alert-success" style="margin:10px 0"><span><i class="fa-solid fa-gift"></i> You qualify for free delivery!</span></div>
        <?php else: $left = $freeMin - $cart['subtotal']; $pctBar = min(100, round($cart['subtotal'] / $freeMin * 100)); ?>
          <div style="margin:10px 0;font-size:13.5px">Add <strong><?= money($left) ?></strong> more for free delivery
            <div style="height:7px;background:var(--line);border-radius:6px;margin-top:6px;overflow:hidden"><div style="height:100%;width:<?= $pctBar ?>%;background:var(--primary)"></div></div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
      <div class="row total"><span>Total</span><span><?= money($cart['subtotal']) ?></span></div>
      <?php if ($minOrder > 0 && $cart['subtotal'] < $minOrder): ?>
        <div class="alert alert-warning" style="margin-top:10px"><span>Minimum order is <?= money($minOrder) ?>.</span></div>
      <?php else: ?>
        <a class="btn btn-primary btn-lg btn-block" style="margin-top:12px" href="<?= url('checkout.php') ?>">Proceed to checkout <i class="fa-solid fa-arrow-right"></i></a>
      <?php endif; ?>
      <a class="btn btn-wa btn-block" style="margin-top:10px" href="<?= e(wa_link(implode("\n", array_merge(['Hello ' . setting('site_name') . ', I want to order:'], array_map(fn($i) => '• ' . $i['product']['name'] . ' x' . $i['qty'] . ' — ' . money($i['total']), $cart['items']), ['Total: ' . money($cart['subtotal'])])))) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Order via WhatsApp</a>
    </aside>
  </div>

  <div class="sticky-total">
    <div><small>Subtotal</small><strong><?= money($cart['subtotal']) ?></strong></div>
    <?php if (!($minOrder > 0 && $cart['subtotal'] < $minOrder)): ?>
      <a class="btn btn-primary" href="<?= url('checkout.php') ?>">Checkout <i class="fa-solid fa-arrow-right"></i></a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
