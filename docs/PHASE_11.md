# Phase 11 — Orders and Stock Reservations

Implemented the order lifecycle and its connection to the existing inventory and
sales services. No returns, refunds, exchanges or payment integrations were added.

## Behavior

- Registered-customer orders begin NEW/UNPAID. Server-calculated prices and line
  discounts determine totals. Creation changes neither stock nor revenue.
- Confirmation atomically reserves all lines, records ACTIVE reservations and
  moves the order to CONFIRMED. Insufficient stock rolls back every line.
- Staff manually record full payment and its method/reference. This changes the
  order to PAYMENT_RECEIVED/PAID, without creating revenue or deducting stock.
- Conversion validates matching active reservations, creates one primary sale,
  captures current weighted-average costs, reduces physical/reserved quantities
  together, completes reservations and updates customer statistics atomically.
- Fulfilment requires a linked sale and advances in sequence through PREPARING,
  OUT_FOR_DELIVERY and DELIVERED.
- Cancellation is limited to unpaid NEW/CONFIRMED orders. It releases only that
  order's reservations, keeps physical stock unchanged and records a reason.
- Orders, lines and reservations reject arbitrary Eloquent writes/deletes.
  Services use transactions, row locks and audited transitions. Direct database
  administration remains outside these application-level immutability guards.
- New-order retries with identical request keys/payloads return the original
  order. Conflicting keys and repeated state transitions are rejected. A unique
  sales.order_id constraint prevents duplicate primary sales. The counter-sale
  endpoint rejects the internal order-conversion request-key namespace.
- Salespeople manage their own orders. Administrators receive orders.manage.
  Conversion also requires sales.create. Original order staff retain sale
  attribution; audits record the actual operator. Cost visibility is unchanged.
- Existing paid reservations can be fulfilled after customer/variant archival;
  new orders and new reservations still require available references.

## Files

Created the orders/order_items/stock_reservations migration, OrderStatus,
Order/OrderItem/StockReservation models, OrderService, OrderController, order list
and detail views, OrderTest and OrderConcurrencyTest.

Modified SaleService to share atomic completion with order conversion; reused
the POS cart/search view for order creation. Updated routes, sidebar, permissions,
the MySQL worker, README, local setup and database/workflow implementation notes.
Earlier uncommitted work remains in place. No dependency was added.

## Verification

Automated tests cover creation and validation, exact totals, no premature revenue,
reservation/release ownership, multi-line rollback, payment ordering, conversion,
customer statistics, historical cost snapshots, duplicate requests, invalid
fulfilment transitions, missing reservations, permissions, archival and audit
failure rollback. Real independent MySQL workers exercise competing orders for
the last unit and duplicate confirmation, conversion and cancellation.

Chrome completed creation, confirmation, payment, conversion, delivery and unpaid
cancellation using an isolated database. Creation, list, reserved, delivered and
cancelled screens passed 1440px/390px overflow checks with no JavaScript exceptions.
Desktop and mobile screenshots were visually inspected.

- Application suite: **140 tests, 1,055 assertions passed**.
- MySQL suite: **19 tests, 278 assertions passed**; the four order races passed
  again after the final authorization change (59 assertions).
- Pint, production asset build, Blade compilation, route cache/clear and diff
  checks passed. The development migration was applied successfully.
- Browser fixtures used a disposable database; its credentials and database were
  removed. No test orders or stock changes were created in the store database.

## Remaining decisions

Paid-order cancellation and completed-sale cancellation remain blocked until
physical-return/refund rules are approved. No automatic reservation expiry was
invented: expires_at remains nullable and unused. Manual payment is not provider
verification. UI_SPEC.md remains unavailable; UI.md supplies the design system.

Next planned phase: **Phase 12 — Returns**. No commit or push performed.
