# Phase 21 — Automated Testing & QA

Completed automated QA for implemented Version 1 workflows on 2026-09-23.
Actual owner/staff acceptance and production validation remain separate phases.

## Changes

- Added scripts/qa.ps1: fail-fast formatting, unit/feature tests, dedicated MySQL
  tests, production asset build, Blade/route compilation and Git whitespace checks.
- Added a test-database guard before fixture setup. Only APP_ENV=testing with
  SQLite :memory: or MySQL mfbms_testing is accepted; cached production/development
  configuration cannot silently direct RefreshDatabase at business data.
- Added eleven isolated unit cases for exact sale arithmetic and safe/unsafe test
  configurations. No database or Laravel boot is needed for those unit cases.
- Added ReportingScaleTest using 50,000 sales, 100,000 sale lines, 2,000 variants,
  2,000 products and 5,000 customers. Fixtures are inserted in a transaction and
  rolled back; cleanup is verified. A session-only 30-second SELECT timeout prevents
  an unbounded slow query from stalling the suite and is restored afterward.
- Improved dashboard ranking queries by aggregating transaction quantities and
  customer spending before joining descriptive records.
- Improved report product/category filters by joining distinct matching document
  IDs. Multi-line matches still appear once and retain their complete totals.

Created tests/Support/TestDatabaseGuard.php, tests/Unit/TestDatabaseGuardTest.php,
tests/Unit/SaleArithmeticTest.php, tests/MySql/ReportingScaleTest.php, scripts/qa.ps1,
this report and QA_MATRIX.md. Updated tests/TestCase.php, DashboardService,
ReportService, testing/implementation documentation, README and local setup.
No schema migration, package change, commit, push or deployment was performed.

## Final results

The complete QA script passed after both query improvements:

| Check | Result |
| --- | --- |
| Unit and application tests | 224 tests, 1,914 assertions passed |
| MySQL integration/concurrency/scale | 35 tests, 513 assertions passed |
| PHP formatting | Passed across the project |
| Production assets | Built successfully |
| Blade and route compilation | Passed; route cache cleared afterward |
| Git whitespace | Passed; existing Windows line-ending notices only |
| Browser regression | Passed on Chrome at 1440px and 390px |

Browser verification covered all nine report pages, twenty workspace screens,
filters, CSV formula protection, staff export denial and an actual isolated POS
search/cart/review/payment-confirmation/completion flow. Desktop dashboard and
mobile completed-sale screenshots were inspected. The report checks were repeated
after the filter change. No page overflow or JavaScript exceptions occurred.
The browser harness initially omitted the required payment method; selecting Cash
corrected the harness, with no product validation change needed.

The full automated run exercised existing WAC, inventory, sales, reservations,
returns, refunds, exchanges, profit and authorization regression tests. See
[QA coverage matrix](QA_MATRIX.md) for the mapping and exclusions.

## Reporting performance

Final full-suite measurements on this local MySQL instance, including application
request handling and view rendering, excluding dataset generation:

| Page | Time | SQL queries |
| --- | ---: | ---: |
| Dashboard | 2.460 s | 82 |
| Sales report with category filter | 1.321 s | 30 |
| Inventory report | 0.068 s | 30 |
| Customer report | 0.382 s | 29 |
| Profit report | 0.337 s | 57 |

The fixture reconciled exactly to TZS 10,000,000 gross sales, TZS 4,000,000 COGS
and TZS 6,000,000 gross profit above the pre-existing baseline. List pagination
returned the expected 50,000 sales, 2,000 variants and 5,000 customers, 25 per page.
Query counts stayed bounded rather than increasing per displayed transaction.

Profiling exposed slow dashboard catalogue joins and an unstable correlated report
filter. The original dashboard measured 11.5 seconds initially and 3.29 seconds on
a repeat. One full-suite report query ran for several minutes and was explicitly
cancelled; its fixture transaction rolled back. The revised queries passed both
the focused scale run and the subsequent complete suite. Timings depend on cache,
machine load and dataset distribution; these observations are not a production SLA.

This is a single-user reporting workload, not a concurrent production load test.
It does not include the suggested 10,000-order/20,000-movement load dataset; order
and movement correctness/concurrency are covered by the separate business suites.

## Repeating the checks

From a local development checkout:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\qa.ps1
```

The script-policy override is process-only and leaves Windows policy unchanged.
The runner refuses cached configuration. Verify the checkout before clearing it.
Use -PhpPath and -NodeDirectory to override bundled tools. -SkipMySql explicitly
means an incomplete QA run. Browser checks and staff acceptance are separate.

Browser data used a disposable SQLite database. Its credentials/database were
removed, and the browser/server stopped. Large-data rows were rolled back in
mfbms_testing. No business database transaction data was changed.

## Remaining acceptance work

Next: **Phase 22 — User Acceptance Testing** with the owner/sales staff performing
their actual store scenarios. This report does not count developer automation as
business sign-off. Real-device and other-browser checks, full accessibility review,
production load, external mail, deployment security and backup restoration remain
unverified here. Public integration APIs, provider integrations, product-image
uploads and completed-sale cancellation are not silently counted as implemented.