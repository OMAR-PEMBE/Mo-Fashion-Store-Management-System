# Phase 12 — Returns

Implemented the guided merchandise return workflow using the owner's approved
three-day policy. Both administrators and salespeople may approve returns, with
existing sale access boundaries preserved. A receipt or matching recorded sale
is accepted as proof; all returns link to original sale items.

## Behavior

The three-day window is 72 elapsed hours from completed_at, inclusive. Creation,
approval and completion validate the current time against that deadline in the
business timezone. The deadline is displayed. Expired pending/approved returns
can be rejected but cannot be completed.

Save for review creates PENDING. Approval changes it to APPROVED without stock
effects. Completion changes it to COMPLETED, restores only SELLABLE items through
RETURN movements and records original-cost adjustments in the same transaction.
DAMAGED, DEFECTIVE and OTHER items remain out of sellable stock. Rejection from
PENDING/APPROVED records its reason and restores no stock.

Completed quantities alone reduce the remaining returnable quantity. The original
sale row serializes competing returns; completion rechecks quantities while
locked. Multi-line stock changes follow the established product/variant/balance
lock order. A later line or audit failure rolls back the whole completion.
Identical creation retries return the original record, conflicting keys fail,
and repeated transitions cannot duplicate stock restoration.

Historical unit costs and cost adjustments are snapshotted from original sale
items, including when current WAC has changed. Only COMPLETED return adjustments
should be counted by future financial reports. Original sales and their COGS
remain immutable. Current WAC, reserved stock and customer purchase/spending
statistics remain unchanged. This module does not issue refunds or mark sales
REFUNDED. All return_item.refund_amount values remain zero for Phase 13.

New returns require returns.create; approval/completion/rejection require
returns.approve. Both roles receive these permissions. Salespeople remain scoped
to their own sales and cannot see cost adjustments; administrators with
sales.view_all can handle all sales. Stock restoration authorizes the specific
return permission without granting general inventory adjustment rights. Archived
variants can be returned without being reactivated. Actor IDs, transition times
and audit records preserve processing history.

## Files

Created the returns/return_items migration, returns policy configuration,
ReturnStatus, SaleReturn/ReturnItem models, ReturnService, ReturnController,
three return views, ReturnTest and ReturnConcurrencyTest.

Modified InventoryService authorization for RETURN movements, permissions,
routes/sidebar, Sale relationship/controller/detail history and the concurrency
worker. Updated requirements, PRD, database/API/workflow notes, README, local
setup and the Phase 12 plan. No new dependency was added.

## Verification

- Application suite: **152 tests, 1,156 assertions passed**.
- MySQL suite: **22 tests, 324 assertions passed**.
- Twelve return feature tests cover stock restoration, non-sellable conditions,
  partial/excess/competing returns, inclusive deadline and late transitions,
  proof requirements, invalid data, retry protection, role/ownership restrictions,
  revoked permissions, rejection, historical cost after purchasing, unchanged
  reservations, multi-line and audit rollback, archived variants and cost secrecy.
- Three real MySQL races cover competing returns for the same sold quantity,
  duplicate completion, and a return alongside a counter sale.
- Chrome completed sellable and damaged returns using both proof options,
  approval/completion and rejection. Create/review/completed/rejected/history
  screens passed 1440px and 390px overflow checks with no JavaScript exceptions.
  Desktop review and mobile creation screenshots were inspected.
- Pint, production asset build, Blade compilation and compiled return-detail
  syntax, route cache/clear and diff checks passed. The local MySQL migration
  applied successfully and the database connection check passed.
- Browser fixtures used an isolated SQLite database. Temporary credentials and
  database were removed and the test server/browser stopped. No test returns,
  sales or stock changes were made in the store's development database.

## Scope and next step

The public integration API, financial reports and money refunds are not introduced
by this phase. Return records supply historical adjustments for later reporting.
There is no unsupported sale-less return or automatic cash payout. Refund policy
and completed-sale cancellation remain separate decisions. UI_SPEC.md is still
unavailable; the approved UI.md design system is used.

Next planned module: **Phase 13 — Refunds**. No commit or push performed.
