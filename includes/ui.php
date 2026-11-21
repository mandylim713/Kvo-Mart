<?php
/* ==================================================================
   Shared UI pieces — the same building blocks as the approved demo.
   ================================================================== */

const ICON = [
    'search' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
    'cart' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.4a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L22 7H6"/></svg>',
    'user' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
    'menu' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>',
    'x' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    'nophoto' => '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="18" height="18"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21M2 2l20 20"/></svg>',
    'truck' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#B8892B" stroke-width="2" aria-hidden="true"><path d="M1 3h14v13H1zM15 8h4l4 4v4h-8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    'doc' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#B8892B" stroke-width="2" aria-hidden="true"><path d="M14 2H6v20h12V6zM14 2v4h4M8 13h8M8 17h8"/></svg>',
    'shield' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#B8892B" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
    'wa' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>',
];

/* ---------- form helpers (errors and old values survive the redirect) ---------- */
function ferr(string $name): string { $e = $_SESSION['errors'][$name] ?? null; return $e ? '<p class="err">' . h($e) . '</p>' : ''; }
function bad(string $name): string { return isset($_SESSION['errors'][$name]) ? ' bad' : ''; }
function field(string $label, string $id, string $input, array $o = []): string
{
    return '<div class="f"><label class="l" for="' . h($id) . '">' . h($label) . (!empty($o['optional']) ? ' <span class="muted" style="font-weight:400">(optional)</span>' : '') . '</label>' . $input
        . (!empty($o['hint']) ? '<p class="hint">' . h($o['hint']) . '</p>' : '') . ferr($o['name'] ?? $id) . '</div>';
}
function inp(string $name, $v = '', string $a = '', ?string $id = null): string
{
    $id ??= $name; $val = $_SESSION['old'][$name] ?? $v;
    return '<input class="in' . bad($name) . '" id="' . h($id) . '" name="' . h($name) . '" value="' . h($val) . '" ' . $a . '>';
}
function ta(string $name, $v = '', string $a = '', ?string $id = null): string
{
    $id ??= $name; $val = $_SESSION['old'][$name] ?? $v;
    return '<textarea class="in' . bad($name) . '" id="' . h($id) . '" name="' . h($name) . '" ' . $a . '>' . h($val) . '</textarea>';
}
function sel(string $name, array $opts, $v = '', string $a = '', ?string $id = null): string
{
    $id ??= $name; $val = (string)($_SESSION['old'][$name] ?? $v);
    $o = '';
    foreach ($opts as $k => $lab) { if (is_int($k) && !is_array($lab)) $k = $lab; if (is_array($lab)) [$k, $lab] = $lab; $o .= '<option value="' . h($k) . '"' . ((string)$k === $val ? ' selected' : '') . '>' . h($lab) . '</option>'; }
    return '<select class="in' . bad($name) . '" id="' . h($id) . '" name="' . h($name) . '" ' . $a . '>' . $o . '</select>';
}
function states_opts(string $first = 'Select state'): array { return array_merge([['', $first]], array_map(fn($s) => [$s, $s], STATES)); }
function form_msg(): string
{
    $m = $_SESSION['form_msg'] ?? null;
    return $m ? '<div class="note ' . h($m[0]) . ' formmsg mt4" role="alert">' . h($m[1]) . '</div>' : '';
}

