# Administrator variant corrections

Updated policy, 2026-09-24: the owner authorized administrator edits after sales.
This supersedes the earlier unsold-stock-only restriction.

On Products > product > Edit variant, administrators can edit size, colour, SKU,
selling price, low-stock threshold and status regardless of sale, order, exchange,
reservation or movement history. A size/colour correction on a used variant needs
a reason. Unique SKUs/combinations and reference validation still apply.

Salespeople cannot create/edit/archive products or variants through catalogue
routes, even with catalogue-write permissions. ProductPolicy and catalogue save
service checks require an active administrator with the applicable product
permission. Temporary-password changes must also be completed.

Stock quantities, average cost, variant IDs, movement records and original sale
quantities, prices, discounts, costs and totals remain unchanged. Linked documents
use current catalogue labels and display the corrected details. These labels are
not sale-time snapshots. Attribute changes record the actor, before/after values
and reason in CORRECT_VARIANT_ATTRIBUTES; price changes keep their separate audit.
An audit failure rolls back the update. Existing SKU stays unless explicitly edited.

## Files changed for this policy update

- app/Policies/ProductPolicy.php: administrator-only catalogue writes.
- app/Services/ProductCatalogueService.php: administrator checks and removal of
  transaction-history edit restrictions while retaining reason/audit handling.
- resources/views/products/variant-form.blade.php: updated guidance.
- tests/Feature/ProductCatalogueTest.php: edits after sales/orders/reservations/
  damage retain transaction data; privileged salespeople are denied.
- tests/Feature/ExchangeTest.php: pending exchange data survives catalogue edits.
- tests/MySql/ProductCatalogueDatabaseTest.php: correction after a real MySQL sale.
- Markdown Files/REQUIREMENTS.md and docs/LOCAL_SETUP.md: approved policy.

## Verification

- Full application suite: 242 tests, 2,054 assertions passed.
- MySQL catalogue/correction integration: 1 test, 30 assertions passed.
- Changed PHP formatted with Pint; Blade compilation and Git whitespace passed.
  Compiled views cleared afterward.

No migration or live business-record edit was needed. Stock adjustments still use
inventory workflows. This change permits catalogue edits, not overwriting completed
financial transactions. No additional policy decision is pending for this change.
