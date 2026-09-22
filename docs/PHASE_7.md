# Phase 7 — Stock purchasing

## Implemented

PurchaseService provides createDraft, updateDraft, confirm and cancelDraft.
Drafts contain an active supplier, purchase date, optional invoice/notes, an
informational payment status and 1–100 distinct variant lines. Quantities must be
positive whole numbers; costs are nonnegative with at most two decimal places.
Server calculations ignore submitted totals/status/actor fields. Total amounts
cannot exceed DECIMAL(15,2); resulting stock cannot exceed the inventory limit.

Drafts have no stock effect. Confirmation locks the purchase and its items,
supplier, then all affected parent products, variants and inventory balances.
Parent and variant locks use consistent ID ordering. It revalidates availability,
calculates each cost from physical quantity (including reserved units), receives
stock through InventoryService, writes confirmation metadata and an audit record,
then commits. All changes roll back together on any failure. Deadlocks are retried
by the outer transaction. Reserved quantities are unchanged.

Cost arithmetic uses Brick Math already installed with Laravel, without binary
floating-point calculations. The weighted average is rounded half-up to two
decimal places when stored; historical purchase unit costs remain unchanged.
The formula is `(old physical × old WAC + incoming quantity × incoming cost) /
(old physical + incoming quantity)`.

Repeat confirmation returns 409 without another stock change. A revision column
also rejects stale draft edits, cancellations and confirmations so the user must
review the latest draft. Confirmed and cancelled purchases are read-only; no delete
or confirmed-purchase reversal endpoint is exposed. Model guards direct writes
through PurchaseService; these guards do not prevent privileged SQL changes.

An atomic document_sequences row allocates references such as MFS-PUR-000001 in
the draft transaction. Minimal audit_logs storage implements the documented schema
for CONFIRM_PURCHASE and CANCEL_PURCHASE. Confirmation audit data includes actor,
purchase reference, status, total and before/after variant costs. Audit browsing
and settings remain in their scheduled phase.

The UI supports list/search/status/payment/date filters, draft creation/editing,
review, confirmation dialogs and permanent detail views. Supplier and variant
lookups return at most 20 results; refine the search for larger catalogues. Forms
require JavaScript. Supplier pages now show paginated real purchase history.

purchases.manage protects every screen, lookup and service mutation. Confirmation
also requires inventory.adjust. Existing administrators receive the new permission
through migration; salespeople receive none. Seeder reruns preserve revocations.
Deactivated suppliers retain history, and archived products remain readable in
historical purchases but cannot be selected or confirmed for new receiving.

## Files created

- Migrations `2026_09_22_000006_create_purchasing.php` and
  `2026_09_22_000007_create_document_sequences.php`.
- PurchaseStatus enum, Purchase and PurchaseItem models, PurchaseService.
- PurchaseController and `resources/views/purchases/{index,form,show}.blade.php`.
- PurchaseTest and PurchaseConcurrencyTest.

Modified Supplier relationship/controller/detail view, permission catalogue, web
routes, sidebar, Alpine form script and the existing MySQL worker. Updated README,
local setup guide and database implementation notes. No dependency was added.

## Verification

- Application suite: **107 tests, 781 assertions passed**.
- Dedicated MySQL suite: **11 tests, 169 assertions passed**.
- 21 purchase feature cases cover draft create/edit/cancel, totals, WAC, rounding,
  retained purchase costs/reservations, duplicate confirmation, stale revisions,
  invalid/duplicate lines, inactive/archived references, permission revocation,
  supplier history, escaping, CSRF and rollback after second-line/audit failures.
- Three MySQL two-process races cover duplicate confirmation, two purchases for
  one variant and a purchase competing with a sale. They check final quantities,
  costs, ledger counts and audit counts. MySQL also verifies the maximum supported
  monetary amount persists exactly. SQLite numeric affinity cannot certify that
  boundary; SQLite is used only for the general feature suite/browser fixture.
- Chrome creation, supplier/variant search, draft editing, review, confirmation,
  received-stock display and supplier history passed against a disposable database.
  List/review/form screens passed 1440px and 390px checks with no page overflow or
  JavaScript exceptions. Desktop review and mobile form screenshots were inspected.
- Pint, production build, Blade compilation, route caching and diff checks passed.
- Local development migrations applied. No store purchases or stock were fabricated.

## Limits / TBD

Payment status (PAID, PARTIALLY_PAID, UNPAID) is informational. Supplier credit,
payables, payment verification and confirmed-purchase reversal policy remain TBD.
No expenses, tax, discounts, freight allocation or supplier payment accounting were
invented. The application is still under phased implementation, not production-ready.
Public REST endpoints are not introduced; the Blade application uses the shared
service. UI_SPEC remains unavailable; existing UI.md components/tokens are reused.

Next: Phase 8 — Opening Stock. No commit or push was performed.
