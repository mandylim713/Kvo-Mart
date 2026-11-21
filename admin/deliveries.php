<?php
$st = q('status');
$groups = [
    'quote' => ['Needs a delivery quote', "status = 'PAID' AND d_status IN ('AWAITING_ASSESSMENT','QUOTE_PENDING')"],
    'waiting' => ['Waiting on the customer', "d_status IN ('QUOTE_AVAILABLE','AWAITING_PAYMENT') AND status <> 'CANCELLED'"],
    'ready' => ['Ready for dispatch / collection', "status = 'PAID' AND d_status = 'READY_FOR_DISPATCH'"],
    'moving' => ['On the way', "d_status IN ('DISPATCHED','IN_TRANSIT')"],
    'exception' => ['Exceptions', "d_status = 'EXCEPTION'"],
    'done' => ['Delivered (last 50)', "d_status = 'DELIVERED'"],
];
admin_open('Deliveries');
echo ahead('Deliveries', 'Unpaid orders appear here once payment is verified.');
echo '<div class="chip-row" style="margin-bottom:1rem"><a class="chip' . ($st === '' ? ' on' : '') . '" href="' . h(aurl('deliveries')) . '">All open</a>';
foreach ($groups as $k => [$l, $w]) { $n = (int)db()->query("SELECT COUNT(*) FROM orders WHERE $w")->fetchColumn(); echo '<a class="chip' . ($st === $k ? ' on' : '') . '" href="' . h(aurl('deliveries?status=' . $k)) . '">' . h($l) . ' (' . $n . ')</a>'; }
echo '</div>';
foreach ($groups as $k => [$l, $w]) {
    if ($st !== '' && $st !== $k) continue;
    if ($st === '' && $k === 'done') continue;
    $rows = db()->query("SELECT * FROM orders WHERE $w ORDER BY id " . ($k === 'done' ? 'DESC LIMIT 50' : 'ASC'))->fetchAll();
    echo panel($l . ' (' . count($rows) . ')', admin_orders_table($rows));
}
admin_close();
