<?php
require __DIR__ . '/includes/bootstrap.php';

$next = get('next', post('next', ''));
$safeNext = ($next !== '' && $next[0] === '/' && strpos($next, '//') !== 0) ? $next : url('account.php');
if (customer()) { header('Location: ' . $safeNext); exit; }

if (is_post()) {
    csrf_check();
    $name = mb_substr(post('name'), 0, 150);
    $email = strtolower(mb_substr(post('email'), 0, 190));
    $phone = mb_substr(post('phone'), 0, 40);
    $pass = post('password');
    $err = '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter your name and a valid email.';
    elseif (strlen($pass) < 6) $err = 'Password must be at least 6 characters.';
    elseif ($pass !== post('password2')) $err = 'Passwords do not match.';
    else {
        $st = db()->prepare('SELECT COUNT(*) FROM customers WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetchColumn()) $err = 'An account with that email already exists. Please log in.';
    }
    if ($err) { flash('error', $err); header('Location: ' . url('register.php?next=' . urlencode($next))); exit; }

    db()->prepare('INSERT INTO customers (name, email, phone, password, is_active, created_at) VALUES (?,?,?,?,1,?)')
        ->execute([$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), now()]);
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)db()->lastInsertId();
    // link previous guest orders made with the same email
    db()->prepare('UPDATE orders SET customer_id = ? WHERE customer_id IS NULL AND LOWER(email) = ?')->execute([$_SESSION['customer_id'], $email]);
    flash('success', 'Your account has been created. Welcome!');
    header('Location: ' . $safeNext);
    exit;
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="card auth-card">
    <h1>Create an account</h1>
    <p class="muted" style="font-size:14px">Save your details, track orders and check out in seconds.</p>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field"><label>Full name</label><input name="name" required autocomplete="name"></div>
      <div class="field"><label>Email</label><input type="email" name="email" required autocomplete="email" inputmode="email"></div>
      <div class="field"><label>Phone</label><input type="tel" name="phone" autocomplete="tel" inputmode="tel"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required minlength="6" autocomplete="new-password"></div>
      <div class="field"><label>Confirm password</label><input type="password" name="password2" required minlength="6" autocomplete="new-password"></div>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Create account</button>
    </form>
    <p class="text-center" style="margin:16px 0 0;font-size:14px">Already have an account? <a href="<?= url('login.php' . ($next ? '?next=' . urlencode($next) : '')) ?>" style="color:var(--primary);font-weight:700">Log in</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
