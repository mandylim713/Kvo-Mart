<?php
$pdo = db();
require_once __DIR__ . '/../pages/order_parts.php';

if ($id === 'new') {
    need('quotations.manage');
    $num = 'QT-' . date('y') . code(5);
    $pdo->prepare("INSERT INTO quotations (number, token, name, email, phone, company, location, status, valid_until, terms, notes, delivery, created) VALUES (?,?,'','','','','','DRAFT',?,?,'',0,?)")
        ->execute([$num, token(), date('Y-m-d', time() + 14 * 86400), 'Valid for 14 days. Prices in Naira. Delivery as stated.', now()]);
    redirect('/admin/quotations/' . $pdo->lastInsertId());
}
if ($id) {
    $q = quote_by_id((int)$id); if (!$q) { flash('Quotation not found.', 'warn'); redirect('/admin/quotations'); }
    $qid = (int)$q['id']; $back = '/admin/quotations/' . $qid;
    if (is_post()) {
        require_csrf(); need('quotations.manage');
        $do = post('do');
        try {
            if ($do === 'save' || $do === 'send') {
                if (!in_array($q['status'], ['DRAFT', 'SENT', 'VIEWED'], true)) throw new RuntimeException('Accepted or closed quotations cannot be edited. Create a new one.');
                $e = [];
                if (mb_strlen(post('name')) < 2) $e['name'] = 'Enter the customer name.';
                if (!email_ok(post('email'))) $e['email'] = 'Enter a valid email.';
                $vu = post('valid_until'); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $vu)) $e['valid_until'] = 'Choose a date.';
                try { $del = parse_naira(post('delivery')) ?? 0; } catch (InvalidArgumentException $ex) { $e['delivery'] = $ex->getMessage(); $del = 0; }
                $lines = [];
                $descs = (array)($_POST['l_desc'] ?? []);
                foreach ($descs as $i => $desc) {
                    $desc = trim((string)$desc); $pid = (int)($_POST['l_pid'][$i] ?? 0); $qty = (int)($_POST['l_qty'][$i] ?? 0);
                    try { $unit = parse_naira($_POST['l_unit'][$i] ?? '') ?? 0; } catch (InvalidArgumentException $ex) { $e['lines'] = 'Line ' . ($i + 1) . ': ' . $ex->getMessage(); continue; }
                    $tax = (int)round(((float)($_POST['l_tax'][$i] ?? 0)) * 100);
                    if ($desc === '' && !$pid) continue;
                    if ($pid && ($pp = prod($pid)) && $desc === '') $desc = $pp['name'];
                    if ($qty < 1) { $e['lines'] = 'Line ' . ($i + 1) . ': quantity must be at least 1.'; continue; }
                    $lines[] = [$pid ?: null, mb_substr($desc, 0, 255), $qty, $unit, max(0, min(5000, $tax))];
                }
                if (!$lines) $e['lines'] = 'Add at least one line.';
                if ($do === 'send' && array_filter($lines, fn($l) => $l[3] <= 0)) $e['lines'] = 'Every line needs a unit price before sending.';
                if ($e) { $_SESSION['errors'] = $e; $_SESSION['old'] = $_POST; flash(reset($e), 'danger'); redirect($back); }
                $pdo->prepare('UPDATE quotations SET name=?, email=?, phone=?, company=?, location=?, valid_until=?, terms=?, notes=?, delivery=? WHERE id=?')
                    ->execute([mb_substr(post('name'), 0, 150), strtolower(post('email')), mb_substr(post('phone'), 0, 40), mb_substr(post('company'), 0, 150), mb_substr(post('location'), 0, 200), $vu, mb_substr(post('terms'), 0, 3000), mb_substr(post('notes'), 0, 3000), $del, $qid]);
                $pdo->prepare('DELETE FROM quotation_items WHERE quotation_id = ?')->execute([$qid]);
                $ins = $pdo->prepare('INSERT INTO quotation_items (quotation_id, product_id, description, qty, unit, tax_bps) VALUES (?,?,?,?,?,?)');
                foreach ($lines as $l) $ins->execute([$qid, ...$l]);
                recalc_quote($qid);
                $u = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $u->execute([strtolower(post('email'))]);
                if ($uid = $u->fetchColumn()) $pdo->prepare('UPDATE quotations SET user_id = ? WHERE id = ?')->execute([$uid, $qid]);
                if ($do === 'send') {
                    $pdo->prepare("UPDATE quotations SET status = 'SENT', sent_at = ? WHERE id = ?")->execute([now(), $qid]);
                    $q = quote_by_id($qid);
                    if ($q['enquiry_id']) $pdo->prepare("UPDATE enquiries SET status = 'QUOTED' WHERE id = ?")->execute([$q['enquiry_id']]);
                    send_email($q['email'], 'Quotation ' . $q['number'] . ' from ' . setting('short_name', 'KVO Mart'), "Dear {$q['name']},\n\nPlease find our quotation {$q['number']} for " . naira($q['total']) . ', valid until ' . fmt_date($q['valid_until']) . ".\n\nOpen the quotation to view the details, accept it and pay online or by bank transfer.", quote_link($q));
                    audit('quotation.sent', $q['number'], naira($q['total']));
                    flash('Quotation sent to ' . $q['email'] . '.');
                } else { audit('quotation.saved', $q['number']); flash('Draft saved.'); }
            } elseif ($do === 'accept') {
                if (!in_array(quote_status($q), ['SENT', 'VIEWED'], true)) throw new RuntimeException('Only an open quotation can be marked accepted.');
                $pdo->prepare("UPDATE quotations SET status = 'ACCEPTED', accepted_at = ? WHERE id = ?")->execute([now(), $qid]);
                audit('quotation.accepted', $q['number'], 'confirmed offline by ' . current_staff()['name']); flash('Marked as accepted.');
            } elseif ($do === 'decline') {
                $pdo->prepare("UPDATE quotations SET status = 'DECLINED' WHERE id = ?")->execute([$qid]); audit('quotation.declined', $q['number'], 'by staff'); flash('Marked as declined.');
            } elseif ($do === 'convert') {
                if ($q['status'] !== 'ACCEPTED') throw new RuntimeException('Mark the quotation accepted first.');
                $e = [];
                if (mb_strlen(post('line1')) < 3) $e['line1'] = 'Enter the street address.';
                if (mb_strlen(post('city')) < 2) $e['city'] = 'Enter the city.';
                if (!in_array(post('state'), STATES, true)) $e['state'] = 'Select a state.';
                if ($e) fail($e, $back);
                $pay = in_array(post('pay'), ['BANK_TRANSFER', 'PAYSTACK', 'FLUTTERWAVE'], true) ? post('pay') : 'BANK_TRANSFER';
                $o = quote_to_order($q, ['line1' => post('line1'), 'city' => post('city'), 'state' => post('state'), 'instr' => ''], $pay);
                if ($pay === 'BANK_TRANSFER') new_payment($o, 'BANK_TRANSFER');
                flash('Order ' . $o['ref'] . ' created. The customer has been emailed a link to pay.');
                redirect('/admin/orders/' . $o['ref']);
            } elseif ($do === 'delete') {
                if ($q['status'] !== 'DRAFT') throw new RuntimeException('Only drafts can be deleted.');
                $pdo->prepare('DELETE FROM quotation_items WHERE quotation_id = ?')->execute([$qid]); $pdo->prepare('DELETE FROM quotations WHERE id = ?')->execute([$qid]);
                flash('Draft deleted.'); redirect('/admin/quotations');
            }
        } catch (RuntimeException $ex) { flash($ex->getMessage(), 'danger'); }
        redirect($back);
    }
    $q = quote_by_id($qid); $items = quote_items($qid); $st = quote_status($q);
    $editable = can('quotations.manage') && in_array($q['status'], ['DRAFT', 'SENT', 'VIEWED'], true);
    $old = $_SESSION['old'] ?? null;
    if ($old && isset($old['l_desc'])) { $items = []; foreach ($old['l_desc'] as $i => $d) $items[] = ['product_id' => $old['l_pid'][$i] ?? '', 'description' => $d, 'qty' => $old['l_qty'][$i] ?? 1, 'unit' => null, 'unit_raw' => $old['l_unit'][$i] ?? '', 'tax_bps' => (int)round((float)($old['l_tax'][$i] ?? 0) * 100)]; }
    $prods = $pdo->query("SELECT id, name, sku, price, mode FROM products WHERE status = 'PUBLISHED' ORDER BY name")->fetchAll();
    $popts = '<option value="">Custom line</option>'; foreach ($prods as $p) $popts .= '<option value="' . $p['id'] . '" data-name="' . h($p['name']) . '" data-price="' . h(naira_input($p['price'])) . '">' . h($p['name'] . ' (' . $p['sku'] . ')') . '</option>';
    $line = function ($i) use ($popts) {
        $pid = (string)($i['product_id'] ?? '');
        return '<div class="ql" data-line><div class="f"><label class="l">Catalogue item</label><select class="in" name="l_pid[]" data-lpick>' . str_replace('value="' . $pid . '"', 'value="' . $pid . '" selected', $popts) . '</select></div>'
            . '<div class="f"><label class="l">Description</label><input class="in" name="l_desc[]" value="' . h($i['description'] ?? '') . '"></div>'
            . '<div class="f"><label class="l">Qty</label><input class="in" name="l_qty[]" type="number" min="1" value="' . (int)($i['qty'] ?? 1) . '"></div>'
            . '<div class="f"><label class="l">Unit price (₦)</label><input class="in" name="l_unit[]" inputmode="decimal" value="' . h($i['unit_raw'] ?? naira_input($i['unit'] ?? null)) . '"></div>'
            . '<div class="f"><label class="l">Tax %</label><input class="in" name="l_tax[]" inputmode="decimal" value="' . h(($i['tax_bps'] ?? 0) / 100) . '"></div>'
            . '<div class="f"><button type="button" class="btn ghost sm" style="color:var(--danger)" data-lrm>Remove</button></div></div>';
    };
    admin_open($q['number']);
    echo ahead($q['number'], 'Created ' . fmt_date($q['created']) . ($q['enquiry_id'] ? ' · from an enquiry' : ''), '<div class="row">' . badge(humanize($st), quote_tone($q)) . ($q['status'] !== 'DRAFT' ? '<a class="btn out sm" target="_blank" href="' . h(url(quote_link($q))) . '">Customer view</a>' : '') . '</div>');
    if ($editable) {
        $c = csrf_field();
        $lines = ''; foreach ($items ?: [['description' => '', 'qty' => 1, 'unit' => null]] as $i) $lines .= $line($i);
        echo '<form method="post" novalidate>' . $c . '<div class="split admin" style="align-items:start"><div>'
            . panel('Customer', '<div class="grid g2" style="gap:0 1rem">' . field('Name', 'qn', inp('name', $q['name'], '', 'qn'), ['name' => 'name']) . field('Email', 'qe', inp('email', $q['email'], 'type="email"', 'qe'), ['name' => 'email']) . field('Phone', 'qph', inp('phone', $q['phone'], 'type="tel"', 'qph'), ['optional' => true, 'name' => 'phone']) . field('Company', 'qc', inp('company', $q['company'], '', 'qc'), ['optional' => true, 'name' => 'company']) . field('Delivery location', 'ql', inp('location', $q['location'], '', 'ql'), ['optional' => true, 'name' => 'location']) . field('Valid until', 'qv', inp('valid_until', substr((string)$q['valid_until'], 0, 10), 'type="date"', 'qv'), ['name' => 'valid_until']) . '</div>')
            . panel('Line items', '<div id="qlines">' . $lines . '</div><template id="qline-tpl">' . $line(['description' => '', 'qty' => 1, 'unit' => null]) . '</template><button type="button" class="btn out sm" data-ladd>Add line</button>' . ferr('lines')
                . '<div class="grid g2 mt6" style="gap:0 1rem">' . field('Delivery charge (₦)', 'qd', inp('delivery', naira_input((int)$q['delivery'] ?: null), 'inputmode="decimal" placeholder="Blank = collection / not included"', 'qd'), ['name' => 'delivery']) . '</div>'
                . field('Terms', 'qt', ta('terms', $q['terms'], 'rows="2"', 'qt'), ['name' => 'terms']) . field('Notes for the customer', 'qno', ta('notes', $q['notes'], 'rows="2"', 'qno'), ['optional' => true, 'name' => 'notes']))
            . '</div><div>' . panel('Totals', pl([['Subtotal', naira($q['subtotal'])], (int)$q['tax'] ? ['Tax', naira($q['tax'])] : null, ['Delivery', naira($q['delivery'])], ['Total', '<span class="price">' . naira($q['total']) . '</span>']]) . '<p class="xs muted mt2">Totals update when you save.</p>'
                . '<div class="mt4" style="display:grid;gap:.5rem"><button class="btn dark" name="do" value="save">Save draft</button><button class="btn" name="do" value="send">' . ($q['status'] === 'DRAFT' ? 'Save and send to customer' : 'Save and re-send') . '</button></div>')
            . '</div></div></form>';
    } else {
        echo panel('Quotation', quote_doc($q, $items));
    }
    // side actions
    $acts = '';
    if (can('quotations.manage')) {
        $c = csrf_field();
        if (in_array($st, ['SENT', 'VIEWED'], true)) $acts .= '<form method="post">' . $c . '<input type="hidden" name="do" value="accept"><button class="btn out sm">Mark accepted (customer confirmed offline)</button></form><form method="post" class="mt2">' . $c . '<input type="hidden" name="do" value="decline"><button class="btn ghost sm">Mark declined</button></form>';
        if ($q['status'] === 'ACCEPTED') $acts .= '<form method="post" novalidate>' . $c . '<input type="hidden" name="do" value="convert"><p class="b sm">Create the order</p><p class="xs muted">The customer can also do this themselves from their quotation page.</p><div class="grid g2 mt2" style="gap:0 1rem">' . field('Street address', 'qc-l', inp('line1', '', '', 'qc-l'), ['name' => 'line1']) . field('City', 'qc-c', inp('city', '', '', 'qc-c'), ['name' => 'city']) . field('State', 'qc-s', sel('state', states_opts(), '', '', 'qc-s'), ['name' => 'state']) . field('Payment', 'qc-p', sel('pay', array_map(fn($k, $v) => [$k, $v[0]], array_keys(pay_methods()), pay_methods()), '', '', 'qc-p'), ['name' => 'pay']) . '</div><button class="btn">Create order</button></form>';
        if ($q['status'] === 'DRAFT') $acts .= '<form method="post" class="mt4" data-confirm="Delete this draft quotation?">' . $c . '<input type="hidden" name="do" value="delete"><button class="btn ghost sm" style="color:var(--danger)">Delete draft</button></form>';
    }
    $info = pl(array_filter([['Status', humanize($st)], ['Sent', fmt_date($q['sent_at'], true)], $q['accepted_at'] ? ['Accepted', fmt_date($q['accepted_at'], true)] : null, ['Valid until', fmt_date($q['valid_until'])], $q['order_ref'] ? ['Order', '<a href="' . h(aurl('orders/' . $q['order_ref'])) . '">' . h($q['order_ref']) . '</a>'] : null, $q['status'] !== 'DRAFT' ? ['Customer link', '<a href="' . h(url(quote_link($q))) . '" target="_blank" style="word-break:break-all">Open</a>'] : null]));
    if ($acts || $info) echo '<div class="split admin" style="align-items:start"><div></div><div>' . panel('Status', $info) . ($acts ? panel('Actions', $acts) : '') . '</div></div>';
    admin_close(); return;
}

