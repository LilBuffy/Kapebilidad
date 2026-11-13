# Kapebilidad: Implementation Documentation

This document describes what is actually implemented. Items marked "proposed" or "to confirm" are not verified by the development checks.

## A. Required technologies and hosting

| Item | Status |
|---|---|
| PHP | Code uses PHP 8 syntax and was run on PHP 8.3.6 in the test sandbox. The SQL dump header shows the local XAMPP used PHP 8.1.25. Required: PHP 8.0 or higher with PDO MySQL, DOM/libxml, fileinfo. GD is optional (used to re-encode profile photos). |
| Database | MySQL or MariaDB with InnoDB. Tested on MariaDB 10.11 in the sandbox. The local dump header shows MariaDB 10.4.32. |
| JavaScript | Plain browser JavaScript with no framework and no build step (fetch, DOMParser, localStorage, async/await). |
| XML | Menu catalog data exchange. Described in section B. |
| HTML/CSS | Server-rendered HTML5 pages and hand-written CSS (assets/css/style.css customer, assets/css/admin.css admin). |
| Local development | XAMPP (Apache, PHP, MySQL). Default config/database.php uses root with an empty password. |
| Online hosting | Proposed: PHP/MySQL hosting. The database dump header names an InfinityFree MySQL host. The live URL and HTTPS status are not recorded in the project and must be confirmed by you. |

## B. System structure and data

### PHP modules
- includes/functions.php: shared helpers, product and category loading, discount lookup, status labels.
- includes/pricing.php: all money logic. Amounts are calculated in integer cents. Builds validated order lines from IDs, applies discounts, recalculates totals, loads order items.
- includes/security.php: session start, CSRF token, JSON helpers.
- includes/customer_auth.php and includes/auth.php: customer and admin authentication. They use separate session names (KB_CUSTOMER and KB_ADMIN), so a customer session never grants admin access.
- includes/profile_image.php: validated profile photo upload.
- includes/menu_xml.php: XML export, validation, parsing and import.
- includes/order_view.php and includes/receipt_view.php: shared order and receipt rendering used by customer and admin pages.
- Customer pages: index.php, menu.php, register.php, login.php, logout.php, profile.php, checkout.php, order_confirmation.php, order_history.php, order_details.php, receipt.php.
- API endpoints (api/): cart_quote, place_order, update_order_item_size, delete_customer_order, menu_xml (public), and the admin-only update_order_status, delete_order, delete_product.
- Admin pages (admin/): login, dashboard, order_details, receipt, products, product_form, categories, customization (add-ons and sizes), discounts, xml_catalog.

### JavaScript interactions (assets/js/main.js)
- Product modal: size selection, add-on selection with quantity, live price and breakdown, feedback text.
- Cart in localStorage (key kb_cart_v2). Different sizes or add-ons of the same drink are separate lines.
- Menu sync: on the menu page, fetches api/menu_xml.php, parses it with DOMParser and refreshes each product's price, sizes and add-ons, and hides products that are no longer available.
- Checkout: asks api/cart_quote.php for server-calculated prices, then submits the order.
- Order details: submits a size change and updates the displayed totals from the server reply.
- Admin: confirmation dialogs and CSRF-protected fetch calls (assets/js/admin.js).

### XML menu structure and data exchange
- Schema: xml/menu.xsd. Root menuCatalog contains currency, sizes, add-ons and categories. Each category contains products. Each product has an id, availability, bestseller flag, name, description, basePrice, optional image, per-product sizes (sizeId, availability, extraPrice) and add-on references (addonId).
- Export: Admin, XML Catalog, Download. Built from the database with DOMDocument. Contains no customers, orders, passwords or admin data.
- Public live copy: api/menu_xml.php, available products only, never cached. The customer menu reads it so displayed prices cannot be stale.
- Import: Admin, XML Catalog, upload. Steps: size limit 1 MB, UTF-8 check, DOCTYPE and ENTITY rejected, parse with LIBXML_NONET, validate against xml/menu.xsd, then validate every ID and value against the database. Duplicates, unknown IDs, a product in the wrong category, negative or malformed prices and unknown sizes or add-ons are rejected. Valid data is applied in one transaction. A backup export is written to xml/backups before every real import. A "check only" mode validates without changing anything.
- Import updates existing categories, products, sizes and add-ons only. It does not create or delete records, does not import images, and never touches customers, admins or orders.
- xml/menu_catalog.xml is a sample export generated from the project database. The XML files are blocked from direct web access by xml/.htaccess. Orders never read XML. The final price is always calculated by the server from MySQL.

### MySQL responsibilities
Source of truth for customers, orders, order items, order item add-ons, order change history, products, categories, sizes, add-ons, discounts and admins. Orders store snapshots (names, base price, size price, add-on prices) so later menu changes do not alter past orders.

### Input validation and order processing
1. The browser sends only IDs and quantities. 2. The server loads products, sizes and add-ons from MySQL and rejects unavailable or invalid choices. 3. Prices, discount and totals are computed in cents on the server. 4. The order, items and add-ons are saved in one transaction. A request token prevents double submission. 5. Editing a size locks the order row, checks ownership, status (pending only) and a version number, recalculates, and logs the change in a transaction.

### Sessions and authentication
Passwords use password_hash and password_verify. Session IDs are regenerated at login. Cookies are HttpOnly and SameSite=Lax (Secure over HTTPS). All state-changing customer and admin requests require a CSRF token.

### Admin access control
Admin pages and admin APIs check the admin session on the server. There is one admin role. Customers are blocked from admin URLs. No admin link appears in the customer interface.

### Backup and recovery
- Export the database regularly (phpMyAdmin Export). Keep copies of uploads/profiles.
- xml/backups holds automatic menu snapshots taken before XML imports. A snapshot can be re-imported to restore menu values.
- XML does not back up orders or customers. Only the database dump does.

### Input, processing, output flow
Browser (forms, JSON, XML upload) to PHP (validation, authorization, pricing) to MySQL (transactions) to PHP (HTML, JSON, XML, receipts) to browser.

## C. Screens and responsive layout

Pages present in the application: customer home, menu with customization modal, cart drawer, checkout, order confirmation, My Orders, order details with size editing, receipt, sign up, log in, profile; admin login, orders dashboard, order details, receipt, products, product form, categories, add-ons and sizes, discounts, XML catalog. No wireframe images are included. Capture real screenshots of these pages from the running application. Responsive behavior was written with media queries but has not been checked in a real browser by the development checks.

## Install and upgrade

Existing database (keeps all data): back up first, then import database/upgrade_customer_features.sql in phpMyAdmin. It is safe to re-run. It uses ADD COLUMN IF NOT EXISTS, which MariaDB supports. It was tested on MariaDB 10.11 only. MariaDB 10.4 (your XAMPP) was not available for testing, so run it on a backup first. It seeds Tall, Medio, Grande, the five initial add-ons, product size rows for drink categories, and keeps legacy add-ons as available add-ons.

Fresh database: import database/fresh.sql. It creates the kapebilidad database with the full schema, the seed menu and the existing admin account row.

Placeholder prices to review in the admin: size extras (Tall 0, Medio 10, Grande 20) and the new add-ons (Pearl 15, Cream Cheese 25, Nata 15, Coffee Jelly 15). Extra Shot keeps its earlier price of 20.

Uploads: uploads/profiles must be writable by the web server. It contains an .htaccess that blocks script execution.

Menu seed: the database has five categories including Frappes (three products). The intended specification has four. Nothing was deleted. database/optional_hide_frappes.sql hides Frappes products without deleting them. It is not run automatically.
