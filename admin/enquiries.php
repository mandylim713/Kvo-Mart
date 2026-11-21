<?php
$pdo = db();
if ($id) {
    $s = $pdo->prepare('SELECT * FROM enquiries WHERE code = ?'); $s->execute([$id]); $e = $s->fetch();
    if (!$e) { flash('Enquiry not found.', 'warn'); redirect('/admin/enquiries'); }
    if (is_post()) {
        require_csrf(); need('enquiries.manage');
        if (post('do') === 'quote') {
            need('quotations.manage');
            $num = 'QT-' . date('y') . code(5);
            $pdo->prepare("INSERT INTO quotations (number, token, enquiry_id, user_id, name, email, phone, company, location, status, valid_until, terms, notes, delivery, created) VALUES (?,?,?,?,?,?,?,?,?,'DRAFT',?,?,?,0,?)")
                ->execute([$num, token(), $e['id'], $e['user_id'], $e['name'], $e['email'], $e['phone'], $e['company'], $e['location'], date('Y-m-d', time() + 14 * 86400), 'Valid for 14 days. Prices in Naira. Delivery as stated.', '', now()]);
            $qid = (int)$pdo->lastInsertId();
            $p = $e['product_id'] ? prod($e['product_id']) : null;
            $pdo->prepare('INSERT INTO quotation_items (quotation_id, product_id, description, qty, unit, tax_bps) VALUES (?,?,?,?,?,0)')->execute([$qid, $p['id'] ?? null, $p ? $p['name'] . ($p['model'] ? ' (' . $p['model'] . ')' : '') : $e['subject'], max(1, (int)$e['qty']), $p ? (int)(price_of($p) ?? 0) : 0]);
            recalc_quote($qid);
            $pdo->prepare("UPDATE enquiries SET status = 'IN_PROGRESS', assignee = CASE WHEN assignee = '' THEN ? ELSE assignee END WHERE id = ?")->execute([current_staff()['name'], $e['id']]);
            audit('quotation.created', $num, 'from enquiry ' . $e['code']);
            redirect('/admin/quotations/' . $qid);
        }
        $st = array_key_exists(post('status'), ENQ_STATUS) ? post('status') : $e['status'];
        $pdo->prepare('UPDATE enquiries SET status = ?, assignee = ? WHERE id = ?')->execute([$st, mb_substr(post('assignee'), 0, 150), $e['id']]);
        audit('enquiry.updated', $e['code'], $st);
        flash('Enquiry updated.');
        redirect('/admin/enquiries/' . $e['code']);
    }
    $p = $e['product_id'] ? prod($e['product_id']) : null;
    $qs = $pdo->prepare('SELECT * FROM quotations WHERE enquiry_id = ? ORDER BY id DESC'); $qs->execute([$e['id']]); $qs = $qs->fetchAll();
    $staffOpts = array_merge([['', 'Unassigned']], array_map(fn($n) => [$n, $n], $pdo->query('SELECT name FROM staff WHERE active = 1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN)));
    $n = preg_replace('/\D+/', '', $e['phone']); if (str_starts_with($n, '0')) $n = '234' . substr($n, 1);
    admin_open('Enquiry ' . $e['code']);
    echo ahead($e['subject'], h(ENQ_TYPES[$e['type']] ?? $e['type']) . ' · ' . h($e['code']) . ' · ' . fmt_date($e['created'], true), badge(ENQ_STATUS[$e['status']] ?? $e['status'], $e['status'] === 'NEW' ? 'gold' : ''));
    $body = pl(array_filter([['From', h($e['name'])], $e['company'] ? ['Company', h($e['company'])] : null, ['Email', '<a href="mailto:' . h($e['email']) . '?subject=' . rawurlencode('Re: ' . $e['subject']) . '">' . h($e['email']) . '</a>'], ['Phone', '<a href="tel:' . h($e['phone']) . '">' . h($e['phone']) . '</a>'],
        $p ? ['Product', '<a href="' . h(aurl('products/' . $p['id'])) . '">' . h($p['name']) . '</a> · ' . price_html($p)] : null, $e['property'] ? ['Property', h($e['property'])] : null, $e['qty'] ? ['Quantity', (int)$e['qty']] : null, $e['location'] ? ['Delivery location', h($e['location'])] : null, ['Account', $e['user_id'] ? 'Registered customer' : 'Guest']]));
    if ($e['requirements']) $body .= '<p class="b sm mt4">Technical requirements</p><p class="sm" style="white-space:pre-line">' . h($e['requirements']) . '</p>';
    if ($e['message']) $body .= '<p class="b sm mt4">Message</p><p class="sm" style="white-space:pre-line">' . h($e['message']) . '</p>';
    $body .= '<div class="row mt4"><a class="btn out sm" href="mailto:' . h($e['email']) . '?subject=' . rawurlencode('Re: ' . $e['subject']) . '">Reply by email</a>' . ($n ? '<a class="btn wa sm" target="_blank" rel="noopener" href="https://wa.me/' . h($n) . '?text=' . rawurlencode('Hello ' . $e['name'] . ', thank you for your enquiry about ' . $e['subject'] . ' (ref ' . $e['code'] . ').') . '">' . ICON['wa'] . ' WhatsApp</a>' : '') . '</div>';
    $side = '';
    if (can('enquiries.manage')) $side .= panel('Handle', '<form method="post">' . csrf_field() . field('Status', 'es', sel('status', array_map(fn($k, $l) => [$k, $l], array_keys(ENQ_STATUS), ENQ_STATUS), $e['status'], '', 'es')) . field('Assigned to', 'ea', sel('assignee', $staffOpts, $e['assignee'], '', 'ea')) . '<button class="btn sm">Save</button></form>'
        . (can('quotations.manage') ? '<form method="post" class="mt4">' . csrf_field() . '<input type="hidden" name="do" value="quote"><button class="btn dark full">Prepare a quotation</button></form>' : ''));
    if ($qs) { $qb = ''; foreach ($qs as $q) $qb .= '<p class="sm"><a class="b" href="' . h(aurl('quotations/' . $q['id'])) . '">' . h($q['number']) . '</a> · ' . badge(humanize(quote_status($q)), quote_tone($q)) . ' · ' . naira($q['total']) . '</p>'; $side .= panel('Quotations', $qb); }
    echo '<div class="split admin" style="align-items:start"><div>' . panel('Request', $body) . '</div><div>' . $side . '</div></div>';
    admin_close(); return;
}
$st = q('status'); $type = q('type'); $qq = q('q');
$w = ['1=1']; $args = [];
if ($st !== '' && isset(ENQ_STATUS[$st])) { $w[] = 'status = ?'; $args[] = $st; }
if ($type !== '' && isset(ENQ_TYPES[$type])) { $w[] = 'type = ?'; $args[] = $type; }
if ($qq !== '') { $w[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR code LIKE ? OR company LIKE ?)'; $l = "%$qq%"; array_push($args, $l, $l, $l, $l, $l); }
$s = $pdo->prepare('SELECT * FROM enquiries WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT 300'); $s->execute($args); $rows = $s->fetchAll();
admin_open('Enquiries');
echo ahead('Enquiries', 'Quotation requests, financing, compatibility checks, property enquiries and contact messages.');
echo '<div class="chip-row" style="margin-bottom:1rem"><a class="chip' . ($st === '' ? ' on' : '') . '" href="' . h(aurl('enquiries')) . '">All</a>';
foreach (ENQ_STATUS as $k => $l) { $n = (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status = '$k'")->fetchColumn(); echo '<a class="chip' . ($st === $k ? ' on' : '') . '" href="' . h(aurl('enquiries?status=' . $k)) . '">' . $l . ' (' . $n . ')</a>'; }
echo '</div><form class="row" style="margin-bottom:1rem"><input type="hidden" name="status" value="' . h($st) . '"><input class="in" name="q" value="' . h($qq) . '" placeholder="Name, email, subject, reference" style="max-width:300px"><select class="in" name="type" style="width:auto"><option value="">All types</option>';
foreach (ENQ_TYPES as $k => $l) echo '<option value="' . $k . '"' . ($type === $k ? ' selected' : '') . '>' . $l . '</option>';
echo '</select><button class="btn dark sm">Filter</button></form>';
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Subject</th><th>From</th><th>Type</th><th>Received</th><th>Status</th><th>Assigned</th></tr></thead><tbody>';
foreach ($rows as $e) $t .= '<tr><td class="first"><a class="b" href="' . h(aurl('enquiries/' . $e['code'])) . '">' . h($e['subject']) . '</a><div class="xs muted">' . h($e['code']) . ($e['qty'] ? ' · qty ' . (int)$e['qty'] : '') . ($e['location'] ? ' · ' . h($e['location']) : '') . '</div></td><td data-l="From">' . h($e['name']) . '<div class="xs muted">' . h($e['company'] ?: $e['email']) . '</div></td><td data-l="Type" class="sm">' . h(ENQ_TYPES[$e['type']] ?? $e['type']) . '</td><td data-l="Received" class="sm">' . fmt_date($e['created'], true) . '</td><td data-l="Status">' . badge(ENQ_STATUS[$e['status']] ?? $e['status'], $e['status'] === 'NEW' ? 'gold' : ($e['status'] === 'CLOSED' ? '' : 'ok')) . '</td><td data-l="Assigned" class="sm">' . h($e['assignee'] ?: '—') . '</td></tr>';
echo panel('Enquiries (' . count($rows) . ')', $rows ? $t . '</tbody></table></div>' : '<p class="sm muted">No enquiries yet. Requests from the website appear here and are emailed to the notification address.</p>');
admin_close();
