<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    if (post('action') === 'delete') {
        $pdo->prepare('DELETE FROM delivery_zones WHERE id = ?')->execute([$id]);
        flash('success', 'Zone deleted.');
    } else {
        $name = mb_substr(post('name'), 0, 150);
        if ($name === '') { flash('error', 'Zone name is required.'); redirect('admin/zones.php'); }
        $data = [$name, max(0, (int)post('fee')), mb_substr(post('eta'), 0, 80), (int)post('sort_order'), post('is_active') ? 1 : 0];
        if ($id) $pdo->prepare('UPDATE delivery_zones SET name=?, fee=?, eta=?, sort_order=?, is_active=? WHERE id=?')->execute([...$data, $id]);
        else $pdo->prepare('INSERT INTO delivery_zones (name, fee, eta, sort_order, is_active) VALUES (?,?,?,?,?)')->execute($data);
        flash('success', 'Zone saved.');
    }
    redirect('admin/zones.php');
}
$edit = null;
if (get('edit')) { $s = $pdo->prepare('SELECT * FROM delivery_zones WHERE id = ?'); $s->execute([(int)get('edit')]); $edit = $s->fetch(); }
$rows = $pdo->query('SELECT * FROM delivery_zones ORDER BY sort_order, id')->fetchAll();

$adminTitle = 'Delivery zones';
require __DIR__ . '/_header.php';
?>
<div class="alert alert-info"><span>Customers pick a zone at checkout and its fee is added to the order. Free-delivery threshold and store pickup are in <?= is_super() ? '<a href="' . url('admin/settings.php?tab=delivery') . '" style="text-decoration:underline">Settings → Delivery</a>' : 'Settings' ?>.</span></div>
<div class="split">
  <div class="card flush-sm">
    <table class="table cards">
      <thead><tr><th>Zone</th><th>Fee</th><th>Delivery time</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $z): ?>
        <tr>
          <td class="t-main"><div class="t-name"><?= e($z['name']) ?></div></td>
          <td data-label="Fee"><strong><?= money($z['fee']) ?></strong></td>
          <td data-label="Delivery time"><?= e($z['eta'] ?: '—') ?></td>
          <td data-label="Status"><span class="pill <?= $z['is_active'] ? 'on' : 'off' ?>"><?= $z['is_active'] ? 'Active' : 'Off' ?></span></td>
          <td class="t-actions"><div class="btn-row">
            <a class="btn btn-outline btn-sm" href="?edit=<?= $z['id'] ?>#form"><i class="fa-solid fa-pen"></i></a>
            <form method="post" data-confirm="Delete this zone?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $z['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-regular fa-trash-can"></i></button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card" id="form">
    <h3><?= $edit ? 'Edit zone' : 'Add zone' ?></h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field"><label>Zone / area name *</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Wuse, Garki, Maitama"></div>
      <div class="field"><label>Delivery fee (<?= e(setting('currency')) ?>)</label><input type="number" name="fee" min="0" value="<?= (int)($edit['fee'] ?? 0) ?>" inputmode="numeric"></div>
      <div class="field"><label>Delivery time</label><input name="eta" value="<?= e($edit['eta'] ?? '') ?>" placeholder="e.g. Same day"></div>
      <div class="field"><label>Order</label><input type="number" name="sort_order" value="<?= (int)($edit['sort_order'] ?? count($rows) + 1) ?>"></div>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>> Active</label>
      <button class="btn btn-primary" type="submit">Save zone</button>
      <?php if ($edit): ?><a class="btn btn-outline" href="<?= url('admin/zones.php') ?>">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
