<?php
$rows = db()->query('SELECT * FROM email_log ORDER BY id DESC LIMIT 300')->fetchAll();
admin_open('Email log');
echo ahead('Email log', 'Every email the store sends. “Not sent” means emails are switched off or the server refused it — check Settings → Emails.');
$t = '<div class="scroll"><table class="tbl cards"><thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr></thead><tbody>';
foreach ($rows as $e) $t .= '<tr><td class="first sm">' . fmt_date($e['at'], true) . '</td><td data-l="To" class="sm">' . h($e['to_addr']) . '</td><td data-l="Subject"><details><summary class="sm b">' . h($e['subject']) . '</summary><p class="sm mt2" style="white-space:pre-line">' . h($e['body']) . '</p>' . ($e['link'] ? '<p class="xs mt1"><a href="' . h(url($e['link'])) . '" target="_blank">' . h($e['link']) . '</a></p>' : '') . '</details></td><td data-l="Status">' . badge($e['sent'] ? 'Sent' : 'Not sent', $e['sent'] ? 'ok' : 'warn') . '</td></tr>';
echo panel('Emails', $rows ? $t . '</tbody></table></div>' : '<p class="sm muted">No emails yet.</p>');
admin_close();
