# Restro POS — Phases & Build Plan

A small restaurant POS built on Laravel 13 / PHP 8.4 / MySQL 9 / Blade / Tailwind 4 / Alpine 3,
with Spatie Laravel Permission for roles.

The goal is a POS a first-time cashier can use without training, on a codebase another
developer can extend without archaeology. It is deliberately **not** a restaurant ERP.

---

## Status

All twelve phases below are **built, migrated, seeded and tested**. The suite is 51 tests /
193 assertions, run against MySQL (not SQLite) because one business rule is enforced by a
MySQL-only schema feature.

```
php artisan test     # 51 passed
./vendor/bin/pint    # clean
npm run build        # clean
```

---

## Phase map

Each phase left the application working and runnable. Later phases only added to earlier ones.

### Phase 1 — Authentication, users, roles, permissions

| What | Where |
| --- | --- |
| Login / logout (no public registration — admins create staff) | `app/Http/Controllers/Auth/AuthenticatedSessionController.php` |
| Rate-limited credential check, rejects deactivated staff | `app/Http/Requests/Auth/LoginRequest.php` |
| Deactivation ends any open session on the next request | `app/Http/Middleware/EnsureUserIsActive.php` |
| The 13 permissions, in one place | `app/Support/Permissions.php` |
| Admin + Cashier roles, idempotent seeding | `database/seeders/RolesAndPermissionsSeeder.php` |
| Order-level authorization | `app/Policies/OrderPolicy.php` |

Routes are gated by `permission:<name>`, never by role name, so a restaurant can invent
its own roles without a code change. Admin holds every permission explicitly rather than
through an "is admin" shortcut, which keeps admin access auditable.

### Phase 2 — Categories and menu items

`Admin\CategoryController`, `Admin\MenuItemController`, `CategoryRequest`, `MenuItemRequest`.

Disabled items and items in disabled categories never reach the POS (`MenuItem::scopeSellable`).
Deleting an item that already appears on an order disables it instead, so reporting history
survives.

Each item takes an **optional photo** (JPG/PNG/WebP, up to 4MB) stored on the public disk and
shown on its POS tile. `MenuItemImageService` owns storing, replacing and deleting the file so
no upload is ever orphaned. Items without a photo stay the same height in the grid, so a
half-photographed menu still lines up.

### Phase 3 — Tables

`Admin\RestaurantTableController`. The model is `RestaurantTable` (table `tables`) so the
class never reads like a database table in code.

**Occupancy is not stored.** A table is occupied exactly when it has an open dine-in order
(`RestaurantTable::activeOrder`). Nothing can drift out of sync because there is only one fact.

### Phase 4 — POS order screen

`Pos\OrderScreenController` + `resources/views/pos/order.blade.php` + `resources/js/pos-order.js`.

Categories and items on the left, the running order on the right. Every tap posts to the
server and the server returns the recalculated order; **there is no client-side price
arithmetic anywhere**. On phones the order panel collapses to a summary bar.

### Phase 5 — Dine-in orders

Tapping a table either opens its running order or starts a new one, so a cashier never has to
think about which of the two they are doing (`PosController::selectTable`).
Moving an order between tables is supported; reservations are not (out of scope).

### Phase 6 — Takeaway (walk-in)

One button, no customer details, straight to the order screen.

### Phase 7 — Phone takeaway

Tapping PHONE ORDER opens an empty order and goes straight to the order screen, because on a
real call the customer reads out their food long before they give a number. Contact details are
captured afterwards from a panel in the order pane: **mobile number required, name optional**,
plus an optional note. Details are stored on the order itself — no customer accounts, no CRM.

A phone order additionally tracks **Pending → Ready → Collected**.

### Phase 8 — Checkout and payments

`Pos\CheckoutController` + `app/Services/CheckoutService.php`.

Cash shows change live as the cashier types, with quick-tender buttons. Card takes an optional
reference. The whole thing — recalculate, validate, record payment, complete the order — runs
inside one transaction with the order row locked.

### Phase 9 — Receipts

`resources/views/orders/receipt.blade.php`. 80mm print stylesheet, auto-prints when reached
straight from checkout, reprintable from history at any time.

### Phase 10 — Order history

`OrderHistoryController`, `OrderHistoryFilters`, `OrderHistoryQuery`.

Built for a table that keeps growing — 100 orders a day is 36,000 rows after a year and 180,000
after five. A sortable, paged table with quick date ranges (today / yesterday / 7 days / this
month / all time), a custom range, filters for type, status and payment, and a search over
order number, mobile and customer name. Page size is 25/50/100.

