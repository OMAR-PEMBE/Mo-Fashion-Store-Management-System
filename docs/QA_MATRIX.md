# Version 1 QA coverage

Phase 21 automated review, 2026-09-23. This maps implemented functionality to
executable evidence; it is not a statement of code coverage percentage.

| Area | Evidence | Scope |
| --- | --- | --- |
| Decimal sale arithmetic | tests/Unit/SaleArithmeticTest.php | Fractions, full discounts, losses, near-maximum money, multiple lines |
| Test database safety | tests/Unit/TestDatabaseGuardTest.php; tests/TestCase.php | Reject production/local databases before fixture traits run |
| WAC and historical costs | PurchaseTest, OpeningStockTest, SaleTest | Initial/repeated purchases, rounding, immutable sale snapshots |
| Stock and reservations | InventoryTest, OrderTest | Nonnegative balances, reserve/release/conversion and ownership |
| Returns, refunds, exchanges | ReturnTest, RefundTest, ExchangeTest | 72-hour limits, quantities, conditions, approvals, settlements and rollback |
| Profit reconciliation | DashboardTest, ReportTest, DashboardAggregateTest | Combined sales/returns/exchanges/refunds/expenses and large exact decimals |
| Staff/security | AuthenticationTest, StaffTest, SecurityHardeningTest | Session revocation, password workflows, CSRF, role/permission enforcement, production guards |
| Audit and settings | AdministrationTest | Read-only history, redaction, settings revision/password checks, atomic audit failure rollback |
| Catalogue and relationships | ReferenceDataTest, ProductCatalogueTest, SupplierTest, CustomerTest | Validation, duplicate records, archive/history and visibility |
| Expenses | ExpenseTest | Exact amounts, revisions, audit history, permissions and rollback |
| Real database constraints | tests/MySql/*DatabaseTest.php | Dedicated MySQL migrations, uniqueness, foreign keys and infrastructure |
| Concurrent writers | tests/MySql/*ConcurrencyTest.php | Independent worker processes; retries, final-item stock, reservations, refunds/exchanges, staff access |
| Reporting scale | ReportingScaleTest | 50,000 sales, 100,000 lines, 2,000 variants, 5,000 customers; totals, pagination and query bounds |
| Browser regression | Phase 21 browser run | Nine reports, 20 workspace screens, real POS search/review/completion, CSV protection and staff denial at 1440/390px |
| Public integration API | Not implemented | Existing /api/v1 exposure tests reject unavailable routes; no claim of a completed public API suite |
| Future provider integrations | Not applicable to Version 1 | Payment webhooks, WhatsApp and AI integrations remain outside current implemented scope |

Feature filenames are under tests/Feature unless explicitly identified otherwise.
MySQL suites use mfbms_testing and clean only their own fixtures or roll back their
transaction. The reporting scale fixture is synthetic read-workload data, not an
alternative route for creating business transactions.

Actual owner/staff acceptance, real-device and other-browser checks, infrastructure
security, production load and backup restoration still require their planned phases.
No image-upload security acceptance is claimed: product-image uploads remain
unimplemented. Completed-sale cancellation remains disabled pending its reversal
policy. These limitations are not silently counted as passing tests.