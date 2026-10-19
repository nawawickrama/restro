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

### Time

The application runs on **Asia/Colombo**, set by `APP_TIMEZONE` in `.env`:

```dotenv
APP_TIMEZONE=Asia/Colombo
```

Every date in this codebase comes from PHP rather than from the database clock, so that one line
decides when the day turns over — for the daily order numbers (`ORD-20260826-0001`), for "today"
on the dashboard and the reports, for the catering diary, and for the time printed on receipts.
Sri Lanka keeps no daylight saving, so the offset never moves.

It is worth being deliberate about, because the failure is quiet: left on UTC, the day would roll
at 5:30am local, so an evening's takings would be split across two reports and the last sales of
a night would carry tomorrow's order number. `php artisan restro:doctor` reports the timezone in
use, and the server's own clock does not matter — PHP converts.

Changing this on a database that already holds orders reinterprets their stored timestamps rather
than converting them: a sale written at 20:00 UTC begins reading as 20:00 Colombo. Set it before
the restaurant starts trading.

### Sign in

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@restro.test` | `password` |
| Cashier | `cashier@restro.test` | `password` |

**Change both passwords before this goes anywhere near a real restaurant.**

The seed creates the roles, the two accounts above, the restaurant's details and its menu. It
does **not** create tables, because nobody has said how the dining room is laid out.

The menu is the restaurant's own, taken from its price list: 16 categories and 173 items. A dish
sold in two sizes is two items — "Chicken Fried Rice (Small)" and "(Large)" — because an order
line holds one price. Seven pork dishes had no price on the list and are created **switched off**,
so they show in the back office waiting to be priced and cannot reach an order until they are.

Items are only ever created, never overwritten, so once you are trading the back office owns
prices and a seeder re-run during a deploy cannot undo an edit.

For sample tables to develop or demonstrate against:

```bash
php artisan db:seed --class=DemoDataSeeder   # 8 numbered tables
```

### Clearing a system down for a real restaurant

To hand over an install that has been used for testing:

```bash
php artisan restro:clear-data
```

It deletes the menu, tables, orders, customers and any uploaded menu photographs, and keeps the
staff logins, roles, permissions and restaurant settings. It asks before doing anything; add
`--force` to skip the prompt.

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
changes, per-item notes, hold and checkout. The order is created by the first item tapped, not by
opening the screen, so staff can check a price or search the menu from it without leaving an
empty order behind. For a price or "do you have…?" at the counter, the **MENU** bar under the
sale types opens the menu on its own — search, categories, prices and descriptions, read-only.

**Dine in** — Tapping a table opens its running order or starts a new one. The table is occupied
from the first item onwards, stays occupied while the order is open and frees itself when the order is completed or cancelled.
Orders can be moved between tables.

**Phone orders** — Tapping PHONE ORDER goes straight to the order screen, so the food goes down
while the caller is still talking. The mobile number is taken afterwards and is required before
checkout; the name is optional. Pending → Ready → Collected tracks them while they're on the way.

**Food photos** — Menu items take an optional picture, shown on the POS tile. Items without one
still work exactly the same.

**Customer display** — A second screen facing the customer, in the restaurant's own colours. It
shows each item as it is rung up, the running total, the change due during a cash payment, and a
thank-you with the order number afterwards. Between sales it rests on the logo and a welcome
line. See [Setting up the customer display](#setting-up-the-customer-display).

**Checkout** — Cash with live change and quick-tender buttons, or card with an optional
reference. Nothing completes without a payment that covers the total.

**Receipts** — 80mm printable, auto-printed straight after checkout, reprintable from history.
Set compact to save roll: no logo, receipt-sized type, two details to a line, and nothing printed
twice (no subtotal unless a discount or tax sits under it, no unit price on a single item). Each
one prints the kitchen its own copy on the same job: items, quantities, prices and the time, plus
the order number and table so the food can be matched to a bill. No logo, no totals.

**Deleting an order** — An order can be erased outright from its detail page, which is different
from cancelling it: a cancelled order stays on the books as cancelled, while a deleted one leaves
no trace. Its items and payments go with it, and because every figure is aggregated live from
those tables, the money leaves the reports, the dashboard and the customer's history in the same
stroke. There is no undo.

It sits behind its own permission, `delete_orders`, which **a cashier does not get by default** —
erasing sales is the usual route for till theft, so it has to be granted deliberately from the
users screen. Cancel an order instead when you want the record kept.

**Order history** — A sortable, paged table built to stay quick as the years pile up: quick date
ranges, a custom range, filters for type/status/payment, and search by order number, mobile or
customer name. All filtering, sorting and paging happens in the database, so the browser only
ever receives one page. Measured at 0.17 ms per page against a year of orders.

**Reports** — Today, yesterday, this week, last week, this month, last month or a custom range,
each one tap away. Takings, order count, cash vs card, sales by order type, a day-by-day
breakdown, best sellers and slowest movers. Every period can be viewed on screen, printed as a
plain black-and-white sheet, or downloaded as a CSV for a spreadsheet.

**Customers** — Everyone whose mobile number has been taken, gathered automatically from the
orders that met them and filterable by walk in, dine in, phone call or added by hand. Search by
name or number, sort by orders, last visit or total spent, and open anyone to see their history.
Customers can also be added manually. Deleting one keeps their orders.

**Catering** — A month-at-a-glance diary of what the kitchen is booked for. A booking holds the
customer, the number of guests, the venue, the date and time, and a sheet of dishes with a
quantity and a unit each — 5 kg, 250 portions, 30 litres — because one kitchen sends curry out by
the kilo and rice by the portion on the same job. Clicking a day's booking opens it in place;
from there it prints as a kitchen sheet. Prices are optional, so a job can be booked before
anybody has quoted it, and a booking taken with a mobile number is filed against the customer
record. Catering dishes have their own catalogue, kept apart from the menu the till sells.

**Catering catalogue** — The designed artwork customers are shown, as opposed to the working list
of dishes a booking sheet is built from. A category — Weddings, Office lunches — holds as many
**packages** as it needs, each its own artwork with its own name: Weddings → Package 1, Package 2,
Package 3. Uploads are named from the file they arrived as, so a designer sending `Package 2.jpg`
needs no retyping, and each can be renamed and reordered afterwards.

Artworks are added a row at a time — choose the image, give it its title — so a category is
created with its packages already named rather than uploaded first and labelled afterwards. A row
left blank takes its name from the file it arrived as, and a spare row nobody used is ignored.

Each artwork is then managed on its own: rename it, move it, or upload a revised image in its
place —
a corrected Package 2 is still Package 2, so it keeps its name and its position and the file it
replaced is deleted rather than left on disk.

Published at `/catalogue`, with each category at `/catalogue/its-name` and every package linkable
on its own. Tapping an artwork opens it full screen, since these are designed sheets meant to be
read on a phone — an address that can be sent to somebody enquiring or put behind a QR code. The
address is fixed when the category is created and survives a rename, because it may already be
printed on something. A category can be hidden without losing its artwork, and one with no
artwork stays off the public page.

**Back office** — Menu items and categories, tables, staff accounts and roles, and restaurant
settings.

**Roles** — Admin and Cashier out of the box. Access is decided by permission, never by role
name, so new roles are a settings change rather than a code change.

Dark mode is included and remembered per terminal.

---

## The public site

The domain root is the restaurant's own page — hero, what the business does, the dining room,
a live menu, catering and events, and contact details. **Staff go to `/pos`** — the public pages
carry no link to it — and everything else still requires signing in.

The menu section reads the same categories and items the till sells from, so the website cannot
drift out of date: change a price in the POS and the public page changes with it. A category with
no active items is skipped, and the whole section disappears if the menu is empty.

Photographs live in `public/images/` — `storefront.jpg`, `interior.jpg` and `logo.png`. Replace
those files to change the imagery; nothing else needs editing. Contact details, social links and
the section list sit in one block at the top of `resources/views/site/landing.blade.php`.

### The QR menu

`/menu` is the full menu as a public, read-only page — the one to put behind a QR code on the
table. It lists every active category and item with its price, description and, where the back
office has one, the item's photograph. There is nothing on it to press: it tells a customer what
is on and what it costs, and that is all.

Because it reads the same items the till sells, a price changed in the back office reaches the
table without anybody reprinting a card. Items switched off disappear, and a category with
nothing sellable in it is skipped entirely.

Point your QR code at `https://your-domain/menu`.

