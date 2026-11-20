<?php
/**
 * First-run seed data. All of it is placeholder content that the owner
 * replaces from the admin dashboard.
 */

function seed_placeholder_svg(string $file, string $title, string $c1, string $c2, string $label = 'KVO MART'): string
{
    $dir = __DIR__ . '/../uploads/products';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $words = explode(' ', $title);
    $lines = [];
    $cur = '';
    foreach ($words as $w) {
        if (strlen($cur . ' ' . $w) > 16 && $cur !== '') { $lines[] = $cur; $cur = $w; }
        else $cur = trim($cur . ' ' . $w);
    }
    if ($cur !== '') $lines[] = $cur;
    $lines = array_slice($lines, 0, 3);
    $text = '';
    $y = 430 - (count($lines) - 1) * 22;
    foreach ($lines as $i => $l) {
        $text .= '<text x="300" y="' . ($y + $i * 44) . '" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="36" font-weight="700" fill="#ffffff">' . htmlspecialchars($l) . '</text>';
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="600" height="600">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient></defs>'
        . '<rect width="600" height="600" fill="url(#g)"/>'
        . '<circle cx="520" cy="80" r="140" fill="#ffffff" opacity=".08"/><circle cx="60" cy="560" r="160" fill="#000000" opacity=".06"/>'
        . '<g transform="translate(300 230)" fill="none" stroke="#ffffff" stroke-width="10" stroke-linejoin="round" opacity=".95">'
        . '<path d="M-90 -40 L0 -90 L90 -40 L90 60 L0 110 L-90 60 Z"/><path d="M-90 -40 L0 10 L90 -40 M0 10 L0 110"/></g>'
        . $text
        . '<text x="300" y="560" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="18" letter-spacing="6" fill="#ffffff" opacity=".7">' . htmlspecialchars($label) . '</text>'
        . '</svg>';
    file_put_contents($dir . '/' . $file, $svg);
    return 'uploads/products/' . $file;
}

function seed_slide_svg(string $file, string $c1, string $c2): string
{
    $dir = __DIR__ . '/../uploads/branding';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 700" preserveAspectRatio="xMidYMid slice">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient></defs>'
        . '<rect width="1600" height="700" fill="url(#g)"/>'
        . '<circle cx="1300" cy="200" r="320" fill="#fff" opacity=".07"/><circle cx="1450" cy="620" r="220" fill="#fff" opacity=".05"/>'
        . '<g transform="translate(1180 360)" fill="none" stroke="#fff" stroke-width="14" stroke-linejoin="round" opacity=".25">'
        . '<path d="M-180 -80 L0 -180 L180 -80 L180 120 L0 220 L-180 120 Z"/><path d="M-180 -80 L0 20 L180 -80 M0 20 L0 220"/></g>'
        . '</svg>';
    file_put_contents($dir . '/' . $file, $svg);
    return 'uploads/branding/' . $file;
}

function seed_database(PDO $pdo): void
{
    $now = date('Y-m-d H:i:s');

    // ---------- settings ----------
    $settings = [
        'site_name'          => 'KVO Mart',
        'company_name'       => 'KVO Mart Ideal Services Ltd.',
        'tagline'            => 'Quality products, fair prices, delivered to your door.',
        'meta_description'   => 'KVO Mart Ideal Services Ltd. — shop groceries, household essentials, electronics, appliances and more online with fast delivery across Nigeria.',
        'logo'               => '',
        'favicon'            => '',
        'color_primary'      => '#0B6E4F',
        'color_accent'       => '#F59E0B',
        'currency'           => '₦',
        'phone'              => '+234 800 000 0000',
        'phone2'             => '',
        'whatsapp'           => '2348000000000',
        'email'              => 'info@kvomart.com',
        'address'            => 'Store address, City, Nigeria',
        'opening_hours'      => 'Mon – Sat: 8:00am – 8:00pm · Sun: 12:00pm – 6:00pm',
        'map_embed'          => '',
        'facebook'           => '',
        'instagram'          => '',
        'twitter'            => '',
        'tiktok'             => '',
        'topbar_text'        => 'Free delivery on orders above ₦50,000 within the city',
        'free_delivery_min'  => '50000',
        'pickup_enabled'     => '1',
        'pickup_address'     => 'Pick up from our store during opening hours.',
        'pay_flutterwave'    => '0',
        'flw_public_key'     => '',
        'flw_secret_key'     => '',
        'flw_hash'           => '',
        'pay_transfer'       => '1',
        'bank_name'          => 'Bank Name',
        'bank_account_name'  => 'KVO Mart Ideal Services Ltd.',
        'bank_account_no'    => '0000000000',
        'pay_pod'            => '1',
        'pay_whatsapp'       => '1',
        'min_order'          => '0',
        'notify_email'       => 'info@kvomart.com',
        'send_emails'        => '0',
        'about_short'        => 'KVO Mart Ideal Services Ltd. is your one-stop shop for everyday essentials and quality home products. We source genuine products, price them fairly and deliver them quickly, so you can shop with confidence.',
        'show_powered_by'    => '1',
    ];
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
    foreach ($settings as $k => $v) $st->execute([$k, $v]);

    // ---------- admin ----------
    $pdo->prepare('INSERT INTO admins (name, email, password, role, is_active, created_at) VALUES (?,?,?,?,1,?)')
        ->execute(['Administrator', 'admin@kvomart.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin', $now]);

    // ---------- categories ----------
    $cats = [
        ['Groceries & Provisions', 'fa-basket-shopping', 'Rice, grains, pasta, oils, spices and pantry staples.', ['#0B6E4F', '#14A06F']],
        ['Beverages & Drinks',     'fa-mug-hot',         'Juices, soft drinks, water, tea, coffee and beverages.', ['#B45309', '#F59E0B']],
        ['Household & Cleaning',   'fa-spray-can-sparkles','Detergents, cleaners, tissues and home care.', ['#1D4ED8', '#60A5FA']],
        ['Personal Care',          'fa-pump-soap',       'Toiletries, skincare, hair care and hygiene.', ['#BE185D', '#F472B6']],
        ['Electronics & Gadgets',  'fa-plug',            'Phones accessories, power banks, lighting and gadgets.', ['#111827', '#4B5563']],
        ['Home Appliances',        'fa-blender',         'Blenders, kettles, irons, fans and kitchen appliances.', ['#6D28D9', '#A78BFA']],
        ['Kitchen & Dining',       'fa-utensils',        'Cookware, utensils, storage and dining ware.', ['#B91C1C', '#F87171']],
        ['Baby & Kids',            'fa-baby',            'Diapers, baby food, wipes and kids essentials.', ['#0E7490', '#22D3EE']],
    ];
    $stc = $pdo->prepare('INSERT INTO categories (name, slug, icon, image, description, sort_order, is_active) VALUES (?,?,?,?,?,?,1)');
    $catIds = [];
    foreach ($cats as $i => $c) {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $c[0]), '-'));
        $img = seed_placeholder_svg('cat-' . $slug . '.svg', $c[0], $c[3][0], $c[3][1], 'CATEGORY');
        $stc->execute([$c[0], $slug, $c[1], $img, $c[2], $i + 1]);
        $catIds[$i] = (int)$pdo->lastInsertId();
    }

    // ---------- products: [catIndex, name, brand, unit, price, old, stock, featured, deal] ----------
    $products = [
        [0, 'Premium Long Grain Parboiled Rice', 'Royal Stallion', '50kg bag', 78000, 82000, 40, 1, 0],
        [0, 'Pure Vegetable Cooking Oil', 'Power Oil', '5 litres', 14500, 15800, 60, 1, 1],
        [0, 'Spaghetti Pasta', 'Golden Penny', 'Carton of 20', 17500, 0, 35, 0, 0],
        [0, 'Seasoning Cubes', 'Maggi', 'Pack of 100', 2400, 2700, 120, 0, 1],
        [1, 'Malt Drink Can', 'Malta Guinness', 'Pack of 24', 13800, 0, 50, 1, 0],
        [1, 'Pure Orange Juice', 'Chivita 100%', '1 litre', 2300, 2500, 80, 0, 1],
        [1, 'Table Water', 'Eva', 'Pack of 12 (75cl)', 3200, 0, 90, 0, 0],
        [1, 'Instant Coffee', 'Nescafé Classic', '200g jar', 6800, 7400, 30, 0, 0],
        [2, 'Washing Detergent Powder', 'Ariel', '2kg', 5600, 6200, 70, 1, 1],
        [2, 'Toilet Tissue Rolls', 'Rose Plus', 'Pack of 12', 4200, 0, 100, 0, 0],
        [2, 'Multi-Surface Cleaner', 'Hypo', '1 litre', 1800, 0, 65, 0, 0],
        [3, 'Moisturising Body Lotion', 'Nivea', '400ml', 5900, 6500, 45, 1, 0],
        [3, 'Antibacterial Bar Soap', 'Dettol', 'Pack of 4', 2900, 0, 85, 0, 1],
        [3, 'Fluoride Toothpaste', 'Close-Up', '140g', 1500, 0, 110, 0, 0],
        [4, '20000mAh Fast-Charge Power Bank', 'Oraimo', '1 unit', 21500, 24000, 25, 1, 1],
        [4, 'Rechargeable LED Lantern', 'Lontor', '1 unit', 9800, 0, 30, 0, 0],
        [4, 'Wireless Bluetooth Earbuds', 'Oraimo', '1 pair', 16500, 18500, 20, 0, 0],
        [5, 'Electric Blender with Grinder', 'Binatone', '1.5 litres', 32000, 36000, 15, 1, 0],
        [5, 'Electric Kettle Stainless', 'Scanfrost', '1.7 litres', 14800, 0, 22, 0, 1],
        [5, '18-inch Standing Fan', 'Ox', '1 unit', 38500, 42000, 12, 1, 0],
        [6, 'Non-Stick Cookware Set', 'Tower', '5 pieces', 45000, 52000, 10, 1, 0],
        [6, 'Airtight Food Storage Containers', 'Kitchen Pro', 'Set of 6', 8900, 0, 40, 0, 0],
        [7, 'Baby Diapers Size 4', 'Molfix', 'Jumbo pack (64)', 15500, 16800, 35, 0, 1],
        [7, 'Baby Wet Wipes', 'Huggies', 'Pack of 3 x 56', 4800, 0, 60, 0, 0],
    ];
    $stp = $pdo->prepare('INSERT INTO products (category_id, name, slug, sku, brand, unit, short_desc, description, specs, price, old_price, stock, low_stock, image, is_featured, is_deal, is_active, views, sold, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,5,?,?,?,1,0,0,?,?)');
    $stl = $pdo->prepare('INSERT INTO stock_log (product_id, qty_change, stock_after, reason, admin_id, created_at) VALUES (?,?,?,?,1,?)');
    foreach ($products as $i => $p) {
        [$ci, $name, $brand, $unit, $price, $old, $stock, $feat, $deal] = $p;
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $c = $cats[$ci][3];
        $img = seed_placeholder_svg('p-' . $slug . '.svg', $name, $c[0], $c[1]);
        $sku = 'KVO-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT);
        $short = "$brand $name — $unit. Genuine product, carefully stored and ready for delivery.";
        $desc = "<p>$brand $name ($unit) sourced directly from trusted distributors. Every item is checked before dispatch so you receive it in perfect condition.</p><p>This product description is a placeholder — update it with the real details from the admin dashboard.</p>";
        $specs = "Brand: $brand\nPack size: $unit\nCategory: {$cats[$ci][0]}\nCondition: Brand new";
        $created = date('Y-m-d H:i:s', strtotime("-" . (count($products) - $i) . " hours"));
        $stp->execute([$catIds[$ci], $name, $slug, $sku, $brand, $unit, $short, $desc, $specs, $price, $old, $stock, $img, $feat, $deal, $created, $created]);
        $stl->execute([(int)$pdo->lastInsertId(), $stock, $stock, 'Opening stock', $now]);
    }

    // ---------- delivery zones ----------
    $zones = [
        ['Within the city', 1500, 'Same day / next day'],
        ['City outskirts & satellite towns', 3000, '1 – 2 days'],
        ['Neighbouring states', 6000, '2 – 4 days'],
        ['Other states in Nigeria', 9000, '3 – 6 days'],
    ];
    $stz = $pdo->prepare('INSERT INTO delivery_zones (name, fee, eta, sort_order, is_active) VALUES (?,?,?,?,1)');
    foreach ($zones as $i => $z) $stz->execute([$z[0], $z[1], $z[2], $i + 1]);

    // ---------- slides ----------
    $slides = [
        ['Everyday essentials, delivered fast', 'Groceries, household items and personal care at fair prices — order online and we bring it to your door.', 'Shop now', 'shop.php', seed_slide_svg('slide-1.svg', '#0B6E4F', '#064E3B')],
        ['Upgrade your home', 'Quality appliances and kitchenware from brands you trust, with warranty support.', 'Browse appliances', 'shop.php?cat=home-appliances', seed_slide_svg('slide-2.svg', '#1E293B', '#0B6E4F')],
        ['Hot deals this week', 'Save big on selected products while stock lasts.', 'See deals', 'shop.php?deals=1', seed_slide_svg('slide-3.svg', '#B45309', '#7C2D12')],
    ];
    $sts = $pdo->prepare('INSERT INTO slides (title, subtitle, button_text, button_link, image, sort_order, is_active) VALUES (?,?,?,?,?,?,1)');
    foreach ($slides as $i => $s) $sts->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $i + 1]);

    // ---------- pages ----------
    $pages = [
        ['About Us', 'about', '<h2>Who we are</h2><p>KVO Mart Ideal Services Ltd. is a retail and supply company committed to making quality products easy to buy. From groceries and household essentials to electronics and home appliances, we stock genuine products at fair prices.</p><h2>Our promise</h2><ul><li>Genuine products from trusted brands and distributors</li><li>Fair, transparent pricing</li><li>Fast, reliable delivery</li><li>Friendly support before and after you buy</li></ul><p>Edit this page anytime from Admin → Pages.</p>'],
        ['Delivery Information', 'delivery', '<p>We deliver to all locations listed at checkout. Delivery fees depend on your zone and are shown before you pay.</p><ul><li>Orders confirmed before 2pm are usually dispatched the same day within the city.</li><li>You will receive a call or WhatsApp message before delivery.</li><li>You can also pick up your order from our store.</li></ul>'],
        ['Returns & Refunds', 'returns', '<p>If an item arrives damaged, expired or incorrect, contact us within 48 hours of delivery with your order number and photos. We will replace the item or refund you after inspection.</p><p>Perishable goods and opened personal care items cannot be returned unless faulty.</p>'],
        ['Privacy Policy', 'privacy', '<p>We collect only the information needed to process your orders and improve our service: your name, phone number, email address and delivery address. We never sell your data. Card payments are processed securely by Flutterwave; we do not store card details.</p>'],
        ['Terms & Conditions', 'terms', '<p>By using this website and placing an order you agree to these terms. Prices and availability may change without notice. An order is confirmed once payment is received or, for pay-on-delivery orders, once we confirm by phone.</p>'],
        ['Frequently Asked Questions', 'faq', '<h3>How do I place an order?</h3><p>Add products to your cart, go to checkout, fill in your delivery details and choose a payment method.</p><h3>Which payment methods do you accept?</h3><p>Card/bank payment online, bank transfer, pay on delivery (selected areas) and WhatsApp orders.</p><h3>How can I track my order?</h3><p>Use the Track Order page with your order number and phone number.</p><h3>Can I buy in bulk?</h3><p>Yes — contact us for wholesale and bulk pricing.</p>'],
    ];
    $stpg = $pdo->prepare('INSERT INTO pages (title, slug, content, in_footer, is_active, updated_at) VALUES (?,?,?,1,1,?)');
    foreach ($pages as $p) $stpg->execute([$p[0], $p[1], $p[2], $now]);
}
