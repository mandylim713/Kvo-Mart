<?php
require __DIR__ . '/_init.php';
unset($_SESSION['admin_id']);
session_regenerate_id(true);
flash('success', 'Logged out.');
redirect('admin/login.php');
