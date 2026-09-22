# Phase 13 — Refunds

Implemented money refund requests, administrator approval/method selection,
manual completion and rejection/cancellation using the owner's approved policies.
Salespeople can request refunds; approval, completion and closure enforce both
administrator role and the relevant permission. Existing sale access still applies.

## Behavior

Creation records PENDING monetary allocations against original discounted sale
lines. RefundService calculates totals with decimal arithmetic. Identical request
retries return the original record; changed payloads with the same key fail.
The request cannot set its status, approver, method or authoritative total.

Approval selects any supported method, including one different from the original
payment, and changes status to APPROVED. Approved amounts hold refundable capacity
so overlapping payouts cannot both be approved. Completion requires server-validated
acknowledgement that money has been returned outside the system, retains the
approved method and optionally records a payment reference. It changes status to
COMPLETED and never calls a payment provider or changes inventory.

Creation, approval and completion recheck per-line limits against other APPROVED
and COMPLETED refunds under the original sale lock. Per-line caps include discounts
and collectively prevent exceeding the original sale total. Pending/approved
requests may be rejected/cancelled by an administrator with a reason, releasing
any hold. Completed records cannot be changed or processed twice.

Optional return linkage requires a completed return from the same sale and caps
each line to the returned quantity's proportional discounted sale value, rounded
down to two decimals. The global sale-item cap still includes all other refund
allocations. Linked completion updates return_item.refund_amount in the same
transaction as refund status and audit records. No stock or COGS adjustment is
repeated. Audit failure rolls back all financial state, retaining the approved hold.

Refund items allocate money, not additional returned quantities; their optional
quantity field remains NULL in this workflow. Refunds may be linked to completed
returns or recorded independently against original sales as specified in the
schema. The merchandise-return deadline is not applied to money refunds.

Original sale amounts, costs, status and customer gross purchase statistics remain
unchanged. Completed refund records supply net-revenue reductions for future
reports; approved amounts are holds, not completed revenue reductions. No general
financial-report module or provider verification is claimed in this phase.

## Files

Created the refunds/refund_items migration, RefundStatus, Refund/RefundItem models,
RefundService, RefundController, three refund views, RefundTest and
RefundConcurrencyTest. Modified permissions, routes/sidebar, Sale and SaleReturn
relationships/controllers/history views and the MySQL worker. Updated requirements,
PRD, security, database/API/workflow notes, README, setup and implementation plan.
No new dependency was added.

## Verification

- Application suite: **161 tests, 1,243 assertions passed**.
- Dedicated MySQL suite: **25 tests, 370 assertions passed**.
- Nine refund feature tests cover partial/full and excessive refunds, discounted
  and return-linked limits, receipt-independent money recording, idempotent requests,
  duplicate completion, approval holds/release, alternative methods, role/ownership
  enforcement, accidental permission grants, revoked permissions, invalid inputs,
  CSRF, unchanged sales/stock, and audit rollback including linked return amounts.
- Three independent MySQL races cover competing approvals, duplicate completion
  and simultaneous valid partial completions.
- Chrome verified salesperson requests, administrator approval with M-Pesa for
  an original cash sale, manual completion/reference recording, approved cancellation
  and linked sale history. Create/staff-review/approved/completed/cancelled/history
  layouts passed at 1440px and 390px with no page overflow or JavaScript exceptions.
  Desktop approval and mobile request screenshots were visually inspected.
- Pint, production asset build, Blade compilation and compiled refund-view syntax,
  route cache/clear and diff checks passed. The development MySQL migration applied
  successfully and the database connection check passed.
- Browser checks used a disposable database with separate test administrator and
  salesperson accounts. Temporary database/credentials were removed and the test
  server/browser stopped. No real customer refund, payment or store stock change
  was created for testing.

Next planned phase: **Phase 14 — Exchanges**. Exchange deadline and cross-product
exchange policy remain TBD. Completed-sale cancellation remains disabled as a
separate workflow. UI_SPEC.md remains unavailable; UI.md supplies the design system.
No commit or push performed.
