<?php
$pdo = db();
if (is_post()) {
    require_csrf();
    $do = post('do');
    if ($do === 'page') {
        $pdo->prepare('UPDATE pages SET title = ?, content = ?, updated = ? WHERE slug = ?')->execute([mb_substr(post('title'), 0, 200), clean_html((string)post('content')), now(), post('slug')]);
        audit('page.updated', post('slug')); flash('Page saved.'); redirect('/admin/content?page=' . urlencode(post('slug')));
    }
    if ($do === 'faq') {
        $fid = (int)post('id');
        if (post('delete')) { $pdo->prepare('DELETE FROM faqs WHERE id = ?')->execute([$fid]); flash('Question removed.'); redirect('/admin/content'); }
        if (mb_strlen(post('question')) < 5 || mb_strlen(post('answer')) < 2) { flash('Write the question and the answer.', 'warn'); redirect('/admin/content'); }
        if ($fid) $pdo->prepare('UPDATE faqs SET question = ?, answer = ?, sort_order = ? WHERE id = ?')->execute([post('question'), post('answer'), (int)post('sort_order'), $fid]);
        else $pdo->prepare('INSERT INTO faqs (question, answer, sort_order) VALUES (?,?,?)')->execute([post('question'), post('answer'), (int)post('sort_order')]);
        flash('FAQ saved.'); redirect('/admin/content');
    }
}
admin_open('Pages & FAQs');
$c = csrf_field();
$pg = q('page');
if ($pg) {
    $s = $pdo->prepare('SELECT * FROM pages WHERE slug = ?'); $s->execute([$pg]); $p = $s->fetch();
    if ($p) {
        echo ahead($p['title'], 'Shown at /' . h($p['slug']) . ' · updated ' . fmt_date($p['updated'], true), '<a class="link" href="' . h(aurl('content')) . '">← All pages</a>');
        echo panel('Edit page', '<form method="post">' . $c . '<input type="hidden" name="do" value="page"><input type="hidden" name="slug" value="' . h($p['slug']) . '">' . field('Title', 'pt', '<input class="in" id="pt" name="title" value="' . h($p['title']) . '">')
            . field('Content', 'pc', '<textarea class="in" id="pc" name="content" rows="18" style="font-family:ui-monospace,monospace;font-size:13px">' . h($p['content']) . '</textarea>', ['hint' => 'Simple HTML: <p>paragraph</p>, <h2>heading</h2>, <ul><li>list</li></ul>, <strong>bold</strong>, <a href="...">link</a>.'])
            . '<div class="row"><button class="btn">Save page</button><a class="btn out" target="_blank" href="' . h(url('/' . $p['slug'])) . '">View</a></div></form>');
        admin_close(); return;
    }
}
echo ahead('Pages & FAQs', 'About, services and policy pages, and the questions on the FAQ page.');
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>Page</th><th>Address</th><th>Updated</th></tr></thead><tbody>';
foreach ($pdo->query('SELECT * FROM pages ORDER BY id') as $p) $t .= '<tr><td class="first"><a class="b" href="' . h(aurl('content?page=' . $p['slug'])) . '">' . h($p['title']) . '</a></td><td data-l="Address" class="sm">/' . h($p['slug']) . '</td><td data-l="Updated" class="sm">' . fmt_date($p['updated']) . '</td></tr>';
$fq = '';
foreach ($pdo->query('SELECT * FROM faqs ORDER BY sort_order, id') as $f) $fq .= '<details class="box" style="margin-bottom:.5rem"><summary class="b sm">' . h($f['question']) . '</summary><form method="post" class="mt3">' . $c . '<input type="hidden" name="do" value="faq"><input type="hidden" name="id" value="' . $f['id'] . '">' . field('Question', 'fq' . $f['id'], '<input class="in" id="fq' . $f['id'] . '" name="question" value="' . h($f['question']) . '">') . field('Answer', 'fa' . $f['id'], '<textarea class="in" id="fa' . $f['id'] . '" name="answer" rows="3">' . h($f['answer']) . '</textarea>') . field('Order', 'fo' . $f['id'], '<input class="in" id="fo' . $f['id'] . '" name="sort_order" type="number" value="' . (int)$f['sort_order'] . '" style="width:100px">') . '<div class="row"><button class="btn sm">Save</button><button class="btn ghost sm" style="color:var(--danger)" name="delete" value="1">Remove</button></div></form></details>';
$fq .= '<details class="box"><summary class="b sm">+ Add a question</summary><form method="post" class="mt3">' . $c . '<input type="hidden" name="do" value="faq">' . field('Question', 'fqn', '<input class="in" id="fqn" name="question">') . field('Answer', 'fan', '<textarea class="in" id="fan" name="answer" rows="3"></textarea>') . '<input type="hidden" name="sort_order" value="99"><button class="btn sm">Add</button></form></details>';
echo '<div class="grid g2" style="align-items:start"><div>' . panel('Pages', $t . '</tbody></table></div>') . '</div><div>' . panel('FAQ', $fq) . '</div></div>';
admin_close();
