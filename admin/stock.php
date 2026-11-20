<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (is_post()) {
    csrf_check();
    $pid = (int)post('product_id');
    $qty = (int)post('qty');
    $type = post('type');
    $reason = mb_substr(post('reason'), 0, 200);
    if ($qty > 0 && $pid) {
        if ($type === 'set') {
            $cur = (int)$pdo->query('SELECT stock FROM products WHERE id = ' . $pid)->fetchColumn();
            stock_adjust($pid, $qty - $cur, $reason ?: 'Stock count');
        } else {
            stock_adjust($pid, $type === 'remove' ? -$qty : $qty, $reason ?: ($type === 'remove' ? 'Removed' : 'Restocked'));
        }
        flash('success', 'Stock updated.');
    } elseif ($type === 'set' && $pid && $qty === 0) {
        $cur = (int)$pdo->query('SELECT stock FROM products WHERE id = ' . $pid)->fetchColumn();
        stock_adjust($pid, -$cur, $reason ?: 'Stock count');
        flash('success', 'Stock set to 0.');
    } else flash('error', 'Enter a quantity.');
    $back = $_SERVER['HTTP_REFERER'] ?? url('admin/stock.php');
    header('Location: ' . $back); exit;
}

$productId = (int)get('product');
$filter = get('filter');
$qs = get('q');

if ($productId) {
    $p = $pdo->query('SELECT * FROM products WHERE id = ' . $productId)->fetch();
    if (!$p) redirect('admin/stock.php');
    $log = $pdo->prepare('SELECT l.*, a.name admin_name FROM stock_log l LEFT JOIN admins a ON a.id = l.admin_id WHERE l.product_id = ? ORDER BY l.id DESC LIMIT 200');
    $log->execute([$productId]);
    $logs = $log->fetchAll();
} else {
    $w = ['1=1']; $a = [];
    if ($filter === 'low') $w[] = 'stock <= low_stock';
    if ($filter === 'out') $w[] = 'stock <= 0';
    if ($qs !== '') { $w[] = '(name LIKE ? OR sku LIKE ?)'; $a[] = "%$qs%"; $a[] = "%$qs%"; }
    $st = $pdo->prepare('SELECT * FROM products WHERE ' . implode(' AND ', $w) . ' ORDER BY stock ASC, name LIMIT 300');
    $st->execute($a);
    $rows = $st->fetchAll();
    $recent = $pdo->query('SELECT l.*, p.name pname, a.name admin_name FROM stock_log l LEFT JOIN products p ON p.id = l.product_id LEFT JOIN admins a ON a.id = l.admin_id ORDER BY l.id DESC LIMIT 15')->fetchAll();
}

$adminTitle = 'Inventory';
require __DIR__ . '/_header.php';
?>
<?php if ($productId): ?>
  <div class="btn-row" style="margin-bottom:12px"><a class="btn btn-outline btn-sm" href="<?= url('admin/stock.php') ?>"><i class="fa-solid fa-arrow-left"></i> Inventory</a><a class="btn btn-outline btn-sm" href="<?= url('admin/product-edit.php?id=' . $p['id']) ?>"><i class="fa-solid fa-pen"></i> Edit product</a></div>
  <div class="split">
    <div class="card flush-sm">
      <div class="card-head"><h3><?= e($p['name']) ?> — stock history</h3></div>
      <table class="table cards">
        <thead><tr><th>Date</th><th>Change</th><th>Balance</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="t-main"><div><div class="t-name"><?= e(date('j M Y, g:ia', strtotime($l['created_at']))) ?></div></div></td>
            <td data-label="Change"><strong style="color:<?= $l['qty_change'] < 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= $l['qty_change'] > 0 ? '+' : '' ?><?= (int)$l['qty_change'] ?></strong></td>
            <td data-label="Balance"><?= (int)$l['stock_after'] ?></td>
            <td data-label="Reason"><?= e($l['reason']) ?></td>
            <td data-label="By"><?= e($l['admin_name'] ?? 'Website') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card">
      <h3>Current stock: <?= (int)$p['stock'] ?></h3>
      <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $p['id'] ?>">
        <div class="field"><label>Action</label><select name="type"><option value="add">Add stock (received)</option><option value="remove">Remove stock (damaged/used)</option><option value="set">Set exact count</option></select></div>
        <div class="field"><label>Quantity</label><input type="number" name="qty" min="0" required inputmode="numeric"></div>
        <div class="field"><label>Reason / note</label><input name="reason" placeholder="e.g. Supplier delivery"></div>
        <button class="btn btn-primary" type="submit">Update stock</button>
      </form>
    </div>
  </div>
<?php else: ?>
  <form class="toolbar" method="get">
    <div class="grow"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search product or SKU"></div>
    <select name="filter" onchange="this.form.submit()"><option value="">All products</option><option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Low stock</option><option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>Out of stock</option></select>
  </form>
  <div class="card flush-sm">
    <table class="table cards">
      <thead><tr><th>Product</th><th>In stock</th><th>Value</th><th>Quick restock</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $p): ?>
        <tr>
          <td class="t-main"><img class="thumb" src="<?= e(product_img($p)) ?>" alt=""><div><a class="t-name" href="<?= url('admin/stock.php?product=' . $p['id']) ?>"><?= e($p['name']) ?></a><div class="t-sub"><?= e($p['sku']) ?> · alert at <?= (int)$p['low_stock'] ?></div></div></td>
          <td data-label="In stock"><span class="pill <?= $p['stock'] <= 0 ? 'off' : ($p['stock'] <= $p['low_stock'] ? 'warn' : 'on') ?>"><?= (int)$p['stock'] ?></span></td>
          <td data-label="Value"><?= money($p['stock'] * $p['price']) ?></td>
          <td data-label="Restock">
            <form method="post" class="btn-row" style="flex-wrap:nowrap">
              <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= $p['id'] ?>"><input type="hidden" name="type" value="add">
              <input class="input" style="width:80px;min-height:36px;font-size:14px" type="number" name="qty" min="1" placeholder="+ qty" required inputmode="numeric">
              <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-plus"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$rows): ?><div class="empty card"><i class="fa-solid fa-boxes-stacked"></i>Nothing here.</div><?php endif; ?>
  </div>
  <div class="card">
    <h3>Recent stock movements</h3>
    <?php foreach ($recent as $l): ?>
      <div class="list-row"><span style="font-size:13.5px"><strong style="color:<?= $l['qty_change'] < 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= $l['qty_change'] > 0 ? '+' : '' ?><?= (int)$l['qty_change'] ?></strong> <?= e($l['pname']) ?> <span class="muted">· <?= e($l['reason']) ?></span></span><small class="muted" style="white-space:nowrap"><?= e(date('j M', strtotime($l['created_at']))) ?></small></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
