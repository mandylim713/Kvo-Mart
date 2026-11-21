<?php
/** Front controller: every request comes through here (see .htaccess). */
require __DIR__ . '/includes/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bp = base_path();
if ($bp !== '' && str_starts_with($path, $bp)) $path = substr($path, strlen($bp));
$path = '/' . trim(rawurldecode($path), '/');
if ($path === '/index.php') $path = '/';
$ROUTE_PATH = $path;

if (!str_starts_with($path, '/pay/webhook')) expire_holds();

$seg = array_values(array_filter(explode('/', $path), 'strlen'));
$a = $seg[0] ?? ''; $b = $seg[1] ?? null; $c = $seg[2] ?? null;
$P = __DIR__ . '/pages/';

switch ($a) {
    case '': require $P . 'home.php'; break;
    case 'equipment': case 'spare-parts': case 'supplies': case 'search': require $P . 'catalogue.php'; break;
    case 'p': $slug = $b; require $P . 'product.php'; break;
    case 'compare': require $P . 'compare.php'; break;
    case 'cart': $action = $b; require $P . 'cart.php'; break;
    case 'checkout': $action = $b; require $P . 'checkout.php'; break;
    case 'pay': $action = $b; require $P . 'pay.php'; break;
    case 'orders': $ref = $b; $action = $c; require $P . 'order.php'; break;
    case 'track': require $P . 'track.php'; break;
    case 'quotes': $number = $b; require $P . 'quote.php'; break;
    case 'request-quote': case 'contact': case 'enquiry': require $P . 'enquiry.php'; break;
    case 'newsletter': require $P . 'newsletter.php'; break;
    case 'real-estate': $slug = $b; require $P . 'estate.php'; break;
    case 'faq': case 'image-credits': case 'about': case 'services': case 'delivery-policy': case 'returns': case 'privacy': case 'terms':
        require $P . 'content.php'; break;
    case 'account': $sub = $b; $id = $c; require $P . 'account.php'; break;
    case 'admin': $sec = $b; $id = $c; $sub = $seg[3] ?? null; require __DIR__ . '/admin/router.php'; break;
    case 'sitemap.xml': require $P . 'sitemap.php'; break;
    case 'robots.txt':
        header('Content-Type: text/plain');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout\nDisallow: /account\nDisallow: /orders\nSitemap: " . full_url('/sitemap.xml') . "\n";
        break;
    default: not_found();
}
