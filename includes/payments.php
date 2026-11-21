<?php
/* ==================================================================
   Online payment providers: Paystack and Flutterwave.
   Amounts are in kobo internally. Paystack takes kobo; Flutterwave
   takes Naira. Every result is re-verified with the provider's API
   before it is applied, so nothing on the browser side is trusted.
   ================================================================== */

function pay_enabled(string $provider): bool
{
    return match ($provider) {
        'PAYSTACK' => setting('paystack_enabled') === '1' && setting('paystack_secret') !== '',
        'FLUTTERWAVE' => setting('flutterwave_enabled') === '1' && setting('flutterwave_secret') !== '',
        'BANK_TRANSFER' => setting('bank_transfer_enabled') === '1',
        default => false,
    };
}
function pay_methods(): array
{
    $m = [];
    if (pay_enabled('PAYSTACK')) $m['PAYSTACK'] = ['Pay online — Paystack', "Card, bank or USSD. You'll be taken to Paystack's secure payment page."];
    if (pay_enabled('FLUTTERWAVE')) $m['FLUTTERWAVE'] = ['Pay online — Flutterwave', 'Card, bank transfer or USSD through Flutterwave.'];
    if (pay_enabled('BANK_TRANSFER') || !$m) $m['BANK_TRANSFER'] = ['Bank transfer', 'Pay into our account. Your order is confirmed once our finance team verifies the transfer. Stock is held for ' . (int)setting('transfer_hold_hours', 48) . ' hours.'];
    return $m;
}
function online_provider(): ?string { return pay_enabled('PAYSTACK') ? 'PAYSTACK' : (pay_enabled('FLUTTERWAVE') ? 'FLUTTERWAVE' : null); }

function http_json(string $method, string $url, string $secret, ?array $body = null): ?array
{
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret, 'Content-Type: application/json', 'Accept: application/json']]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch);
    curl_close($ch);
    $j = $res ? json_decode($res, true) : null;
    return is_array($j) ? $j : null;
}

/** Start a hosted checkout. Returns the provider URL to redirect to, or throws. */
function start_online_payment(array $o, array $pay): string
{
    $o = order_by_id($o['id']);
    $callback = full_url('/pay/return?ref=' . urlencode($pay['ref']));
    if ($pay['provider'] === 'PAYSTACK') {
        $r = http_json('POST', 'https://api.paystack.co/transaction/initialize', setting('paystack_secret'), [
            'email' => $o['email'], 'amount' => (int)$pay['amount'], 'currency' => 'NGN', 'reference' => $pay['ref'], 'callback_url' => $callback,
            'metadata' => ['order_ref' => $o['ref'], 'purpose' => $pay['purpose'], 'custom_fields' => [['display_name' => 'Order', 'variable_name' => 'order', 'value' => $o['ref']]]],
        ]);
        if (!empty($r['status']) && !empty($r['data']['authorization_url'])) return $r['data']['authorization_url'];
        throw new RuntimeException('Paystack could not start the payment' . (!empty($r['message']) ? ': ' . $r['message'] : '. Try again or pay by bank transfer.'));
    }
    if ($pay['provider'] === 'FLUTTERWAVE') {
        $logo = setting('logo') ? full_url(setting('logo')) : null;
        $r = http_json('POST', 'https://api.flutterwave.com/v3/payments', setting('flutterwave_secret'), [
            'tx_ref' => $pay['ref'], 'amount' => round((int)$pay['amount'] / 100, 2), 'currency' => 'NGN', 'redirect_url' => $callback,
            'customer' => ['email' => $o['email'], 'phonenumber' => $o['phone'], 'name' => $o['name']],
            'customizations' => array_filter(['title' => setting('short_name', 'KVO Mart'), 'description' => ($pay['purpose'] === 'DELIVERY' ? 'Delivery for ' : 'Order ') . $o['ref'], 'logo' => $logo]),
            'meta' => ['order_ref' => $o['ref'], 'purpose' => $pay['purpose']],
        ]);
        if (($r['status'] ?? '') === 'success' && !empty($r['data']['link'])) return $r['data']['link'];
        throw new RuntimeException('Flutterwave could not start the payment' . (!empty($r['message']) ? ': ' . $r['message'] : '. Try again or pay by bank transfer.'));
    }
    throw new RuntimeException('That payment method is not available.');
}

/**
 * Ask the provider for the real result of a payment and apply it.
 * Returns paid|already|failed|abandoned|mismatch|pending.
 */
function verify_online_payment(array $pay, string $source, ?string $flwTxId = null): string
{
    if ($pay['status'] === 'SUCCEEDED') return 'already';
    if ($pay['provider'] === 'PAYSTACK') {
        $r = http_json('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($pay['ref']), setting('paystack_secret'));
        if (!$r || empty($r['status'])) return 'pending';
        $d = $r['data'] ?? [];
        $st = $d['status'] ?? '';
        if ($st === 'success' && strtoupper($d['currency'] ?? 'NGN') === 'NGN') return apply_payment($pay['ref'], 'success', (int)$d['amount'], $source, (string)($d['id'] ?? ''));
        if (in_array($st, ['failed', 'reversed'], true)) return apply_payment($pay['ref'], 'failed', 0, $source);
        if ($st === 'abandoned') return 'pending';
        return 'pending';
    }
    if ($pay['provider'] === 'FLUTTERWAVE') {
        $url = $flwTxId ? 'https://api.flutterwave.com/v3/transactions/' . rawurlencode($flwTxId) . '/verify'
                        : 'https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=' . rawurlencode($pay['ref']);
        $r = http_json('GET', $url, setting('flutterwave_secret'));
        if (!$r || ($r['status'] ?? '') !== 'success') return 'pending';
        $d = $r['data'] ?? [];
        if (($d['tx_ref'] ?? '') !== $pay['ref']) return 'pending';
        $st = $d['status'] ?? '';
        if ($st === 'successful' && strtoupper($d['currency'] ?? '') === 'NGN') return apply_payment($pay['ref'], 'success', (int)round(((float)$d['amount']) * 100), $source, (string)($d['id'] ?? ''));
        if ($st === 'failed') return apply_payment($pay['ref'], 'failed', 0, $source);
        return 'pending';
    }
    return 'pending';
}
