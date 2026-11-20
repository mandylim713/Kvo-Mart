<?php
require __DIR__ . '/_init.php';
require_admin();
$pdo = db();

if (is_post()) {
    csrf_check();
    $id = (int)post('id');
    if (post('action') === 'delete') { $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]); flash('success', 'Message deleted.'); }
    if (post('action') === 'read') $pdo->prepare('UPDATE messages SET is_read = 1 - is_read WHERE id = ?')->execute([$id]);
    redirect('admin/messages.php');
}

$open = (int)get('id');
if ($open) $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$open]);
$rows = $pdo->query('SELECT * FROM messages ORDER BY id DESC LIMIT 200')->fetchAll();

$adminTitle = 'Messages';
require __DIR__ . '/_header.php';
?>
<div class="card">
<?php if (!$rows): ?><div class="empty"><i class="fa-regular fa-envelope"></i>No messages yet. Contact form submissions appear here.</div><?php endif; ?>
<?php foreach ($rows as $m): $isOpen = $open === (int)$m['id']; ?>
  <details class="list-row" style="display:block" <?= $isOpen ? 'open' : '' ?>>
    <summary style="list-style:none;cursor:pointer;display:flex;justify-content:space-between;gap:10px" onclick="if(!this.parentNode.open&&<?= $m['is_read'] ? 'false' : 'true' ?>)location.href='?id=<?= $m['id'] ?>'">
      <span><strong style="<?= $m['is_read'] ? 'font-weight:600' : '' ?>"><?= $m['is_read'] ? '' : '<i class="fa-solid fa-circle" style="color:var(--primary);font-size:8px;vertical-align:middle"></i> ' ?><?= e($m['name']) ?></strong> <span class="muted">— <?= e($m['subject'] ?: mb_strimwidth($m['message'], 0, 50, '…')) ?></span></span>
      <small class="muted" style="white-space:nowrap"><?= e(date('j M, g:ia', strtotime($m['created_at']))) ?></small>
    </summary>
    <div style="padding:12px 0 4px">
      <p style="white-space:pre-wrap;margin:0 0 10px"><?= e($m['message']) ?></p>
      <p class="muted" style="font-size:13px;margin:0 0 10px"><?= e($m['email']) ?> <?= e($m['phone']) ?></p>
      <div class="btn-row">
        <?php if ($m['email']): ?><a class="btn btn-outline btn-sm" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . ($m['subject'] ?: 'Your message')) ?>"><i class="fa-solid fa-reply"></i> Email</a><?php endif; ?>
        <?php if ($m['phone']): $pd = preg_replace('/\D+/', '', $m['phone']); if (strpos($pd, '0') === 0) $pd = '234' . substr($pd, 1); ?><a class="btn btn-wa btn-sm" href="https://wa.me/<?= e($pd) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><a class="btn btn-outline btn-sm" href="tel:<?= e($m['phone']) ?>"><i class="fa-solid fa-phone"></i></a><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-outline btn-sm">Mark <?= $m['is_read'] ? 'unread' : 'read' ?></button></form>
        <form method="post" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="btn btn-danger btn-sm"><i class="fa-regular fa-trash-can"></i></button></form>
      </div>
    </div>
  </details>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
