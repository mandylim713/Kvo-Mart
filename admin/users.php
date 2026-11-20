<?php
require __DIR__ . '/_init.php';
$me = require_admin('admin');
$pdo = db();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    $action = post('action');
    if ($action === 'delete' || $action === 'toggle') {
        if ($id === (int)$me['id']) { flash('error', 'You cannot change your own account here.'); redirect('admin/users.php'); }
        if ($action === 'delete') $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
        else $pdo->prepare('UPDATE admins SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        flash('success', 'Admin updated.');
        redirect('admin/users.php');
    }
    $name = mb_substr(post('name'), 0, 120);
    $email = strtolower(post('email'));
    $role = post('role') === 'admin' ? 'admin' : 'staff';
    $pass = post('password');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error', 'Enter a name and valid email.'); redirect('admin/users.php'); }
    $dup = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE email = ? AND id != ?'); $dup->execute([$email, $id]);
    if ($dup->fetchColumn()) { flash('error', 'That email already has an admin account.'); redirect('admin/users.php'); }
    if ($id) {
        if ($id === (int)$me['id']) $role = 'admin';
        $pdo->prepare('UPDATE admins SET name=?, email=?, role=? WHERE id=?')->execute([$name, $email, $role, $id]);
        if ($pass !== '') {
            if (strlen($pass) < 8) { flash('error', 'Password must be at least 8 characters.'); redirect('admin/users.php?edit=' . $id); }
            $pdo->prepare('UPDATE admins SET password=? WHERE id=?')->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
        }
    } else {
        if (strlen($pass) < 8) { flash('error', 'Password must be at least 8 characters.'); redirect('admin/users.php'); }
        $pdo->prepare('INSERT INTO admins (name, email, password, role, is_active, created_at) VALUES (?,?,?,?,1,?)')->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, now()]);
    }
    flash('success', 'Admin saved.');
    redirect('admin/users.php');
}
$edit = null;
if (get('edit')) { $s = $pdo->prepare('SELECT * FROM admins WHERE id = ?'); $s->execute([(int)get('edit')]); $edit = $s->fetch(); }
$rows = $pdo->query('SELECT * FROM admins ORDER BY id')->fetchAll();

$adminTitle = 'Admin users';
require __DIR__ . '/_header.php';
?>
<div class="split">
  <div class="card flush-sm">
    <table class="table cards">
      <thead><tr><th>Name</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td class="t-main"><span class="a-avatar"><?= e(strtoupper(substr($u['name'], 0, 1))) ?></span><div><div class="t-name"><?= e($u['name']) ?><?= $u['id'] == $me['id'] ? ' <span class="pill">You</span>' : '' ?></div><div class="t-sub"><?= e($u['email']) ?></div></div></td>
          <td data-label="Role"><?= $u['role'] === 'admin' ? 'Administrator' : 'Staff' ?></td>
          <td data-label="Last login"><?= $u['last_login'] ? e(date('j M, g:ia', strtotime($u['last_login']))) : '—' ?></td>
          <td data-label="Status"><span class="pill <?= $u['is_active'] ? 'on' : 'off' ?>"><?= $u['is_active'] ? 'Active' : 'Disabled' ?></span></td>
          <td class="t-actions"><div class="btn-row">
            <a class="btn btn-outline btn-sm" href="?edit=<?= $u['id'] ?>#form"><i class="fa-solid fa-pen"></i></a>
            <?php if ($u['id'] != $me['id']): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-outline btn-sm"><i class="fa-solid <?= $u['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i></button></form>
            <form method="post" data-confirm="Delete this admin?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-regular fa-trash-can"></i></button></form>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card" id="form">
    <h3><?= $edit ? 'Edit admin' : 'Add admin / staff' ?></h3>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div class="field"><label>Name</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
      <div class="field"><label>Email (login)</label><input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>"></div>
      <div class="field"><label>Role</label><select name="role"><option value="staff" <?= ($edit['role'] ?? 'staff') === 'staff' ? 'selected' : '' ?>>Staff — products, stock, orders</option><option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator — everything incl. settings</option></select></div>
      <div class="field"><label>Password</label><input type="password" name="password" minlength="8" <?= $edit ? '' : 'required' ?> autocomplete="new-password"><small><?= $edit ? 'Leave blank to keep current password.' : 'At least 8 characters.' ?></small></div>
      <button class="btn btn-primary" type="submit">Save</button>
      <?php if ($edit): ?><a class="btn btn-outline" href="<?= url('admin/users.php') ?>">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
