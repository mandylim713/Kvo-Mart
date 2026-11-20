<?php
require __DIR__ . '/includes/bootstrap.php';

$next = get('next', post('next', ''));
$safeNext = ($next !== '' && $next[0] === '/' && strpos($next, '//') !== 0) ? $next : url('account.php');
if (customer()) { header('Location: ' . $safeNext); exit; }

if (is_post()) {
    csrf_check();
    $_SESSION['login_tries'] = ($_SESSION['login_tries'] ?? 0) + 1;
    if ($_SESSION['login_tries'] > 8 && time() - ($_SESSION['login_first'] ?? 0) < 600) {
        flash('error', 'Too many attempts. Please wait a few minutes.');
    } else {
        if (empty($_SESSION['login_first']) || time() - $_SESSION['login_first'] > 600) { $_SESSION['login_first'] = time(); $_SESSION['login_tries'] = 1; }
        $st = db()->prepare('SELECT * FROM customers WHERE email = ? AND is_active = 1');
        $st->execute([strtolower(post('email'))]);
        $u = $st->fetch();
        if ($u && password_verify(post('password'), $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['customer_id'] = (int)$u['id'];
            unset($_SESSION['login_tries'], $_SESSION['login_first']);
            flash('success', 'Welcome back, ' . explode(' ', $u['name'])[0] . '!');
            header('Location: ' . $safeNext);
            exit;
        }
        flash('error', 'Incorrect email or password.');
    }
    header('Location: ' . url('login.php?next=' . urlencode($next)));
    exit;
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="card auth-card">
    <h1>Welcome back</h1>
    <p class="muted" style="font-size:14px">Log in to track orders and check out faster.</p>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field"><label>Email</label><input type="email" name="email" required autocomplete="email" inputmode="email"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Log in</button>
    </form>
    <p class="text-center" style="margin:16px 0 0;font-size:14px">New here? <a href="<?= url('register.php' . ($next ? '?next=' . urlencode($next) : '')) ?>" style="color:var(--primary);font-weight:700">Create an account</a></p>
    <p class="text-center muted" style="margin:8px 0 0;font-size:13px">Forgot your password? <a href="<?= e(wa_link('Hello, I need help resetting my account password.')) ?>" target="_blank" rel="noopener" style="text-decoration:underline">Contact us</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
