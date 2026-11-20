KVO MART — INSTALLATION (cPanel)
================================

1. Upload kvomart.zip to public_html (or a sub-folder) in cPanel File Manager and Extract.
2. Visit your domain. The database (SQLite, in /data) creates itself on first load
   with sample categories, products, slides, pages and delivery zones.
3. Admin:  https://YOURDOMAIN/admin/
   Email:    admin@kvomart.com
   Password: admin123      <-- change immediately (top-right menu > My password)
4. Admin > Settings: business name, logo, colours, phone, WhatsApp number, address,
   bank details, Flutterwave keys, delivery options, email notifications.
5. Replace the placeholder products (Admin > Products) and slides (Admin > Homepage slides).

Folder permissions: /data and /uploads must be writable (755 is normally fine).

Using MySQL instead of SQLite (optional)
  Create a database + user in cPanel > MySQL Databases, then edit includes/config.php:
  DB_DRIVER = 'mysql' and fill in DB_NAME / DB_USER / DB_PASS. Tables create automatically.

Flutterwave
  Admin > Settings > Payments: tick Flutterwave, paste Public + Secret keys.
  Webhook URL (Flutterwave dashboard > Settings > Webhooks): https://YOURDOMAIN/pay/webhook.php
  Use the same secret hash in both places.

Features
  Storefront: homepage slider, categories, deals, featured, new arrivals, search,
  filters (category, price, brand, stock), product gallery, specs, reviews, cart,
  checkout (Flutterwave / bank transfer / pay on delivery / WhatsApp order),
  delivery zones + free-delivery threshold + store pickup, order tracking,
  customer accounts, contact form, newsletter, CMS pages, printable receipts.
  Mobile: bottom tab bar, slide-out menu, category bottom sheet, filter sheet,
  2-column product grid, sticky add-to-cart and checkout bars, swipeable galleries.
  Admin: sales dashboard, orders (status, payment, notes, CSV export, WhatsApp
  customer), products with images, inventory with stock history and low-stock
  alerts, categories, customers, messages, reviews, slides, pages, zones,
  subscribers, settings, admin/staff users. Fully usable on a phone.

Powered by DWO — digitalweboracleict.com
