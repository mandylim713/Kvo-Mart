<?php
require __DIR__ . '/_init.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $name = post('name') ?: $me['name'];
    $email = strtolower(post('email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error', 'Enter a valid email.'); redirect('admin/profile.php'); }
    $dup = db()->prepare('SELECT COUNT(*) FROM admins WHERE email = ? AND id != ?');
    $dup->execute([$email, $me['id']]);
    if ($dup->fetchColumn()) { flash('error', 'That email is used by another admin.'); redirect('admin/profile.php'); }
    db()->prepare('UPDATE admins SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $me['id']]);
    if (post('new') !== '') {
        if (!password_verify(post('current'), $me['password'])) { flash('error', 'Current password is incorrect.'); redirect('admin/profile.php'); }
        if (strlen(post('new')) < 8) { flash('error', 'New password must be at least 8 characters.'); redirect('admin/profile.php'); }
        if (post('new') !== post('new2')) { flash('error', 'New passwords do not match.'); redirect('admin/profile.php'); }
        db()->prepare('UPDATE admins SET password = ? WHERE id = ?')->execute([password_hash(post('new'), PASSWORD_DEFAULT), $me['id']]);
        flash('success', 'Password changed.');
    }
    flash('success', 'Profile saved.');
    redirect('admin/profile.php');
}

$adminTitle = 'My profile';
require __DIR__ . '/_header.php';
?>
<div class="card" style="max-width:560px">
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <div class="field"><label>Name</label><input name="name" value="<?= e($me['name']) ?>" required></div>
    <div class="field"><label>Login email</label><input type="email" name="email" value="<?= e($me['email']) ?>" required></div>
    <hr style="border:0;border-top:1px solid var(--line);width:100%">
    <h3 style="margin:0">Change password</h3>
    <div class="field"><label>Current password</label><input type="password" name="current" autocomplete="current-password"></div>
    <div class="field"><label>New password</label><input type="password" name="new" minlength="8" autocomplete="new-password"><small>At least 8 characters. Leave blank to keep the current one.</small></div>
    <div class="field"><label>Confirm new password</label><input type="password" name="new2" autocomplete="new-password"></div>
    <button class="btn btn-primary" type="submit">Save</button>
  </form>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
