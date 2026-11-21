<?php
header('Content-Type: application/xml; charset=utf-8');
$u = ['/', '/equipment', '/spare-parts', '/supplies', '/real-estate', '/request-quote', '/contact', '/about', '/services', '/faq', '/delivery-policy', '/returns', '/privacy', '/terms'];
foreach (categories() as $c) $u[] = catalogue_base($c['kind']) . '?category=' . $c['slug'];
foreach (db()->query("SELECT slug FROM products WHERE status = 'PUBLISHED'") as $r) $u[] = '/p/' . $r['slug'];
foreach (db()->query('SELECT slug FROM properties WHERE active = 1') as $r) $u[] = '/real-estate/' . $r['slug'];
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($u as $x) echo '<url><loc>' . h(full_url($x)) . '</loc></url>';
echo '</urlset>';
