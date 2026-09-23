# Local development

This foundation uses PHP 8.4+, Composer 2, Node LTS and MySQL 8+.
PHP 8.4 meets the project's PHP 8.3+ requirement; the dependency lock was
resolved on PHP 8.4 and must be used with that runtime or newer.
XAMPP's existing PHP 8.2 and MariaDB 10.4 do not meet the locked project stack.
Use the project-local PHP and Node tools when present; XAMPP is unchanged.

## Create your databases on Windows

**This workstation already has MySQL96 running on port 3306.** Use
`DB_PORT=3306` in its local `.env` and keep XAMPP's database service stopped.
Set `DB_PASSWORD` to the password assigned to `mfbms_app`; the root password
is only for administration. A blank application password causes error 1045
when that account requires a password. The fresh-install instructions below
use port 3307 only when installing a separate server alongside XAMPP.

1. Download **MySQL Community Server 8.4 LTS, Windows MSI** from
   <https://dev.mysql.com/downloads/mysql/>. Follow the official Windows guide:
   <https://dev.mysql.com/doc/refman/8.4/en/windows-installation.html>.
2. Install the server and run MySQL Configurator. Choose development use,
   TCP/IP port **3307** (to coexist with XAMPP), and a Windows service. For local
   development, do not open the port to other computers. Set a strong root
   password and save it privately.
3. Open the MySQL command line client, sign in as root, and copy the commands
   from `database/setup-local.sql`. Replace the two password placeholders with
   different strong passwords before executing. Do not save real passwords in
   the tracked SQL file. This creates `mfbms` and `mfbms_testing`, each with its
   own restricted database user. Run user creation only once.
4. Put the development account password into your ignored `.env` file. Copy
   `.env.testing.example` to `.env.testing` and put the test account password
   there. Never use the development database for automated database tests.
   Generate its key with `php artisan key:generate --env=testing`.
5. Run the connection check and migrations below. Do not run migrations against
   an existing unrelated database.

## Project tools

In PowerShell, from the project root:

```powershell
$taskRoot = (Get-Location).Path
$taskNode = Get-ChildItem .tools/node -Directory | Select-Object -First 1
$env:Path = "$taskRoot\.tools\php;$($taskNode.FullName);$env:Path"
php artisan key:generate
php artisan app:check-database
php artisan migrate
php artisan test
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open <http://127.0.0.1:8000>. The development server uses the supported local
PHP runtime. For Apache, configure a dedicated virtual host whose document root
is this project's `public` directory and use PHP 8.4+. The root `.htaccess`
intentionally denies access to the project directory itself.

Once dependencies and assets are installed, double-click `scripts/serve.cmd`
to start the local server without changing PowerShell's execution policy.

If your PowerShell policy allows local scripts, `. .\scripts\dev-env.ps1` is a
shortcut for the tool-path setup and also defines a `composer` command. Otherwise
use `php .tools/composer.phar` in place of `composer`. Use `npm.cmd` on Windows
when PowerShell blocks npm's script shim. No machine-wide PATH changes are needed.

Fresh checkouts with globally installed tools:

```powershell
Copy-Item .env.example .env
composer install
npm ci
php artisan key:generate
npm run build
```

The font files are bundled locally using Fontsource. No Google Fonts request
is needed at runtime. Alpine is loaded once by Livewire.

## Verification

```powershell
php artisan test
php vendor/bin/pint --test
composer validate --strict
composer audit
npm run build
```

The default tests use isolated in-memory SQLite for foundation, authentication
and permission checks. They do not certify MySQL row locking. With `.env.testing` set up,
run `php vendor/bin/phpunit --configuration phpunit.mysql.xml` for the dedicated MySQL
suite. That suite rejects any database other than `mfbms_testing` before migrations.

The database queue uses the scaffold's jobs tables after migration. Start a
worker with `php artisan queue:work` when jobs are introduced. Logging uses daily
files with 14-day development retention. Production infrastructure and backup
policy remain separate deployment work.

## Staff sign-in (Phase 2)

After configuring your development database, run these commands from the project root:

```powershell
& '.tools/php/php.exe' artisan migrate
& '.tools/php/php.exe' artisan db:seed --class=RolePermissionSeeder
& '.tools/php/php.exe' artisan app:create-administrator
```

The last command asks for your name, email and password twice. Password entry is
hidden. Use a unique password of at least 10 characters with letters and numbers.
There is no default account and no public registration. Existing accounts are
never overwritten by this command. Open <http://127.0.0.1:8000/login> and sign in
with the email and password you chose. Use **Your account → Profile & password**
to change your password, or **Sign out** to end your session.

The seeder initializes Administrator and Salesperson roles and their permissions.
Rerunning it preserves existing permission assignments. Legacy scaffold users
without a role cannot sign in. Staff account administration screens are Phase 18.

Password-reset email uses the configured mail transport. With the local
`MAIL_MAILER=log`, emails appear only in the private `storage/logs` directory;
they are not delivered to an inbox. Reset links in those logs are sensitive.
Configure a real mail transport before using password recovery in production,
and set `APP_URL` to the correct application URL. Reset links expire after
60 minutes and can be used once. Password changes invalidate other sessions
on their next protected request. Production requires HTTPS and secure cookies.

## Catalogue setup (Phase 3)

For a fresh checkout or upgrade:

```powershell
& '.tools/php/php.exe' artisan migrate
& '.tools/php/php.exe' artisan db:seed
```

Sign in as an administrator and use **Catalogue setup → Categories / Sizes /
Colours** in the sidebar (open **Menu** on mobile). Each list supports search,
status filtering and pagination. Use **Add** to create an entry and **Edit** to
change its details or set its status to Inactive. Entries can be reactivated.
No permanent delete action is exposed.

Category slugs are unique lowercase words separated by hyphens. Size and colour
codes are unique uppercase identifiers with optional hyphens or underscores.
Size display order is configurable; lower values appear first. Optional colour
hex values use six digits, for example `#123ABC`.

