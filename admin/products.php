<?php
$pdo = db();
$cats = categories(false);
$catOpts = array_map(fn($c) => [$c['slug'], $c['name'] . ' (' . (KINDS[$c['kind']] ?? $c['kind']) . ')'], $cats);

if ($id) {
    $p = $id === 'new' ? null : prod((int)$id);
    if ($id !== 'new' && !$p) { flash('Product not found.', 'warn'); redirect('/admin/products'); }
    if (is_post()) {
        require_csrf();
        need('products.edit');
        $back = '/admin/products/' . ($p['id'] ?? 'new');
        if (post('do') === 'delete_img') {
            $s = $pdo->prepare('SELECT * FROM product_images WHERE id = ? AND product_id = ?'); $s->execute([(int)post('img'), $p['id']]);
            if ($g = $s->fetch()) { delete_upload($g['path']); $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$g['id']]); flash('Photo removed.'); }
            redirect($back);
        }
        if (post('do') === 'delete') {
            $used = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?'); $used->execute([$p['id']]);
            $usedQ = $pdo->prepare('SELECT COUNT(*) FROM quotation_items WHERE product_id = ?'); $usedQ->execute([$p['id']]);
            if ($used->fetchColumn() || $usedQ->fetchColumn() || (int)$p['reserved'] > 0) { $pdo->prepare("UPDATE products SET status = 'ARCHIVED' WHERE id = ?")->execute([$p['id']]); flash('This product has orders or quotations, so it was archived (hidden) instead of deleted.'); }
            else {
                delete_upload($p['image']);
                foreach ($pdo->query('SELECT path FROM product_images WHERE product_id = ' . (int)$p['id'])->fetchAll(PDO::FETCH_COLUMN) as $g) delete_upload($g);
                foreach (['product_images', 'price_history', 'movements'] as $t) $pdo->prepare("DELETE FROM $t WHERE product_id = ?")->execute([$p['id']]);
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$p['id']]);
                flash('Product deleted.');
            }
            audit('product.deleted', $p['sku']);
            redirect('/admin/products');
        }
        $d = array_map(fn($v) => is_string($v) ? trim($v) : $v, $_POST);
        $e = [];
        if (mb_strlen($d['name'] ?? '') < 3) $e['name'] = 'Enter the product name.';
        $catRow = cat_by_slug($d['cat'] ?? ''); if (!$catRow) $e['cat'] = 'Choose a category.';
        if (!isset(COND[$d['cond'] ?? ''])) $e['cond'] = 'Choose the condition.';
        if (!isset(MODES[$d['mode'] ?? ''])) $e['mode'] = 'Choose how it is sold.';
        try { $price = parse_naira($d['price'] ?? ''); } catch (InvalidArgumentException $ex) { $e['price'] = $ex->getMessage(); $price = null; }
        if (($d['mode'] ?? '') === 'FIXED_PRICE' && !$price) $e['price'] = 'Fixed-price items need a price.';
        $sku = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $d['sku'] ?? ''));
        if ($sku === '') $sku = 'KVO-' . code(6);
        $dup = $pdo->prepare('SELECT COUNT(*) FROM products WHERE sku = ? AND id != ?'); $dup->execute([$sku, $p['id'] ?? 0]); if ($dup->fetchColumn()) $e['sku'] = 'Another product uses this SKU.';
        $slug = slugify($d['slug'] !== '' ? $d['slug'] : ($d['name'] ?? ''));
        $dup = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?'); $dup->execute([$slug, $p['id'] ?? 0]); if ($dup->fetchColumn()) $slug .= '-' . strtolower(code(4));
        $status = $d['status'] ?? 'DRAFT';
        if ($status === 'PUBLISHED' && !can('products.publish') && ($p['status'] ?? '') !== 'PUBLISHED') { $status = 'DRAFT'; flash('Saved as draft — a manager publishes it.', 'warn'); }
        if (!in_array($status, ['PUBLISHED', 'DRAFT', 'ARCHIVED'], true)) $status = 'DRAFT';
        $specs = [];
        foreach (preg_split('/\r?\n/', (string)($d['specs'] ?? '')) as $line) if (str_contains($line, ':')) { [$k, $v] = array_map('trim', explode(':', $line, 2)); if ($k !== '') $specs[] = [$k, $v]; }
        $inv = ($d['inv'] ?? '') === 'UNIQUE_UNIT' ? 'UNIQUE_UNIT' : 'QUANTITY';
        if ($e) fail($e, $back);
        try { $img = upload_image($_FILES['image'] ?? null, 'products'); } catch (RuntimeException $ex) { fail(['image' => $ex->getMessage()], $back); }
        $row = ['sku' => $sku, 'slug' => $slug, 'name' => mb_substr($d['name'], 0, 200), 'model' => mb_substr($d['model'] ?? '', 0, 120), 'kind' => $catRow['kind'], 'cat' => $catRow['slug'], 'brand' => mb_substr($d['brand'] ?? '', 0, 120),
            'cond' => $d['cond'], 'mode' => $d['mode'], 'price' => $price, 'low' => max(0, (int)($d['low'] ?? 3)), 'inv' => $inv, 'dq' => !empty($d['dq']) ? 1 : 0, 'featured' => !empty($d['featured']) ? 1 : 0, 'status' => $status,
            'description' => mb_substr($d['description'] ?? '', 0, 20000), 'specs' => json_encode($specs), 'credit' => mb_substr($d['credit'] ?? '', 0, 1000) ?: null, 'credit_url' => mb_substr($d['credit_url'] ?? '', 0, 500) ?: null, 'updated' => now()];
        if ($img) { if ($p) delete_upload($p['image']); $row['image'] = $img; if (empty($d['credit'])) { $row['credit'] = null; $row['credit_url'] = null; } }
        if (!empty($d['remove_main']) && $p && !$img) { delete_upload($p['image']); $row['image'] = null; $row['credit'] = null; $row['credit_url'] = null; }
        if ($p) {
            $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($row)));
            $pdo->prepare("UPDATE products SET $sets WHERE id = ?")->execute([...array_values($row), $p['id']]);
            $pid = (int)$p['id'];
            if ($price !== price_of($p) && $price !== null) $pdo->prepare('INSERT INTO price_history (product_id, price, at) VALUES (?,?,?)')->execute([$pid, $price, now()]);
            audit('product.updated', $sku, $price !== price_of($p) ? 'Price ' . naira(price_of($p)) . ' → ' . naira($price) : '');
        } else {
            $row['on_hand'] = 0; $row['reserved'] = 0; $row['created'] = now(); $row['sales'] = 0; $row['views'] = 0;
            $pdo->prepare('INSERT INTO products (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
            $pid = (int)$pdo->lastInsertId();
            if ($price !== null) $pdo->prepare('INSERT INTO price_history (product_id, price, at) VALUES (?,?,?)')->execute([$pid, $price, now()]);
            $open = max(0, (int)($d['opening'] ?? 0));
            if ($open) { $pdo->prepare('UPDATE products SET on_hand = ? WHERE id = ?')->execute([$open, $pid]); move(prod($pid), 'RECEIPT', $open, 'Opening stock'); }
            audit('product.created', $sku);
        }
        $n = (int)$pdo->query('SELECT COUNT(*) FROM product_images WHERE product_id = ' . $pid)->fetchColumn();
        foreach (files_list('gallery') as $f) { if ($n >= 10) break; try { if ($pth = upload_image($f, 'products')) { $pdo->prepare('INSERT INTO product_images (product_id, path, sort_order) VALUES (?,?,?)')->execute([$pid, $pth, $n++]); } } catch (RuntimeException $ex) { flash($ex->getMessage(), 'warn'); } }
        flash('Product saved.');
        redirect('/admin/products/' . $pid);
    }

    $gal = []; if ($p) { $g = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id'); $g->execute([$p['id']]); $gal = $g->fetchAll(); }
    $ph = []; if ($p) { $s = $pdo->prepare('SELECT * FROM price_history WHERE product_id = ? ORDER BY id DESC LIMIT 10'); $s->execute([$p['id']]); $ph = $s->fetchAll(); }
    $v = fn($k, $def = '') => $p[$k] ?? $def;
    $specTxt = $p ? implode("\n", array_map(fn($s) => $s[0] . ': ' . ($s[1] ?? ''), specs($p))) : '';
    $ro = can('products.edit') ? '' : ' disabled';
    admin_open($p ? $p['name'] : 'New product');
    echo ahead($p ? $p['name'] : 'New product', $p ? h($p['sku']) . ' · ' . badge(humanize($p['status']), $p['status'] === 'PUBLISHED' ? 'ok' : 'warn') : '', $p ? '<a class="btn out sm" href="' . h(url('/p/' . $p['slug'])) . '" target="_blank">View on site</a>' : '');
    echo form_msg();
    echo '<form method="post" enctype="multipart/form-data" novalidate>' . csrf_field() . '<fieldset style="border:0;padding:0;margin:0"' . $ro . '><div class="split admin" style="align-items:start"><div>';
    echo panel('Details', '<div class="grid g2" style="gap:0 1rem">'
        . field('Product name', 'pn', inp('name', $v('name'), '', 'pn'), ['name' => 'name'])
        . field('Category', 'pc', sel('cat', array_merge([['', 'Choose…']], $catOpts), $v('cat'), '', 'pc'), ['name' => 'cat'])
        . field('Manufacturer / brand', 'pb', inp('brand', $v('brand'), '', 'pb'), ['optional' => true, 'name' => 'brand'])
        . field('Model', 'pm', inp('model', $v('model'), '', 'pm'), ['optional' => true, 'name' => 'model'])
        . field('Condition', 'pcond', sel('cond', array_map(fn($k, $l) => [$k, $l], array_keys(COND), COND), $v('cond', 'NEW'), '', 'pcond'), ['name' => 'cond'])
        . field('SKU / code', 'psku', inp('sku', $v('sku'), 'placeholder="Created automatically if blank"', 'psku'), ['name' => 'sku'])
        . '</div>' . field('Description', 'pd', ta('description', $v('description'), 'rows="7"', 'pd'), ['name' => 'description', 'hint' => 'Plain text. Line breaks are kept.'])
        . field('Specifications', 'psp', ta('specs', $specTxt, 'rows="6" placeholder="Operating weight: About 29,000 kg&#10;Engine: Cat C7.1"', 'psp'), ['name' => 'specs', 'hint' => 'One per line as Name: Value. Shown as a table and used for comparisons.']));
    $img = '<div class="grid g2" style="gap:1rem">';
    $img .= '<div>' . ($p && $p['image'] ? '<img src="' . h(asset($p['image'])) . '" alt="" style="width:100%;aspect-ratio:4/3;object-fit:cover;border:1px solid var(--steel)">' . cb('remove_main', false, 'Remove main photo') : '<div class="gallery"><div class="nophoto">' . ICON['nophoto'] . '<span>No photo</span></div></div>') . '</div>';
    $img .= '<div>' . field('Main photo', 'pimg', '<input class="in" id="pimg" type="file" name="image" accept="image/*">', ['hint' => 'JPG, PNG or WEBP up to 10MB. Wide 4:3 photos look best.', 'name' => 'image'])
        . field('Photo credit', 'pcr', inp('credit', $v('credit'), 'placeholder="Leave blank for your own photos"', 'pcr'), ['optional' => true, 'name' => 'credit', 'hint' => 'Only for stock photos — marks the photo as representative.'])
        . field('Credit link', 'pcu', inp('credit_url', $v('credit_url'), '', 'pcu'), ['optional' => true, 'name' => 'credit_url']) . '</div></div>';
    $img .= '<p class="b sm mt4">More photos</p><div class="row mt2" style="align-items:flex-start">';
    foreach ($gal as $g) $img .= '<div style="width:110px"><img src="' . h(asset($g['path'])) . '" alt="" style="width:110px;height:82px;object-fit:cover;border:1px solid var(--steel)"><button class="btn ghost sm" style="color:var(--danger)" form="delimg' . $g['id'] . '">Remove</button></div>';
    $img .= '</div>' . field('Add photos', 'pgal', '<input class="in" id="pgal" type="file" name="gallery[]" accept="image/*" multiple>', ['hint' => 'Up to 10 extra photos.']);
    echo panel('Photos', $img);
    echo '</div><div>';
    echo panel('Price and selling', field('How it is sold', 'pmode', sel('mode', array_map(fn($k, $l) => [$k, $l], array_keys(MODES), MODES), $v('mode', 'FIXED_PRICE'), '', 'pmode'), ['name' => 'mode'])
        . field('Price (₦)', 'pp', inp('price', naira_input($v('price')), 'inputmode="decimal" placeholder="Leave blank for “Request price”"', 'pp'), ['name' => 'price', 'hint' => 'Includes tax if your settings say prices include tax.'])
        . field('Stock type', 'pinv', sel('inv', [['QUANTITY', 'Quantity (parts, supplies, new units)'], ['UNIQUE_UNIT', 'Single unique unit (used machine)']], $v('inv', 'QUANTITY'), '', 'pinv'), ['name' => 'inv'])
        . field('Low-stock alert at', 'plow', inp('low', $v('low', 3), 'type="number" min="0"', 'plow'), ['name' => 'low'])
        . (!$p ? field('Opening stock', 'pop', inp('opening', '1', 'type="number" min="0"', 'pop'), ['name' => 'opening', 'hint' => 'Later changes go through Inventory so every movement is logged.']) : '<p class="sm">On hand <b>' . (int)$p['on_hand'] . '</b> · reserved <b>' . (int)$p['reserved'] . '</b> · available <b>' . avail($p) . '</b> · <a class="link" href="' . h(aurl('inventory?product=' . $p['id'])) . '">Adjust stock</a></p>')
        . cb('dq', (bool)$v('dq'), 'Heavy item — delivery is quoted after the order') . cb('featured', (bool)$v('featured'), 'Feature on the home page'));
    echo panel('Visibility', field('Status', 'pst', sel('status', [['PUBLISHED', 'Published — visible in the store'], ['DRAFT', 'Draft — hidden'], ['ARCHIVED', 'Archived — sold / withdrawn']], $v('status', 'DRAFT'), '', 'pst'), ['name' => 'status'])
        . field('Web address', 'pslug', inp('slug', $v('slug'), 'placeholder="Made from the name if blank"', 'pslug'), ['optional' => true, 'name' => 'slug'])
        . (can('products.edit') ? '<button class="btn full">Save product</button>' : ''));
    if ($ph) { $hb = ''; foreach ($ph as $r) $hb .= '<div class="pr"><dt>' . fmt_date($r['at']) . '</dt><dd>' . naira($r['price']) . '</dd></div>'; echo panel('Price history', '<dl>' . $hb . '</dl>'); }
    if ($p) echo panel('Performance', pl([['Views', (int)$p['views']], ['Units sold', (int)$p['sales']], ['Created', fmt_date($p['created'])], ['Updated', fmt_date($p['updated'], true)]]));
    echo '</div></div></fieldset></form>';
    foreach ($gal as $g) echo '<form method="post" id="delimg' . $g['id'] . '">' . csrf_field() . '<input type="hidden" name="do" value="delete_img"><input type="hidden" name="img" value="' . $g['id'] . '"></form>';
    if ($p && can('products.edit')) echo '<form method="post" data-confirm="Delete this product? Products with orders are archived instead.">' . csrf_field() . '<input type="hidden" name="do" value="delete"><button class="btn ghost" style="color:var(--danger)">Delete product</button></form>';
    admin_close();
    return;
}