/* ---------- small components ---------- */
function badge(string $t, string $tone = ''): string { return '<span class="badge ' . h($tone) . '">' . h($t) . '</span>'; }
function note(string $title, string $body = '', string $tone = ''): string { return '<div class="note ' . h($tone) . '">' . ($title !== '' ? '<strong>' . h($title) . '</strong>' : '') . $body . '</div>'; }
function crumbs(array $items): string
{
    $o = []; $n = count($items);
    foreach ($items as $i => $it) $o[] = (!empty($it[1]) && $i < $n - 1) ? '<a href="' . h(url($it[1])) . '">' . h($it[0]) . '</a>' : '<span>' . h($it[0]) . '</span>';
    return '<nav class="crumbs" aria-label="Breadcrumb">' . implode(' › ', $o) . '</nav>';
}
function empty_state(string $title, string $body = '', string $action = ''): string
{
    return '<div class="empty"><p class="b" style="font-family:var(--disp);font-size:1.1rem">' . h($title) . '</p>' . ($body ? '<p class="muted sm mt2">' . $body . '</p>' : '') . ($action ? '<div class="mt4 row" style="justify-content:center">' . $action . '</div>' : '') . '</div>';
}
function photo(array $p, string $cls = ''): string
{
    return !empty($p['image'])
        ? '<img src="' . h(asset($p['image'])) . '" alt="' . h($p['name']) . (!empty($p['credit']) ? ' (representative photo)' : '') . '" loading="lazy" class="' . h($cls) . '">'
        : '<div class="nophoto">' . ICON['nophoto'] . '<span>Photo coming soon</span></div>';
}
function pl(array $rows): string
{
    $o = '<dl>';
    foreach ($rows as $r) { if (!$r || $r[1] === '' || $r[1] === null) continue; $o .= '<div class="pr"><dt>' . h($r[0]) . '</dt><dd>' . $r[1] . '</dd></div>'; }
    return $o . '</dl>';
}
function ost_tone(string $s): string { return $s === 'PAID' ? 'ok' : (in_array($s, ['PAYMENT_FAILED', 'CANCELLED'], true) ? 'danger' : (str_contains($s, 'REFUND') ? '' : 'warn')); }
function price_html(array $p): string { return ($p['mode'] === 'QUOTE_ONLY' || price_of($p) === null) ? 'Request price' : naira($p['price']); }

function card(array $p): string
{
    [$st, $tone] = stock_label($p);
    $b = buyable($p);
    $c = cat_by_slug($p['cat']);
    $rows = [['Make', h($p['brand'])], ['Model', h($p['model'])]];
    if ($p['kind'] !== 'EQUIPMENT') $rows[] = ['SKU', h($p['sku'])];
    $link = url('/p/' . $p['slug']);
    return '<article class="card">'
        . '<a class="ph" href="' . h($link) . '" tabindex="-1" aria-hidden="true">' . photo($p) . (!empty($p['image']) && !empty($p['credit']) ? '<span class="tagr">Representative photo</span>' : '') . '</a>'
        . '<div class="bd plate"><p class="xs muted">' . h($c['name'] ?? '') . '</p>'
        . '<h3><a href="' . h($link) . '">' . h($p['name']) . '</a></h3>'
        . '<div class="mt2">' . pl($rows) . '</div>'
        . '<div class="row mt3" style="gap:.4rem">' . badge(COND[$p['cond']] ?? $p['cond']) . badge($st, $tone) . '</div>'
        . '<p class="price mt3">' . price_html($p) . '</p>'
        . '<div class="act"><a class="btn out sm grow" href="' . h($link) . '">View details</a>'
        . ($b['ok'] ? '<form method="post" action="' . h(url('/cart/add')) . '" class="js-add">' . csrf_field() . '<input type="hidden" name="pid" value="' . (int)$p['id'] . '"><input type="hidden" name="qty" value="1"><button class="btn sm" aria-label="Add ' . h($p['name']) . ' to cart">' . ICON['cart'] . '</button></form>' : '')
        . '</div></div></article>';
}

const NAV = [['/', 'Home'], ['/equipment', 'Heavy Equipment'], ['/equipment?category=agricultural-tractors', 'Tractors'], ['/equipment?category=caterpillar-machinery', 'Caterpillar'], ['/spare-parts', 'Spare Parts'], ['/supplies', 'Supplies'], ['/real-estate', 'Real Estate'], ['/services', 'Services'], ['/about', 'About Us'], ['/contact', 'Contact']];

function nav_on(string $href): bool
{
    global $ROUTE_PATH;
    [$p, $qs] = array_pad(explode('?', $href, 2), 2, '');
    if ($p !== $ROUTE_PATH) return false;
    if ($qs !== '') { parse_str($qs, $want); foreach ($want as $k => $v) if (($_GET[$k] ?? '') !== $v) return false; return true; }
    return $p !== '/equipment' || empty($_GET['category']);
}