$st = q('status');
$w = '1=1'; $args = [];
if ($st !== '') { $w = 'status = ?'; $args[] = $st; }
$s = $pdo->prepare("SELECT * FROM quotations WHERE $w ORDER BY id DESC LIMIT 300"); $s->execute($args); $rows = $s->fetchAll();
admin_open('Quotations');
echo ahead('Quotations', 'Written quotes for machines, bulk orders and special specifications.', can('quotations.manage') ? '<a class="btn" href="' . h(aurl('quotations/new')) . '">New quotation</a>' : '');
echo '<div class="chip-row" style="margin-bottom:1rem"><a class="chip' . ($st === '' ? ' on' : '') . '" href="' . h(aurl('quotations')) . '">All</a>';
foreach (['DRAFT', 'SENT', 'VIEWED', 'ACCEPTED', 'CONVERTED', 'DECLINED'] as $k) echo '<a class="chip' . ($st === $k ? ' on' : '') . '" href="' . h(aurl('quotations?status=' . $k)) . '">' . humanize($k) . '</a>';
echo '</div>';
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Quotation</th><th>Customer</th><th>Status</th><th>Valid until</th><th class="r">Total</th></tr></thead><tbody>';
foreach ($rows as $q) $t .= '<tr><td class="first"><a class="b" href="' . h(aurl('quotations/' . $q['id'])) . '">' . h($q['number']) . '</a><div class="xs muted">' . fmt_date($q['sent_at'] ?: $q['created']) . '</div></td><td data-l="Customer">' . h($q['name'] ?: '—') . '<div class="xs muted">' . h($q['company'] ?: $q['email']) . '</div></td><td data-l="Status">' . badge(humanize(quote_status($q)), quote_tone($q)) . '</td><td data-l="Valid until">' . fmt_date($q['valid_until']) . '</td><td data-l="Total" class="r tab">' . naira($q['total']) . '</td></tr>';
echo panel('Quotations (' . count($rows) . ')', $rows ? $t . '</tbody></table></div>' : '<p class="sm muted">No quotations yet. Prepare one from an enquiry or start a new one.</p>');
admin_close();
