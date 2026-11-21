<?php
$me = current_staff();
if (is_post()) {
    require_csrf();
    if (!password_verify((string)post('old'), $me['password'])) fail(['old' => 'Current password is incorrect.'], '/admin/profile');
    if (!pw_ok((string)post('password'))) fail(['password' => PW_RULE], '/admin/profile');
    if (post('password') !== post('password2')) fail(['password2' => "Passwords don't match."], '/admin/profile');
    if (post('password') === 'KvoAdmin2026') fail(['password' => 'Choose a new password, not the starting one.'], '/admin/profile');
    db()->prepare('UPDATE staff SET password = ? WHERE id = ?')->execute([password_hash((string)post('password'), PASSWORD_DEFAULT), $me['id']]);
    audit('staff.password_changed', $me['email']);
    flash('Password changed.');
    redirect('/admin');
}
admin_open('My password');
echo ahead('My password', h($me['name']) . ' · ' . h($me['email']));
echo panel('Change password', '<form method="post" style="max-width:28rem" novalidate>' . csrf_field()
    . field('Current password', 'c-old', '<input class="in' . bad('old') . '" id="c-old" name="old" type="password" autocomplete="current-password">', ['name' => 'old'])
    . field('New password', 'c-new', '<input class="in' . bad('password') . '" id="c-new" name="password" type="password" autocomplete="new-password">', ['hint' => PW_RULE, 'name' => 'password'])
    . field('Confirm new password', 'c-new2', '<input class="in' . bad('password2') . '" id="c-new2" name="password2" type="password" autocomplete="new-password">', ['name' => 'password2'])
    . '<button class="btn">Change password</button></form>');
admin_close();
