<?php
/**
 * First-run data: the catalogue, categories, zones, pages and settings
 * from the approved demo. Everything here is editable in the admin.
 */
function seed_database(PDO $pdo): void
{
    $now = date('c');
    $data = json_decode(file_get_contents(__DIR__ . '/seed_data.json'), true);

    $settings = [
        'business_name'       => 'KVO Mart Ideal Services Ltd.',
        'short_name'          => 'KVO Mart',
        'tagline'             => 'Contracts, Real Estates & Supplies',
        'meta_description'    => 'KVO Mart supplies tractors, construction machinery, industrial equipment, spare parts and commercial supplies across Nigeria — priced in Naira, with delivery arranged to your site.',
        'phone'               => '',
        'email'               => '',
        'whatsapp'            => '',
        'address'             => '',
        'hours'               => '',
        'announcement'        => '',
        'logo'                => 'assets/img/logo.jpg',
        'tax_bps'             => '0',
        'tax_label'           => 'VAT',
        'prices_include_tax'  => '1',
        'pickup_enabled'      => '1',
        'pickup_note'         => 'Collect from our yard — we confirm the collection time after payment.',
        'bank_transfer_enabled' => '1',
        'bank_instructions'   => "Bank: (add your bank)\nAccount name: KVO Mart Ideal Services Ltd.\nAccount number: (add account number)\nUse your payment reference as the narration.",
        'card_hold_min'       => '60',
        'transfer_hold_hours' => '48',
        'admin_email'         => '',
        'send_emails'         => '1',
        'mail_from'           => '',
        'paystack_enabled'    => '0',
        'paystack_public'     => '',
        'paystack_secret'     => '',
        'flutterwave_enabled' => '0',
        'flutterwave_public'  => '',
        'flutterwave_secret'  => '',
        'flutterwave_hash'    => '',
        'facebook' => '', 'instagram' => '', 'linkedin' => '', 'x' => '',
        'show_credit'         => '1',
    ];
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
    foreach ($settings as $k => $v) $st->execute([$k, $v]);

    // first administrator — must be changed after first sign-in
    $pdo->prepare('INSERT INTO staff (name, email, password, role, active, created) VALUES (?,?,?,?,1,?)')
        ->execute(['Administrator', 'admin@kvomart.com', password_hash('KvoAdmin2026', PASSWORD_DEFAULT), 'SUPER_ADMIN', $now]);

    $sc = $pdo->prepare('INSERT INTO categories (slug, name, kind, description, sort_order, active) VALUES (?,?,?,?,?,1)');
    foreach ($data['categories'] as $i => $c) $sc->execute([$c['slug'], $c['name'], $c['kind'], $c['desc'], $i + 1]);

    $sp = $pdo->prepare('INSERT INTO products (sku, slug, name, model, kind, cat, brand, cond, mode, price, on_hand, reserved, low, inv, dq, featured, status, description, specs, image, credit, credit_url, sales, views, created, updated) VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,\'PUBLISHED\',?,?,?,?,?,0,0,?,?)');
    $ph = $pdo->prepare('INSERT INTO price_history (product_id, price, at) VALUES (?,?,?)');
    $mv = $pdo->prepare('INSERT INTO movements (product_id, sku, type, qty, on_hand, reserved, reason, order_ref, by_name, at) VALUES (?,?,\'RECEIPT\',?,?,0,\'Opening stock\',NULL,\'System\',?)');
    $n = count($data['products']);
    foreach ($data['products'] as $i => $p) {
        $sku = str_replace(['SAMPLE-', 'DEMO-'], 'KVO-', $p['sku']);
        $price = $p['price'] === null ? null : (int)$p['price'];
        $desc = $p['kind'] === 'EQUIPMENT'
            ? $p['name'] . '. ' . ($price === null ? 'Price on request' : 'Price shown is a typical Nigerian market asking price for this model and condition') . '. Our sales team confirms availability, specification, hours and final price before you buy. Delivery is ' . ($p['dq'] ? 'quoted separately for your approval, based on size, weight and route' : 'calculated at checkout') . '.'
            : $p['name'] . '. Contact us for bulk pricing.';
        $created = date('c', time() - ($n - $i) * 3600);
        $img = $p['img'] ? 'uploads/products/' . $p['img'] . '.jpg' : null;
        $sp->execute([$sku, $p['slug'], $p['name'], $p['model'] ?: '', $p['kind'], $p['cat'], $p['brand'] === 'Generic' ? '' : ($p['brand'] ?: ''), $p['condition'], $p['mode'], $price, (int)$p['stock'], $p['inv'] === 'UNIQUE_UNIT' ? 1 : 3, $p['inv'], $p['dq'] ? 1 : 0, $p['featured'] ? 1 : 0, $desc, json_encode($p['specs'] ?: []), $img, $p['credit'], $p['creditUrl'], $created, $created]);
        $id = (int)$pdo->lastInsertId();
        if ($price !== null) $ph->execute([$id, $price, $created]);
        $mv->execute([$id, $sku, (int)$p['stock'], (int)$p['stock'], $created]);
    }

    $sz = $pdo->prepare('INSERT INTO zones (name, states, fee, lead_days, active) VALUES (?,?,?,?,1)');
    $sz->execute(['FCT', json_encode(['FCT']), 1500000, 2]);
    $sz->execute(['North Central', json_encode(['Nasarawa', 'Niger', 'Kogi', 'Kwara', 'Plateau', 'Benue']), 2500000, 4]);
    $sz->execute(['Lagos & South West', json_encode(['Lagos', 'Ogun', 'Oyo', 'Osun', 'Ondo', 'Ekiti']), 3500000, 5]);

    $pdo->prepare('INSERT INTO promos (code, description, type, value, min_order, max_disc, starts, ends, max_uses, per_customer, uses, active) VALUES (?,?,?,?,?,?,?,?,?,?,0,0)')
        ->execute(['KVO10', '10% off orders over ₦100,000 (max ₦500,000 off)', 'PERCENT', 1000, 10000000, 50000000, date('c'), date('c', time() + 30 * 86400), 100, 1]);

    $sr = $pdo->prepare('INSERT INTO properties (slug, title, type, listing, location, state, price, size, beds, features, description, image, active, sort_order, created) VALUES (?,?,?,?,?,?,?,?,NULL,?,?,NULL,1,?,?)');
    $sr->execute(['commercial-plot-kubwa', 'Commercial plot', 'Land', 'Sale', 'Kubwa', 'FCT', null, 1200, json_encode(['Road frontage', 'Fenced']), 'Commercial plot with road frontage. Property is sold by enquiry; our team arranges viewings and documentation.', 1, $now]);
    $sr->execute(['warehouse-idu', 'Warehouse for lease', 'Warehouse', 'Lease', 'Idu industrial area', 'FCT', 1800000000, 2500, json_encode(['Loading bay', 'Three-phase power']), 'Warehouse for lease in Idu industrial area. Price shown is per year.', 2, $now]);

    $pages = [
        'about' => ['About KVO Mart', '<p><strong>KVO Mart Ideal Services Ltd.</strong> — Contracts, Real Estates &amp; Supplies.</p><p>We supply tractors, construction machinery, industrial equipment, spare parts and commercial supplies to contractors, farms and businesses across Nigeria, and handle procurement and supply contracts for projects.</p><p>Our team sources new, used and refurbished machinery, confirms specifications in writing, and arranges delivery to your site.</p>'],
        'services' => ['Services', '<h2>Equipment sales and sourcing</h2><p>New, used and refurbished machines from stock, or sourced to your specification.</p><h2>Parts and supplies</h2><p>Spare parts, filters, implements and construction materials, with compatibility checked before you buy.</p><h2>Contracts and procurement</h2><p>Supply contracts and procurement for construction, agriculture and industrial projects.</p><h2>Real estate</h2><p>Land, warehouses and commercial property for sale or lease.</p>'],
        'delivery-policy' => ['Delivery policy', '<p>Parts, supplies and smaller equipment ship at a fixed rate to states inside our delivery zones. The charge for your address is shown at checkout before you pay.</p><h2>Heavy machinery</h2><p>Excavators, dozers, graders, rollers, tractors and other large machines are moved on low-bed trailers. The cost depends on machine size and weight, loading, distance and route. After your order we send a delivery quote; you approve and pay it before dispatch, or you can collect the machine yourself.</p><h2>Delivery status</h2><p>You can follow each stage — assessment, quote, dispatch, in transit, delivered — from your order page.</p><h2>Inspection on arrival</h2><p>Please inspect the machine on delivery and note any issue on the delivery note before signing.</p>'],
        'returns' => ['Returns and refunds', '<p>Unused spare parts and supplies in original packaging can be returned within 7 days of delivery. Wrong or faulty items are replaced or refunded.</p><h2>Equipment</h2><p>Machines are sold after inspection. Report any issue that differs from the agreed specification within 48 hours of delivery and we\'ll work with you to resolve it.</p><h2>Refunds</h2><p>Approved refunds go back to the original payment method or by bank transfer. Partial refunds are possible where part of an order is returned.</p>'],
        'privacy' => ['Privacy policy', '<p>We collect the details you give us — name, contact details, delivery address and order history — to process orders, arrange delivery and respond to enquiries.</p><p>Payments are processed by our payment providers. We don\'t store card details.</p><p>We don\'t sell your information. You can ask us to correct or delete your personal data at any time.</p>'],
        'terms' => ['Terms and conditions', '<p>Prices are in Nigerian Naira. An order is confirmed once payment is verified. Stock is held for a limited time while payment is pending; unpaid orders are cancelled when the hold expires.</p><p>Machines marked “Request price” are sold by written quotation. Quotations state their validity period and payment terms.</p><p>Delivery for heavy equipment is quoted separately and is your responsibility to approve before dispatch.</p>'],
    ];
    $spg = $pdo->prepare('INSERT INTO pages (slug, title, content, updated) VALUES (?,?,?,?)');
    foreach ($pages as $slug => [$t, $c]) $spg->execute([$slug, $t, $c, $now]);

    $faqs = [
        ['Are the prices on the website final?', 'Listed prices are our selling prices in Naira. For machines marked “Request price”, or for special specifications, we send you a written quotation.'],
        ['How does delivery work for heavy machinery?', 'Machines are delivered on a separate delivery quote based on size, weight, loading and route. You approve and pay the delivery quote before dispatch, or collect yourself.'],
        ['Which payment methods do you accept?', 'Card, bank and USSD payments through our payment providers, and bank transfer (confirmed by our finance team before your order is processed).'],
        ["Can I buy a spare part if I'm not sure it fits?", "Send a compatibility enquiry from the product page with your machine's make, model and serial number. We confirm fitment before you buy."],
    ];
    $sf = $pdo->prepare('INSERT INTO faqs (question, answer, sort_order) VALUES (?,?,?)');
    foreach ($faqs as $i => $f) $sf->execute([$f[0], $f[1], $i + 1]);

    $pdo->prepare('INSERT INTO audit (at, actor, action, entity, details) VALUES (?,?,?,?,?)')->execute([$now, 'System', 'site.installed', '-', 'Store set up with the approved catalogue']);
}
