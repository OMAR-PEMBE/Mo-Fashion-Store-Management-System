# Phase 8 — Opening stock

## Implemented

Administrators can search eligible variants, enter existing quantity and unit cost,
review those values, and confirm opening stock. Only active administrators with
inventory.adjust permission may use any route or invoke the service. A salesperson
with that general permission still cannot perform opening setup.

OpeningStockService validates positive whole quantities and nonnegative costs with
at most two decimal places. It locks the parent product, variant and inventory in
the established order, then checks eligibility using current locking reads. Setup
requires zero physical/reserved quantities, zero WAC and no previous movements.
This also blocks reinitialization after previously stocked items sell out.

One transaction sets initial WAC to the entered cost, increases physical stock via
InventoryService, creates OPENING_BALANCE and writes an OPENING_STOCK audit record.
The audit records actor, variant, quantity, cost and movement ID. The stable operation
key is opening-stock:variant:{id}; reference_type is opening_stock and reference_id
is the variant ID. Duplicate submissions return 409. Audit failure rolls everything
back. No new tables, migrations or dependencies are required.

## Files

Created OpeningStockService, OpeningStockController, two opening-stock Blade views,
OpeningStockTest and this report. Modified web routes/sidebar, the MySQL concurrency
test/worker, README, local setup and the opening-stock workflow specification.

## Verification

- Application suite: **113 tests, 819 assertions passed**.
- Dedicated MySQL suite: **12 tests, 185 assertions passed**.
- Six feature tests cover review without writes, stock/cost/audit creation,
  duplicate prevention, prior history after depletion, role/permission restrictions,
  invalid values, archival, zero cost, CSRF and rollback after audit failure.
- A real two-process MySQL race proves only one opening submission succeeds;
  the other receives 409, with one movement/audit and the correct quantity/cost.
- Chrome verified entry/review/confirmation/history against a disposable database.
  List/form layouts passed at 1440px and 390px; mobile review was visually inspected.
  No JavaScript exceptions or page overflow occurred in the measured screens.
- Production asset build, Pint, Blade compilation, route caching and diff checks
  passed. Browser fixtures never used store data and were removed afterwards.

## Limits and next phase

This is one-time setup per variant, not a stock correction or bulk import tool.
No global store go-live cutoff was invented. Later deliveries use Purchases;
correction/reversal handling remains in its scheduled workflow. No stock was
entered into the actual store database. The broader system remains under phased
development. Next: Phase 9 — Customer Management. No commit or push performed.