/* ---------- list ---------- */
$Q = array_map(fn($v) => is_string($v) ? trim($v) : '', $_GET);
$w = ['1=1']; $args = [];
if (!empty($Q['q'])) { $w[] = '(name LIKE ? OR sku LIKE ? OR brand LIKE ? OR model LIKE ?)'; $l = '%' . $Q['q'] . '%'; array_push($args, $l, $l, $l, $l); }
if (!empty($Q['cat'])) { $w[] = 'cat = ?'; $args[] = $Q['cat']; }
if (!empty($Q['status'])) { $w[] = 'status = ?'; $args[] = $Q['status']; }
$s = $pdo->prepare('SELECT * FROM products WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC'); $s->execute($args);
$rows = $s->fetchAll();
admin_open('Products');
echo ahead('Products', count($rows) . ' product' . (count($rows) === 1 ? '' : 's'), can('products.edit') ? '<a class="btn" href="' . h(aurl('products/new')) . '">Add product</a>' : '');
echo '<form class="row" style="margin-bottom:1rem"><input class="in" name="q" value="' . h($Q['q'] ?? '') . '" placeholder="Name, SKU, brand, model" style="max-width:300px"><select class="in" name="cat" style="width:auto"><option value="">All categories</option>';
foreach ($cats as $c) echo '<option value="' . h($c['slug']) . '"' . (($Q['cat'] ?? '') === $c['slug'] ? ' selected' : '') . '>' . h($c['name']) . '</option>';
echo '</select><select class="in" name="status" style="width:auto"><option value="">Any status</option>';
foreach (['PUBLISHED' => 'Published', 'DRAFT' => 'Draft', 'ARCHIVED' => 'Archived'] as $k => $l) echo '<option value="' . $k . '"' . (($Q['status'] ?? '') === $k ? ' selected' : '') . '>' . $l . '</option>';
echo '</select><button class="btn dark sm">Filter</button></form>';
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th></th><th>Product</th><th>Category</th><th class="r">Price</th><th class="r">Available</th><th>Status</th></tr></thead><tbody>';
foreach ($rows as $p) {
    [$sl, $tone] = stock_label($p);
    $t .= '<tr><td class="first" style="width:72px"><div style="width:64px;height:48px;background:var(--steel100);overflow:hidden">' . ($p['image'] ? '<img src="' . h(asset($p['image'])) . '" alt="" class="cover" style="width:64px;height:48px;object-fit:cover">' : '') . '</div></td>'
        . '<td data-l="Product"><a class="b" href="' . h(aurl('products/' . $p['id'])) . '">' . h($p['name']) . '</a><div class="xs muted">' . h($p['sku']) . ($p['featured'] ? ' · Featured' : '') . '</div></td>'
        . '<td data-l="Category" class="sm">' . h(cat_by_slug($p['cat'])['name'] ?? $p['cat']) . '</td><td data-l="Price" class="r tab">' . ($p['mode'] === 'QUOTE_ONLY' || $p['price'] === null ? '<span class="muted">On request</span>' : naira($p['price'])) . '</td>'
        . '<td data-l="Available" class="r tab">' . avail($p) . ((int)$p['reserved'] ? ' <span class="xs muted">(' . (int)$p['reserved'] . ' held)</span>' : '') . '</td><td data-l="Status">' . badge(humanize($p['status']), $p['status'] === 'PUBLISHED' ? 'ok' : ($p['status'] === 'DRAFT' ? 'warn' : '')) . ' ' . badge($sl, $tone) . '</td></tr>';
}
echo panel('Catalogue', $rows ? $t . '</tbody></table></div>' : '<p class="sm muted">No products match.</p>');
admin_close();