The catering brochure sits alongside it at `/catalogue`, with each package at
`/catalogue/{name}` — managed from **Catering catalogue** in the back office.

Item photographs are served at the size they were uploaded, and are lazy-loaded so a phone only
fetches what is scrolled into view. If the menu grows long with large photographs, generating
thumbnails on upload is the next step.

## Deploying to shared hosting

**The compiled assets are committed on purpose.** `public/build` is normally gitignored in a
Laravel project, because you build on the server or in CI. This restaurant runs on shared hosting
with no Node, so the CSS, JS and fonts ship with the repository and the server needs nothing but
PHP and MySQL.

The trade this makes: **anything changed under `resources/` needs a rebuild committed alongside
it.** Change a Blade file only and nothing is needed; change CSS or JS and the deployed site keeps
serving the old bundle until you do:

```bash
npm run build
git add public/build && git commit -m "Rebuild assets"
```

Vite empties the folder each build, so old hashed files are removed by the same commit.

**If a deploy seems to have changed nothing**, run the check — every way a deploy silently fails
to take effect looks identical from the outside:

```bash
php artisan restro:doctor
```

It reports pending migrations, permissions the code checks but the database lacks (which hide
whole sections of the menu without erroring), compiled views and cached config left over from the
previous deployment, the built assets, the storage link and `APP_DEBUG` — and prints the commands
to fix whatever it found.

