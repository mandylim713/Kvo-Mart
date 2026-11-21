<?php
/** Payment return pages and provider webhooks. */

if ($action === 'return') {
    $pay = payment_by_ref(q('ref'));
    if (!$pay) not_found();
    $o = order_by_id($pay['order_id']);
    $_SESSION['order_access'][$o['ref']] = 1;
    // Flutterwave adds status + transaction_id; Paystack adds reference. Either way we ask the provider directly.
    $status = q('status');
    if ($pay['provider'] === 'FLUTTERWAVE' && $status === 'cancelled') {
        $r = apply_payment($pay['ref'], 'abandoned', 0, 'callback');
    } else {
        $r = verify_online_payment($pay, 'callback', q('transaction_id') ?: null);
    }
    redirect(order_link($o) . '&pay=' . $r);
}

if ($action === 'webhook') {
    $provider = $seg[2] ?? '';
    $raw = file_get_contents('php://input');
    if ($provider === 'paystack') {
        $sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        $secret = setting('paystack_secret');
        if (!$secret || !hash_equals(hash_hmac('sha512', $raw, $secret), $sig)) { http_response_code(401); exit('bad signature'); }
        $ev = json_decode($raw, true);
        if (($ev['event'] ?? '') === 'charge.success' && ($pay = payment_by_ref($ev['data']['reference'] ?? ''))) verify_online_payment($pay, 'webhook');
        http_response_code(200); exit('ok');
    }
    if ($provider === 'flutterwave') {
        $hash = setting('flutterwave_hash');
        $sig = $_SERVER['HTTP_VERIF_HASH'] ?? '';
        if (!$hash || !hash_equals($hash, $sig)) { http_response_code(401); exit('bad signature'); }
        $ev = json_decode($raw, true);
        $d = $ev['data'] ?? $ev;
        $ref = $d['tx_ref'] ?? ($d['txRef'] ?? '');
        if ($ref && ($pay = payment_by_ref($ref))) verify_online_payment($pay, 'webhook', isset($d['id']) ? (string)$d['id'] : null);
        http_response_code(200); exit('ok');
    }
}
not_found();
