<div align="center">

<img src="./public/logo.png" width="300" alt="Brread! Ice cream sandwich, Singapore style" />

### Your Bread. Your Scoop. Your Happiness.

Build-your-own ice cream sandwiches in the Singapore street-cart style, made in Jakarta,<br />with the staff portal that runs the shop behind them.

<br />

<img src="./docs/readme/badge-laravel.svg" height="28" alt="Laravel 13" />
<img src="./docs/readme/badge-php.svg" height="28" alt="PHP 8.3" />
<img src="./docs/readme/badge-tailwind.svg" height="28" alt="Tailwind CSS 4" />
<img src="./docs/readme/badge-alpine.svg" height="28" alt="Alpine.js 3" />
<img src="./docs/readme/badge-sqlite.svg" height="28" alt="SQLite default" />
<img src="./docs/readme/badge-docker.svg" height="28" alt="Docker ready" />

<br /><br />

<img src="./docs/readme/strip.png" width="100%" alt="Four Brread! sandwiches: vanilla on white bread, strawberry with rainbow sprinkles, chocolate on wheat bread, and vanilla with chocolate sauce on wheat bread" />

</div>

## What it is

Brread! sells ice cream sandwiches: two slices of soft bread around a slab of ice cream, the way an Uncle cart does it on Orchard Road. Customers design each sandwich in a live builder and order it from the storefront.

The same app contains the back office. Staff sign in to a portal that tracks ingredient stock, employees, cash flow and sales, and every order a customer places feeds all four.

### From bread to doorstep

<table>
<tr>
<td width="33%" valign="top">
<img src="./docs/readme/step-1.png" width="100%" alt="White and whole wheat bread slices stacked beside stalks of wheat" /><br />
<b>Pick your bread</b><br />
Plain white for the classic soft bite, or whole wheat for something heartier.
</td>
<td width="33%" valign="top">
<img src="./docs/readme/step-2.png" width="100%" alt="Slabs of vanilla, strawberry and chocolate ice cream, whole and sliced" /><br />
<b>Slice your ice cream</b><br />
Vanilla, strawberry or chocolate, hand-sliced from a block.
</td>
<td width="33%" valign="top">
<img src="./docs/readme/step-3.png" width="100%" alt="A finished sandwich with peanuts next to a pink insulated delivery bag" /><br />
<b>Pack it cold</b><br />
Every order goes into insulated foil for delivery across Jakarta.
</td>
</tr>
</table>

## Build it layer by layer

<img src="./docs/readme/layers-to-sandwich.png" width="100%" alt="Three ingredient layers, white bread, strawberry ice cream with sprinkles and white bread, turning into a finished sandwich held in a hand" />

The builder stacks a photo of every ingredient you pick and shows the result as you go. **Exploded View** pulls the layers apart, and the signature combos fill a whole sandwich in one click.

<table>
<tr><th width="33%">Bread</th><th width="33%">Ice cream</th><th width="33%">Toppings</th></tr>
<tr>
<td valign="top" align="center"><img src="./docs/readme/menu-white-bread.png" width="100%" alt="Plain White Bread" /><br /><b>Plain White Bread</b><br />Rp 6,000</td>
<td valign="top" align="center"><img src="./docs/readme/menu-vanilla.png" width="100%" alt="Vanilla" /><br /><b>Vanilla</b><br />Rp 8,000</td>
<td valign="top" align="center"><img src="./docs/readme/menu-chocolate-sauce.png" width="100%" alt="Chocolate Sauce" /><br /><b>Chocolate Sauce</b><br />Rp 3,000</td>
</tr>
<tr>
<td valign="top" align="center"><img src="./docs/readme/menu-wheat-bread.png" width="100%" alt="Whole Wheat Bread" /><br /><b>Whole Wheat Bread</b><br />Rp 7,000</td>
<td valign="top" align="center"><img src="./docs/readme/menu-strawberry.png" width="100%" alt="Strawberry" /><br /><b>Strawberry</b><br />Rp 8,000</td>
<td valign="top" align="center"><img src="./docs/readme/menu-sprinkles.png" width="100%" alt="Rainbow Sprinkles" /><br /><b>Rainbow Sprinkles</b><br />Rp 3,000</td>
</tr>
<tr>
<td valign="middle" align="center"><b>24 combinations</b><br />2 breads × 3 flavors × 4 topping choices (none, either one, or both)</td>
<td valign="top" align="center"><img src="./docs/readme/menu-chocolate.png" width="100%" alt="Chocolate" /><br /><b>Chocolate</b><br />Rp 8,000</td>
<td valign="middle" align="center"><b>Rp 14,000 to Rp 21,000</b><br />per sandwich at the seeded prices</td>
</tr>
</table>

