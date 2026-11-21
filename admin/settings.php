<?php
$tabs = ['business' => 'Business', 'payments' => 'Payments', 'checkout' => 'Checkout & tax', 'emails' => 'Emails', 'branding' => 'Branding'];
$tab = isset($tabs[q('tab')]) ? q('tab') : 'business';
if (is_post()) {
    require_csrf();
    $t = post('tab'); $back = '/admin/settings?tab=' . $t; $e = [];
    if ($t === 'business') {
        if (post('email') !== '' && !email_ok(post('email'))) $e['email'] = 'Enter a valid email or leave blank.';
        if ($e) fail($e, $back);
        foreach (['business_name', 'short_name', 'tagline', 'phone', 'email', 'address', 'hours', 'announcement', 'meta_description', 'facebook', 'instagram', 'linkedin', 'x'] as $k) save_setting($k, mb_substr(post($k), 0, 1000));
        save_setting('whatsapp', preg_replace('/[^\d+]/', '', post('whatsapp')));
    } elseif ($t === 'payments') {
        foreach (['paystack', 'flutterwave'] as $pv) {
            save_setting($pv . '_enabled', post($pv . '_enabled') ? '1' : '0');
            save_setting($pv . '_public', trim(post($pv . '_public')));
            if (post($pv . '_secret') !== '') save_setting($pv . '_secret', trim(post($pv . '_secret')));
            if (post($pv . '_clear')) save_setting($pv . '_secret', '');
        }
        if (post('flutterwave_hash') !== '') save_setting('flutterwave_hash', trim(post('flutterwave_hash')));
        save_setting('bank_transfer_enabled', post('bank_transfer_enabled') ? '1' : '0');
        save_setting('bank_instructions', mb_substr(post('bank_instructions'), 0, 2000));
        foreach (['paystack' => 'sk_', 'flutterwave' => 'FLWSECK'] as $pv => $pre) if (setting($pv . '_secret') && !str_starts_with(setting($pv . '_secret'), $pre)) flash(ucfirst($pv) . ' secret key looks unusual — check you pasted the secret (not public) key.', 'warn');
        if (setting('paystack_enabled') === '1' && !setting('paystack_secret')) flash('Paystack is switched on but has no secret key, so it stays hidden at checkout.', 'warn');
        if (setting('flutterwave_enabled') === '1' && !setting('flutterwave_secret')) flash('Flutterwave is switched on but has no secret key, so it stays hidden at checkout.', 'warn');
        audit('settings.payments', 'settings');
    } elseif ($t === 'checkout') {
        $tax = (float)post('tax'); if ($tax < 0 || $tax > 50) $e['tax'] = 'Enter a rate between 0 and 50.';
        if ((int)post('card_hold_min') < 10) $e['card_hold_min'] = 'At least 10 minutes.';
        if ((int)post('transfer_hold_hours') < 1) $e['transfer_hold_hours'] = 'At least 1 hour.';
        if ($e) fail($e, $back);
        save_setting('tax_bps', (string)(int)round($tax * 100)); save_setting('tax_label', post('tax_label') ?: 'VAT');
        save_setting('prices_include_tax', post('prices_include_tax') ? '1' : '0');
        save_setting('pickup_enabled', post('pickup_enabled') ? '1' : '0'); save_setting('pickup_note', mb_substr(post('pickup_note'), 0, 500));
        save_setting('card_hold_min', (string)(int)post('card_hold_min')); save_setting('transfer_hold_hours', (string)(int)post('transfer_hold_hours'));
    } elseif ($t === 'emails') {
        foreach (['admin_email', 'mail_from'] as $k) if (post($k) !== '' && !email_ok(post($k))) $e[$k] = 'Enter a valid email or leave blank.';
        if ($e) fail($e, $back);
        save_setting('admin_email', strtolower(post('admin_email'))); save_setting('mail_from', strtolower(post('mail_from'))); save_setting('send_emails', post('send_emails') ? '1' : '0');
        if (post('test')) { $to = admin_email(); if ($to) { send_email($to, 'Test email from your store', 'If you can read this, order and enquiry emails will reach you.'); flash('Test email sent to ' . $to . '. Check the inbox and spam folder; the Email log shows whether the server accepted it.'); } }
    } elseif ($t === 'branding') {
        try { if ($logo = upload_image($_FILES['logo'] ?? null, 'branding', 800)) { if (str_starts_with(setting('logo'), 'uploads/')) delete_upload(setting('logo')); save_setting('logo', $logo); } } catch (RuntimeException $ex) { fail(['logo' => $ex->getMessage()], $back); }
        if (post('reset_logo')) save_setting('logo', 'assets/img/logo.jpg');
        save_setting('show_credit', post('show_credit') ? '1' : '0');
    }
    audit('settings.updated', $t);
    flash('Settings saved.');
    redirect($back);
}
$s = fn($k) => setting($k);
admin_open('Settings');
echo ahead('Settings', 'Contact details stay blank until you add them — nothing is made up on the website.');
echo '<div class="chip-row" style="margin-bottom:1rem">'; foreach ($tabs as $k => $l) echo '<a class="chip' . ($tab === $k ? ' on' : '') . '" href="' . h(aurl('settings?tab=' . $k)) . '">' . $l . '</a>'; echo '</div>';
$c = csrf_field() . '<input type="hidden" name="tab" value="' . $tab . '">';
$secretNote = fn($k) => setting($k) ? '•••• saved — leave blank to keep' : 'Not set';
if ($tab === 'business') {
    echo '<form method="post" novalidate>' . $c . panel('Business details', '<div class="grid g2" style="gap:0 1rem">'
        . field('Registered name', 's1', inp('business_name', $s('business_name'), '', 's1'), ['name' => 'business_name']) . field('Short name (logo text)', 's2', inp('short_name', $s('short_name'), '', 's2'), ['name' => 'short_name'])
        . field('Tagline', 's3', inp('tagline', $s('tagline'), '', 's3'), ['name' => 'tagline']) . field('Phone', 's4', inp('phone', $s('phone'), 'type="tel"', 's4'), ['name' => 'phone'])
        . field('Email', 's5', inp('email', $s('email'), 'type="email"', 's5'), ['name' => 'email']) . field('WhatsApp number', 's6', inp('whatsapp', $s('whatsapp'), 'placeholder="0803 000 0000 or 234803…"', 's6'), ['name' => 'whatsapp', 'hint' => 'Turns on the WhatsApp buttons across the site.'])
        . field('Opening hours', 's7', inp('hours', $s('hours'), 'placeholder="Mon–Sat, 8am–6pm"', 's7'), ['name' => 'hours']) . field('Address', 's8', inp('address', $s('address'), '', 's8'), ['name' => 'address']) . '</div>'
        . field('Announcement bar', 's9', inp('announcement', $s('announcement'), 'placeholder="Blank hides the bar"', 's9'), ['name' => 'announcement', 'optional' => true])
        . field('Search engine description', 's10', ta('meta_description', $s('meta_description'), 'rows="2" maxlength="300"', 's10'), ['name' => 'meta_description', 'hint' => 'Shown on Google and when links are shared. About 155 characters.']))
        . panel('Social media links', '<div class="grid g2" style="gap:0 1rem">' . field('Facebook', 'sf', inp('facebook', $s('facebook'), 'type="url" placeholder="https://…"', 'sf'), ['optional' => true, 'name' => 'facebook']) . field('Instagram', 'si', inp('instagram', $s('instagram'), 'type="url"', 'si'), ['optional' => true, 'name' => 'instagram']) . field('LinkedIn', 'sl', inp('linkedin', $s('linkedin'), 'type="url"', 'sl'), ['optional' => true, 'name' => 'linkedin']) . field('X (Twitter)', 'sx', inp('x', $s('x'), 'type="url"', 'sx'), ['optional' => true, 'name' => 'x']) . '</div>')
        . '<button class="btn">Save</button></form>';
} elseif ($tab === 'payments') {
    $hook = fn($p) => '<p class="xs muted">Webhook URL: <code style="word-break:break-all">' . h(full_url('/pay/webhook/' . $p)) . '</code></p>';
    echo '<form method="post" autocomplete="off" novalidate>' . $c
        . panel('Paystack', cb('paystack_enabled', $s('paystack_enabled') === '1', 'Offer Paystack at checkout') . '<div class="grid g2 mt3" style="gap:0 1rem">' . field('Public key', 'pk1', inp('paystack_public', $s('paystack_public'), 'placeholder="pk_live_…"', 'pk1'), ['name' => 'paystack_public']) . field('Secret key', 'sk1', '<input class="in" id="sk1" name="paystack_secret" type="password" placeholder="' . h($secretNote('paystack_secret')) . '" autocomplete="new-password">', ['hint' => 'From Paystack → Settings → API keys & webhooks.']) . '</div>'
            . ($s('paystack_secret') ? cb('paystack_clear', false, 'Remove the saved secret key') : '') . $hook('paystack') . '<p class="xs muted mt1">Paste the webhook URL into Paystack so payments confirm even if the customer closes the page.</p>')
        . panel('Flutterwave', cb('flutterwave_enabled', $s('flutterwave_enabled') === '1', 'Offer Flutterwave at checkout') . '<div class="grid g2 mt3" style="gap:0 1rem">' . field('Public key', 'pk2', inp('flutterwave_public', $s('flutterwave_public'), 'placeholder="FLWPUBK-…"', 'pk2'), ['name' => 'flutterwave_public']) . field('Secret key', 'sk2', '<input class="in" id="sk2" name="flutterwave_secret" type="password" placeholder="' . h($secretNote('flutterwave_secret')) . '" autocomplete="new-password">')
            . field('Webhook secret hash', 'fh', '<input class="in" id="fh" name="flutterwave_hash" type="password" placeholder="' . h($secretNote('flutterwave_hash')) . '" autocomplete="new-password">', ['hint' => 'Any long random text — paste the same value in Flutterwave → Settings → Webhooks.']) . '</div>'
            . ($s('flutterwave_secret') ? cb('flutterwave_clear', false, 'Remove the saved secret key') : '') . $hook('flutterwave'))
        . panel('Bank transfer', cb('bank_transfer_enabled', $s('bank_transfer_enabled') === '1', 'Offer bank transfer at checkout') . '<div class="mt3">' . field('Transfer instructions shown to the customer', 'bi', ta('bank_instructions', $s('bank_instructions'), 'rows="5"', 'bi'), ['name' => 'bank_instructions', 'hint' => 'Bank, account name and number. The customer also sees the amount and a payment reference.']) . '</div>')
        . '<button class="btn">Save payment settings</button></form>';
} elseif ($tab === 'checkout') {
    echo '<form method="post" novalidate>' . $c . panel('Tax', '<div class="grid g2" style="gap:0 1rem">' . field('Tax rate (%)', 't1', inp('tax', (int)$s('tax_bps') / 100, 'inputmode="decimal"', 't1'), ['name' => 'tax', 'hint' => '0 hides tax everywhere. Nigeria VAT is 7.5% if you charge it.']) . field('Tax label', 't2', inp('tax_label', $s('tax_label'), '', 't2'), ['name' => 'tax_label']) . '</div>' . cb('prices_include_tax', $s('prices_include_tax') === '1', 'Listed prices already include tax'))
        . panel('Collection', cb('pickup_enabled', $s('pickup_enabled') === '1', 'Customers can choose to collect from KVO Mart') . '<div class="mt3">' . field('Collection note', 't3', inp('pickup_note', $s('pickup_note'), '', 't3'), ['name' => 'pickup_note']) . '</div><p class="sm"><a class="link" href="' . h(aurl('zones')) . '">Delivery zones and fees →</a></p>')
        . panel('Stock holds', '<p class="sm muted" style="margin-bottom:1rem">Stock is reserved while an order waits for payment, then released automatically.</p><div class="grid g2" style="gap:0 1rem">' . field('Card / online payment hold (minutes)', 't4', inp('card_hold_min', $s('card_hold_min'), 'type="number" min="10"', 't4'), ['name' => 'card_hold_min']) . field('Bank transfer hold (hours)', 't5', inp('transfer_hold_hours', $s('transfer_hold_hours'), 'type="number" min="1"', 't5'), ['name' => 'transfer_hold_hours', 'hint' => 'Orders where the customer reported a transfer are never cancelled automatically.']) . '</div>')
        . '<button class="btn">Save</button></form>';
} elseif ($tab === 'emails') {
    echo '<form method="post" novalidate>' . $c . panel('Email notifications', cb('send_emails', $s('send_emails') === '1', 'Send emails (order confirmations, payment receipts, delivery updates, quotations)')
        . '<div class="grid g2 mt3" style="gap:0 1rem">' . field('Send staff notifications to', 'e1', inp('admin_email', $s('admin_email'), 'type="email" placeholder="sales@yourdomain.com"', 'e1'), ['name' => 'admin_email', 'hint' => 'New orders, transfers to verify and enquiries. Uses the business email if blank.'])
        . field('Send from', 'e2', inp('mail_from', $s('mail_from'), 'type="email" placeholder="no-reply@yourdomain.com"', 'e2'), ['name' => 'mail_from', 'hint' => 'Use an address on your own domain (create it in cPanel → Email Accounts) so emails don’t land in spam.']) . '</div>'
        . '<div class="row"><button class="btn">Save</button><button class="btn out" name="test" value="1">Save and send a test email</button></div>') . '</form>';
} else {
    echo '<form method="post" enctype="multipart/form-data" novalidate>' . $c . panel('Logo', '<img src="' . h(asset(setting('logo'))) . '" alt="" style="height:80px;margin-bottom:1rem;border:1px solid var(--steel);padding:4px;background:#fff">' . field('Upload a new logo', 'bl', '<input class="in" id="bl" type="file" name="logo" accept="image/*">', ['name' => 'logo', 'hint' => 'PNG or JPG, wider than tall works best.'])
        . (str_starts_with(setting('logo'), 'uploads/') ? cb('reset_logo', false, 'Go back to the original KVO logo') : '')) . panel('Footer', cb('show_credit', $s('show_credit') === '1', 'Show “Powered by DWO” in the footer')) . '<button class="btn">Save</button></form>';
}
admin_close();
