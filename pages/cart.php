<?php
$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
if (is_post()) {
    if (!csrf_ok()) { if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => 'Session expired — refresh the page.']); exit; } require_csrf(); }
    try {
        if ($action === 'add') {
            add_to_cart((int)post('pid'), max(1, (int)post('qty', 1)));
            if (post('go') === 'buy') { if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'redirect' => url('/checkout')]); exit; } redirect('/checkout'); }
            $msg = 'Added to cart.';
        } elseif ($action === 'update') {
            $pid = (int)post('pid'); $v = (int)post('qty');
            if ($v < 1) throw new RuntimeException('Quantity must be at least 1.');
            $p = prod($pid); $bb = $p ? buyable($p) : ['ok' => false];
            $max = $bb['ok'] ? $bb['max'] : 0;
            $_SESSION['cart'][$pid] = min($v, max(1, $max));
            $msg = $v > $max ? "Only $max available." : 'Cart updated.';
        } elseif ($action === 'remove') {
            unset($_SESSION['cart'][(int)post('pid')]); $msg = 'Removed from cart.';
        } else not_found();
        if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'message' => $msg, 'count' => cart_count()]); exit; }
        flash($msg);
    } catch (RuntimeException $e) {
        if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => $e->getMessage(), 'count' => cart_count()]); exit; }
        flash($e->getMessage(), 'warn');
    }
    redirect($action === 'add' ? back_path('/cart') : '/cart');
}
if ($action) not_found();

$lines = cart_lines();
page_open('Your cart', ['noindex' => true]);
echo '<div class="page py">';
if (!$lines) {
    echo '<h1>Your cart</h1><div class="mt8">' . empty_state('Your cart is empty', 'Add machines, parts or supplies to see them here.', '<a class="btn" href="' . h(url('/equipment')) . '">Browse equipment</a>') . '</div></div>';
    page_close(); return;
}
$ok = array_values(array_filter($lines, fn($l) => !$l['problem']));
$t = $ok ? totals($ok) : null;
$heavy = (bool)array_filter($ok, fn($l) => $l['p']['dq']);
$problems = count($ok) !== count($lines);
?>
<h1>Your cart</h1><p class="sm muted mt1">Prices and stock are checked again when you place your order.</p>
<?php if ($problems): ?><div class="mt4"><?= note('Some items need attention', 'Remove or adjust the highlighted items before checking out.', 'warn') ?></div><?php endif; ?>
<div class="split sum mt8"><ul style="list-style:none;padding:0;margin:0;border-top:1px solid var(--steel)">
<?php foreach ($lines as $l): $p = $l['p']; ?>
  <li style="display:grid;grid-template-columns:88px 1fr auto;gap:1rem;padding:1.25rem 0;border-bottom:1px solid var(--steel)">
    <a href="<?= h(url('/p/' . $p['slug'])) ?>" style="aspect-ratio:1;background:var(--steel100);overflow:hidden"><?= photo($p, 'cover') ?></a>
    <div style="min-width:0"><a class="b" href="<?= h(url('/p/' . $p['slug'])) ?>" style="text-decoration:none"><?= h($p['name']) ?></a><p class="sm muted">SKU <?= h($p['sku']) ?></p>
      <p class="sm tab mt1"><?= $l['unit'] !== null ? naira($l['unit']) . ' each' : 'Price unavailable' ?></p>
      <?php if ($l['problem']): ?><p class="sm" style="color:var(--danger);font-weight:500"><?= h($l['problem']) ?></p><?php endif; ?>
      <div class="row mt3">
        <?php if ($l['max'] > 1): ?><form method="post" action="<?= h(url('/cart/update')) ?>" class="row" style="gap:.4rem"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= $l['pid'] ?>"><label class="sm muted" for="cq-<?= $l['pid'] ?>">Qty</label><input id="cq-<?= $l['pid'] ?>" class="in tab" type="number" name="qty" min="1" max="<?= $l['max'] ?>" value="<?= $l['qty'] ?>" style="width:80px" data-autosubmit inputmode="numeric"><noscript><button class="btn ghost sm">Update</button></noscript></form>
        <?php else: ?><span class="sm muted">Qty <?= $l['qty'] ?></span><?php endif; ?>
        <form method="post" action="<?= h(url('/cart/remove')) ?>"><?= csrf_field() ?><input type="hidden" name="pid" value="<?= $l['pid'] ?>"><button class="btn ghost sm" style="color:var(--danger)">Remove</button></form>
      </div></div>
    <p class="tab b"><?= $l['unit'] !== null && !$l['problem'] ? naira($l['unit'] * $l['qty']) : '—' ?></p>
  </li>
<?php endforeach; ?>
</ul>
<aside class="plate box" style="height:fit-content;border-top:3px solid var(--gold)"><h2 style="font-size:1.2rem">Summary</h2>
  <?php if ($t): ?><div class="mt4"><?= pl(array_filter([['Subtotal', naira($t['subtotal'])], (int)setting('tax_bps') ? [h(setting('tax_label')) . (setting('prices_include_tax') === '1' ? ' (included)' : ''), naira($t['tax'])] : null, ['Delivery', '<span class="muted" style="font-weight:400">' . ($heavy ? 'Quoted after order' : 'Calculated at checkout') . '</span>']])) ?><div class="row between mt3"><span class="b">Total before delivery</span><span class="price"><?= naira($t['total']) ?></span></div></div><?php endif; ?>
  <?php if ($heavy): ?><p class="sm muted mt3">Your cart includes heavy equipment. Delivery is quoted separately for your approval, or you can collect.</p><?php endif; ?>
  <?= $problems || !$t ? '<p class="sm mt4" style="color:var(--danger)">Fix the items marked above to continue.</p>' : '<a class="btn full mt6" href="' . h(url('/checkout')) . '">Continue to checkout</a>' ?>
  <a class="link sm mt4" style="display:block;text-align:center" href="<?= h(url('/equipment')) ?>">Continue shopping</a></aside>
</div></div>
<?php page_close();