A sandwich takes exactly one bread and one ice cream, and each topping at most once.

## Features

### Storefront

<table>
<tr>
<td width="50%" valign="top">
<img src="./docs/readme/card-studio.svg" width="100%" alt="A sandwich preview stacked from layers beside bread, flavor and topping pickers" /><br />
<img src="./docs/readme/icon-studio.svg" width="22" height="22" align="absmiddle" alt="" />&nbsp; <b>Brread! Studio</b><br />
Pick a base, a flavor and toppings, and the preview updates live. Ingredients unlock in order, so you can't build an invalid sandwich.
</td>
<td width="50%" valign="top">
<img src="./docs/readme/card-cart.svg" width="100%" alt="A cart drawer with two sandwiches, quantity pickers and a total" /><br />
<img src="./docs/readme/icon-cart.svg" width="22" height="22" align="absmiddle" alt="" />&nbsp; <b>Cart and checkout</b><br />
The cart survives a page refresh. An order records the customer's name and phone number, and the server charges current prices even if the cart is older.
</td>
</tr>
</table>

Four public pages: home (`/`), the builder (`/builder`), the full menu (`/menu`) and the shop's contact details (`/contact`).

### Staff portal

<table>
<tr>
<td width="50%" valign="top">
<img src="./docs/readme/card-mrp.svg" width="100%" alt="Stock levels per ingredient with a reorder line and one low-stock alert" /><br />
<img src="./docs/readme/icon-mrp.svg" width="22" height="22" align="absmiddle" alt="" />&nbsp; <b>MRP, ingredient stock</b><br />
Stock levels, reorder points and low-stock alerts for every ingredient. Restock or correct a count with a note. A restock cost becomes an expense in Accounting, and every change is kept in a movement history.
</td>
<td width="50%" valign="top">
<img src="./docs/readme/card-hr.svg" width="100%" alt="Six employees, three on duty, and a rotation of three-day shifts" /><br />
<img src="./docs/readme/icon-hr.svg" width="22" height="22" align="absmiddle" alt="" />&nbsp; <b>HR, people and duty rotation</b><br />
Add, edit and deactivate employees. A fixed rotation puts 3 active employees on duty for 3 days at a time and wraps around the list, so adding or reordering staff reshapes the schedule immediately.
</td>
</tr>
<tr>
<td width="50%" valign="top">
<img src="./docs/readme/card-accounting.svg" width="100%" alt="A ledger of money in and money out with a closing balance" /><br />
<img src="./docs/readme/icon-accounting.svg" width="22" height="22" align="absmiddle" alt="" />&nbsp; <b>Accounting, cash flow</b><br />
Income arrives from orders, and staff log expenses by category. Choose a date range to see the opening balance, income, expenses and closing balance, then export the report and ledger to Excel.
</td>
<td width="50%" valign="top">
<img src="./docs/readme/card-sales.svg" width="100%" alt="A weekly bar chart and a ranking of the best sellers" /><br />
<img src="./docs/readme/icon-sales.svg" width="22" height="22" align="absmiddle" alt="" />&nbsp; <b>Sales, what sells</b><br />
Best-selling combinations, rankings for breads, flavors and toppings (including the ones that never sold), trends by day, week or month against the previous period, and the busiest days and hours. Admin only.
</td>
</tr>
</table>

