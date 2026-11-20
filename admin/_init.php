<?php
require_once __DIR__ . '/../includes/bootstrap.php';

function admin(): ?array
{
    static $a = false;
    if ($a !== false) return $a;
    if (empty($_SESSION['admin_id'])) return $a = null;
    $st = db()->prepare('SELECT * FROM admins WHERE id = ? AND is_active = 1');
    $st->execute([$_SESSION['admin_id']]);
    return $a = ($st->fetch() ?: null);
}

function require_admin(string $role = ''): array
{
    $a = admin();
    if (!$a) redirect('admin/login.php');
    if ($role === 'admin' && $a['role'] !== 'admin') {
        flash('error', 'Only administrators can access that page.');
        redirect('admin/index.php');
    }
    return $a;
}

function is_super(): bool { return (admin()['role'] ?? '') === 'admin'; }

function admin_counts(): array
{
    static $c = null;
    if ($c === null) {
        $pdo = db();
        $c = [
            'orders'   => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
            'messages' => (int)$pdo->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn(),
            'reviews'  => (int)$pdo->query('SELECT COUNT(*) FROM reviews WHERE is_approved = 0')->fetchColumn(),
            'lowstock' => (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock <= low_stock')->fetchColumn(),
        ];
    }
    return $c;
}

function stock_adjust(int $productId, int $change, string $reason): void
{
    if ($change === 0) return;
    $pdo = db();
    $pdo->prepare('UPDATE products SET stock = CASE WHEN stock + ? < 0 THEN 0 ELSE stock + ? END, updated_at = ? WHERE id = ?')->execute([$change, $change, now(), $productId]);
    $after = (int)$pdo->query('SELECT stock FROM products WHERE id = ' . $productId)->fetchColumn();
    $pdo->prepare('INSERT INTO stock_log (product_id, qty_change, stock_after, reason, admin_id, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$productId, $change, $after, $reason, admin()['id'] ?? null, now()]);
}

function paginate(int $total, int $page, int $per, array $extra = []): string
{
    $pages = (int)ceil($total / $per);
    if ($pages <= 1) return '';
    $h = '<nav class="pagination">';
    for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++) {
        $q = http_build_query(array_merge($_GET, $extra, ['page' => $i]));
        $h .= $i === $page ? '<span class="active">' . $i . '</span>' : '<a href="?' . e($q) . '">' . $i . '</a>';
    }
    return $h . '</nav>';
}