Everything is done in the database; the browser only ever receives one page. See
**"Order history is written for five years of rows"** below for the specifics.

### Phase 11 — Dashboard and reports

`DashboardController`, `ReportController`, `app/Services/ReportService.php`.

Today's sales, order count, cash vs card, sales by order type, best sellers and slowest movers.
Only completed orders count as sales. Takings are hidden from staff without `view_reports`,
while the "what's open right now" panels stay visible to everyone.

**Daily, weekly and monthly in one tap.** `ReportPeriod` holds the presets — today, yesterday,
this week, last week, this month, last month, plus a custom range — and the same object feeds
all three shapes of the report, so the heading and the figures can never disagree:

| shape | route | for |
| --- | --- | --- |
| screen | `reports.index` | glancing at it, with bars and a day-by-day table |
| plain sheet | `reports.print` | printing, filing, handing to whoever does the books |
| CSV | `reports.download` | opening in a spreadsheet |

Over more than one day the report gains a **day-by-day breakdown**, including days with no
sales as zero rows — a week's total tells you nothing about which days carried it.

Two details in the CSV worth keeping: amounts are written as bare numbers with no currency
symbol or thousands separator so a spreadsheet can sum them, and any cell beginning `=`, `+`,
`-` or `@` is prefixed with an apostrophe. Menu item names are typed by staff, and Excel runs a
leading `=` as a formula.

### Phase 13 — Customer display

`Pos\CustomerDisplayController`, `resources/views/pos/display.blade.php`,
`resources/js/display-channel.js`, `resources/js/customer-display.js`.

A second screen facing the customer, in the restaurant's own colours — sampled from its logo,
which is the only place in the app that departs from the POS greys, because it is the only
screen the public sees. Four states: idle (logo and welcome), building (each line as it is rung
up, total pinned so it never scrolls away), paying (total, and the change in the largest type on
the screen), done (thank you, and the number to wait for on a takeaway).

**It is a mirror, not a second terminal.** No controls, so it cannot be put into a state nobody
asked for. It carries no order data in its own HTML either — a screen left running overnight
cannot leak the last customer's name.

**How the screens stay in sync.** The cashier's window already holds the order exactly as the
server last returned it, so it broadcasts that same payload over a `BroadcastChannel` — same
browser, same terminal, no server round trip, no websocket daemon to supervise. The display can
never show a total the server did not calculate. A display opened or refreshed mid-order asks
for the current state and catches up; if it stops answering its heartbeat, the POS button stops
claiming it is connected.

Polling was rejected as it needs a terminal concept that does not exist and lags visibly;
websockets as a daemon to run and restart for a screen sitting 40cm from the one driving it.

**Placement.** Chrome and Edge expose the physical screen layout, so the window opens on the
second monitor at its exact bounds with no dragging. The window carries a fixed name, so moving
between POS screens reuses it rather than opening another.

### Phase 12 — Settings and UI polish

`Admin\SettingController` + `app/Services/SettingsService.php` (cached key/value store).
Restaurant name, address, phone, currency symbol, tax percentage, receipt footer.

---

## Design decisions worth knowing

**Order status vs fulfillment status.** The spec lists Open/Completed/Cancelled and then
"phone orders can additionally use Pending/Ready/Collected". Merging all six into one column
would make "is this order still open?" ambiguous — a Ready order is also open. So there are
two enums: `OrderStatus` (the lifecycle, all order types) and `FulfillmentStatus` (the
kitchen→counter journey, phone orders only, null elsewhere).

**A table cannot hold two active dine-in orders — enforced by the database.**
`orders.active_table_id` is a MySQL *stored generated column*:

```sql
CASE WHEN status = 'open' AND type = 'dine_in' THEN table_id ELSE NULL END
```

with a unique index on it. The service layer checks first under a row lock and raises a
friendly "Table 3 already has an open order", but if two cashiers tap the same table in the
same instant, the database itself refuses the second one. Two consequences to know about:
MySQL forbids cascading foreign key actions on `table_id` (a generated column depends on it),
and the tests therefore run on MySQL rather than SQLite.

**Phone orders are validated at checkout, not at creation.** The spec asks that a phone order
store the customer's name and mobile number. Requiring both up front fights the actual phone
call, so the order opens empty and the rule moves to the point where it matters: an order
cannot be *completed* without a mobile number (`CheckoutService`, `OrderPolicy::checkout`, and
`Order::needsCustomerPhone()` which greys out the checkout button). The name is optional
throughout — plenty of callers never give one, and the number is what the counter uses to find
them anyway.

