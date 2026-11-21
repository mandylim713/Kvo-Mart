<?php
/** Quotation requests, financing, compatibility, property and contact messages. */
if ($a === 'enquiry') {
    if (!is_post()) not_found();
    require_csrf();
    $back = safe_next(post('back'), '/request-quote');
    $_SESSION['form_for'] = 'enquiry';
    if (post('website') !== '') redirect($back); // bot trap
    if (!throttle('enquiry', 6, 600)) fail([], $back . '#quote', 'Too many requests. Please wait a few minutes.');
    $type = array_key_exists(post('type'), ENQ_TYPES) ? post('type') : 'GENERAL';
    $d = array_map(fn($v) => is_string($v) ? trim($v) : '', $_POST);
    $e = [];
    if (mb_strlen($d['name'] ?? '') < 2) $e['name'] = 'Enter your name or company name.';
    if (!email_ok($d['email'] ?? '')) $e['email'] = 'Enter a valid email address.';
    if (!phone_ok($d['phone'] ?? '')) $e['phone'] = 'Enter a phone number we can reach you on.';
    $p = !empty($d['pid']) ? prod((int)$d['pid']) : null;
    $prop = null; if (!empty($d['prop'])) { $s = db()->prepare('SELECT * FROM properties WHERE slug = ?'); $s->execute([$d['prop']]); $prop = $s->fetch() ?: null; }
    if (!$p && !$prop && !in_array($type, ['GENERAL', 'SUPPORT'], true) && empty($d['subject'])) $e['subject'] = 'Tell us which equipment or product you need.';
    if (in_array($type, ['GENERAL', 'SUPPORT'], true) && empty($d['message']) && empty($d['subject'])) $e['message'] = 'Add a short message so we know how to help.';
    if (!empty($d['qty']) && (int)$d['qty'] < 1) $e['qty'] = 'Quantity must be at least 1.';
    if ($e) fail($e, $back . (str_contains($back, '/p/') || $back === '/' ? '#quote' : ''));
    $code = 'E' . code(6);
    $subject = mb_substr($d['subject'] ?: ($p['name'] ?? ($prop['title'] ?? ENQ_TYPES[$type])), 0, 250);
    db()->prepare('INSERT INTO enquiries (code, type, status, name, company, email, phone, product_id, property, subject, qty, location, requirements, message, assignee, user_id, created) VALUES (?,?,\'NEW\',?,?,?,?,?,?,?,?,?,?,?,\'\',?,?)')
        ->execute([$code, $type, mb_substr($d['name'], 0, 150), mb_substr($d['company'] ?? '', 0, 150), strtolower($d['email']), mb_substr($d['phone'], 0, 40), $p['id'] ?? null, $prop['title'] ?? null, $subject, !empty($d['qty']) ? (int)$d['qty'] : null, mb_substr($d['location'] ?? '', 0, 200), mb_substr($d['requirements'] ?? '', 0, 3000), mb_substr($d['message'] ?? '', 0, 5000), current_user()['id'] ?? null, now()]);
    send_email(strtolower($d['email']), "We've received your request", "Thanks for your request: $subject" . (!empty($d['qty']) ? ', qty ' . (int)$d['qty'] : '') . ". A member of our sales team will review it and respond. Your reference is $code.");
    send_email(admin_email(), 'New ' . strtolower(ENQ_TYPES[$type]) . ' from ' . $d['name'], "{$d['name']} ({$d['email']}, {$d['phone']}): $subject\n\n" . ($d['message'] ?? ''), '/admin/enquiries/' . $code);
    clear_form_state();
    flash('Thanks — your ' . (in_array($type, ['GENERAL', 'SUPPORT'], true) ? 'message' : 'request') . " is in (reference $code). We've emailed a confirmation to " . strtolower($d['email']) . '.');
    redirect($back);
}

if ($a === 'request-quote') {
    $type = in_array(q('type'), ['QUOTE', 'FINANCING', 'PART_COMPATIBILITY'], true) ? q('type') : 'QUOTE';
    $_SESSION['form_for'] = 'enquiry';
    page_open($type === 'FINANCING' ? 'Equipment financing enquiry' : 'Request a quote', ['canonical' => '/request-quote']);
    echo '<div class="page py" style="max-width:52rem">' . crumbs([['Home', '/'], ['Request a quote']]) . '<h1 class="mt4">' . ($type === 'FINANCING' ? 'Equipment financing enquiry' : 'Request a quote') . '</h1>'
        . '<p class="muted mt2">Tell us the equipment, quantity and delivery location. Our sales team replies with a written quotation, including delivery and validity terms.</p><div class="box mt6">' . enquiry_form($type) . '</div></div>';
    page_close(); return;
}

// contact
$_SESSION['form_for'] = 'enquiry';
page_open('Contact us', ['canonical' => '/contact']);
$has = setting('phone') || setting('email') || setting('address');
?>
<div class="page py"><?= crumbs([['Home', '/'], ['Contact']]) ?><h1 class="mt4">Contact us</h1>
  <div class="split mt8"><div class="box"><?= enquiry_form(str_starts_with(q('subject'), 'Order ') ? 'SUPPORT' : 'GENERAL') ?></div>
  <div><?php if ($has): ?><div class="plate box"><?= pl([
      ['Phone', setting('phone') ? '<a href="tel:' . h(preg_replace('/[^\d+]/', '', setting('phone'))) . '">' . h(setting('phone')) . '</a>' : ''],
      ['Email', setting('email') ? '<a href="mailto:' . h(setting('email')) . '">' . h(setting('email')) . '</a>' : ''],
      ['Address', h(setting('address'))], ['Hours', h(setting('hours'))],
    ]) ?></div><?php else: echo note('Contact details', 'Send us a message using the form and our team will respond.'); endif; ?>
    <?php if (wa_number()): ?><a class="btn wa mt6" href="<?= h(wa_link('Hello ' . setting('short_name', 'KVO Mart') . ', ')) ?>" target="_blank" rel="noopener"><?= ICON['wa'] ?> Chat on WhatsApp</a><?php endif; ?>
    <div class="mt6"><?= note('Order help', 'For an existing order, <a class="link" href="' . h(url('/track')) . '">track it here</a> or include your order number in the message.') ?></div></div></div></div>
<?php page_close();
