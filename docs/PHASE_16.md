# Phase 16 — Dashboard

Replaced the setup placeholder with an operational dashboard for the owner and
salespeople. Today and month-to-date figures use Africa/Dar_es_Salaam and a single
request timestamp. Salespeople see their own sales/orders without restricted
financial data. No migration, dependency or business-data rewrite was required.

## Metrics and approved financial policy

Today/month show completed sale count and gross sale revenue, orders created and
permitted new-customer counts. Authorized financial users also see net sales,
adjusted COGS, gross profit, expenses and **Estimated Net Profit**, with an expandable
breakdown of the amounts used in each calculation.

The owner approved retaining original cost for damaged/defective merchandise that
cannot be resold. Non-sellable returns do not reverse COGS. Formulas are:

- Net sales = completed sale revenue + completed exchange additional payments
  minus completed refunds.
- Adjusted COGS = original completed sale costs + completed exchange replacement
  costs minus original costs of sellable completed returns/exchange receipts.
- Gross profit = net sales minus adjusted COGS.
- Estimated Net Profit = gross profit minus operating expenses.

Exchange refund differences are already represented by linked completed refunds;
the dashboard does not subtract refund_due a second time. Monetary refunds do not
independently reverse COGS. Pending/approved adjustments are excluded. Original
cost snapshots are used rather than current catalogue prices or WAC.

Sales/returns use completed_at, refunds/exchanges use processed_at, and expenses
use expense_date. Adjustments affect their completion period even if the sale was
earlier. Negative period revenue/profit is valid. Expense dates after today and
transaction timestamps after the request time are excluded. Original sales,
inventory, refund records and customer lifetime statistics remain unchanged.

Stock metrics cover active catalogue variants with active parent/reference data:
product count, physical, reserved and available units, low stock and out of stock.
Available units exclude reservations. Low stock means positive availability at or
below the variant threshold; zero availability is counted separately.

Performance lists show up to five products and variants by this month's original
completed-sale units. Returns and exchange replacements do not change these gross
rankings, as labelled. Owner customer rankings use monthly gross completed-sale
value and exclude walk-ins. Historical product/variant names remain represented
after archival. Recent sales contain only completed sales; recent orders include
all statuses. Orders-created counts include cancelled orders, not just open work.

## Authorization and implementation

DashboardService refreshes the actor and permissions. Sales data requires
sales.create and is actor-scoped without sales.view_all. Orders require
orders.create and are actor-scoped without orders.manage. Inventory requires
inventory.view. Global customer counts require customers.manage. Financial
summaries require sales.create, sales.view_all, reports.view, products.view_cost
and expenses.view together. Customer rankings additionally require customer access.

Restricted financial results are absent from the service/view payload, not merely
hidden by templates. Recent-sale queries omit cost/profit columns. Responses use
private/no-store caching. Aggregations run in a read transaction, use database
decimal sums and Brick Math decimal arithmetic, and fetch bounded ranking/recent
lists rather than loading the transaction history into PHP. MySQL remains the
supported financial database.

Created DashboardService, DashboardController, the reusable metric Blade component,
DashboardTest and DashboardAggregateTest. Replaced the home view, changed the
dashboard route and updated the authentication test's expected page content.
Updated requirements, PRD, database, workflows, security, API, implementation plan,
README and local setup documentation. No public reporting API was exposed.

## Verification

- Application suite: **185 tests, 1,538 assertions passed**.
- Dedicated MySQL suite: **32 tests, 464 assertions passed**.
- Six dashboard feature tests cover mixed sellable/damaged returns, cheaper and
  dearer exchanges, refund deduplication, pending-refund exclusion, expense
  deductions, immutable originals, local midnight/month boundaries, later-period
  refunds, negative net sales, future-date exclusion, staff scope, revoked finance
  permissions, reserved stock, order/customer counts, rankings, escaping and empty
  states.
- A MySQL aggregate test verifies grouped rankings and exact summed expenses over
  10000000000000, including fractional cents and negative estimated profit. Its
  fixture transaction is rolled back. An initial test-only date outside MySQL's
  TIMESTAMP range was corrected; the complete suite then passed.
- Chrome verified owner calculations and breakdown, customer rankings, own-sale
  salesperson scope and restricted financial visibility. Owner, breakdown,
  performance, salesperson and recent-activity layouts passed at 1440px/390px
  without page overflow or JavaScript exceptions. Desktop/mobile screenshots were
  visually inspected.
- Pint, production asset build, Blade compilation and compiled-view syntax,
  route caching/clearing, database connectivity and Git whitespace checks passed.

Browser fixtures used a disposable SQLite database. Its credentials/database were
removed; the test server and browser were stopped. No commit or push was performed.

## Remaining scope

The overview refreshes on page load or its refresh link; it is not a live feed.
Rankings show gross sales, not net item profitability. Archived/inactive catalogue
stock remains available in inventory history rather than active-stock dashboard
counts. Delivery fees remain outside Version 1 accounting. Full accounting,
standalone loss valuation and payment-provider verification are not claimed.

No unresolved decision blocks this phase. Date-range reports, transaction-level
drill-downs and exports remain **Phase 17 — Reporting**.
