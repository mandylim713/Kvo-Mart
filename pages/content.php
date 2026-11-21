<?php
$prose = function (string $title, string $body, string $canon) {
    page_open($title, ['canonical' => $canon]);
    echo '<div class="page py" style="max-width:52rem">' . crumbs([['Home', '/'], [$title]]) . '<h1 class="mt4">' . h($title) . '</h1><div class="mt6 prose">' . $body . '</div></div>';
    page_close();
};
if ($a === 'faq') {
    $b = '';
    foreach (db()->query('SELECT * FROM faqs ORDER BY sort_order, id') as $f) $b .= '<details class="box" style="margin-bottom:.5rem"><summary class="b">' . h($f['question']) . '</summary><p class="mt3 muted">' . nl2br(h($f['answer'])) . '</p></details>';
    $b .= '<p class="mt6">Still have a question? <a class="link" href="' . h(url('/contact')) . '">Contact us</a>.</p>';
    $prose('Frequently asked questions', $b, '/faq'); return;
}
if ($a === 'image-credits') {
    $rows = db()->query("SELECT name, slug, credit, credit_url FROM products WHERE status = 'PUBLISHED' AND credit IS NOT NULL AND credit <> '' ORDER BY name")->fetchAll();
    $b = '<p class="muted">Product photos marked “representative” show the model, not the exact unit for sale. They are used under their Creative Commons licences.</p><div class="scroll mt6"><table class="tbl"><thead><tr><th>Listing</th><th>Credit</th></tr></thead><tbody>';
    foreach ($rows as $p) $b .= '<tr><td><a href="' . h(url('/p/' . $p['slug'])) . '">' . h($p['name']) . '</a></td><td class="sm">' . ($p['credit_url'] ? '<a href="' . h($p['credit_url']) . '" target="_blank" rel="noopener noreferrer license">' . h($p['credit']) . '</a>' : h($p['credit'])) . '</td></tr>';
    $b .= '</tbody></table></div>';
    $prose('Image credits', $b, '/image-credits'); return;
}
$s = db()->prepare('SELECT * FROM pages WHERE slug = ?'); $s->execute([$a]);
$pg = $s->fetch(); if (!$pg) not_found();
$body = clean_html($pg['content']);
if ($a === 'services') $body .= '<p class="mt6"><a class="btn" href="' . h(url('/request-quote')) . '">Request a quote</a></p>';
if ($a === 'about') $body .= '<p class="mt6"><a class="btn" href="' . h(url('/equipment')) . '">Browse equipment</a> <a class="btn out" href="' . h(url('/contact')) . '">Contact us</a></p>';
$prose($pg['title'], $body, '/' . $a);
