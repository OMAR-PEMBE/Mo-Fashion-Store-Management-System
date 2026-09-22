# Phase 6 — Inventory foundation

## Implemented

Each variant has one inventory row containing physical and reserved quantities.
Available quantity is computed, never independently stored. The migration creates
zero balances for existing variants, including archived variants, without inventing
stock or movement history. Catalogue creation initializes balances atomically.

InventoryService implements increase, decrease, reserve, releaseReservation,
completeReservation and signed adjustments. Each operation uses a transaction and
locks parent product, variant and inventory in that order. Balance updates and
movement insertion succeed or roll back together, including inside a caller's
outer business transaction. Quantities must be PHP integers within 1–2147483647;
adjustments accept nonzero signed integers in that range. Reserved stock cannot
exceed physical stock, and unreserved stock reductions cannot consume reservations.

Movements retain actor, timestamp, type, business reference, reason, notes and both
before/after balances. Quantity change represents physical change; reservation-only
movements therefore have zero physical change. Ordinary Eloquent writes/deletes
are blocked on balances and movements; the service performs controlled writes.
This is an application safeguard, not protection against privileged direct SQL.
MySQL CHECK constraints additionally enforce valid balances and ledger snapshots.

The schema extends the specification with a unique operation_key and request_hash.
Retrying the same key and payload returns the original movement; changing the
payload fails. Reference type and ID are required for service operations. Reservation
release/completion is limited to the outstanding reserved quantity for that same
variant and business reference, derived from ledger snapshots using locking reads.
Variant size/colour becomes immutable after any inventory movement.

The inventory list supports product/SKU search, low-stock filtering and pagination.
Administrators can inspect archived stock and movement history; salespeople can
view stock for available catalogue items. No costs appear on these screens.
All routes enforce permissions. There are no public stock mutation endpoints.

## Integration contract and limits

Future workflow services must authorize and validate the actual purchase, order,
sale or adjustment document before invoking InventoryService. Reference metadata
alone does not prove a document exists or belongs to the actor. Use a stable key
per business operation and line, retaining the same payload on retries. Wrap
document updates and all stock operations in one outer database transaction; use
consistent lock ordering across multiple products/variants.

The kernel requires inventory.adjust for stock-in and adjustments, sales.create
for direct sales, orders.create for reservations/releases, and both order and sale
permissions for reservation completion. It reloads the actor before authorization.
Future workflows must add their own document permissions, payment prerequisites,
status transitions, audit records and ownership checks. This phase does not create
orders, stock_reservations, purchase documents or financial transactions.

Weighted-average costs are unchanged. Purchasing must calculate costs within its
transaction in Phase 7. Opening-stock and adjustment entry screens remain in their
scheduled phases. New sales/reservations reject unavailable variants; existing
reservations may be released after archival. No actual store stock was fabricated.

## Files

- Migration: `2026_09_22_000005_create_inventory_foundation.php`.
- Models: Inventory and InventoryMovement; ProductVariant relationships.
- InventoryMovementType enum, InventoryContext and InventoryService.
- InventoryController, two inventory views, web routes and sidebar entry.
- ProductCatalogueService zero initialization and dimension-history protection.
- InventoryTest, InventoryConcurrencyTest and InventoryRaceWorker; updated
  catalogue/foundation assertions, README and local setup instructions.

## Verification

- Application suite: **86 tests, 649 assertions passed**.
- MySQL suite: **8 tests, 120 assertions passed** on the dedicated test database.
- Four actual two-process races cover competing sales, reservations, concurrent
  increases and duplicate-key retries. A parent lock and file barriers ensure both
  workers overlap before release. These tests also verify MySQL CHECK enforcement.
- Feature tests cover zero initialization, reservation lifecycle/reference scope,
  insufficient stock, invalid quantities, retry mismatch, authorization, archived
  stock, dimension history, model write protection and rollback after ledger failure.
- The dimension-history regression passed again after strengthening its locking read.
- Pint, production asset build, Blade compilation, route cache and diff checks passed.
- Chrome verified 10 physical / 3 reserved / 7 available and two ledger entries
  against a disposable SQLite fixture. List and history screens passed 1440px and
  390px viewport checks with no page overflow or JavaScript exceptions. Desktop list
  and mobile history screenshots were reviewed. Fixture credentials/database removed.
- Development migration applied successfully. Browser fixtures never used store data.

SQLite tests do not certify MySQL locking or its additional CHECK constraints;
the separate MySQL suite supplies that evidence. Reference implementation guidance:
[MySQL locking reads](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html)
and [Laravel transactions](https://laravel.com/docs/13.x/database#database-transactions).

Next: Phase 7 — Stock Purchasing. Changes remain local; no commit or push performed.