Initial sizes are XS, S, M, L, XL and XXL. Product categories and colours start
empty so you can add your store's actual choices. Expense categories and basic
system settings are prepared for later phases; they have no editing screens yet.
Rerunning seeders preserves existing values and permission revocations.

Keep database passwords only in `.env` and `.env.testing`. Their `.example`
templates are tracked by Git and must contain no credentials.

## Products and variants (Phase 4)

Run `& '.tools/php/php.exe' artisan migrate` after pulling this phase. Existing
administrators receive the new cost-viewing permission through the migration.

1. Sign in as an administrator and open **Products → Add product**.
2. Enter a name, unique product code and active category. A default selling price
   and description are optional.
3. Save, then select **Add variant** on the product detail page.
4. Select size/colour, enter a unique SKU, selling price and low-stock threshold.
   For accessories you may choose **No size / one size** and/or **No colour**.
5. Save. Repeat for each combination. Creating a product or variant adds no stock.

Codes and SKUs are stored in uppercase. Prices must be nonnegative with no more
than two decimal places. The default product price only prefills new variant
forms; changing it does not change existing variant prices. Average cost starts
at zero and cannot be manually edited here.

Use **Edit** to update or deactivate an item. **Archive** asks for confirmation
and preserves records. Choose the **Archived** filter to find and restore items;
restored records are inactive until reviewed. Codes, SKUs and combinations stay
reserved for archived records, so restore an existing item instead of duplicating
it. Product archival preserves its variants. No hard-delete action is exposed.

Salespeople can browse available products, variants and selling prices. They
cannot create/edit/archive entries or see average cost. Inactive categories,
sizes and colours are not available for new selections. A previously linked
inactive reference can be retained while deactivating the existing item.

Product image uploads are optional in the specifications and are not included
in this phase. Stock receiving, opening balances, sales and costing calculations
remain in their scheduled phases.

## Suppliers (Phase 5)

After pulling this phase, run `& '.tools/php/php.exe' artisan migrate`. The migration
adds supplier storage and grants supplier management to existing administrators.
New installations receive the permission through normal role seeding.

Open **Suppliers → Add supplier**. A business name and unique supplier code are
required. Contact person, phone, email, location and notes are optional. Supplier
codes use letters/numbers separated by hyphens or underscores and save in uppercase.

The list supports search by name, code, contact person, phone or email, plus status
filters and pagination. Open **View → Edit supplier** to change details or select
Inactive. Deactivation retains the same record and contact details; change the
status back to Active to reactivate it. There is no permanent deletion action.
Salespeople cannot access supplier records.

The purchase-history panel is a placeholder until stock purchasing is implemented
in Phase 7. No purchase records, balances or supplier credit rules are introduced.

## Inventory foundation (Phase 6)

