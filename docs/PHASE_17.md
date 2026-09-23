# Phase 17 — Reporting

Implemented all nine planned report types: sales, inventory, purchases, customers,
expenses, profit, returns, refunds and exchanges. Reports use existing transactional
records, with no new migration, dependency or manually maintained financial total.

## Behavior and filters

Open Reports, choose a type, select applicable filters and apply. Date ranges are
inclusive in Africa/Dar_es_Salaam, default to month-to-date and cannot end in the
future. Sales use completion/sale date; purchases use purchase_date; expenses use
expense_date. Completed returns/refunds/exchanges use completion/processing date;
unfinished records use creation date. Completed/confirmed status is the default;
select All to inspect other statuses.

Filters include product name/code, variant SKU, category, customer name/code/phone,
supplier name/code, salesperson, payment method and status where relevant. Expense
filters include category/recorder. Inventory additionally supports size, colour
and stock status. Customer reports support minimum sale count and gross spending.
Invalid or known inapplicable filters are rejected instead of silently changing
the meaning of a report.

Product filters select matching documents using EXISTS. A multi-line sale or
purchase appears once, and the amount remains the full document total. This is
explicit on screen; these are not product-level allocated revenue reports.
Transaction numbers link to the existing detail/history screens. Results use
25-row pagination and preserve filters between pages.

Inventory shows current balances, including archived/inactive catalogue records;
it does not offer a historical date filter. Available balance means physical minus
reserved, not a guarantee that the catalogue item is active for sale. Customer
reports calculate gross completed sales within the period from transactions,
exclude walk-ins and retain zero-sale customers. They do not filter profiles by
creation date or rewrite lifetime customer totals.

## Profit reconciliation

Extracted FinancialSummaryService from the dashboard so both surfaces use exactly
the same decimal calculations and permission checks. Profit supports whole-store
date ranges, not arbitrary product/customer expense allocation. It shows:

- Gross sales, additional exchange payments and completed refunds.
- Net sales, original sale costs, sellable return/exchange cost reversals and
  replacement costs.
- Adjusted COGS, gross profit, expenses and Estimated Net Profit.

The approved policy is preserved: non-sellable returned goods retain their original
cost, sellable receipts reverse original snapshots, replacements add captured cost,
and exchange refund differences are counted through their completed refund once.
Adjustments affect the completion period. Reconciliation links open source reports
with matching dates/statuses. No inventory, transaction or historical cost is changed.

## Permissions and CSV downloads

reports.view is necessary but not sufficient. Module permissions and global sale
visibility are checked as applicable. Profit requires the same financial permissions
as the dashboard; purchases additionally require cost visibility. Inventory output
contains quantities without cost fields. Authorization is enforced by services and
routes for both HTML and downloads, with private/no-store responses.

CSV downloads use the same filters and export all matching pages up to 5,000 rows.
Oversized exports are rejected before download; users must narrow their filters.
Downloads are throttled and streamed directly without public files. CSV formatting
quotes delimiters correctly, uses UTF-8 with BOM, and neutralizes formula prefixes,
including leading whitespace/control characters. Negative calculated values are
also protected as text cells. No PDF or queued export is claimed.

## Files created and modified

Created ReportService, FinancialSummaryService, ReportController, report catalogue/
results views, ReportTest and this report. Modified DashboardService to reuse the
financial service, routes/sidebar, dashboard financial tests and the MySQL decimal
aggregate test. Updated API/security/workflow notes, implementation plan, README
and local setup. Existing Phase 16 changes were preserved.

## Verification

- Full application suite: **192 tests, 1,664 assertions passed**.
- Full MySQL suite: **32 tests, 466 assertions passed**.
- Seven report feature tests cover all report pages/exports, permissions and direct
  service denial, date/category/recorder/product/customer/status filters, duplicate
  prevention for multi-line documents, confirmed versus draft purchases, no expense
  duplication of supplier purchases, CSV formula protection, full-page exports,
  pagination, the 5,000-row limit and invalid/inapplicable filters.
- Existing mixed return/refund/exchange dashboard fixtures now also verify identical
  report profit and matching source reports. MySQL verifies identical large-decimal
  dashboard/report results and grouped customer spending filters.
- Visual review found a literal Blade marker in explanatory text; it was fixed.
  Final focused report suite: **7 tests, 129 assertions passed**, including the
  added explanatory-text regression. This was a view-only correction after the
  full suites above.
- Chrome verified all nine report pages, profit reconciliation, matching sales
  filters, protected CSV responses and staff export denial at 1440px and 390px,
  without page overflow or JavaScript errors. The browser checks were repeated
  after the text fix, and final desktop/mobile screenshots were inspected.
- Pint, production assets, Blade compilation/compiled report-view syntax, route
  caching/clearing, database connectivity and Git whitespace checks passed.

Browser tests used a disposable SQLite database, not business data. Test credentials
and database were removed and the test browser/server stopped. No commit or push
was performed.

## Remaining scope

Large queued exports and PDF downloads remain optional future work. Inventory is
current, customer rankings/spend are gross, and document filters do not allocate
line-level profit. The system remains an operational estimate rather than full
accounting. There are no unresolved policy decisions blocking this phase.

Next: **Phase 18 — Users & Administration**.
