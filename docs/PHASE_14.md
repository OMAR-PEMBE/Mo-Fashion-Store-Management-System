# Phase 14 — Exchanges

Implemented exchanges for any available replacement product within the owner's
approved three-day (72-hour) window from the original sale. The exact deadline
is included. Administrators and salespeople can process eligible exchanges;
salespeople retain access to their own sales. Refund differences require an
administrator with both refund approval and completion permissions.

## Behavior

Creation validates original sale items, combined return/exchange quantities and
financial eligibility, then saves PENDING returned and replacement lines. Identical
creation retries return the existing record; changed payloads with the same key
fail. Pending records reserve neither stock nor credit and can be cancelled with
a reason. Original discounted line value is allocated proportionally to returned
quantity, rounded down to two decimal places. Replacement prices come from the
catalogue and remain fixed at the reviewed quote; client prices are ignored.

Completion rechecks the deadline, active replacement references, available stock,
quantities and credit under row locks. Sellable returns generate EXCHANGE_IN;
replacement items generate EXCHANGE_OUT. Damaged/defective merchandise does not
increase sellable stock. Existing order reservations remain protected. A same-
variant exchange can use the sellable item received during that transaction.
Original return costs use immutable sale snapshots; replacement costs use WAC at
completion. Exchanges do not recalculate WAC or rewrite the original sale.

Equal-value exchanges need no payment. Extra payment requires acknowledgement of
money received and a selected method. A refund difference requires administrator
approval and confirmation that money was returned; the exchange creates, approves
and completes its linked refund in the same database transaction. Any failure,
including audit failure, rolls back inventory, refund records and exchange state.
All payment recording is manual; no money is sent or provider payment verified.

ReturnService counts completed exchange quantities alongside completed returns.
RefundService subtracts completed exchange applied_credit as well as approved and
completed refund allocations. Returned credit is allocated in sale-item order:
the part used for replacements becomes applied_credit, and the remainder becomes
the linked refund. This prevents reusing either an item or its financial credit.
Sale-level locks serialize competing return, refund and exchange operations.
Completed exchanges cannot be processed twice or cancelled.

## Files created and modified

Created ExchangeService, ExchangeController, ExchangeStatus, Exchange/ExchangeItem
models, exchange configuration, the exchanges/exchange_items migration, three
exchange views, ExchangeTest and ExchangeConcurrencyTest. Modified InventoryService,
ReturnService and RefundService integration, Sale relationships/history, permissions,
routes, sidebar and shared product picker. Added a fractional-refund regression
and an exchange mode to the MySQL race worker. Updated requirements, PRD, database,
API, workflows, security, implementation plan, README and local setup documentation.
No dependency was added.

The refund regression fixes raw SQLite decimal values being implicitly coerced
when passed to decimal arithmetic; explicit string conversion preserves cents in
both global refund limits and linked-return refund totals.

## Verification

- Application suite: **172 tests, 1,344 assertions passed**.
- Dedicated MySQL suite: **29 tests, 427 assertions passed**.
- Ten exchange feature tests cover same-value and cross-product exchanges,
  additional payment, administrator-only refunds, discounted values, repeat
  requests, permission/ownership checks, exact deadline, invalid inputs, CSRF,
  insufficient/reserved/inactive stock, historical costs, shared return/refund
  eligibility, cancellation and transaction/audit rollback.
- Four independent MySQL races cover duplicate completion, competing exchanges,
  exchange versus return, and exchange versus refund approval.
- Added a fractional linked-refund regression preserving repeated partial cents.
- Chrome passed equal-value completion, extra payment, administrator refund
  settlement, cancellation, refund linkage and original sale history. Creation,
  review, completed and history layouts passed at 1440px and 390px without page
  overflow or JavaScript exceptions. Desktop and mobile screenshots were inspected.
- Pint, production asset build, Blade compilation, route caching and Git whitespace
  checks passed. The local development migration was applied and MySQL connectivity
  verified. Browser fixtures used a disposable SQLite database, not business data.

## Known limitations and remaining decisions

Replacement lines are exchange history, not new sale items. This phase does not
provide a chained return/exchange workflow against replacement lines or restart
the three-day period. Original sale/customer gross purchase statistics remain
unchanged. Financial reports are a later phase: completed exchange net revenue
change is amount_due minus refund_due, but the linked completed refund must be
subtracted only once. Returned original cost and replacement cost snapshots are
available for the corresponding COGS adjustments.

No unresolved decision blocks this exchange workflow. Completed-sale cancellation
still needs its separate stock/refund cancellation policy; payment-provider
integration remains future work. No commit or push was performed.

Next planned phase: **Phase 15 — Expense Management**.
