<?php
require __DIR__ . '/_init.php';
require_admin('admin');

$tabs = [
    'general'  => ['General', 'fa-store'],
    'branding' => ['Branding', 'fa-palette'],
    'contact'  => ['Contact & social', 'fa-address-book'],
    'payments' => ['Payments', 'fa-credit-card'],
    'delivery' => ['Delivery', 'fa-truck'],
    'emails'   => ['Emails', 'fa-envelope'],
];
$tab = isset($tabs[get('tab')]) ? get('tab') : 'general';

$fields = [
    'general'  => ['site_name', 'company_name', 'tagline', 'meta_description', 'about_short', 'topbar_text', 'min_order'],
    'branding' => ['color_primary', 'color_accent', 'show_powered_by'],
    'contact'  => ['phone', 'phone2', 'whatsapp', 'email', 'address', 'opening_hours', 'map_embed', 'facebook', 'instagram', 'twitter', 'tiktok'],
    'payments' => ['pay_flutterwave', 'flw_public_key', 'flw_secret_key', 'flw_hash', 'pay_transfer', 'bank_name', 'bank_account_name', 'bank_account_no', 'pay_pod', 'pay_whatsapp'],
    'delivery' => ['free_delivery_min', 'pickup_enabled', 'pickup_address'],
    'emails'   => ['send_emails', 'notify_email'],
];
$checkboxes = ['show_powered_by', 'pay_flutterwave', 'pay_transfer', 'pay_pod', 'pay_whatsapp', 'pickup_enabled', 'send_emails'];
$secrets = ['flw_secret_key', 'flw_hash'];

if (is_post()) {
    csrf_check();
    foreach ($fields[$tab] as $f) {
        if (in_array($f, $checkboxes, true)) { save_setting($f, post($f) ? '1' : '0'); continue; }
        $val = (string)post($f);
        if (in_array($f, $secrets, true) && $val === '') continue; // keep existing secret
        if (in_array($f, ['color_primary', 'color_accent'], true) && !preg_match('/^#[0-9a-f]{6}$/i', $val)) continue;
        if ($f === 'whatsapp') $val = preg_replace('/\D+/', '', $val);
        if ($f === 'map_embed' && preg_match('/src="([^"]+)"/', $val, $m)) $val = $m[1]; // allow pasting full iframe
        if (in_array($f, ['min_order', 'free_delivery_min'], true)) $val = (string)max(0, (int)$val);
        save_setting($f, trim($val));
    }
    if ($tab === 'branding') {
        if ($logo = upload_image($_FILES['logo'] ?? [], 'branding', 600)) { delete_upload(setting('logo')); save_setting('logo', $logo); }
        if (post('remove_logo')) { delete_upload(setting('logo')); save_setting('logo', ''); }
        if ($fav = upload_image($_FILES['favicon'] ?? [], 'branding', 256)) { delete_upload(setting('favicon')); save_setting('favicon', $fav); }
    }
    if ($tab === 'payments' && post('pay_flutterwave') && (!post('flw_public_key') || (setting('flw_secret_key') === '' && !post('flw_secret_key')))) {
        flash('warning', 'Flutterwave is switched on but keys are missing, so it will stay hidden at checkout until you add both keys.');
    }
    flash('success', 'Settings saved.');
    redirect('admin/settings.php?tab=' . $tab);
}

$s = fn($k) => e(setting($k));
$chk = fn($k) => setting($k) === '1' ? 'checked' : '';
$adminTitle = 'Settings';
require __DIR__ . '/_header.php';
?>
<div class="tabs-nav"><?php foreach ($tabs as $k => [$l, $i]): ?><a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><i class="fa-solid <?= $i ?>"></i> <?= $l ?></a><?php endforeach; ?></div>

