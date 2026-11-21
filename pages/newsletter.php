<?php
if (!is_post()) not_found();
require_csrf();
$email = strtolower(post('email'));
$back = safe_next(post('back'), '/');
if (!email_ok($email)) { flash('Enter a valid email address.', 'warn'); redirect($back); }
$s = db()->prepare('SELECT COUNT(*) FROM newsletter WHERE email = ?'); $s->execute([$email]);
if (!$s->fetchColumn()) db()->prepare('INSERT INTO newsletter (email, created) VALUES (?, ?)')->execute([$email, now()]);
flash("You're subscribed. We'll email you when new machines and parts arrive.");
redirect($back);
