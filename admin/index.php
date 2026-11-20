<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

$today = date('Y-m-d');
$month = date('Y-m');
$q = fn($sql) => $pdo->query($sql)->fetchColumn();
$revToday = (int)$q("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled' AND substr(created_at,1,10) = '$today'");
$revMonth = (int)$q("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled' AND substr(created_at,1,7) = '$month'");
$paidMonth = (int)$q("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status = 'paid' AND substr(created_at,1,7) = '$month'");
$ordersToday = (int)$q("SELECT COUNT(*) FROM orders WHERE substr(created_at,1,10) = '$today'");
$pending = (int)$q("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$products = (int)$q('SELECT COUNT(*) FROM products WHERE is_active = 1');
$customers = (int)$q('SELECT COUNT(*) FROM customers');
$stockValue = (int)$q('SELECT COALESCE(SUM(price * stock),0) FROM products WHERE is_active = 1');
$awaiting = (int)$q("SELECT COUNT(*) FROM orders WHERE payment_status = 'awaiting'");

// last 14 days revenue
$days = [];
for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = 0;
$st = $pdo->prepare("SELECT substr(created_at,1,10) d, SUM(total) t FROM orders WHERE status != 'cancelled' AND created_at >= ? GROUP BY substr(created_at,1,10)");
$st->execute([array_key_first($days) . ' 00:00:00']);
foreach ($st->fetchAll() as $r) if (isset($days[$r['d']])) $days[$r['d']] = (int)$r['t'];
$maxDay = max(1, max($days));

$recent = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 8')->fetchAll();
$low = $pdo->query('SELECT id, name, stock, low_stock, image FROM products WHERE is_active = 1 AND stock <= low_stock ORDER BY stock ASC LIMIT 8')->fetchAll();
$top = $pdo->query('SELECT oi.name, SUM(oi.qty) q, SUM(oi.total) t FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status != \'cancelled\' GROUP BY oi.name ORDER BY q DESC LIMIT 6')->fetchAll();

$adminTitle = 'Dashboard';
require __DIR__ . '/_header.php';
?>
<?php if ($awaiting): ?><div class="alert alert-warning"><span><i class="fa-solid fa-building-columns"></i> <?= $awaiting ?> bank transfer<?= $awaiting > 1 ? 's' : '' ?> waiting for confirmation. <a href="<?= url('admin/orders.php?pay=awaiting') ?>" style="font-weight:800;text-decoration:underline">Review</a></span></div><?php endif; ?>

<div class="stats">
  <div class="stat"><small><i class="fa-solid fa-sack-dollar"></i> Sales today</small><strong><?= money($revToday) ?></strong></div>
  <div class="stat"><small><i class="fa-solid fa-calendar"></i> Sales this month</small><strong><?= money($revMonth) ?></strong><span class="muted" style="font-size:12px"><?= money($paidMonth) ?> paid</span></div>
  <div class="stat"><small><i class="fa-solid fa-receipt"></i> Orders today</small><strong><?= $ordersToday ?></strong><a class="muted" style="font-size:12px" href="<?= url('admin/orders.php?status=pending') ?>"><?= $pending ?> pending</a></div>
  <div class="stat"><small><i class="fa-solid fa-warehouse"></i> Stock value</small><strong><?= money($stockValue) ?></strong><span class="muted" style="font-size:12px"><?= $products ?> products · <?= $customers ?> customers</span></div>
</div>

<div class="split">
  <div>
    <div class="card">
      <div class="card-head"><h3>Sales — last 14 days</h3><span class="muted" style="font-size:13px"><?= money(array_sum($days)) ?></span></div>
      <div class="bars"><?php foreach ($days as $d => $v): ?><div style="height:<?= max(2, round($v / $maxDay * 100)) ?>%" title="<?= e(date('j M', strtotime($d))) ?>: <?= e(money($v)) ?>"><span><?= e(money($v)) ?></span></div><?php endforeach; ?></div>
      <div class="bars-x"><?php foreach ($days as $d => $v): ?><span><?= date('j', strtotime($d)) ?></span><?php endforeach; ?></div>
    </div>

    <div class="card flush-sm">
      <div class="card-head"><h3>Recent orders</h3><a class="btn btn-outline btn-sm" href="<?= url('admin/orders.php') ?>">All orders</a></div>
      <?php if ($recent): ?>
      <table class="table cards">
        <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $o): ?>
          <tr onclick="location.href='<?= url('admin/order-view.php?id=' . $o['id']) ?>'" style="cursor:pointer">
            <td class="t-main"><div><div class="t-name"><?= e($o['order_no']) ?></div><div class="t-sub"><?= e(date('j M, g:ia', strtotime($o['created_at']))) ?></div></div></td>
            <td data-label="Customer"><?= e($o['name']) ?></td>
            <td data-label="Total"><strong><?= money($o['total']) ?></strong></td>
            <td data-label="Status"><?= status_badge($o['status']) ?></td>
            <td data-label="Payment"><?= pay_badge($o['payment_status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><div class="empty"><i class="fa-solid fa-receipt"></i>No orders yet. They'll show here as soon as customers check out.</div><?php endif; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--warn)"></i> Low stock</h3><a class="btn btn-outline btn-sm" href="<?= url('admin/stock.php?filter=low') ?>">Restock</a></div>
      <?php if ($low): foreach ($low as $p): ?>
        <div class="list-row"><a href="<?= url('admin/product-edit.php?id=' . $p['id']) ?>" style="display:flex;gap:10px;align-items:center;min-width:0"><img class="thumb" src="<?= e(product_img($p)) ?>" alt=""><span class="t-name" style="font-size:13.5px"><?= e($p['name']) ?></span></a><span class="pill <?= $p['stock'] <= 0 ? 'off' : 'warn' ?>"><?= (int)$p['stock'] ?> left</span></div>
      <?php endforeach; else: ?><p class="muted" style="margin:0">All products are well stocked.</p><?php endif; ?>
    </div>
    <div class="card">
      <h3>Top sellers</h3>
      <?php if ($top): foreach ($top as $t): ?>
        <div class="list-row"><span style="font-size:13.5px"><?= e($t['name']) ?></span><span class="muted" style="font-size:13px;white-space:nowrap"><?= (int)$t['q'] ?> sold</span></div>
      <?php endforeach; else: ?><p class="muted" style="margin:0">No sales yet.</p><?php endif; ?>
    </div>
    <div class="card">
      <h3>Quick actions</h3>
      <div class="form-grid">
        <a class="btn btn-primary" href="<?= url('admin/product-edit.php') ?>"><i class="fa-solid fa-plus"></i> Add product</a>
        <a class="btn btn-outline" href="<?= url('admin/stock.php') ?>"><i class="fa-solid fa-boxes-stacked"></i> Update stock</a>
        <?php if (is_super()): ?><a class="btn btn-outline" href="<?= url('admin/settings.php') ?>"><i class="fa-solid fa-gear"></i> Store settings</a><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
