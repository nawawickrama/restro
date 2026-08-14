# Restro POS

A small, fast restaurant point of sale. Three kinds of sale — **dine in**, **walk-in takeaway**
and **phone order** — and nothing else in the way.

Open POS → pick the sale type → tap the food → take payment → print receipt → done.

Built with Laravel 13, PHP 8.4, MySQL, Blade, Tailwind CSS 4, Alpine.js and
Spatie Laravel Permission.

See [BUILD_PLAN.md](BUILD_PLAN.md) for the phase-by-phase build and the design decisions.

---

## Requirements

- PHP 8.4 (8.3 minimum)
- Composer 2
- MySQL 8+ (tested on 9.7)
- Node 20+

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan storage:link      # serves uploaded food photos
```

Point `.env` at your database, then:

```bash
mysql -e "CREATE DATABASE restro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

php artisan migrate --seed
npm run build

php artisan serve
```

The default `.env` expects MySQL on `127.0.0.1:3306` with user `root` and an empty password,
database `restro`.

### Sign in

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@restro.test` | `password` |
| Cashier | `cashier@restro.test` | `password` |

**Change both passwords before this goes anywhere near a real restaurant.**

The seed also creates 8 tables, 4 categories and 17 sample menu items, so the POS works the
moment you sign in.

---

## Day-to-day

```bash
npm run dev          # Vite dev server with hot reload
php artisan test     # 51 tests, runs against MySQL (see below)
./vendor/bin/pint    # code style
```

### Tests need their own database

Some rules are enforced by MySQL-only schema features, so the suite runs on MySQL rather than
SQLite. Create the test database once:

```bash
mysql -e "CREATE DATABASE restro_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

`phpunit.xml` already points at it.

---

## What's in it

**POS** — Touch-first home screen with the three sale types and a live table plan showing free
and occupied at a glance. Order screen with category tabs, menu search, one-tap quantity
changes, per-item notes, hold and checkout.

**Dine in** — Tapping a table opens its running order or starts a new one. The table stays
occupied while the order is open and frees itself when the order is completed or cancelled.
Orders can be moved between tables.

**Phone orders** — Tapping PHONE ORDER goes straight to the order screen, so the food goes down
while the caller is still talking. The mobile number is taken afterwards and is required before
checkout; the name is optional. Pending → Ready → Collected tracks them while they're on the way.

**Food photos** — Menu items take an optional picture, shown on the POS tile. Items without one
still work exactly the same.

**Checkout** — Cash with live change and quick-tender buttons, or card with an optional
reference. Nothing completes without a payment that covers the total.

**Receipts** — 80mm printable, auto-printed straight after checkout, reprintable from history.

**Order history** — A sortable, paged table built to stay quick as the years pile up: quick date
ranges, a custom range, filters for type/status/payment, and search by order number, mobile or
customer name. All filtering, sorting and paging happens in the database, so the browser only
ever receives one page. Measured at 0.17 ms per page against a year of orders.

**Reports** — Today, yesterday, this week, last week, this month, last month or a custom range,
each one tap away. Takings, order count, cash vs card, sales by order type, a day-by-day
breakdown, best sellers and slowest movers. Every period can be viewed on screen, printed as a
plain black-and-white sheet, or downloaded as a CSV for a spreadsheet.

**Back office** — Menu items and categories, tables, staff accounts and roles, and restaurant
settings.

**Roles** — Admin and Cashier out of the box. Access is decided by permission, never by role
name, so new roles are a settings change rather than a code change.

Dark mode is included and remembered per terminal.

---

## Project layout

```
app/
  Enums/          OrderType, OrderStatus, FulfillmentStatus, PaymentMethod, PaymentStatus
  Services/       OrderService, CheckoutService, ReportService, SettingsService
  Policies/       OrderPolicy
  Support/        Permissions (the single list), helpers
  Http/
    Controllers/  Pos/*, Admin/*, Auth/*, order history, dashboard, reports
    Requests/     validation
resources/
  views/pos/      POS home, order screen, phone order, checkout
  views/orders/   history, detail, receipt
  views/admin/    menu, categories, tables, users, settings
  js/pos-order.js the order screen
database/
  migrations/     users, categories, menu_items, tables, orders, order_items, payments, settings
  seeders/        roles & permissions, users, settings, menu, tables
```
