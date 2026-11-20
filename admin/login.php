<?php
require __DIR__ . '/_init.php';
if (admin()) redirect('admin/index.php');

if (is_post()) {
    csrf_check();
    $k = 'admin_tries';
    if (empty($_SESSION[$k . '_t']) || time() - $_SESSION[$k . '_t'] > 900) { $_SESSION[$k] = 0; $_SESSION[$k . '_t'] = time(); }
    $_SESSION[$k]++;
    if ($_SESSION[$k] > 6) {
        flash('error', 'Too many login attempts. Try again in 15 minutes.');
    } else {
        $st = db()->prepare('SELECT * FROM admins WHERE email = ? AND is_active = 1');
        $st->execute([strtolower(post('email'))]);
        $a = $st->fetch();
        if ($a && password_verify(post('password'), $a['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            $_SESSION[$k] = 0;
            db()->prepare('UPDATE admins SET last_login = ? WHERE id = ?')->execute([now(), $a['id']]);
            if (password_verify('admin123', $a['password'])) flash('warning', 'You are still using the default password. Change it now under My password.');
            redirect('admin/index.php');
        }
        flash('error', 'Incorrect email or password.');
    }
    redirect('admin/login.php');
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Admin login · <?= e(setting('site_name')) ?></title>
<link rel="stylesheet" href="<?= url('assets/vendor/jakarta/jakarta.css') ?>">
<link rel="stylesheet" href="<?= url('assets/vendor/fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>?v=3">
<style>:root{--primary:<?= e(setting('color_primary')) ?>}</style></head>
<body class="login">
<div class="login-wrap">
  <div class="login-card">
    <div class="a-mark"><?= e(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', setting('site_name')), 0, 3))) ?></div>
    <h2 style="text-align:center;margin-bottom:4px"><?= e(setting('site_name')) ?> Admin</h2>
    <p class="muted" style="text-align:center;margin:0 0 18px;font-size:14px">Sign in to manage your store</p>
    <?= flashes() ?>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <div class="field"><label>Email</label><input type="email" name="email" required autofocus autocomplete="username" inputmode="email"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn-primary btn-block" style="min-height:48px" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Sign in</button>
    </form>
    <p style="text-align:center;margin:16px 0 0;font-size:13px"><a href="<?= url('index.php') ?>" class="muted">&larr; Back to website</a></p>
  </div>
</div>
</body></html>
