<?php
if (is_post()) {
    require_csrf();
    if (post('clear')) { $_SESSION['compare'] = []; redirect('/equipment'); }
    $s = post('slug'); $l = $_SESSION['compare'] ?? [];
    if (($i = array_search($s, $l, true)) !== false) { array_splice($l, $i, 1); flash('Removed from comparison.'); }
    elseif (prod_by_slug($s)) { $l[] = $s; if (count($l) > 4) array_shift($l); flash('Added to comparison.' . (count($l) > 1 ? ' You can now compare ' . count($l) . ' items.' : '')); }
    $_SESSION['compare'] = $l;
    redirect(safe_next(post('back'), '/compare'));
}
$list = array_values(array_filter(array_map('prod_by_slug', $_SESSION['compare'] ?? [])));
page_open('Compare equipment', ['noindex' => true]);
echo '<div class="page py">';
if (count($list) < 2) {
    echo '<h1>Compare equipment</h1><div class="mt8">' . empty_state('Add at least two items to compare', 'Use “Add to comparison” on product pages.', '<a class="btn" href="' . h(url('/equipment')) . '">Browse equipment</a>') . '</div>';
} else {
    $keys = []; foreach ($list as $p) foreach (specs($p) as $s) $keys[$s[0]] = true;
    $rows = [['Price', fn($p) => price_html($p)], ['Availability', fn($p) => h(stock_label($p)[0])], ['Manufacturer', fn($p) => h($p['brand'] ?: '—')], ['Model', fn($p) => h($p['model'] ?: '—')], ['Condition', fn($p) => COND[$p['cond']] ?? '']];
    foreach (array_keys($keys) as $k) $rows[] = [$k, function ($p) use ($k) { foreach (specs($p) as $s) if ($s[0] === $k) return h($s[1]); return '—'; }];
    echo '<div class="row between"><h1>Compare equipment</h1><form method="post">' . csrf_field() . '<input type="hidden" name="clear" value="1"><button class="link">Clear comparison</button></form></div>'
        . '<div class="scroll mt6"><table class="tbl" style="min-width:640px"><thead><tr><th></th>';
    foreach ($list as $p) echo '<th><a href="' . h(url('/p/' . $p['slug'])) . '">' . h($p['name']) . '</a></th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as [$l, $fn]) { echo '<tr><th class="muted" style="font-weight:500;border-bottom:1px solid var(--steel)">' . h($l) . '</th>'; foreach ($list as $p) echo '<td class="tab">' . $fn($p) . '</td>'; echo '</tr>'; }
    echo '<tr><th></th>'; foreach ($list as $p) echo '<td><a class="btn out sm" href="' . h(url('/p/' . $p['slug'])) . '">View</a></td>'; echo '</tr>';
    echo '</tbody></table></div>';
}
echo '</div>';
page_close();
