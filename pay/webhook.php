<?php
/**
 * Flutterwave webhook — set this URL in your Flutterwave dashboard
 * (Settings → Webhooks):  https://YOURDOMAIN/pay/webhook.php
 * and copy the same "Secret hash" into Admin → Settings → Payments.
 */
require __DIR__ . '/../includes/bootstrap.php';

$hash = setting('flw_hash');
$sig  = $_SERVER['HTTP_VERIF_HASH'] ?? '';
if ($hash === '' || !hash_equals($hash, $sig)) { http_response_code(401); exit('unauthorized'); }

$body = json_decode(file_get_contents('php://input'), true);
$data = $body['data'] ?? [];
if (($data['tx_ref'] ?? '') && ($data['id'] ?? '')) {
    $st = db()->prepare('SELECT * FROM orders WHERE tx_ref = ?');
    $st->execute([$data['tx_ref']]);
    if ($order = $st->fetch()) flw_verify_and_mark((string)$data['id'], $order); // always re-verify with the API
}
http_response_code(200);
echo 'ok';
