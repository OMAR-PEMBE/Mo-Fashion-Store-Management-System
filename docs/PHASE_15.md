# Phase 15 — Expense Management

Implemented operating expense entry, correction, filtering, category management
and audit history. Administrators receive access by default; salespeople do not.
The module follows the existing operating-expense policy and remains separate
from supplier purchases, inventory and payment-provider processing.

## Behavior

Record an expense using an active category, expense date, nonnegative amount and
optional description. Amounts use decimal arithmetic with at most two fractional
digits; zero is allowed by WORKFLOWS.md. The MySQL DECIMAL(15,2) upper bound is
9999999999999.99. Numbers use MFS-EXP- and the server records the authenticated
operator. Stock-purchase fields are rejected; supplier purchases are never copied
automatically into expenses. Saving an expense does not send money.

Creation is retry-safe: the same key, normalized payload and recorder return the
existing entry. Reusing a key with different data or another recorder fails.
Edits require the revision displayed by the form, reject stale changes with 409,
retain the original recorder and save full before/after audit snapshots. Audit
history identifies the editor and preserves category names at the time of each
operation. No expense deletion route is provided; direct expense model writes
and deletion are guarded.

Category management supports creation, renaming, description changes, deactivation
and reactivation. New names are validated for uniqueness. Inactive categories
remain on historical expenses and may be retained while correcting an existing
entry, but cannot be selected for a new entry or reassignment. Category edits also
use revision checks and transactional audit records. Category history is retained
in audit_logs; the dedicated expense detail screen shows that expense's history.

ExpenseService serializes expense/category writes using the EXPENSE sequence lock,
then locks the affected rows. This deliberately simple approach suits the
single-branch workload, prevents duplicate creation and conflicting corrections,
and keeps numbering, record changes and audit inserts atomic. Audit failures roll
back the entire operation, including sequence changes. No inventory, sale or
purchase operation is invoked.

List filters cover number/description, category, inclusive dates and recorder.
Lists and audit history are paginated. Forms and navigation enforce expenses.view
plus the relevant expenses.create, expenses.update or expense-categories.manage
permission. Service writes refresh actor authorization. Migration grants existing
administrators the new permissions once; seeding does not restore revoked access.

## Files created and modified

Created the expenses migration, Expense model, ExpenseService, ExpenseController,
ExpenseCategoryController, three expense views, two category views, ExpenseTest,
ExpenseConcurrencyTest and ExpenseRaceWorker. Modified ExpenseCategory casts,
permissions, routes and sidebar. Updated database/API/workflow/security notes,
implementation plan, README and local setup. No new dependency was added.

## Tests and verification

- Application suite: **179 tests, 1,470 assertions passed**.
- Dedicated MySQL suite: **31 tests, 455 assertions passed**.
- Seven new feature tests cover decimal entry/correction, server attribution,
  before/after history, duplicate retries, stale edits, validation, zero amounts,
  stock-purchase payload rejection, categories, permissions/revocation, CSRF,
  escaping, filters, pagination, unchanged inventory and audit-failure rollback.
- Two independent MySQL worker races cover simultaneous duplicate creation and
  conflicting corrections. Exactly one expense is created; only one competing
  revision update succeeds. MySQL also verifies exact maximum-value storage and
  audit snapshots; SQLite's floating-point numeric storage cannot verify that bound.
- Chrome verified expense/category creation, decimal correction, before/after
  history, deactivation, retained inactive categories, filtering and salesperson
  denial. Screens passed at 1440px and 390px without page overflow. Desktop audit
  and mobile creation screenshots were visually inspected.
- Pint, production assets, Blade compilation, syntax checks for all five compiled
  views, route caching/clearing and Git whitespace checks passed.

The development migration was applied and the local MySQL connection verified.
Browser checks used a disposable SQLite database; no test expenses were inserted
into the business database. Temporary browser credentials/database were removed.

## Limitations and next phase

This is manual operating-expense recording, not payment execution, supplier
payables, full accounting or an approval workflow. Descriptions/categories still
depend on staff classifying costs correctly. Version 1 excludes customer delivery
fees. There is no delete/void operation; authorized corrections preserve audit
history. MySQL is required for supported financial storage.

No unresolved policy decision blocks this phase. Dashboard totals, expense reports
and Estimated Net Profit follow in Phases 16 and 17. Existing Phase 14 work was
preserved. No commit or push was performed.

Next: **Phase 16 — Dashboard**.
