<?php
$pdo = db();
if (is_post()) {
    require_csrf();
    $pid = (int)post('id');
    if (post('do') === 'delete') { $r = $pdo->query('SELECT image FROM properties WHERE id = ' . $pid)->fetch(); if ($r) delete_upload($r['image']); $pdo->prepare('DELETE FROM properties WHERE id = ?')->execute([$pid]); flash('Listing deleted.'); redirect('/admin/estate'); }
    $e = [];
    if (mb_strlen(post('title')) < 3) $e['title'] = 'Enter a title.';
    if (!in_array(post('state'), STATES, true)) $e['state'] = 'Select a state.';
    try { $price = parse_naira(post('price')); } catch (InvalidArgumentException $ex) { $e['price'] = $ex->getMessage(); $price = null; }
    $slug = slugify(post('slug') ?: post('title'));
    $dup = $pdo->prepare('SELECT COUNT(*) FROM properties WHERE slug = ? AND id != ?'); $dup->execute([$slug, $pid]); if ($dup->fetchColumn()) $slug .= '-' . strtolower(code(4));
    if ($e) fail($e, '/admin/estate?edit=' . ($pid ?: 'new'));
    try { $img = upload_image($_FILES['image'] ?? null, 'properties'); } catch (RuntimeException $ex) { fail(['image' => $ex->getMessage()], '/admin/estate?edit=' . ($pid ?: 'new')); }
    $features = json_encode(array_values(array_filter(array_map('trim', explode(',', post('features'))))));
    $vals = [$slug, mb_substr(post('title'), 0, 200), mb_substr(post('type'), 0, 60), post('listing') === 'Lease' ? 'Lease' : 'Sale', mb_substr(post('location'), 0, 150), post('state'), $price, post('size') !== '' ? (int)post('size') : null, post('beds') !== '' ? (int)post('beds') : null, $features, mb_substr(post('description'), 0, 10000), post('active') ? 1 : 0, (int)post('sort_order')];
    if ($pid) {
        $pdo->prepare('UPDATE properties SET slug=?, title=?, type=?, listing=?, location=?, state=?, price=?, size=?, beds=?, features=?, description=?, active=?, sort_order=? WHERE id=?')->execute([...$vals, $pid]);
        if ($img) { delete_upload($pdo->query('SELECT image FROM properties WHERE id = ' . $pid)->fetchColumn() ?: null); $pdo->prepare('UPDATE properties SET image = ? WHERE id = ?')->execute([$img, $pid]); }
    } else $pdo->prepare('INSERT INTO properties (slug, title, type, listing, location, state, price, size, beds, features, description, active, sort_order, image, created) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$vals, $img, now()]);
    audit('property.saved', $slug); flash('Listing saved.'); redirect('/admin/estate');
}
$e = q('edit');
$edit = ($e && $e !== 'new') ? $pdo->query('SELECT * FROM properties WHERE id = ' . (int)$e)->fetch() : null;
admin_open('Real estate');
if ($e) {
    $c = csrf_field();
    echo ahead($edit ? $edit['title'] : 'New listing', '', '<a class="link" href="' . h(aurl('estate')) . '">← All listings</a>');
    echo '<form method="post" enctype="multipart/form-data" novalidate>' . $c . '<input type="hidden" name="id" value="' . (int)($edit['id'] ?? 0) . '"><div class="split admin" style="align-items:start"><div>'
        . panel('Listing', field('Title', 'et', inp('title', $edit['title'] ?? '', '', 'et'), ['name' => 'title']) . '<div class="grid g2" style="gap:0 1rem">'
            . field('Type', 'ety', sel('type', ['Land', 'Warehouse', 'Office', 'Shop', 'House', 'Apartment', 'Industrial', 'Farm land'], $edit['type'] ?? 'Land', '', 'ety'), ['name' => 'type']) . field('Listing', 'el', sel('listing', ['Sale', 'Lease'], $edit['listing'] ?? 'Sale', '', 'el'), ['name' => 'listing'])
            . field('Location / area', 'elo', inp('location', $edit['location'] ?? '', '', 'elo'), ['name' => 'location']) . field('State', 'es', sel('state', states_opts(), $edit['state'] ?? 'FCT', '', 'es'), ['name' => 'state'])
            . field('Price (₦)', 'ep', inp('price', naira_input($edit['price'] ?? null), 'inputmode="decimal" placeholder="Blank = price on request"', 'ep'), ['name' => 'price', 'hint' => 'For leases, the yearly rent.']) . field('Size (m²)', 'esz', inp('size', $edit['size'] ?? '', 'type="number" min="0"', 'esz'), ['optional' => true, 'name' => 'size'])
            . field('Bedrooms', 'eb', inp('beds', $edit['beds'] ?? '', 'type="number" min="0"', 'eb'), ['optional' => true, 'name' => 'beds']) . field('Order', 'eo', inp('sort_order', $edit['sort_order'] ?? 0, 'type="number"', 'eo'), ['name' => 'sort_order']) . '</div>'
            . field('Features', 'ef', inp('features', implode(', ', json_decode((string)($edit['features'] ?? '[]'), true) ?: []), 'placeholder="Road frontage, Fenced, C of O"', 'ef'), ['name' => 'features', 'hint' => 'Separate with commas.'])
            . field('Description', 'ed', ta('description', $edit['description'] ?? '', 'rows="6"', 'ed'), ['name' => 'description']))
        . '</div><div>' . panel('Photo', ($edit && $edit['image'] ? '<img src="' . h(asset($edit['image'])) . '" alt="" style="width:100%;margin-bottom:.75rem">' : '') . field('Upload photo', 'ei', '<input class="in" id="ei" type="file" name="image" accept="image/*">', ['name' => 'image']))
        . panel('Publish', field('Web address', 'eslug', inp('slug', $edit['slug'] ?? '', '', 'eslug'), ['optional' => true, 'name' => 'slug']) . cb('active', (bool)($edit['active'] ?? 1), 'Visible on the website') . '<button class="btn full mt4">Save listing</button>') . '</div></div></form>';
    if ($edit) echo '<form method="post" data-confirm="Delete this listing?">' . $c . '<input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . (int)$edit['id'] . '"><button class="btn ghost" style="color:var(--danger)">Delete listing</button></form>';
    admin_close(); return;
}
$rows = $pdo->query('SELECT * FROM properties ORDER BY sort_order, id DESC')->fetchAll();
echo ahead('Real estate', 'Land, warehouses and property shown on the Real estate page. Enquiries arrive under Enquiries.', '<a class="btn" href="' . h(aurl('estate?edit=new')) . '">Add listing</a>');
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Listing</th><th>Location</th><th class="r">Price</th><th>Status</th></tr></thead><tbody>';
foreach ($rows as $p) $t .= '<tr><td class="first"><a class="b" href="' . h(aurl('estate?edit=' . $p['id'])) . '">' . h($p['title']) . '</a><div class="xs muted">' . h($p['type']) . ' · ' . h($p['listing']) . '</div></td><td data-l="Location">' . h($p['location']) . ', ' . h($p['state']) . '</td><td data-l="Price" class="r tab">' . ($p['price'] !== null ? naira($p['price']) : 'On request') . '</td><td data-l="Status">' . badge($p['active'] ? 'Visible' : 'Hidden', $p['active'] ? 'ok' : '') . '</td></tr>';
echo panel('Listings', $rows ? $t . '</tbody></table></div>' : '<p class="sm muted">No listings.</p>');
admin_close();
