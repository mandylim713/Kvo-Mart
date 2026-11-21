<?php
/** Customer accounts: sign in, register, password reset, orders, quotations, addresses, profile. */
$next = safe_next(q('next') ?: post('next'), '/account');
$auth_card = function (string $title, string $body, string $sub = '') {
    page_open($title, ['noindex' => true]);
    echo '<div class="page py" style="max-width:30rem"><h1>' . h($title) . '</h1>' . ($sub ? '<p class="muted mt2">' . $sub . '</p>' : '') . '<div class="box mt6">' . $body . '</div></div>';
    page_close();
};

if ($sub === 'login') {
    if (current_user()) redirect($next);
    if (is_post()) {
        require_csrf();
        $em = strtolower(post('email'));
        if (!throttle('login_' . md5($em), 6, 900)) fail([], '/account/login?next=' . urlencode($next), 'Too many attempts. Wait a few minutes and try again.');
        $s = db()->prepare('SELECT * FROM users WHERE email = ?'); $s->execute([$em]); $u = $s->fetch();
        if (!$u || !password_verify((string)post('password'), $u['password'])) fail([], '/account/login?next=' . urlencode($next), 'Email or password is incorrect.');
        if (!$u['active']) fail([], '/account/login', 'This account is disabled. Contact us for help.');
        session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; unset($_SESSION['co']);
        flash('Welcome back, ' . explode(' ', $u['name'])[0] . '.');
        redirect($next);
    }
    $auth_card('Sign in', '<form method="post" novalidate>' . csrf_field() . '<input type="hidden" name="next" value="' . h($next) . '">' . field('Email', 'l-email', inp('email', '', 'type="email" autocomplete="username" required', 'l-email'), ['name' => 'email']) . field('Password', 'l-pw', '<input class="in" id="l-pw" name="password" type="password" autocomplete="current-password" required>') . form_msg() . '<div class="mt4"><button class="btn full">Sign in</button></div></form><div class="row between mt4 sm"><a class="link" href="' . h(url('/account/forgot')) . '">Forgot password?</a><a class="link" href="' . h(url('/account/register?next=' . urlencode($next))) . '">Create an account</a></div>', 'Track orders and quotations, and check out faster.');
    return;
}
if ($sub === 'register') {
    if (current_user()) redirect($next);
    if (is_post()) {
        require_csrf();
        if (post('website') !== '') redirect('/');
        $em = strtolower(post('email')); $e = [];
        if (mb_strlen(post('name')) < 2) $e['name'] = 'Enter your full name.';
        if (!email_ok($em)) $e['email'] = 'Enter a valid email address.';
        else { $s = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?'); $s->execute([$em]); if ($s->fetchColumn()) $e['email'] = 'An account with this email already exists. Sign in instead.'; }
        if (!phone_ok(post('phone'))) $e['phone'] = 'Enter a valid phone number.';
        if (!pw_ok((string)post('password'))) $e['password'] = PW_RULE; elseif (post('password') !== post('password2')) $e['password2'] = "Passwords don't match.";
        if ($e) fail($e, '/account/register?next=' . urlencode($next));
        db()->prepare('INSERT INTO users (name, email, phone, company, password, verified, active, created) VALUES (?,?,?,?,?,0,1,?)')->execute([mb_substr(post('name'), 0, 150), $em, mb_substr(post('phone'), 0, 40), mb_substr(post('company'), 0, 150), password_hash((string)post('password'), PASSWORD_DEFAULT), now()]);
        $uid = (int)db()->lastInsertId();
        db()->prepare('UPDATE orders SET user_id = ? WHERE user_id IS NULL AND email = ?')->execute([$uid, $em]);
        db()->prepare('UPDATE quotations SET user_id = ? WHERE user_id IS NULL AND email = ?')->execute([$uid, $em]);
        session_regenerate_id(true); $_SESSION['uid'] = $uid;
        send_email($em, 'Welcome to ' . setting('short_name', 'KVO Mart'), 'Hi ' . post('name') . ', your account is ready. You can track orders and quotations from your account.', '/account');
        audit('customer.registered', $em);
        flash('Account created.');
        redirect($next);
    }
    $auth_card('Create an account', '<form method="post" novalidate>' . csrf_field() . '<input type="hidden" name="next" value="' . h($next) . '"><input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">'
        . field('Full name', 'r-name', inp('name', '', 'autocomplete="name"', 'r-name'), ['name' => 'name']) . field('Email', 'r-email', inp('email', '', 'type="email" autocomplete="email"', 'r-email'), ['name' => 'email'])
        . field('Phone', 'r-phone', inp('phone', '', 'type="tel" autocomplete="tel"', 'r-phone'), ['name' => 'phone']) . field('Company', 'r-company', inp('company', '', 'autocomplete="organization"', 'r-company'), ['optional' => true, 'name' => 'company'])
        . field('Password', 'r-pw', '<input class="in' . bad('password') . '" id="r-pw" name="password" type="password" autocomplete="new-password">', ['hint' => PW_RULE, 'name' => 'password'])
        . field('Confirm password', 'r-pw2', '<input class="in' . bad('password2') . '" id="r-pw2" name="password2" type="password" autocomplete="new-password">', ['name' => 'password2'])
        . form_msg() . '<div class="mt4"><button class="btn full">Create account</button></div></form><p class="sm mt4">Already registered? <a class="link" href="' . h(url('/account/login')) . '">Sign in</a></p>');
    return;
}
if ($sub === 'forgot') {
    if (is_post()) {
        require_csrf();
        $em = strtolower(post('email'));
        if (!email_ok($em)) fail(['email' => 'Enter a valid email address.'], '/account/forgot');
        if (throttle('forgot', 5, 900)) {
            $s = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1'); $s->execute([$em]);
            if ($u = $s->fetch()) {
                $t = bin2hex(random_bytes(24));
                db()->prepare('DELETE FROM reset_tokens WHERE user_id = ?')->execute([$u['id']]);
                db()->prepare('INSERT INTO reset_tokens (token, user_id, expires) VALUES (?,?,?)')->execute([hash('sha256', $t), $u['id'], time() + 3600]);
                send_email($em, 'Reset your ' . setting('short_name', 'KVO Mart') . ' password', "Use this link within 1 hour to choose a new password. If you didn't ask for this, ignore this email.", '/account/reset/' . $t);
            }
        }
        flash("If an account exists for that email, we've sent a reset link. Check your inbox and spam folder.");
        redirect('/account/forgot');
    }
    $auth_card('Reset your password', '<form method="post" novalidate>' . csrf_field() . field('Email', 'fp-email', inp('email', '', 'type="email" autocomplete="email"', 'fp-email'), ['name' => 'email']) . '<button class="btn full">Send reset link</button></form>', "We'll email you a link to choose a new password.");
    return;
}
if ($sub === 'reset') {
    $s = db()->prepare('SELECT * FROM reset_tokens WHERE token = ? AND expires > CAST(? AS INTEGER)'); $s->execute([hash('sha256', (string)$id), time()]);
    $r = $s->fetch();
    if (!$r) { $auth_card('Link expired', '<p>This reset link is invalid or has already been used.</p><a class="btn mt4" href="' . h(url('/account/forgot')) . '">Request a new link</a>'); return; }
    if (is_post()) {
        require_csrf();
        if (!pw_ok((string)post('password'))) fail(['password' => PW_RULE], '/account/reset/' . $id);
        if (post('password') !== post('password2')) fail(['password2' => "Passwords don't match."], '/account/reset/' . $id);
        db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash((string)post('password'), PASSWORD_DEFAULT), $r['user_id']]);
        db()->prepare('DELETE FROM reset_tokens WHERE user_id = ?')->execute([$r['user_id']]);
        $u = db()->query('SELECT email FROM users WHERE id = ' . (int)$r['user_id'])->fetch();
        send_email($u['email'], 'Your password was changed', 'Your ' . setting('short_name', 'KVO Mart') . " password was just changed. If this wasn't you, contact us immediately.");
        flash('Password updated. Sign in with your new password.');
        redirect('/account/login');
    }
    $auth_card('Choose a new password', '<form method="post" novalidate>' . csrf_field() . field('New password', 'np', '<input class="in' . bad('password') . '" id="np" name="password" type="password" autocomplete="new-password">', ['hint' => PW_RULE, 'name' => 'password']) . field('Confirm password', 'np2', '<input class="in' . bad('password2') . '" id="np2" name="password2" type="password" autocomplete="new-password">', ['name' => 'password2']) . '<button class="btn full">Save new password</button></form>');
    return;
}
if ($sub === 'logout') {
    if (is_post()) { require_csrf(); unset($_SESSION['uid'], $_SESSION['co']); session_regenerate_id(true); flash('Signed out.'); }
    redirect('/');
}