## What happens at checkout

<img src="./docs/readme/checkout-flow.svg" width="100%" alt="Checkout in six steps: check out, lock rows, check the cart, save the order, deduct stock, update reports" />

The ingredient rows stay locked for the whole database transaction, so two customers racing for the last unit can't both get it. If an ingredient runs short, the customer sees how many are left. The order endpoint accepts 30 requests per minute per IP address, and the login forms accept 10 attempts per minute per username and IP address.

## Staff manual

### Sign in

| Portal | Address | Accounts |
|---|---|---|
| Staff | `/staff` | `staffmrp` (MRP), `staffhr` (HR), `staffacc` (Accounting) |
| Admin | `/admin` | `admin` |

Staff and admins use separate login pages, and an account only works on its own page. The seeded staff passwords are in [`docs/STAFF_LOGIN_GUIDE.md`](./docs/STAFF_LOGIN_GUIDE.md), and the admin password comes from the maintainer.

On `/admin`, a one-click admin login appears when `APP_ENV=local`. It stays off everywhere else unless someone sets `ADMIN_QUICK_LOGIN=true` on purpose, because anyone who can open the page would become an admin.

### What each role can open

| | <img src="./docs/readme/icon-mrp.svg" width="16" height="16" align="absmiddle" alt="" />&nbsp; MRP | <img src="./docs/readme/icon-hr.svg" width="16" height="16" align="absmiddle" alt="" />&nbsp; HR | <img src="./docs/readme/icon-accounting.svg" width="16" height="16" align="absmiddle" alt="" />&nbsp; Accounting | <img src="./docs/readme/icon-sales.svg" width="16" height="16" align="absmiddle" alt="" />&nbsp; Sales |
|---|:---:|:---:|:---:|:---:|
| <img src="./docs/readme/icon-access.svg" width="16" height="16" align="absmiddle" alt="" />&nbsp; **Admin** | <img src="./docs/readme/icon-check.svg" width="18" height="18" alt="Yes" /> | <img src="./docs/readme/icon-check.svg" width="18" height="18" alt="Yes" /> | <img src="./docs/readme/icon-check.svg" width="18" height="18" alt="Yes" /> | <img src="./docs/readme/icon-check.svg" width="18" height="18" alt="Yes" /> |
| **MRP staff** | <img src="./docs/readme/icon-check.svg" width="18" height="18" alt="Yes" /> | | | |
| **HR staff** | | <img src="./docs/readme/icon-check.svg" width="18" height="18" alt="Yes" /> | | |
| **Accounting staff** | | | <img src="./docs/readme/icon-check.svg" width="18" height="18" alt="Yes" /> | |

The sidebar lists only the modules your role can open, and the URL of any other module returns a 403.

### Modules

<details>
<summary><b>MRP</b>: ingredient stock at <code>/staff/mrp</code></summary>

<br />

- The dashboard shows each ingredient's stock level, category, reorder point and status.
- A low-stock alert appears when an ingredient reaches its minimum.
- To restock or correct a count, adjust the stock and write a note. If you enter a restock cost, it is booked as an expense in Accounting.
- The full history of stock movements is at `/staff/mrp/movements`.
- Customer orders deduct stock automatically at checkout, so you never subtract those by hand.

</details>

<details>
<summary><b>HR</b>: employees and duty rotation at <code>/staff/hr</code></summary>

<br />

- Add, edit and deactivate employees from the directory.
- The duty rotation puts 3 active employees on shift for 3 days at a time. It follows the rotation order, so deactivating or reordering someone changes the schedule right away.

</details>

<details>
<summary><b>Accounting</b>: cash flow at <code>/staff/accounting</code></summary>

<br />