function page_open(string $title = '', array $o = []): void
{
    $name = setting('short_name', 'KVO Mart');
    $full = $title ? $title . ' — ' . $name : $name . ' — ' . setting('tagline');
    $desc = $o['desc'] ?? setting('meta_description');
    $n = cart_count();
    $acct = current_user() ? '/account' : '/account/login';
    $logo = asset(setting('logo', 'assets/img/logo.jpg'));
    $flashes = take_flashes();
    echo '<!DOCTYPE html><html lang="en-NG"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<title>' . h($full) . '</title><meta name="description" content="' . h($desc) . '"><meta name="theme-color" content="#000000">'
        . '<meta property="og:title" content="' . h($full) . '"><meta property="og:description" content="' . h($desc) . '"><meta property="og:type" content="website">'
        . (!empty($o['image']) ? '<meta property="og:image" content="' . h(full_url($o['image'])) . '">' : '<meta property="og:image" content="' . h(full_url(setting('logo', 'assets/img/logo.jpg'))) . '">')
        . (!empty($o['canonical']) ? '<link rel="canonical" href="' . h(full_url($o['canonical'])) . '">' : '')
        . (!empty($o['noindex']) ? '<meta name="robots" content="noindex">' : '')
        . '<link rel="icon" href="' . h($logo) . '">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . h(url('/assets/css/site.css')) . '?v=7">'
        . ($o['head'] ?? '') . '</head><body' . (!empty($o['body']) ? ' class="' . h($o['body']) . '"' : '') . '>';
    if (!empty($o['bare'])) { foreach ($flashes as [$t, $m]) echo '<div class="toast-static note ' . h($t) . '" role="status">' . h($m) . '</div>'; return; }
    echo '<a class="skip" href="#main">Skip to content</a>';
    if (setting('announcement')) echo '<div class="demo-bar">' . h(setting('announcement')) . '</div>';
    echo '<header class="site"><div class="page hd">'
        . '<a class="logo" href="' . h(url('/')) . '" aria-label="' . h($name) . ' home"><img src="' . h($logo) . '" alt="" width="72" height="48"><span><b>' . h($name) . '</b><span>' . h(setting('tagline')) . '</span></span></a>'
        . '<form class="search" action="' . h(url('/search')) . '" role="search"><label for="hq" class="sr">Search</label><input id="hq" name="q" type="search" placeholder="Search machines, models, part numbers" value="' . h($_GET['q'] ?? '') . '"><button aria-label="Search">' . ICON['search'] . '</button></form>'
        . '<div class="icons"><a class="btn out sm rq" href="' . h(url('/request-quote')) . '">Request a quote</a>'
        . '<a class="ib" href="' . h(url($acct)) . '" aria-label="Account">' . ICON['user'] . '</a>'
        . '<a class="ib" href="' . h(url('/cart')) . '" aria-label="Cart, ' . $n . ' items">' . ICON['cart'] . '<span class="n js-cart-n"' . ($n ? '' : ' hidden') . '>' . $n . '</span></a>'
        . '<button class="ib burger" type="button" data-open="menu" aria-label="Open menu" aria-controls="menu">' . ICON['menu'] . '</button></div></div>'
        . '<form class="page msearch" action="' . h(url('/search')) . '" role="search"><input name="q" type="search" placeholder="Search machines, models, part numbers" aria-label="Search" value="' . h($_GET['q'] ?? '') . '"><button aria-label="Search">' . ICON['search'] . '</button></form>'
        . '<nav class="main" aria-label="Main"><div class="page">';
    foreach (NAV as [$href, $l]) echo '<a href="' . h(url($href)) . '"' . (nav_on($href) ? ' class="on" aria-current="page"' : '') . '>' . h($l) . '</a>';
    echo '</div></nav></header>';
    echo '<div class="drawer" id="menu" role="dialog" aria-label="Menu" hidden><div><div class="dh"><b style="font-family:var(--disp)">Menu</b><button class="ib" style="color:#fff" type="button" data-close aria-label="Close menu">' . ICON['x'] . '</button></div>';
    foreach (NAV as [$href, $l]) echo '<a href="' . h(url($href)) . '">' . h($l) . '</a>';
    echo '<a href="' . h(url('/request-quote')) . '" style="color:var(--gold6)">Request a quote</a><a href="' . h(url($acct)) . '">' . (current_user() ? 'My account' : 'Sign in') . '</a><a href="' . h(url('/track')) . '">Track an order</a>'
        . (wa_number() ? '<a href="' . h(wa_link('Hello ' . $name . ', ')) . '" target="_blank" rel="noopener">Chat on WhatsApp</a>' : '') . '</div></div>';
    echo '<main id="main" tabindex="-1">';
    if ($flashes) { echo '<div class="page" style="padding-top:1rem">'; foreach ($flashes as [$t, $m]) echo '<div class="note ' . h($t) . ' mt2" role="status">' . h($m) . '</div>'; echo '</div>'; }
}