/* ---------- signed-in area ---------- */
$u = current_user();
if (!$u) redirect('/account/login?next=' . urlencode('/account' . ($sub ? '/' . $sub : '')));
$uid = (int)$u['id'];
$myOrders = function () use ($uid) { $s = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC'); $s->execute([$uid]); return $s->fetchAll(); };
$myQuotes = function () use ($u) { $s = db()->prepare("SELECT * FROM quotations WHERE (user_id = ? OR email = ?) AND status <> 'DRAFT' ORDER BY id DESC"); $s->execute([$u['id'], $u['email']]); return $s->fetchAll(); };
$ordersTable = function (array $list) {
    if (!$list) return empty_state('No orders yet', '', '<a class="btn" href="' . h(url('/equipment')) . '">Start shopping</a>');
    $h = '<div class="scroll box" style="padding:0"><table class="tbl cards"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Delivery</th><th class="r">Total</th></tr></thead><tbody>';
    foreach ($list as $o) $h .= '<tr><td class="first"><a class="b" href="' . h(url('/orders/' . $o['ref'])) . '">' . h($o['ref']) . '</a></td><td data-l="Date">' . fmt_date($o['created']) . '</td><td data-l="Status">' . badge(ORDER_STATUS[$o['status']] ?? '', ost_tone($o['status'])) . '</td><td data-l="Delivery" class="sm">' . h(DELIVERY_STATUS[$o['d_status']] ?? '') . '</td><td data-l="Total" class="r tab">' . naira($o['total']) . '</td></tr>';
    return $h . '</tbody></table></div>';
};
$layout = function (string $body) use ($sub) {
    $nav = [['/account', 'Overview', ''], ['/account/orders', 'Orders', 'orders'], ['/account/quotes', 'Quotations', 'quotes'], ['/account/addresses', 'Addresses', 'addresses'], ['/account/profile', 'Profile & password', 'profile']];
    page_open('My account', ['noindex' => true]);
    echo '<div class="page py">' . crumbs([['Home', '/'], ['My account']]) . '<div class="row between mt4"><h1>My account</h1><form method="post" action="' . h(url('/account/logout')) . '">' . csrf_field() . '<button class="btn out sm">Sign out</button></form></div><div class="split side mt6"><nav class="box" style="padding:.5rem;height:fit-content" aria-label="Account">';
    foreach ($nav as [$p, $l, $k]) echo '<a href="' . h(url($p)) . '" style="display:block;padding:.55rem .75rem;text-decoration:none;' . (($sub ?? '') === $k ? 'background:var(--gold50);border-left:3px solid var(--gold);font-weight:600' : '') . '">' . $l . '</a>';
    echo '</nav><div>' . $body . '</div></div></div>';
    page_close();
};

if (!$sub) {
    $os = $myOrders(); $qs = $myQuotes();
    $act = [];
    foreach ($os as $o) {
        $dqOpen = db()->prepare("SELECT COUNT(*) FROM delivery_quotes WHERE order_id = ? AND status = 'SENT'"); $dqOpen->execute([$o['id']]);
        if ($dqOpen->fetchColumn()) $act[] = '<a class="link" href="' . h(url('/orders/' . $o['ref'])) . '">' . h($o['ref']) . '</a> — delivery quote ready';
        elseif (in_array($o['status'], ['PENDING_PAYMENT', 'PAYMENT_FAILED'], true)) $act[] = '<a class="link" href="' . h(url('/orders/' . $o['ref'])) . '">' . h($o['ref']) . '</a> — payment pending';
    }
    foreach ($qs as $q) if (in_array(quote_status($q), ['SENT', 'VIEWED', 'ACCEPTED'], true)) $act[] = '<a class="link" href="' . h(url('/quotes/' . $q['number'])) . '">' . h($q['number']) . '</a> — ' . (quote_status($q) === 'ACCEPTED' ? 'ready to pay' : 'quotation waiting for your reply');
    $layout('<p>Hello, <b>' . h($u['name']) . '</b>.</p>' . ($act ? '<div class="mt4">' . note('Needs your attention', implode('<br>', $act), 'gold') . '</div>' : '')
        . '<div class="tiles mt6"><a href="' . h(url('/account/orders')) . '"><p class="sm muted">Orders</p><p class="v">' . count($os) . '</p></a><a href="' . h(url('/account/quotes')) . '"><p class="sm muted">Quotations</p><p class="v">' . count($qs) . '</p></a><a href="' . h(url('/account/addresses')) . '"><p class="sm muted">Saved addresses</p><p class="v">' . count(user_addresses($uid)) . '</p></a><a href="' . h(url('/cart')) . '"><p class="sm muted">Cart items</p><p class="v">' . cart_count() . '</p></a></div>'
        . '<h2 class="mt8" style="font-size:1.15rem">Recent orders</h2><div class="mt4">' . $ordersTable(array_slice($os, 0, 5)) . '</div>');
    return;
}
if ($sub === 'orders') { $layout('<h2 style="font-size:1.2rem">Orders</h2><div class="mt4">' . $ordersTable($myOrders()) . '</div>'); return; }
if ($sub === 'quotes') {
    $qs = $myQuotes();
    if (!$qs) { $layout('<h2 style="font-size:1.2rem">Quotations</h2><div class="mt4">' . empty_state('No quotations yet', 'Request a quote and our sales team will send it here.', '<a class="btn" href="' . h(url('/request-quote')) . '">Request a quote</a>') . '</div>'); return; }
    $h = '<h2 style="font-size:1.2rem">Quotations</h2><div class="mt4 scroll box" style="padding:0"><table class="tbl cards"><thead><tr><th>Quotation</th><th>Date</th><th>Status</th><th>Valid until</th><th class="r">Total</th></tr></thead><tbody>';
    foreach ($qs as $q) $h .= '<tr><td class="first"><a class="b" href="' . h(url('/quotes/' . $q['number'])) . '">' . h($q['number']) . '</a></td><td data-l="Date">' . fmt_date($q['sent_at'] ?: $q['created']) . '</td><td data-l="Status">' . badge(humanize(quote_status($q)), quote_tone($q)) . '</td><td data-l="Valid until">' . fmt_date($q['valid_until']) . '</td><td data-l="Total" class="r tab">' . naira($q['total']) . '</td></tr>';
    $layout($h . '</tbody></table></div>'); return;
}
if ($sub === 'addresses') {
    if (is_post()) {
        require_csrf();
        $aid = (int)post('id');
        $own = function ($aid) use ($uid) { $s = db()->prepare('SELECT * FROM addresses WHERE id = ? AND user_id = ?'); $s->execute([$aid, $uid]); return $s->fetch(); };
        if (post('do') === 'delete' && ($x = $own($aid))) {
            db()->prepare('DELETE FROM addresses WHERE id = ?')->execute([$aid]);
            if ($x['is_default']) db()->prepare('UPDATE addresses SET is_default = 1 WHERE id = (SELECT MIN(id) FROM addresses WHERE user_id = ?)')->execute([$uid]);
            flash('Address deleted.'); redirect('/account/addresses');
        }
        if (post('do') === 'default' && $own($aid)) { db()->prepare('UPDATE addresses SET is_default = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE user_id = ?')->execute([$aid, $uid]); redirect('/account/addresses'); }
        $e = [];
        if (post('label') === '') $e['label'] = 'Give this address a label.';
        if (mb_strlen(post('name')) < 2) $e['name'] = "Enter the recipient's name.";
        if (!phone_ok(post('phone'))) $e['phone'] = 'Enter a valid phone number.';
        if (mb_strlen(post('line1')) < 3) $e['line1'] = 'Enter the street address.';
        if (mb_strlen(post('city')) < 2) $e['city'] = 'Enter the city or town.';
        if (!in_array(post('state'), STATES, true)) $e['state'] = 'Select a state.';
        if ($e) fail($e, '/account/addresses' . ($aid ? '/' . $aid : ''));
        $vals = [mb_substr(post('label'), 0, 80), mb_substr(post('name'), 0, 150), mb_substr(post('phone'), 0, 40), mb_substr(post('line1'), 0, 255), mb_substr(post('city'), 0, 100), post('state')];
        if ($aid && $own($aid)) db()->prepare('UPDATE addresses SET label=?, name=?, phone=?, line1=?, city=?, state=? WHERE id = ?')->execute([...$vals, $aid]);
        else { db()->prepare('INSERT INTO addresses (user_id, label, name, phone, line1, city, state, is_default) VALUES (?,?,?,?,?,?,?,0)')->execute([$uid, ...$vals]); $aid = (int)db()->lastInsertId(); }
        $count = count(user_addresses($uid));
        if (post('def') || $count === 1) db()->prepare('UPDATE addresses SET is_default = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE user_id = ?')->execute([$aid, $uid]);
        flash('Address saved.'); redirect('/account/addresses');
    }
    $list = user_addresses($uid);
    $edit = null; if ($id) foreach ($list as $x) if ((int)$x['id'] === (int)$id) $edit = $x;
    $c = csrf_field();
    $h = '<h2 style="font-size:1.2rem">Addresses</h2><div class="grid g2 mt4">';
    foreach ($list as $x) $h .= '<div class="box"><p class="b">' . h($x['label']) . ' ' . ($x['is_default'] ? badge('Default', 'gold') : '') . '</p><p class="sm mt2">' . h($x['name']) . ' · ' . h($x['phone']) . '<br>' . h($x['line1']) . '<br>' . h($x['city']) . ', ' . h($x['state']) . '</p><div class="row mt3"><a class="btn ghost sm" href="' . h(url('/account/addresses/' . $x['id'])) . '">Edit</a>'
        . (!$x['is_default'] ? '<form method="post">' . $c . '<input type="hidden" name="do" value="default"><input type="hidden" name="id" value="' . $x['id'] . '"><button class="btn ghost sm">Make default</button></form>' : '')
        . '<form method="post" data-confirm="Delete this address?">' . $c . '<input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . $x['id'] . '"><button class="btn ghost sm" style="color:var(--danger)">Delete</button></form></div></div>';
    if (!$list) $h .= '<p class="muted">No saved addresses.</p>';
    $h .= '</div><div class="box mt6"><h3>' . ($edit ? 'Edit address' : 'Add an address') . '</h3><form method="post" class="mt4" novalidate>' . $c . '<input type="hidden" name="id" value="' . (int)($edit['id'] ?? 0) . '"><div class="grid g2" style="gap:0 1rem">'
        . field('Label', 'a-label', inp('label', $edit['label'] ?? '', 'placeholder="Site, office, farm…"', 'a-label'), ['name' => 'label'])
        . field('Recipient name', 'a-name', inp('name', $edit['name'] ?? $u['name'], '', 'a-name'), ['name' => 'name'])
        . field('Phone', 'a-phone', inp('phone', $edit['phone'] ?? $u['phone'], 'type="tel"', 'a-phone'), ['name' => 'phone'])
        . field('Street address', 'a-line1', inp('line1', $edit['line1'] ?? '', '', 'a-line1'), ['name' => 'line1'])
        . field('City / town', 'a-city', inp('city', $edit['city'] ?? '', '', 'a-city'), ['name' => 'city'])
        . field('State', 'a-state', sel('state', states_opts(), $edit['state'] ?? '', '', 'a-state'), ['name' => 'state'])
        . '</div><label class="chk"><input type="checkbox" name="def" value="1" ' . (($edit['is_default'] ?? !$list) ? 'checked' : '') . '> Use as default address</label><div class="row mt4"><button class="btn">' . ($edit ? 'Save changes' : 'Add address') . '</button>' . ($edit ? '<a class="link" href="' . h(url('/account/addresses')) . '">Cancel</a>' : '') . '</div></form></div>';
    $layout($h); return;
}
if ($sub === 'profile') {
    if (is_post()) {
        require_csrf();
        if (post('do') === 'password') {
            if (!password_verify((string)post('old'), $u['password'])) fail(['old' => 'Current password is incorrect.'], '/account/profile');
            if (!pw_ok((string)post('password'))) fail(['password' => PW_RULE], '/account/profile');
            if (post('password') !== post('password2')) fail(['password2' => "Passwords don't match."], '/account/profile');
            db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash((string)post('password'), PASSWORD_DEFAULT), $uid]);
            send_email($u['email'], 'Your password was changed', 'Your ' . setting('short_name', 'KVO Mart') . " password was just changed. If this wasn't you, contact us immediately.");
            flash('Password changed.');
        } else {
            $e = []; if (mb_strlen(post('name')) < 2) $e['name'] = 'Enter your full name.'; if (!phone_ok(post('phone'))) $e['phone'] = 'Enter a valid phone number.';
            if ($e) fail($e, '/account/profile');
            db()->prepare('UPDATE users SET name = ?, phone = ?, company = ? WHERE id = ?')->execute([mb_substr(post('name'), 0, 150), mb_substr(post('phone'), 0, 40), mb_substr(post('company'), 0, 150), $uid]);
            flash('Profile saved.');
        }
        redirect('/account/profile');
    }
    $c = csrf_field();
    $layout('<div class="box"><h3>Profile</h3><form method="post" class="mt4" novalidate>' . $c . field('Full name', 'p-name', inp('name', $u['name'], 'autocomplete="name"', 'p-name'), ['name' => 'name']) . field('Phone', 'p-phone', inp('phone', $u['phone'], 'type="tel"', 'p-phone'), ['name' => 'phone']) . field('Company', 'p-company', inp('company', $u['company'], '', 'p-company'), ['optional' => true, 'name' => 'company']) . '<p class="sm muted">Email: ' . h($u['email']) . '</p><button class="btn mt4">Save profile</button></form></div>'
        . '<div class="box mt6"><h3>Change password</h3><form method="post" class="mt4" novalidate>' . $c . '<input type="hidden" name="do" value="password">' . field('Current password', 'c-old', '<input class="in' . bad('old') . '" id="c-old" name="old" type="password" autocomplete="current-password">', ['name' => 'old']) . field('New password', 'c-new', '<input class="in' . bad('password') . '" id="c-new" name="password" type="password" autocomplete="new-password">', ['hint' => PW_RULE, 'name' => 'password']) . field('Confirm new password', 'c-new2', '<input class="in' . bad('password2') . '" id="c-new2" name="password2" type="password" autocomplete="new-password">', ['name' => 'password2']) . '<button class="btn dark">Change password</button></form></div>');
    return;
}
not_found();
