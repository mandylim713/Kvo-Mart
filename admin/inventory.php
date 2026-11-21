<?php
$pdo = db();
const MOVE_TYPES = ['RECEIPT' => 'Stock received', 'ADJUSTMENT' => 'Count correction', 'DAMAGE' => 'Damaged / lost', 'RETURN' => 'Customer return'];
if (is_post()) {
    require_csrf(); need('inventory.adjust');
    $p = prod((int)post('pid')); if (!$p) { flash('Product not found.', 'warn'); redirect('/admin/inventory'); }
    $type = post('type'); $qty = (int)post('qty'); $reason = mb_substr(post('reason'), 0, 250);
    $back = '/admin/inventory?product=' . $p['id'];
    if (!isset(MOVE_TYPES[$type])) fail(['type' => 'Choose a movement type.'], $back);
    if ($type === 'ADJUSTMENT') {
        if ($qty < (int)$p['reserved']) fail(['qty' => 'The count cannot be below the ' . (int)$p['reserved'] . ' units held for unpaid orders.'], $back);
        $change = $qty - (int)$p['on_hand'];
        if ($change === 0) { flash('Count matches — nothing changed.'); redirect($back); }
    } else {
        if ($qty < 1) fail(['qty' => 'Enter a quantity of at least 1.'], $back);
        $change = $type === 'DAMAGE' ? -$qty : $qty;
        if ($type === 'DAMAGE' && $qty > avail($p)) fail(['qty' => 'Only ' . avail($p) . ' units are free to write off.'], $back);
    }
    if ($reason === '') fail(['reason' => 'Say why — it goes in the stock log.'], $back);
    $pdo->prepare('UPDATE products SET on_hand = on_hand + ?, updated = ? WHERE id = ?')->execute([$change, now(), $p['id']]);
    move(prod($p['id']), $type, $change, $reason);
    audit('inventory.' . strtolower($type), $p['sku'], ($change > 0 ? '+' : '') . $change . ' — ' . $reason);
    flash('Stock updated: ' . ($change > 0 ? '+' : '') . $change . '. Now ' . ((int)$p['on_hand'] + $change) . ' on hand.');
    redirect($back);
}
$sel = q('product') ? prod((int)q('product')) : null;
admin_open('Inventory');
echo ahead('Inventory', 'Every change is logged. Units held for unpaid orders are released automatically when the hold expires.');
if ($sel) {
    $mv = $pdo->prepare('SELECT * FROM movements WHERE product_id = ? ORDER BY id DESC LIMIT 100'); $mv->execute([$sel['id']]);
    $t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>When</th><th>Type</th><th class="r">Change</th><th class="r">On hand</th><th class="r">Held</th><th>Reason</th><th>By</th></tr></thead><tbody>';
    foreach ($mv as $m) $t .= '<tr><td class="first">' . fmt_date($m['at'], true) . '</td><td data-l="Type">' . h(humanize($m['type'])) . '</td><td data-l="Change" class="r tab">' . ($m['qty'] > 0 ? '+' : '') . (int)$m['qty'] . '</td><td data-l="On hand" class="r tab">' . (int)$m['on_hand'] . '</td><td data-l="Held" class="r tab">' . (int)$m['reserved'] . '</td><td data-l="Reason" class="sm">' . h($m['reason']) . ($m['order_ref'] ? ' · <a href="' . h(aurl('orders/' . $m['order_ref'])) . '">' . h($m['order_ref']) . '</a>' : '') . '</td><td data-l="By" class="sm">' . h($m['by_name']) . '</td></tr>';
    $f = can('inventory.adjust') ? '<form method="post" novalidate>' . csrf_field() . '<input type="hidden" name="pid" value="' . $sel['id'] . '">' . field('Movement', 'mt', sel('type', array_map(fn($k, $l) => [$k, $l], array_keys(MOVE_TYPES), MOVE_TYPES), '', '', 'mt'), ['name' => 'type'])
        . field('Quantity', 'mq', inp('qty', '', 'type="number" min="0" inputmode="numeric"', 'mq'), ['name' => 'qty', 'hint' => 'For a count correction enter the new total on hand.']) . field('Reason / reference', 'mr', inp('reason', '', 'placeholder="Supplier delivery note 1042"', 'mr'), ['name' => 'reason']) . '<button class="btn">Record movement</button></form>' : '<p class="sm muted">Your role can view stock but not change it.</p>';
    echo '<p style="margin-bottom:1rem"><a class="link" href="' . h(aurl('inventory')) . '">← All stock</a></p><div class="split admin" style="align-items:start"><div>' . panel($sel['name'] . ' — stock log', $t . '</tbody></table></div>') . '</div><div>'
        . panel('Stock now', pl([['On hand', (int)$sel['on_hand']], ['Held for unpaid orders', (int)$sel['reserved']], ['Available to sell', avail($sel)], ['Low-stock alert at', (int)$sel['low']], ['Units sold', (int)$sel['sales']]]) . '<a class="link sm mt3" style="display:inline-block" href="' . h(aurl('products/' . $sel['id'])) . '">Edit product</a>')
        . panel('Adjust stock', $f) . '</div></div>';
    admin_close(); return;
}
$filter = q('f');
$rows = $pdo->query("SELECT * FROM products WHERE status <> 'ARCHIVED' ORDER BY kind, name")->fetchAll();
if ($filter === 'low') $rows = array_filter($rows, fn($p) => avail($p) <= (int)$p['low']);
if ($filter === 'held') $rows = array_filter($rows, fn($p) => (int)$p['reserved'] > 0);
$value = 0; foreach ($rows as $p) if (price_of($p)) $value += price_of($p) * (int)$p['on_hand'];
echo '<div class="chip-row" style="margin-bottom:1rem"><a class="chip' . (!$filter ? ' on' : '') . '" href="' . h(aurl('inventory')) . '">All</a><a class="chip' . ($filter === 'low' ? ' on' : '') . '" href="' . h(aurl('inventory?f=low')) . '">Low / out of stock</a><a class="chip' . ($filter === 'held' ? ' on' : '') . '" href="' . h(aurl('inventory?f=held')) . '">Held for unpaid orders</a></div>';
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Product</th><th class="r">On hand</th><th class="r">Held</th><th class="r">Available</th><th class="r">Stock value</th><th></th></tr></thead><tbody>';
foreach ($rows as $p) $t .= '<tr><td class="first"><a class="b" href="' . h(aurl('inventory?product=' . $p['id'])) . '">' . h($p['name']) . '</a><div class="xs muted">' . h($p['sku']) . '</div></td><td data-l="On hand" class="r tab">' . (int)$p['on_hand'] . '</td><td data-l="Held" class="r tab">' . (int)$p['reserved'] . '</td><td data-l="Available" class="r tab">' . (avail($p) <= (int)$p['low'] ? '<span class="badge ' . (avail($p) ? 'warn' : 'danger') . '">' . avail($p) . '</span>' : avail($p)) . '</td><td data-l="Value" class="r tab">' . (price_of($p) ? naira(price_of($p) * (int)$p['on_hand']) : '—') . '</td><td><a class="btn out sm" href="' . h(aurl('inventory?product=' . $p['id'])) . '">Adjust</a></td></tr>';
echo panel('Stock (' . count($rows) . ') · value at list price ' . naira($value), $t . '</tbody></table></div>');
$recent = $pdo->query('SELECT m.*, p.name FROM movements m LEFT JOIN products p ON p.id = m.product_id ORDER BY m.id DESC LIMIT 15')->fetchAll();
$rb = ''; foreach ($recent as $m) $rb .= '<li style="padding:.4rem 0;border-bottom:1px solid var(--steel)" class="sm"><b>' . ($m['qty'] > 0 ? '+' : '') . (int)$m['qty'] . '</b> ' . h($m['name']) . ' — ' . h($m['reason']) . ' <span class="xs muted">' . fmt_date($m['at'], true) . ' · ' . h($m['by_name']) . '</span></li>';
echo panel('Recent movements', '<ul style="list-style:none;padding:0;margin:0">' . $rb . '</ul>');
admin_close();
