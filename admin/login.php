<?php
$next = safe_next(q('next') ?: post('next'), '/admin');
if (!str_starts_with($next, '/admin')) $next = '/admin';
if (current_staff()) redirect($next);
if (is_post()) {
    require_csrf();
    $em = strtolower(post('email'));
    if (!throttle('alogin', 6, 900)) fail([], '/admin/login', 'Too many attempts. Wait 15 minutes and try again.');
    $s = db()->prepare('SELECT * FROM staff WHERE email = ?'); $s->execute([$em]); $u = $s->fetch();
    if (!$u || !password_verify((string)post('password'), $u['password'])) { audit('staff.login_failed', $em ?: '-'); fail([], '/admin/login?next=' . urlencode($next), 'Email or password is incorrect.'); }
    if (!$u['active']) fail([], '/admin/login', 'This staff account is disabled.');
    session_regenerate_id(true);
    $_SESSION['sid'] = (int)$u['id'];
    db()->prepare('UPDATE staff SET last_login = ? WHERE id = ?')->execute([now(), $u['id']]);
    audit('staff.login', $u['email'], ROLE_LABEL[$u['role']] ?? $u['role']);
    if (password_verify('KvoAdmin2026', $u['password'])) { flash('You are using the starting password. Change it now.', 'warn'); redirect('/admin/profile'); }
    redirect($next);
}
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Staff sign in — <?= h(setting('short_name', 'KVO Mart')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(url('/assets/css/site.css')) ?>?v=7"></head><body>
<div class="gw" style="background:var(--ink)"><div style="border-top:4px solid var(--gold)">
  <div class="row"><img src="<?= h(asset(setting('logo'))) ?>" alt="" style="height:44px"><div><b style="font-family:var(--disp)"><?= h(setting('short_name', 'KVO Mart')) ?></b><p class="xs muted">Staff sign in</p></div></div>
  <?php foreach (take_flashes() as [$t, $m]) echo '<div class="note ' . h($t) . ' mt4">' . h($m) . '</div>'; ?>
  <form method="post" class="mt6" novalidate><?= csrf_field() ?><input type="hidden" name="next" value="<?= h($next) ?>">
    <?= field('Work email', 'al-email', inp('email', '', 'type="email" autocomplete="username" required autofocus', 'al-email'), ['name' => 'email']) ?>
    <?= field('Password', 'al-pw', '<input class="in" id="al-pw" name="password" type="password" autocomplete="current-password" required>') ?>
    <?= form_msg() ?><div class="mt4"><button class="btn full">Sign in</button></div></form>
  <a class="link sm mt6" style="display:inline-block" href="<?= h(url('/')) ?>">← Back to store</a></div></div>
</body></html>
<?php clear_form_state();
