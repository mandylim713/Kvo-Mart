<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_check();
    if (post('website') !== '') redirect('contact.php'); // honeypot
    $name = mb_substr(post('name'), 0, 150);
    $email = mb_substr(post('email'), 0, 190);
    $phone = mb_substr(post('phone'), 0, 40);
    $subject = mb_substr(post('subject'), 0, 200);
    $message = mb_substr(post('message'), 0, 4000);
    if ($name === '' || $message === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) || ($email === '' && $phone === '')) {
        flash('error', 'Please enter your name, a message and an email or phone number.');
    } else {
        db()->prepare('INSERT INTO messages (name, email, phone, subject, message, is_read, created_at) VALUES (?,?,?,?,?,0,?)')->execute([$name, $email, $phone, $subject, $message, now()]);
        send_mail(setting('notify_email'), 'New message: ' . ($subject ?: 'Website enquiry'), '<p><strong>' . e($name) . '</strong> (' . e($email) . ' ' . e($phone) . ')</p><p>' . nl2br(e($message)) . '</p>');
        flash('success', 'Thank you! Your message has been sent. We will get back to you shortly.');
    }
    redirect('contact.php');
}

$pageTitle = 'Contact us';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-head"><h1>Contact us</h1><p class="muted">Questions about a product, an order or bulk pricing? We're here to help.</p></div>
  <div class="contact-grid">
    <div class="card">
      <div class="contact-list">
        <div class="contact-item"><i class="fa-solid fa-phone"></i><div><strong>Call us</strong><a href="tel:<?= e(preg_replace('/\s+/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a><?= setting('phone2') ? '<br><a href="tel:' . e(preg_replace('/\s+/', '', setting('phone2'))) . '">' . e(setting('phone2')) . '</a>' : '' ?></div></div>
        <div class="contact-item"><i class="fa-brands fa-whatsapp"></i><div><strong>WhatsApp</strong><a href="<?= e(wa_link('Hello ' . setting('site_name'))) ?>" target="_blank" rel="noopener">Chat with us now</a></div></div>
        <div class="contact-item"><i class="fa-solid fa-envelope"></i><div><strong>Email</strong><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></div></div>
        <div class="contact-item"><i class="fa-solid fa-location-dot"></i><div><strong>Visit our store</strong><?= e(setting('address')) ?></div></div>
        <div class="contact-item"><i class="fa-regular fa-clock"></i><div><strong>Opening hours</strong><?= e(setting('opening_hours')) ?></div></div>
      </div>
      <?php if (setting('map_embed') && preg_match('~^https://(www\.)?google\.[a-z.]+/maps/embed~', setting('map_embed'))): ?>
        <div class="map-embed" style="margin-top:16px"><iframe src="<?= e(setting('map_embed')) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe></div>
      <?php endif; ?>
    </div>
    <div class="card">
      <h3>Send us a message</h3>
      <form method="post" class="form-grid two">
        <?= csrf_field() ?>
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
        <div class="field full"><label>Your name <span class="req">*</span></label><input name="name" required value="<?= e(customer()['name'] ?? '') ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" inputmode="email" value="<?= e(customer()['email'] ?? '') ?>"></div>
        <div class="field"><label>Phone</label><input type="tel" name="phone" inputmode="tel" value="<?= e(customer()['phone'] ?? '') ?>"></div>
        <div class="field full"><label>Subject</label><input name="subject"></div>
        <div class="field full"><label>Message <span class="req">*</span></label><textarea name="message" required></textarea></div>
        <div class="full"><button class="btn btn-primary btn-lg btn-block" type="submit"><i class="fa-solid fa-paper-plane"></i> Send message</button></div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
