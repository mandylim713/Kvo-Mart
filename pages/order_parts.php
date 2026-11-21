<?php
/** Shared order display pieces (customer and admin). */
if (!function_exists('order_items_table')) {
function order_items_table(array $o): string
{
    $h = '<div class="scroll"><table class="tbl"><thead><tr><th>Item</th><th class="r">Unit</th><th class="r">Qty</th><th class="r">Amount</th></tr></thead><tbody>';
    foreach (order_items((int)$o['id']) as $i) $h .= '<tr><td>' . h($i['name']) . '<div class="xs muted">' . h($i['sku']) . '</div></td><td class="r tab">' . naira($i['unit']) . '</td><td class="r tab">' . (int)$i['qty'] . '</td><td class="r tab">' . naira($i['line_total']) . '</td></tr>';
    $h .= '</tbody></table></div><div style="max-width:22rem;margin-left:auto" class="mt3">' . pl(array_filter([
        ['Subtotal', naira($o['subtotal'])],
        $o['discount'] ? ['Discount' . ($o['promo'] ? ' (' . h($o['promo']) . ')' : ''), '−' . naira($o['discount'])] : null,
        (int)$o['tax'] ? [h(setting('tax_label')) . (setting('prices_include_tax') === '1' ? ' (included)' : ''), naira($o['tax'])] : null,
        ['Delivery', $o['delivery_method'] === 'PICKUP' ? 'Collection' : ($o['d_fee'] === null ? 'To be quoted' : naira($o['delivery_charge']))],
        ['Total', '<span class="price" style="font-size:1.1rem">' . naira($o['total']) . '</span>'],
        ['Paid', naira($o['paid'])],
        $o['refunded'] ? ['Refunded', naira($o['refunded'])] : null,
    ])) . '</div>';
    return $h;
}
function order_timeline(array $o): string
{
    $ev = [['at' => $o['created'], 't' => 'Order placed']];
    foreach (order_payments((int)$o['id']) as $p) $ev[] = ['at' => $p['verified_at'] ?: $p['at'], 't' => ($p['purpose'] === 'DELIVERY' ? 'Delivery payment' : 'Payment') . ' ' . (PAY_LABEL[$p['provider']] ?? $p['provider']) . ': ' . humanize($p['status']) . ($p['note'] ? ' — ' . $p['note'] : '')];
    foreach (array_slice(order_events((int)$o['id']), 1) as $e) $ev[] = ['at' => $e['at'], 't' => (DELIVERY_STATUS[$e['status']] ?? $e['status']) . ($e['note'] ? ' — ' . $e['note'] : '')];
    foreach (order_refunds((int)$o['id']) as $r) $ev[] = ['at' => $r['at'], 't' => 'Refund ' . naira($r['amount']) . ': ' . $r['reason']];
    usort($ev, fn($a, $b) => strcmp($a['at'], $b['at']));
    $h = '<ol class="timeline">'; foreach ($ev as $e) $h .= '<li><p class="sm b">' . h($e['t']) . '</p><p class="xs muted">' . fmt_date($e['at'], true) . '</p></li>'; return $h . '</ol>';
}
}

if (!function_exists('quote_doc')) {
function quote_doc(array $q, array $items): string
{
    $h = '<div class="scroll"><table class="tbl"><thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead><tbody>';
    foreach ($items as $i) $h .= '<tr><td>' . h($i['description']) . '</td><td class="r tab">' . (int)$i['qty'] . '</td><td class="r tab">' . naira($i['unit']) . '</td><td class="r tab">' . naira($i['unit'] * $i['qty']) . '</td></tr>';
    $h .= '</tbody></table></div><div style="max-width:22rem;margin-left:auto" class="mt3">' . pl(array_filter([['Subtotal', naira($q['subtotal'])], (int)$q['tax'] ? ['Tax', naira($q['tax'])] : null, ['Delivery', (int)$q['delivery'] ? naira($q['delivery']) : 'Not included / collection'], ['Total', '<span class="price" style="font-size:1.1rem">' . naira($q['total']) . '</span>']])) . '</div>';
    if ($q['terms']) $h .= '<p class="sm mt4"><b>Terms:</b> ' . nl2br(h($q['terms'])) . '</p>';
    if ($q['notes']) $h .= '<p class="sm mt2"><b>Notes:</b> ' . nl2br(h($q['notes'])) . '</p>';
    return $h;
}
}
