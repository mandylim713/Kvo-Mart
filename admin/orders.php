<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

$status = get('status');
$pay = get('pay');
$qs = get('q');
$from = get('from');
$to = get('to');
$page = max(1, (int)get('page', 1));
$per = 25;

$w = ['1=1']; $a = [];
if ($status !== '' && isset(order_statuses()[$status])) { $w[] = 'status = ?'; $a[] = $status; }
if ($pay !== '') { $w[] = 'payment_status = ?'; $a[] = $pay; }
if ($qs !== '') { $w[] = '(order_no LIKE ? OR name LIKE ? OR phone LIKE ? OR email LIKE ?)'; array_push($a, "%$qs%", "%$qs%", "%$qs%", "%$qs%"); }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $w[] = 'created_at >= ?'; $a[] = $from . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $w[] = 'created_at <= ?'; $a[] = $to . ' 23:59:59'; }
$ws = implode(' AND ', $w);

if (get('export') === 'csv') {
    $st = $pdo->prepare("SELECT * FROM orders WHERE $ws ORDER BY id DESC");
    $st->execute($a);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Order no', 'Date', 'Customer', 'Phone', 'Email', 'Address', 'City', 'State', 'Delivery', 'Subtotal', 'Delivery fee', 'Total', 'Payment method', 'Payment status', 'Status', 'Items']);
    $it = $pdo->prepare('SELECT name, qty FROM order_items WHERE order_id = ?');
    foreach ($st as $o) {
        $it->execute([$o['id']]);
        $items = implode('; ', array_map(fn($r) => $r['name'] . ' x' . $r['qty'], $it->fetchAll()));
        fputcsv($out, [$o['order_no'], $o['created_at'], $o['name'], $o['phone'], $o['email'], $o['address'], $o['city'], $o['state'], $o['delivery_method'] === 'pickup' ? 'Pickup' : $o['zone_name'], $o['subtotal'], $o['delivery_fee'], $o['total'], payment_methods()[$o['payment_method']] ?? $o['payment_method'], $o['payment_status'], $o['status'], $items]);
    }
    exit;
}

$c = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total),0) FROM orders WHERE $ws"); $c->execute($a); [$total, $sum] = $c->fetch(PDO::FETCH_NUM);
$st = $pdo->prepare("SELECT * FROM orders WHERE $ws ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($a);
$rows = $st->fetchAll();
$counts = [];
foreach ($pdo->query('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $counts[$r['status']] = $r['n'];

$adminTitle = 'Orders';
require __DIR__ . '/_header.php';
$base = array_filter(['q' => $qs, 'pay' => $pay, 'from' => $from, 'to' => $to]);
?>
<div class="tabs-nav">
  <a href="?<?= http_build_query($base) ?>" class="<?= $status === '' ? 'active' : '' ?>">All<span><?= array_sum($counts) ?></span></a>
  <?php foreach (order_statuses() as $k => $lbl): ?><a href="?<?= http_build_query($base + ['status' => $k]) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($lbl) ?><span><?= (int)($counts[$k] ?? 0) ?></span></a><?php endforeach; ?>
</div>
<form class="toolbar" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="grow"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Order no, name, phone, email"></div>
  <select name="pay" onchange="this.form.submit()"><option value="">Any payment</option><?php foreach (['unpaid' => 'Unpaid', 'awaiting' => 'Awaiting confirmation', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $k => $v): ?><option value="<?= $k ?>" <?= $pay === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
  <input type="date" name="from" value="<?= e($from) ?>" aria-label="From date">
  <input type="date" name="to" value="<?= e($to) ?>" aria-label="To date">
  <button class="btn btn-outline" type="submit"><i class="fa-solid fa-filter"></i></button>
  <a class="btn btn-outline" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="fa-solid fa-file-csv"></i> Export</a>
</form>
<p class="muted" style="margin:0 0 10px;font-size:13px"><?= (int)$total ?> order<?= $total == 1 ? '' : 's' ?> · <?= money($sum) ?></p>

<div class="card flush-sm">
<?php if ($rows): ?>
<table class="table cards">
  <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $o): ?>
    <tr>
      <td class="t-main"><div><a class="t-name" href="<?= url('admin/order-view.php?id=' . $o['id']) ?>"><?= e($o['order_no']) ?></a><div class="t-sub"><?= e(date('j M Y, g:ia', strtotime($o['created_at']))) ?></div></div></td>
      <td data-label="Customer"><div><?= e($o['name']) ?><div class="t-sub"><?= e($o['phone']) ?></div></div></td>
      <td data-label="Total"><strong><?= money($o['total']) ?></strong></td>
      <td data-label="Payment"><div><?= pay_badge($o['payment_status']) ?><div class="t-sub"><?= e(payment_methods()[$o['payment_method']] ?? $o['payment_method']) ?></div></div></td>
      <td data-label="Status"><?= status_badge($o['status']) ?></td>
      <td class="t-actions"><a class="btn btn-outline btn-sm" href="<?= url('admin/order-view.php?id=' . $o['id']) ?>">Open <i class="fa-solid fa-chevron-right"></i></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?= paginate((int)$total, $page, $per) ?>
<?php else: ?><div class="empty card"><i class="fa-solid fa-receipt"></i>No orders match.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
