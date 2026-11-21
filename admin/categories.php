<?php
$pdo = db();
if (is_post()) {
    require_csrf();
    $cid = (int)post('id');
    if (post('do') === 'delete') {
        $c = $pdo->query('SELECT * FROM categories WHERE id = ' . $cid)->fetch();
        $n = $pdo->prepare('SELECT COUNT(*) FROM products WHERE cat = ?'); $n->execute([$c['slug'] ?? '']);
        if ($n->fetchColumn()) flash('Move or delete the products in this category first.', 'warn');
        elseif ($c) { $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$cid]); audit('category.deleted', $c['slug']); flash('Category deleted.'); }
        redirect('/admin/categories');
    }
    $e = [];
    if (mb_strlen(post('name')) < 2) $e['name'] = 'Enter a name.';
    if (!isset(KINDS[post('kind')])) $e['kind'] = 'Choose the type.';
    $slug = slugify(post('slug') ?: post('name'));
    $dup = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?'); $dup->execute([$slug, $cid]); if ($dup->fetchColumn()) $e['slug'] = 'Another category uses this web address.';
    if ($e) fail($e, '/admin/categories' . ($cid ? '?edit=' . $cid : ''));
    $vals = [mb_substr(post('name'), 0, 150), post('kind'), mb_substr(post('description'), 0, 500), (int)post('sort_order'), post('active') ? 1 : 0];
    if ($cid) {
        $old = $pdo->query('SELECT slug FROM categories WHERE id = ' . $cid)->fetchColumn();
        $pdo->prepare('UPDATE categories SET name=?, kind=?, description=?, sort_order=?, active=?, slug=? WHERE id=?')->execute([...$vals, $slug, $cid]);
        if ($old !== $slug) $pdo->prepare('UPDATE products SET cat = ? WHERE cat = ?')->execute([$slug, $old]);
        $pdo->prepare('UPDATE products SET kind = ? WHERE cat = ?')->execute([post('kind'), $slug]);
    } else $pdo->prepare('INSERT INTO categories (name, kind, description, sort_order, active, slug) VALUES (?,?,?,?,?,?)')->execute([...$vals, $slug]);
    audit('category.saved', $slug);
    flash('Category saved.');
    redirect('/admin/categories');
}
$edit = q('edit') ? $pdo->query('SELECT * FROM categories WHERE id = ' . (int)q('edit'))->fetch() : null;
admin_open('Categories');
echo ahead('Categories', 'Order controls how they appear on the home page and in filters.');
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Category</th><th>Type</th><th class="r">Listed</th><th class="r">Order</th><th>Status</th><th></th></tr></thead><tbody>';
foreach (categories(false) as $c) $t .= '<tr><td class="first"><b>' . h($c['name']) . '</b><div class="xs muted">/' . h($c['slug']) . '</div></td><td data-l="Type">' . h(KINDS[$c['kind']] ?? $c['kind']) . '</td><td data-l="Listed" class="r tab">' . pub_count($c['slug']) . '</td><td data-l="Order" class="r tab">' . (int)$c['sort_order'] . '</td><td data-l="Status">' . badge($c['active'] ? 'Visible' : 'Hidden', $c['active'] ? 'ok' : '') . '</td><td><a class="btn out sm" href="' . h(aurl('categories?edit=' . $c['id'])) . '">Edit</a></td></tr>';
$c = csrf_field();
$f = '<form method="post" novalidate>' . $c . '<input type="hidden" name="id" value="' . (int)($edit['id'] ?? 0) . '">' . field('Name', 'cn', inp('name', $edit['name'] ?? '', '', 'cn'), ['name' => 'name'])
    . field('Type', 'ck', sel('kind', array_map(fn($k, $l) => [$k, $l], array_keys(KINDS), KINDS), $edit['kind'] ?? 'EQUIPMENT', '', 'ck'), ['name' => 'kind', 'hint' => 'Decides whether it appears under Heavy equipment, Spare parts or Supplies.'])
    . field('Short description', 'cd', ta('description', $edit['description'] ?? '', 'rows="2"', 'cd'), ['name' => 'description', 'optional' => true])
    . field('Web address', 'cs', inp('slug', $edit['slug'] ?? '', '', 'cs'), ['name' => 'slug', 'optional' => true]) . field('Order', 'co', inp('sort_order', $edit['sort_order'] ?? 99, 'type="number"', 'co'), ['name' => 'sort_order'])
    . cb('active', (bool)($edit['active'] ?? 1), 'Visible in the store') . '<div class="row mt4"><button class="btn">' . ($edit ? 'Save category' : 'Add category') . '</button>' . ($edit ? '<a class="link" href="' . h(aurl('categories')) . '">Cancel</a>' : '') . '</div></form>'
    . ($edit ? '<form method="post" class="mt4" data-confirm="Delete this category?">' . $c . '<input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . (int)$edit['id'] . '"><button class="btn ghost sm" style="color:var(--danger)">Delete category</button></form>' : '');
echo '<div class="split admin" style="align-items:start"><div>' . panel('All categories', $t . '</tbody></table></div>') . '</div><div>' . panel($edit ? 'Edit category' : 'Add a category', $f) . '</div></div>';
admin_close();