On the server, after pulling:

```bash
php artisan migrate --force
php artisan view:clear        # compiled Blade caches URLs; always clear it
php artisan config:clear
php artisan permission:cache-reset
```

**New permissions arrive through migrations on purpose.** Adding a capability means adding it to
`App\Support\Permissions` and seeding it — easy to forget on a server where deploying is "pull,
then migrate". The symptom is confusing: the screen exists and its route works, but the menu item
is invisible because nobody holds the permission that reveals it. So a migration syncs them, and
the `migrate --force` above cannot miss it. It only ever adds: a permission granted to your
cashiers by hand is never revoked.

**Menu photos need the storage symlink.** `php artisan storage:link` over SSH is easiest. If the
host gives you no shell, create it from a one-off PHP file in `public/`, then delete the file:

```php
<?php symlink(__DIR__.'/../storage/app/public', __DIR__.'/storage');
```

Set `APP_URL` to the real address and `APP_DEBUG=false` before going live.

## Setting up the customer display

The customer display is a second browser window on the **same computer**, driven by the
cashier's window over a `BroadcastChannel`. No server, no polling, nothing extra to run.

**The screens must be extended, not mirrored.** On macOS, System Settings → Displays, with
*Mirror Displays* switched off. Mirrored screens show identical pixels, so the customer would be
looking at the cashier's own screen.

**Day to day:** tap **Display** in the POS header once at the start of the shift. In Chrome or
Edge the browser will ask permission to manage windows the first time; allow it, and from then on
the window opens on the second monitor by itself. The dot on the button turns green while the
display is running. Tap the display once to clear the browser chrome.

The window stays put while the cashier moves between POS screens, so it is opened once a day,
not once an order.

### Making it fullscreen with no tap at all

Browsers refuse to enlarge a window without a gesture *inside that window*, and nobody touches
the customer's screen. The POS asks Chrome to open the display fullscreen and also requests it
on the window's behalf, which Chrome usually honours — but when it declines, the display asks
for one tap and stays fullscreen from then on.

**To remove even that tap, launch the display from a desktop shortcut instead of the button.**
This is what fixed terminals normally do, and it survives reboots:

```bash
open -na "Google Chrome" --args \
  --app=https://your-domain.example/pos/display \
  --window-position=1920,0 \
  --kiosk
```

Save it as a `.command` file on the desktop, `chmod +x` it, and double-click it when the
terminal boots. `--kiosk` gives a chromeless fullscreen window with no address bar and no F11.
Set `--window-position` to the second screen's top-left corner — `1920,0` if the primary screen
is 1920 wide and the second sits to its right.

The display still needs a signed-in session in that Chrome profile, so sign in to the POS once
on the terminal beforehand.

**Browser support:** automatic placement needs Chrome or Edge — Safari has no Window Management
API, so there the window opens on the primary screen and has to be moved once, or launched from
the shortcut above. Everything else works in any current browser.

**The logo** lives at `public/images/logo.png`. Replace that file to rebrand the display; nothing
else needs changing.

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