- Customer orders appear as money in, and the expenses you log appear as money out.
- To log an expense, pick a category: Ingredients, Utilities, Salaries, Equipment, Rent or Other. You can delete an expense you entered by mistake.
- Choose a date range to see the opening balance, income, expenses, closing balance and expenses by category.
- Export the report and ledger to an `.xlsx` file.

</details>

<details>
<summary><b>Sales</b>: what sells at <code>/staff/sales</code> (admin only)</summary>

<br />

- Best-selling combinations of flavor, bread and topping.
- Rankings for flavors, breads and toppings, including the ones that haven't sold yet.
- Sandwiches sold and revenue per day, week or month, compared with the previous period.
- The busiest days and hours.

</details>

## Tech stack

<b>Backend</b><br />
<img src="./docs/readme/chip-laravel-13.svg" height="32" alt="Laravel 13" /> <img src="./docs/readme/chip-php-83.svg" height="32" alt="PHP 8.3" /> <img src="./docs/readme/chip-sanctum.svg" height="32" alt="Laravel Sanctum" /> <img src="./docs/readme/chip-phpspreadsheet.svg" height="32" alt="PhpSpreadsheet" />

<b>Frontend</b><br />
<img src="./docs/readme/chip-blade.svg" height="32" alt="Blade" /> <img src="./docs/readme/chip-tailwind-css-4.svg" height="32" alt="Tailwind CSS 4" /> <img src="./docs/readme/chip-alpinejs.svg" height="32" alt="Alpine.js" /> <img src="./docs/readme/chip-axios.svg" height="32" alt="Axios" /> <img src="./docs/readme/chip-vite.svg" height="32" alt="Vite" />

<b>Data</b><br />
<img src="./docs/readme/chip-sqlite.svg" height="32" alt="SQLite" /> <img src="./docs/readme/chip-database-sessions.svg" height="32" alt="Database sessions" /> <img src="./docs/readme/chip-database-queue.svg" height="32" alt="Database queue" />

<b>Quality</b><br />
<img src="./docs/readme/chip-phpunit.svg" height="32" alt="PHPUnit" /> <img src="./docs/readme/chip-laravel-pint.svg" height="32" alt="Laravel Pint" />

<b>Deploy</b><br />
<img src="./docs/readme/chip-docker.svg" height="32" alt="Docker" />

The Dockerfile builds the assets, migrates, seeds and serves the app.

## Run it locally

You need PHP 8.3 with the `sqlite`, `zip` and `gd` extensions, Composer 2 and Node.js 22.

```bash
git clone https://github.com/vassellenha/KWU-BRREAD.git
cd KWU-BRREAD

composer install
npm install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed
npm run build

php artisan serve
```

| | |
|---|---|
| Storefront | http://127.0.0.1:8000 |
| Staff portal | http://127.0.0.1:8000/staff |
| Admin portal | http://127.0.0.1:8000/admin |

While you work on the frontend, run `npm run dev` in a second terminal instead of `npm run build`.

## Run it with Docker

```bash
docker build -t brread .
docker run -p 8080:8080 -e APP_KEY="base64:$(openssl rand -base64 32)" brread
```

Open http://localhost:8080. The container migrates and seeds the database every time it starts, and it listens on the port in the `PORT` variable (8080 by default). The data lives in a SQLite file inside the container, so it resets when you replace the container.

## Tests

```bash
php artisan test
```

The feature tests cover both login portals, role access, ordering with stock deduction, and the MRP, HR, accounting and sales modules.

## Project layout

```text
app/
  Http/Controllers/Api/      public API: components and orders
  Http/Controllers/Staff/    login and the MRP, HR, accounting and sales modules
  Models/                    Component, Order, OrderItem, StockMovement, Employee, Expense, User
  Services/                  CashflowExporter, SalesReport, ShiftScheduler
database/                    migrations and seeders
resources/views/             storefront pages, staff portal, shared partials
routes/                      web.php and api.php
docs/                        staff guide
tests/                       feature and unit tests
```

## Author

Built by [Vassell Enha Atthayya](https://github.com/vassellenha).