Run `& '.tools/php/php.exe' artisan migrate` after pulling this phase, then refresh
the application and open **Inventory**. Existing and new variants receive a zero
balance; catalogue creation does not record incoming stock.

The list shows physical, reserved and available quantities. Search by product name
or SKU, or select **Low stock only**. Available quantity equals physical minus
reserved. Administrators can open **Movements** to inspect the actor, reference and
before/after balances. Salespeople see available catalogue items and stock levels.
On small screens, swipe tables horizontally to see all columns.

These screens are read-only. Stock receiving and weighted-average costing arrive
with purchasing; do not enter stock by editing database quantities directly.
Size/colour can no longer be changed after a variant has inventory history.

Run the standard application suite with `& '.tools/php/php.exe' artisan test`.
Run real MySQL locking tests with
`& '.tools/php/php.exe' vendor/bin/phpunit --configuration phpunit.mysql.xml`.
These require the dedicated `mfbms_testing` database configured in `.env.testing`.
The concurrency tests use separate PHP processes and clean up their own records.

## Stock purchasing (Phase 7)

After pulling this phase, run `& '.tools/php/php.exe' artisan migrate` and rebuild
assets with `npm run build` (or the local toolchain commands above). Existing
administrators receive purchasing permission through the migration.

1. Open **Purchases → New purchase**.
2. Search for an active supplier and choose a result.
3. Enter the purchase date, optional invoice reference, payment status and notes.
4. Search by product name or SKU, choose a variant, and enter quantity and unit
   cost in TZS. Use **Add item** for other variants; use each variant only once.
5. Select **Save draft and review**. Check the server-calculated totals.
6. Choose **Edit draft**, **Cancel draft**, or **Confirm purchase**. Confirmation
   asks you to review the total and then receives all items into stock.

Drafts do not change stock. Confirmation updates physical quantities and weighted
average costs, preserves reservations, and records permanent movement history.
Confirmed purchases cannot be edited, cancelled or deleted through these screens.
Repeated confirmation returns a conflict without adding stock again. If another
user edits your draft, reload and review it before confirming.

Purchases receive references such as `MFS-PUR-000001`. Supplier detail pages now
show actual purchase history, including after a supplier is deactivated. Search
lists by reference, invoice or supplier; filter by status, payment status and date.
Salespeople cannot access purchase costs or purchasing actions.

Payment status is informational only; selecting Paid does not verify payment or
create accounting entries. Supplier credit/payables and confirmed-purchase reversal
policy remain TBD. Opening existing store stock is the next implementation phase.

## Opening stock (Phase 8)

Refresh the application and sign in as an administrator with inventory adjustment
permission. No new migration is needed for this phase.

1. Open **Opening stock** and search for an unused product variant.
2. Select **Enter opening stock**.
3. Enter the physical quantity already in your store and the unit buying cost.
4. Select **Review opening stock**, check the values, then **Confirm opening stock**.

Confirmation initializes stock and weighted-average cost, creates an
OPENING_BALANCE movement and records an audit entry. View the result through
**Inventory → Movements**. Zero unit cost is allowed; quantity must be positive.

Only active variants with zero stock, zero cost and no movement history qualify.
Once a variant has had any stock activity, opening setup is blocked even if its
stock later returns to zero. Use Purchases for new deliveries. Opening records
cannot be edited or repeated here; a future authorized adjustment/reversal workflow
must handle corrections. Salespeople cannot access this workflow, even if granted
the general inventory adjustment permission.

## Customers (Phase 9)

Run `& '.tools/php/php.exe' artisan migrate` after pulling this phase. Administrators
receive the new customer-management permission; existing salesperson registration
permission allows browsing and creating profiles.

Open **Customers → Add customer** for the full profile or **Quick create** for a
short registration modal. Full name is required. Phone, WhatsApp, location, notes,
preferred size/colour and category preferences are optional. Marketing consent is
off by default; check it only when the customer explicitly agrees.

Search by name, customer code, phone or WhatsApp number. Formatting separators are
removed from contact numbers, and leading `00` is treated as an international
prefix. Local numbers are not automatically converted to a country code, so use
a consistent format. Matching phone/WhatsApp contacts produce a warning, including
inactive/archived profiles. Search existing records first; acknowledge the warning
only when a separate profile is appropriate (for example a shared family number).

