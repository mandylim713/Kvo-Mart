<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_post()) {
    csrf_check();
    $email = strtolower(post('email'));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $st = db()->prepare('SELECT COUNT(*) FROM subscribers WHERE email = ?');
        $st->execute([$email]);
        if (!$st->fetchColumn()) db()->prepare('INSERT INTO subscribers (email, created_at) VALUES (?, ?)')->execute([$email, now()]);
        flash('success', 'You are subscribed. Watch your inbox for deals!');
    } else flash('error', 'Please enter a valid email address.');
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('index.php')));
exit;