<form method="post" enctype="multipart/form-data" class="card" style="max-width:860px">
  <?= csrf_field() ?>
  <?php if ($tab === 'general'): ?>
    <div class="form-grid two">
      <div class="field"><label>Store name (short)</label><input name="site_name" value="<?= $s('site_name') ?>" required></div>
      <div class="field"><label>Registered company name</label><input name="company_name" value="<?= $s('company_name') ?>"></div>
      <div class="field full"><label>Tagline</label><input name="tagline" value="<?= $s('tagline') ?>"></div>
      <div class="field full"><label>SEO description</label><textarea name="meta_description" style="min-height:70px" maxlength="300"><?= $s('meta_description') ?></textarea><small>Shown on Google and when links are shared (keep under 160 characters).</small></div>
      <div class="field full"><label>Footer "about" text</label><textarea name="about_short" style="min-height:80px"><?= $s('about_short') ?></textarea></div>
      <div class="field full"><label>Top announcement bar</label><input name="topbar_text" value="<?= $s('topbar_text') ?>"><small>Leave empty to hide the bar.</small></div>
      <div class="field"><label>Minimum order amount (<?= e(setting('currency')) ?>)</label><input type="number" name="min_order" min="0" value="<?= $s('min_order') ?>"><small>0 = no minimum.</small></div>
    </div>
  <?php elseif ($tab === 'branding'): ?>
    <div class="form-grid two">
      <div class="field"><label>Logo</label>
        <?php if (setting('logo')): ?><img src="<?= e(asset(setting('logo'))) ?>" alt="" style="max-height:60px;margin-bottom:8px;background:#f8fafc;padding:6px;border-radius:8px"><label class="check" style="font-weight:500"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label><?php endif; ?>
        <input type="file" name="logo" accept="image/*"><small>Transparent PNG, roughly 400×120. Without a logo the store name is shown.</small></div>
      <div class="field"><label>Favicon (browser tab icon)</label>
        <?php if (setting('favicon')): ?><img src="<?= e(asset(setting('favicon'))) ?>" alt="" style="width:40px;height:40px;margin-bottom:8px"><?php endif; ?>
        <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp"><small>Square PNG, 256×256.</small></div>
      <div class="field"><label>Primary colour</label><div class="color-row"><input type="color" value="<?= $s('color_primary') ?>" oninput="this.nextElementSibling.value=this.value"><input name="color_primary" value="<?= $s('color_primary') ?>" pattern="#[0-9a-fA-F]{6}"></div><small>Buttons, links, header accents.</small></div>
      <div class="field"><label>Accent colour</label><div class="color-row"><input type="color" value="<?= $s('color_accent') ?>" oninput="this.nextElementSibling.value=this.value"><input name="color_accent" value="<?= $s('color_accent') ?>" pattern="#[0-9a-fA-F]{6}"></div><small>Highlights, "Buy now", badges.</small></div>
      <label class="check full"><input type="checkbox" name="show_powered_by" value="1" <?= $chk('show_powered_by') ?>> Show "Powered by DWO" in footer</label>
    </div>
  <?php elseif ($tab === 'contact'): ?>
    <div class="form-grid two">
      <div class="field"><label>Phone</label><input name="phone" value="<?= $s('phone') ?>"></div>
      <div class="field"><label>Second phone</label><input name="phone2" value="<?= $s('phone2') ?>"></div>
      <div class="field"><label>WhatsApp number</label><input name="whatsapp" value="<?= $s('whatsapp') ?>" placeholder="2348012345678"><small>International format without + (e.g. 2348012345678). Orders and chat buttons use this.</small></div>
      <div class="field"><label>Email</label><input type="email" name="email" value="<?= $s('email') ?>"></div>
      <div class="field full"><label>Store address</label><input name="address" value="<?= $s('address') ?>"></div>
      <div class="field full"><label>Opening hours</label><input name="opening_hours" value="<?= $s('opening_hours') ?>"></div>
      <div class="field full"><label>Google Maps embed</label><input name="map_embed" value="<?= $s('map_embed') ?>" placeholder="Paste the embed code or URL from Google Maps → Share → Embed a map"></div>
      <div class="field"><label>Facebook URL</label><input name="facebook" value="<?= $s('facebook') ?>"></div>
      <div class="field"><label>Instagram URL</label><input name="instagram" value="<?= $s('instagram') ?>"></div>
      <div class="field"><label>X / Twitter URL</label><input name="twitter" value="<?= $s('twitter') ?>"></div>
      <div class="field"><label>TikTok URL</label><input name="tiktok" value="<?= $s('tiktok') ?>"></div>
    </div>
  <?php elseif ($tab === 'payments'): ?>
    <h3>Flutterwave (card, bank transfer, USSD)</h3>
    <div class="form-grid two">
      <label class="check full"><input type="checkbox" name="pay_flutterwave" value="1" <?= $chk('pay_flutterwave') ?>> Accept online payments with Flutterwave</label>
      <div class="field full"><label>Public key</label><input name="flw_public_key" value="<?= $s('flw_public_key') ?>" placeholder="FLWPUBK-..."></div>
      <div class="field"><label>Secret key</label><input type="password" name="flw_secret_key" placeholder="<?= setting('flw_secret_key') ? '•••••••• saved — leave blank to keep' : 'FLWSECK-...' ?>" autocomplete="off"></div>
      <div class="field"><label>Webhook secret hash</label><input type="password" name="flw_hash" placeholder="<?= setting('flw_hash') ? '•••••••• saved' : 'Any long random text' ?>" autocomplete="off"></div>
      <div class="field full"><small>Get keys at Flutterwave dashboard → Settings → API keys. Webhook URL: <strong><?= e(full_url('pay/webhook.php')) ?></strong> (paste the same secret hash there).</small></div>
    </div>
    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h3>Bank transfer</h3>
    <div class="form-grid two">
      <label class="check full"><input type="checkbox" name="pay_transfer" value="1" <?= $chk('pay_transfer') ?>> Accept bank transfer</label>
      <div class="field"><label>Bank name</label><input name="bank_name" value="<?= $s('bank_name') ?>"></div>
      <div class="field"><label>Account number</label><input name="bank_account_no" value="<?= $s('bank_account_no') ?>" inputmode="numeric"></div>
      <div class="field full"><label>Account name</label><input name="bank_account_name" value="<?= $s('bank_account_name') ?>"></div>
    </div>
    <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
    <h3>Other options</h3>
    <label class="check"><input type="checkbox" name="pay_pod" value="1" <?= $chk('pay_pod') ?>> Pay on delivery</label>
    <label class="check"><input type="checkbox" name="pay_whatsapp" value="1" <?= $chk('pay_whatsapp') ?>> Order via WhatsApp</label>
  <?php elseif ($tab === 'delivery'): ?>
    <div class="form-grid">
      <div class="field"><label>Free delivery for orders above (<?= e(setting('currency')) ?>)</label><input type="number" name="free_delivery_min" min="0" value="<?= $s('free_delivery_min') ?>"><small>0 = never free. Mention it in the top bar if you use it.</small></div>
      <label class="check"><input type="checkbox" name="pickup_enabled" value="1" <?= $chk('pickup_enabled') ?>> Offer free store pickup</label>
      <div class="field"><label>Pickup instructions</label><input name="pickup_address" value="<?= $s('pickup_address') ?>"></div>
      <a class="btn btn-outline" href="<?= url('admin/zones.php') ?>"><i class="fa-solid fa-truck"></i> Manage delivery zones &amp; fees</a>
    </div>
  <?php elseif ($tab === 'emails'): ?>
    <div class="form-grid">
      <label class="check"><input type="checkbox" name="send_emails" value="1" <?= $chk('send_emails') ?>> Send email notifications (new orders, status updates, contact messages)</label>
      <div class="field"><label>Send admin notifications to</label><input type="email" name="notify_email" value="<?= $s('notify_email') ?>"></div>
      <small class="muted">Uses your hosting's mail function. For best delivery, set the store email (Contact tab) to an address on your own domain created in cPanel → Email Accounts.</small>
    </div>
  <?php endif; ?>
  <div style="margin-top:18px"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save settings</button></div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