**Order history is written for five years of rows.** Measured on 36,600 orders — one year at
100 a day — on the test database:

| query | before | after |
| --- | --- | --- |
| filter an older day | 9.37 ms (full table scan) | 0.17 ms |
| the `COUNT(*)` the pager runs | 4.22 ms (full table scan) | 0.14 ms |
| status + date range | 13.12 ms | 1.96 ms |
| search by number or mobile | 1.31 ms | 0.12 ms |

Four things get it there, and each one is easy to undo by accident:

1. **Never wrap `created_at` in a function.** `whereDate('created_at', today())` compiles to
   `CAST(created_at AS DATE) = ?`, which no index can serve — MySQL's own plan said
   `Table scan on orders (cost=3727 rows=36387)`. Every date filter is a `>=` / `<=` range on
   the bare column. There is a test asserting the generated SQL contains no `date(` or `cast(`.
2. **Composite indexes matching the real query shape** — `(status, created_at)` and
   `(payment_status, created_at)` alongside the existing `(type, created_at)`. Every history
   query is "a window of time, optionally narrowed by one facet, newest first".
3. **Anchored search.** A leading `%wildcard%` cannot use an index, so search matches the start
   of order number, mobile and customer name. The one exception is a short numeric term, treated
   as the daily sequence (`42` → `…-0042`); that one scans, but only inside the selected dates.
4. **A bounded default.** The screen opens on today rather than all time, so the deep-offset
   case (`LIMIT 25 OFFSET 20000`, 20 ms and rising) never comes up in normal use.

Sort column, direction and page size come off the query string and are matched against
whitelists in `OrderHistoryFilters`, never interpolated. Sorting also carries an `id`
tie-breaker, without which two orders sharing a timestamp can swap places between pages and
appear twice or not at all.

**Prices are snapshots.** `order_items` copies `name` and `unit_price` at the moment the item
is added. Repricing or renaming the menu later never rewrites history, and deleting a menu item
leaves the line intact (`menu_item_id` goes null, kept only for reporting).

**Business rule refusals are not errors.** `PosOperationException` carries expected,
explainable refusals ("that table is busy", "this order is completed"). A handler in
`bootstrap/app.php` renders them as a message on the screen the cashier was already on —
never a stack trace, never a 500.

**Money.** `decimal(12,2)` throughout, formatted in exactly one place
(`SettingsService::formatMoney`, exposed to Blade as `money()`). Tax is a single restaurant-wide
percentage applied after the discount — as complicated as the MVP gets.

---

## Deliberately not built

Kitchen display, inventory, ingredients, suppliers, purchasing, delivery, online ordering,
reservations, loyalty, customer accounts, accounting, payroll, attendance, multi-branch,
advanced analytics, complex tax, complex discount engines.

---

## How the next features fit in

The schema and services were shaped so these land as additions, not rewrites:

| Feature | The seam it uses |
| --- | --- |
| **Kitchen display** | `FulfillmentStatus` already exists; widen it past phone orders and add a screen that polls open orders. |
| **Inventory** | `order_items` already records what left the kitchen; hang stock movements off it. |
| **Customers** | Phone orders hold `customer_name` / `customer_phone` inline. Introduce a `customers` table and backfill from those two columns. |
| **Multiple branches** | Add `branch_id` to `orders`, `tables`, `menu_items`, and a global scope. Order numbers are already generated per day by a single service (`OrderNumberGenerator`) — give it the branch prefix. |
| **Delivery** | A new `OrderType` case. `usesTable()` / `requiresCustomer()` on the enum already drive the screens, so most of the UI follows automatically. |
| **More payment methods** | Add a `PaymentMethod` case and decide `requiresTendered()`. The checkout screen and the reports build themselves from the enum cases. |
| **New roles** | Create the role, tick permissions. No code change — nothing checks role names. |
| **Reservations** | New table referencing `tables`; occupancy is derived, not stored, so nothing existing needs to change. |

---

## Architecture conventions

- Business logic lives in `app/Services`, never in controllers or Blade.
- Validation lives in Form Requests; authorization in policies and `permission:` middleware.
- Order creation and checkout run in database transactions with row locks.
- Enums for order type, order status, fulfillment status, payment method and payment status.
- Every schema change is a migration; every schema has foreign keys and indexes on what is searched.
- Seeders are idempotent, so `db:seed` can be re-run safely.