function page_close(array $o = []): void
{
    if (!empty($o['bare'])) { echo '<script src="' . h(url('/assets/js/site.js')) . '?v=7" defer></script></body></html>'; clear_form_state(); return; }
    $name = setting('short_name', 'KVO Mart');
    $logo = asset(setting('logo', 'assets/img/logo.jpg'));
    echo '</main><footer class="site"><div class="page fgrid">'
        . '<div><a class="logo" href="' . h(url('/')) . '"><span style="background:#fff;padding:5px"><img src="' . h($logo) . '" alt="" style="height:44px"></span><span><b style="color:#fff">' . h($name) . '</b><span>' . h(setting('tagline')) . '</span></span></a><p class="sm mt4" style="max-width:20rem">Tractors, construction machinery, industrial equipment, spare parts and commercial supplies — with delivery arranged across Nigeria.</p>';
    $soc = array_filter(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X']);
    $links = ''; foreach ($soc as $k => $l) if (setting($k)) $links .= '<a href="' . h(setting($k)) . '" target="_blank" rel="noopener" style="text-decoration:underline">' . $l . '</a> ';
    if ($links) echo '<p class="sm mt4 row" style="gap:.75rem">' . $links . '</p>';
    echo '</div><div><h3>Shop</h3><ul><li><a href="' . h(url('/equipment')) . '">Heavy equipment</a></li><li><a href="' . h(url('/equipment?category=agricultural-tractors')) . '">Tractors</a></li><li><a href="' . h(url('/spare-parts')) . '">Spare parts</a></li><li><a href="' . h(url('/supplies')) . '">Supplies</a></li><li><a href="' . h(url('/real-estate')) . '">Real estate</a></li><li><a href="' . h(url('/request-quote')) . '">Request a quote</a></li></ul></div>'
        . '<div><h3>Help</h3><ul><li><a href="' . h(url('/track')) . '">Track an order</a></li><li><a href="' . h(url('/faq')) . '">FAQ</a></li><li><a href="' . h(url('/delivery-policy')) . '">Delivery policy</a></li><li><a href="' . h(url('/returns')) . '">Returns and refunds</a></li><li><a href="' . h(url('/privacy')) . '">Privacy policy</a></li><li><a href="' . h(url('/terms')) . '">Terms and conditions</a></li><li><a href="' . h(url('/image-credits')) . '">Image credits</a></li></ul></div>'
        . '<div><h3>Contact</h3><ul>';
    if (setting('address')) echo '<li>' . h(setting('address')) . '</li>';
    if (setting('phone')) echo '<li><a href="tel:' . h(preg_replace('/[^\d+]/', '', setting('phone'))) . '">' . h(setting('phone')) . '</a></li>';
    if (setting('email')) echo '<li><a href="mailto:' . h(setting('email')) . '">' . h(setting('email')) . '</a></li>';
    if (setting('hours')) echo '<li>' . h(setting('hours')) . '</li>';
    if (wa_number()) echo '<li><a href="' . h(wa_link('Hello ' . $name . ', ')) . '" target="_blank" rel="noopener">WhatsApp us</a></li>';
    echo '<li><a href="' . h(url('/contact')) . '" style="text-decoration:underline">Send us a message</a></li><li><a href="' . h(url('/admin/login')) . '">Staff sign in</a></li></ul></div>'
        . '</div><div style="border-top:1px solid var(--char6)"><div class="page row between xs" style="padding:1rem 0;color:var(--steel400)"><span>© ' . date('Y') . ' ' . h(setting('business_name')) . '</span><span>Prices in Nigerian Naira (₦)</span>'
        . (setting('show_credit', '1') === '1' ? '<span>Powered by <a href="https://digitalweboracleict.com" target="_blank" rel="noopener" style="color:var(--gold3)">DWO</a></span>' : '') . '</div></div></footer>';
    if (wa_number()) echo '<a class="wa-fab" href="' . h(wa_link('Hello ' . $name . ', ')) . '" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">' . ICON['wa'] . '</a>';
    echo '<div class="toast" role="status" aria-live="polite" hidden></div>';
    echo '<script>window.KVO=' . json_encode(['cart' => url('/cart')]) . '</script><script src="' . h(url('/assets/js/site.js')) . '?v=7" defer></script>' . ($o['foot'] ?? '') . '</body></html>';
    clear_form_state();
}

function not_found(): never
{
    http_response_code(404);
    page_open('Page not found', ['noindex' => true]);
    echo '<div class="page py">' . empty_state('Page not found', "The page you're looking for doesn't exist or has moved.", '<a class="btn" href="' . h(url('/')) . '">Go to home page</a>') . '</div>';
    page_close();
    exit;
}

/* enquiry form used on the home page, product pages, quote and contact pages */
function enquiry_form(string $type, array $o = []): string
{
    $u = current_user() ?: [];
    $p = $o['product'] ?? null; $prop = $o['property'] ?? null; $compact = !empty($o['compact']);
    $isQuote = in_array($type, ['QUOTE', 'FINANCING', 'PART_COMPATIBILITY'], true);
    $back = $_SERVER['REQUEST_URI'] ?? '/';
    $h = '<form method="post" action="' . h(url('/enquiry')) . '" novalidate>' . csrf_field()
        . '<input type="hidden" name="type" value="' . h($type) . '"><input type="hidden" name="pid" value="' . h($p['id'] ?? '') . '"><input type="hidden" name="prop" value="' . h($prop['slug'] ?? '') . '"><input type="hidden" name="back" value="' . h($back) . '">'
        . '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">'
        . '<div class="grid g2" style="gap:0 1rem">'
        . field('Name or company', 'e-name', inp('name', $u['name'] ?? '', 'autocomplete="name"', 'e-name'), ['name' => 'name'])
        . field('Email', 'e-email', inp('email', $u['email'] ?? '', 'type="email" autocomplete="email"', 'e-email'), ['name' => 'email'])
        . field('Phone', 'e-phone', inp('phone', $u['phone'] ?? '', 'type="tel" autocomplete="tel"', 'e-phone'), ['name' => 'phone'])
        . (!$compact ? field('Company', 'e-company', inp('company', $u['company'] ?? '', '', 'e-company'), ['optional' => true, 'name' => 'company']) : '')
        . '</div>';
    if ($p || $prop) $h .= '<p class="note gold sm" style="margin-bottom:1rem">Enquiring about <strong style="display:inline">' . h($p ? $p['name'] : $prop['title']) . '</strong></p>';
    else $h .= field($isQuote ? 'Equipment or product needed' : 'Subject', 'e-subject', inp('subject', $_GET['subject'] ?? '', 'placeholder="' . ($isQuote ? 'e.g. 20-tonne crawler excavator' : '') . '"', 'e-subject'), ['name' => 'subject']);
    if ($isQuote) $h .= '<div class="grid g2" style="gap:0 1rem">' . field('Quantity', 'e-qty', inp('qty', '1', 'type="number" min="1" inputmode="numeric"', 'e-qty'), ['optional' => true, 'name' => 'qty']) . field('Delivery location', 'e-loc', inp('location', '', 'placeholder="City, State"', 'e-loc'), ['optional' => true, 'name' => 'location']) . '</div>';
    if ($isQuote && !$compact) $h .= field('Technical requirements', 'e-req', ta('requirements', '', 'rows="3"', 'e-req'), ['optional' => true, 'hint' => 'Capacity, attachments, year range, condition, etc.', 'name' => 'requirements']);
    $h .= field($isQuote ? 'Additional notes' : 'Message', 'e-msg', ta('message', '', 'rows="' . ($compact ? 2 : 4) . '"', 'e-msg'), ['optional' => $isQuote, 'name' => 'message']);
    if ($type === 'FINANCING') $h .= '<p class="xs muted">Financing enquiries are for discussion only. Any financing requires separate approval.</p>';
    $h .= '<p class="xs muted">We use these details only to respond to your enquiry.</p>'
        . (($_SESSION['form_for'] ?? '') === 'enquiry' ? form_msg() : '')
        . '<div class="mt4"><button class="btn">' . ($isQuote ? 'Send quotation request' : 'Send enquiry') . '</button></div></form>';
    return $h;
}