Administrators can edit profiles, withdraw consent, deactivate and reactivate
customers. Salespeople can register and view them but cannot edit existing profiles.
No delete action is exposed. Historical preferences may be retained when reference
categories, sizes or colours become inactive.

Statistics start at zero with no purchase dates. They cannot be entered manually;
the sales phase will update them from completed transactions. History is explicitly
labelled as pending sales integration. Walk-in sales will use a null customer ID;
do not create dummy walk-in customer profiles. There is no customer login or
marketing-message sending in this phase.

## Counter sales / POS (Phase 10)

Run `& '.tools/php/php.exe' artisan migrate` after pulling this phase and rebuild
assets using the local Node toolchain. Refresh the app and open **Point of sale**.

1. Search by product name or SKU, then select a variant to add it to the cart.
2. Enter the quantity and check the unit selling price. A line discount is the
   total discount for that entire line, not a per-unit discount.
3. Leave the customer as **Walk-in**, or search and choose an active customer.
   Registration can be opened in a separate tab so the cart remains available.
4. Select the manually collected payment method and optional reference/notes.
5. Choose **Review sale total**. Use **Back to cart** to correct details.
6. After collecting payment outside the system, check the acknowledgement and
   choose **Complete sale**. Inspect the completed sale or start a new one.

Completion deducts available stock, records original unit costs, updates registered
customer statistics/history and creates an audit record. It does not recalculate
average costs. Walk-in sales store customer_id as NULL. Insufficient-stock responses
show the remaining quantity and preserve the cart for review. A repeated submission
of the same cart returns its original sale without another stock deduction.

Use **Sales history** to search by number/customer and filter by payment/date.
Salespeople see their own sales; administrators can see all sales. Cost and profit
details require the existing cost-view permission.

Completed-sale cancellation remains disabled because refund and physical stock
return rules are not yet approved. Do not edit/delete completed records in the
database. Payment methods are staff records, not provider-verified payments.
Returns, refunds and exchanges are described below.

## Orders and reservations (Phase 11)

Run the migrations and rebuild assets after pulling this phase. Open **Orders**.

1. Choose **New order**, add variants and select a registered customer. Enter an
   optional delivery address and notes, then save.
2. Review the saved total and quantities. **Confirm and reserve stock** holds
   available stock without reducing physical quantity.
3. After collecting the full amount outside the system, choose **Record full
   payment**, select a method and optionally enter its reference.
4. **Convert to sale** deducts physical and reserved stock together and records
   historical costs, customer spending and one linked sale.
5. Advance through **Preparing**, **Out for delivery**, then **Delivered**.

Unpaid new/confirmed orders can be cancelled with a reason. Their reservations
are released without increasing physical stock. Paid-order cancellation is
blocked pending refund rules. There is no automatic reservation expiry or
provider verification. Salespeople see their own orders; administrators with
`orders.manage` can manage all orders. Sale attribution stays with the original
order salesperson; the audit records identify the staff member performing each action.

## Returns (Phase 12)

Run migrations and rebuild assets after pulling this phase. Open **Returns → New
return**, or open the original sale and choose **Start return**.

1. Find the original sale by its sale number. The deadline is three days (72
   hours) after sale completion; the screen displays the exact deadline.
2. Enter quantities for items being returned; leave other quantities at zero.
   Select each condition: SELLABLE, DAMAGED, DEFECTIVE or OTHER.
3. Enter the reason. Choose the recorded sale as proof, or choose receipt and
   enter its reference. Both options still require the matching recorded sale.
4. **Save for review** creates a pending return. Check the details and choose
   **Approve return**. Administrators and salespeople can approve within their
   existing sale access scope.
5. After receiving and inspecting the items, **Complete return** restores only
   sellable quantities to stock. **Reject return** instead records a rejection
   reason and changes no stock.

Creation, approval and completion all enforce the deadline. Quantities are
rechecked so repeat/competing requests cannot exceed what was sold. Rejected
returns do not consume returnable quantities. Pending returns do not hold stock.
Archived variants can be returned against the original sale without reactivating
them for new sales. Return history is linked from the sale detail screen.

Returns retain original cost snapshots and completed historical COGS adjustments.
They do not recalculate current WAC, rewrite original sales, reduce customer
spending totals or issue refunds. Refunds are handled separately in Phase 13 below.

## Refunds (Phase 13)

Run migrations and rebuild assets after pulling this phase. Open **Refunds →
Request refund**, or choose **Request refund** on a sale/completed return.

