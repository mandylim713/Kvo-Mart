<?php
/** Admin area router. URL: /admin/{section}/{id}/{sub} */
require_once __DIR__ . '/lib.php';

if ($sec === 'login') { require __DIR__ . '/login.php'; exit; }
if ($sec === 'logout') { if (is_post()) { require_csrf(); audit('staff.logout', current_staff()['email'] ?? '-'); unset($_SESSION['sid']); session_regenerate_id(true); } redirect('/admin/login'); }
if (!current_staff()) redirect('/admin/login?next=' . urlencode($ROUTE_PATH));

$map = [
    ''           => ['dashboard.php', 'dashboard.view'],
    'orders'     => ['orders.php', 'orders.view'],
    'deliveries' => ['deliveries.php', 'deliveries.view'],
    'products'   => ['products.php', 'products.view'],
    'categories' => ['categories.php', 'products.edit'],
    'inventory'  => ['inventory.php', 'inventory.view'],
    'enquiries'  => ['enquiries.php', 'enquiries.view'],
    'quotations' => ['quotations.php', 'quotations.view'],
    'customers'  => ['customers.php', 'customers.view'],
    'promotions' => ['promotions.php', 'promotions.manage'],
    'zones'      => ['zones.php', 'settings.manage'],
    'estate'     => ['estate.php', 'content.manage'],
    'content'    => ['content.php', 'content.manage'],
    'subscribers'=> ['subscribers.php', 'customers.view'],
    'settings'   => ['settings.php', 'settings.manage'],
    'staff'      => ['staff.php', 'staff.manage'],
    'audit'      => ['audit.php', 'audit.view'],
    'emails'     => ['emails.php', 'audit.view'],
    'profile'    => ['profile.php', null],
];
$k = $sec ?? '';
if (!isset($map[$k])) { admin_open('Not found'); echo empty_state('Page not found', '', '<a class="btn" href="' . h(url('/admin')) . '">Dashboard</a>'); admin_close(); exit; }
[$file, $perm] = $map[$k];
if ($perm && !can($perm)) {
    admin_open('Access denied');
    echo panel('Access denied', note('', 'Your role (' . h(ROLE_LABEL[current_staff()['role']] ?? '') . ") can't open this section. Ask a super admin if you need access.", 'danger'));
    admin_close(); exit;
}
require __DIR__ . '/' . $file;
