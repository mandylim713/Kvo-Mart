<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    if (post('action') === 'toggle') {
        $pdo->prepare('UPDATE customers SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', 'Customer updated.');
    }
    if (post('action') === 'reset') {
        $new = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
        $pdo->prepare('UPDATE customers SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $id]);
        flash('success', 'New temporary password: ' . $new . ' — share it with the customer and ask them to change it.');
    }
    redirect('admin/customers.php' . (get('q') ? '?q=' . urlencode(get('q')) : ''));
}

$qs = get('q');
$page = max(1, (int)get('page', 1));
$per = 30;
$w = '1=1'; $a = [];
if ($qs !== '') { $w = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)'; $a = ["%$qs%", "%$qs%", "%$qs%"]; }
$c = $pdo->prepare("SELECT COUNT(*) FROM customers c WHERE $w"); $c->execute($a); $total = (int)$c->fetchColumn();
$st = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) orders, (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id = c.id AND o.status != 'cancelled') spent FROM customers c WHERE $w ORDER BY c.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($a);
$rows = $st->fetchAll();
$guests = (int)$pdo->query('SELECT COUNT(DISTINCT phone) FROM orders WHERE customer_id IS NULL')->fetchColumn();

$adminTitle = 'Customers';
require __DIR__ . '/_header.php';
?>
<form class="toolbar" method="get"><div class="grow"><input type="search" name="q" value="<?= e($qs) ?>" placeholder="Search name, email, phone"></div><button class="btn btn-outline"><i class="fa-solid fa-magnifying-glass"></i></button></form>
<p class="muted" style="margin:0 0 10px;font-size:13px"><?= $total ?> registered · <?= $guests ?> guest buyers (by phone)</p>
<div class="card flush-sm">
<?php if ($rows): ?>
<table class="table cards">
  <thead><tr><th>Customer</th><th>Phone</th><th>Orders</th><th>Spent</th><th>Joined</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="t-main"><span class="a-avatar"><?= e(strtoupper(substr($r['name'], 0, 1))) ?></span><div><div class="t-name"><?= e($r['name']) ?> <?= $r['is_active'] ? '' : '<span class="pill off">Blocked</span>' ?></div><div class="t-sub"><?= e($r['email']) ?></div></div></td>
      <td data-label="Phone"><?= e($r['phone'] ?: '—') ?></td>
      <td data-label="Orders"><a href="<?= url('admin/orders.php?q=' . urlencode($r['email'])) ?>" style="color:var(--primary);font-weight:700"><?= (int)$r['orders'] ?></a></td>
      <td data-label="Spent"><?= money($r['spent']) ?></td>
      <td data-label="Joined"><?= e(date('j M Y', strtotime($r['created_at']))) ?></td>
      <td class="t-actions"><div class="btn-row">
        <form method="post" data-confirm="Generate a new temporary password for this customer?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-outline btn-sm" title="Reset password"><i class="fa-solid fa-key"></i></button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-outline btn-sm" title="<?= $r['is_active'] ? 'Block' : 'Unblock' ?>"><i class="fa-solid <?= $r['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i></button></form>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?= paginate($total, $page, $per) ?>
<?php else: ?><div class="empty card"><i class="fa-solid fa-users"></i>No registered customers yet.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