1. Find the original sale. Optionally select a related completed return and
   choose **Find sale / update limits** to apply its eligibility cap.
2. Enter refund amounts per sale line, leaving other lines at zero. Available
   amounts account for discounts, completed refunds and approved amounts awaiting
   payment. Enter the reason and submit the request.
3. An administrator reviews it, chooses a refund method and confirms approval.
   The method may differ from the original payment. Approval holds the amount
   against the remaining refundable balance.
4. The administrator returns money outside the system using that method, then
   chooses **Record payment completion**, optionally enters a reference and
   acknowledges payment. The app records payment; it does not send money.
5. If no payment has been made, an administrator can reject/cancel the request
   with a reason. This releases any approved hold. Completed refunds cannot be
   cancelled or paid twice through the workflow.

Salespeople can request/read refunds for their own sales; administrators with
sales.view_all can handle all sales. Both roles can inspect linked refund history.
Refunds never restore stock: process physical merchandise through Returns. A
linked return refund is capped by its proportional discounted sale value, rounded
down to two decimals, and all refunds also share the original sale-line cap.
The three-day merchandise-return deadline does not restrict later money recording.
Original sale/customer gross purchase statistics stay intact; completed refunds
are separate financial records for calculating net revenue in later reports.

## Exchanges (Phase 14)

Open **Exchanges → New exchange**, or **Start exchange** on the original sale.
Find the sale number, enter returned quantities and conditions, search for any
replacement product, and save for review. Complete within three days (72 hours)
of the original sale. Saving does not reserve replacement stock.

For an equal-value exchange, inspect the items and confirm completion. For a
higher-value replacement, record the additional payment received. For a cheaper
replacement, an administrator must approve and confirm the refund using the
chosen payment method. Money is handled outside the application. The completed
exchange links its refund record automatically.

Salespeople handle their own sales. Only sellable returned items re-enter stock.
Pending exchanges can be cancelled with a reason. Original sale history and
combined return/refund limits prevent processing the same item or credit twice.
Replacement items remain exchange history; they do not start a new sale or window.
See [Phase 14 report](PHASE_14.md) for verification and current limitations.

## Expenses (Phase 15)

As an administrator, open **Expenses → Record expense**. Select an active
operating-cost category, date and amount, optionally add a description, then save.
Amounts use TZS and at most two decimal places; zero is permitted. Recording an
expense does not send money. Supplier stock belongs in **Purchases**; customer
delivery fees remain outside Version 1 accounting.

Open an expense to inspect its details and audit history. **Edit expense** keeps
the original recorder and saves previous/new values with the editor's identity.
If another user changed the entry, reload it before making your correction.
There is no expense deletion action.

Use **Manage categories** to create, rename, deactivate or reactivate operating
categories. Inactive categories remain on existing expenses but cannot be chosen
for new ones. Filter expenses by description/number, category, date range or recorder.
Salespeople have no expense access by default. Profit dashboards and reports are
subsequent phases. See [Phase 15 report](PHASE_15.md).

## Dashboard (Phase 16)

Open **Overview** after signing in. The dashboard shows today and month-to-date
activity in Africa/Dar_es_Salaam; use **Refresh overview** for current figures.
Salespeople see their own sales/orders and permitted stock availability. Authorized
owners also see net sales, adjusted COGS, gross profit, operating expenses and
**Estimated Net Profit**. Expand **How these figures are calculated** to reconcile
the components.

Completed refunds and exchanges affect their completion period. Exchange refunds
are counted once through their linked refund. Only sellable completed returns
reverse original cost; damaged/defective items retain their cost as approved.
Expenses use their expense date. Negative period totals are possible when a refund
relates to an earlier sale. No history is rewritten.

Stock counts cover active, available catalogue variants; available units exclude
reservations. Low-stock counts exclude zero-availability variants, which appear
as out of stock. Monthly rankings use gross original completed sales, before returns;
replacement items remain in exchange history. Customer rankings exclude walk-ins.
Orders created include all lifecycle statuses, not just outstanding orders.
See [Phase 16 report](PHASE_16.md) for permissions and remaining reporting scope.

## Reports (Phase 17)

Open **Reports** and choose sales, inventory, purchases, customers, expenses,
profit, returns, refunds or exchanges. Apply the relevant filters. Dates are
inclusive in business local time and default to month-to-date. Status defaults
to completed/confirmed; select **All** to include other statuses.

