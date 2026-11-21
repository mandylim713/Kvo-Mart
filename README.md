# KVO Mart — website and store

Full working version of the approved KVO Mart demo for **KVO Mart Ideal Services Ltd.**
(Contracts, Real Estates & Supplies): heavy equipment, tractors, spare parts, supplies and real estate.

Plain PHP 8.1+, no frameworks. SQLite by default (nothing to set up) or MySQL.

## Install on cPanel (Namecheap)

1. Upload the zip to `public_html` (or a sub-folder) with File Manager and **Extract**.
2. Open the website. The database creates itself on the first visit with the full catalogue
   (23 listings, 14 categories), delivery zones, policy pages and FAQs.
3. Sign in at **/admin** — `admin@kvomart.com` / `KvoAdmin2026`. You are asked to change the password straight away.
4. Work through the **Finish setting up the store** list on the dashboard:
   - Settings → Business: phone, email, WhatsApp, address, opening hours
   - Settings → Payments: real bank account, Paystack and/or Flutterwave keys
   - Settings → Emails: where order and enquiry notifications go
5. Make sure PHP is **8.1 or newer** (cPanel → Select PHP Version) with `pdo_sqlite` (or `pdo_mysql`), `curl` and `gd` ticked.
6. Folders `data/` and `uploads/` must be writable (755 is normally fine).

### Using MySQL instead of SQLite
Create a database and user in cPanel → MySQL Databases, then in `includes/config.php` set
`DB_DRIVER` to `'mysql'` and fill in `DB_NAME`, `DB_USER`, `DB_PASS`. Tables are created automatically.

### Payments
- **Paystack**: Settings → Payments → paste the public and secret keys, tick “Offer Paystack”.
  In the Paystack dashboard set the webhook URL to `https://YOURDOMAIN/pay/webhook/paystack`.
- **Flutterwave**: paste the keys and a webhook secret hash; in Flutterwave set the webhook to
  `https://YOURDOMAIN/pay/webhook/flutterwave` with the same hash.
- **Bank transfer**: on by default. Put the real account details in Settings → Payments.
- Every online payment is re-checked with the provider's API before an order is marked paid,
  so nothing the browser sends is trusted. Webhooks are signature-checked.

## What's included

**Store (same design as the demo):** home page, heavy equipment / spare parts / supplies catalogues with
filters and sorting, search, product pages with photo gallery, specifications and quote form, compare up to 4
machines, cart, checkout, order tracking, printable receipts, customer accounts (orders, quotations, saved
addresses, password reset), real estate listings, request-a-quote and financing enquiries, contact form,
FAQ and policy pages, WhatsApp buttons, sitemap.xml and robots.txt.

**How selling works (as in the demo):**
- Fixed-price items are bought online. “Request price” machines are sold by written quotation.
- Stock is held while an order waits for payment and released automatically if it isn't paid in time.
  A single used machine can never be sold twice.
- Parts and supplies pay a flat delivery fee by zone. Heavy machines get a **delivery quote** after the order,
  which the customer accepts and pays before dispatch — or they collect.
- Bank transfers are confirmed by finance staff; the customer can report “I've made the transfer”.
- Promo codes, VAT (inclusive or exclusive), refunds (with optional restock).

**Admin (works on a phone):** dashboard with to-do lists, orders, deliveries, products with photos and
price history, categories, inventory with a full stock log, enquiries, quotations (create, send, customer
accepts online, becomes an order), customers, promotions, delivery zones, real estate, pages & FAQs,
newsletter subscribers, settings, staff with roles (Super admin, Store manager, Inventory manager, Sales rep,
Finance officer, Delivery manager, Customer support), email log and audit log.

## Notes
- Product photos from the demo are representative stock photos with credits (see /image-credits).
  Replace them with real photos from the admin; the “representative photo” label disappears automatically.
- Contact details are blank until added in Settings — nothing is invented on the website.
- The KVO10 promo code from the demo is included but switched **off**; turn it on in Admin → Promotions.

Powered by DWO — digitalweboracleict.com