Click a transaction number to inspect its original details. Product filters select
whole matching documents; amounts are their complete totals. Customer figures
are gross completed sales in the selected period. Inventory is a current balance
snapshot, including archived/inactive catalogue items, and has no historical date
filter. Profit uses the dashboard's approved rules; reconciliation links open
source reports with matching dates.

**Download CSV** exports all matching pages, not just the visible page, up to
5,000 rows. Narrow the filters for larger results. Downloads escape spreadsheet
formula prefixes and require the same report/module permissions as the screen.
Salespeople have no report permission by default. PDF and queued large downloads
remain future options. See [Phase 17 report](PHASE_17.md).

## Phase 18 — Staff & access

Sign in as an administrator and open **Staff & access**. Add a staff member using
their name, sign-in email, optional phone, role, status and a temporary password.
Confirm with your own administrator password, then share the temporary password
privately. The staff member must change it in Profile before opening the workspace.

Use **Edit** to change account details or deactivate/reactivate access. Deactivation
retains transaction and audit history. Email, role and status changes revoke old
sessions. The separate **Reset staff password** form assigns a new temporary
password and revokes sessions/reset links without activating an inactive account.
Change your own password through Profile.

Open **Role permissions** to change access for all members of an existing role.
Staff administration and refund approval/completion remain administrator-only.
You cannot remove all administrator access, deactivate yourself or change your own
role. If another administrator changes a record first, reload its form and review
the current values. All mutations require your current password and are audited.

No automated credential email, custom-role creation or account deletion is included.
See [Phase 18 report](PHASE_18.md) for verification and implementation details.
## Phase 19 — Audit logs and business settings

Administrators can open **Audit logs**, filter by staff member, action, record type,
record ID or local date range, then select a date to inspect before/after values.
This history is read-only. Older entries may contain fewer details.

Open **Business settings** to change the store name, phone, address, receipt footer
or default low-stock threshold. Confirm with your administrator password. The name
appears throughout the signed-in workspace; contact details/footer appear on sale
summaries. The threshold prefills new variant forms, leaving existing variants
unchanged. Historical financial amounts remain unchanged. These are current store
details, not immutable receipt-header snapshots.

TZS currency and Africa/Dar_es_Salaam time retain their established Version 1 values.
If another administrator saves first, reload and review the settings before retrying.
Changes are audited. No payment/integration secrets are editable here.
## Phase 20 — Production configuration checks

Run `php artisan app:check-security` using the project PHP executable. It displays
only configuration problems, never secret values. It is expected to fail on the
local HTTP/debug/log-mail configuration. Keep local development unchanged.

Before production, configure APP_ENV=production, APP_DEBUG=false, a valid APP_KEY,
a canonical HTTPS APP_URL, SESSION_SECURE_COOKIE=true, SESSION_HTTP_ONLY=true,
SESSION_SAME_SITE=lax (or strict), file/database/redis sessions, and an actual mail
transport. Do not include log/array in a production mail failover chain. Clear or
rebuild configuration cache, then run the security check. Unsafe production
configuration serves a generic 503; HTTP mutations are rejected and HTTPS requests
must use the configured host and port.

The deployment must separately verify TLS/proxy configuration, public-only web
root, private database access, log permissions, mail delivery and backup restore.
See [Phase 20 report](PHASE_20.md) for coverage and limits.
## Phase 21 — Repeatable QA

From a development checkout, run:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\qa.ps1
```

The execution-policy setting applies only to that process; it does not change
Windows policy. The runner uses the bundled PHP/Node when available, or accepts
-PhpPath and -NodeDirectory. It stops on failed formatting, unit/feature tests,
MySQL tests, asset compilation, Blade/route checks or Git whitespace checks.
-SkipMySql explicitly produces an incomplete QA run.

The runner refuses cached configuration. After confirming the checkout is local,
clear it with artisan config:clear. The test base also checks the resolved connection
before database fixture setup: only testing + SQLite :memory: or MySQL
mfbms_testing is allowed. Configure .env.testing locally and never reuse the real
business schema. The scale test temporarily inserts 50,000 sales and 100,000 lines
in the test database, then rolls the transaction back.

The automated runner does not claim browser/UAT/production verification. See
[QA coverage](QA_MATRIX.md) and [Phase 21](PHASE_21.md) for the recorded review.