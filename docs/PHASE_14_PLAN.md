# Phase 14 — Exchanges implementation plan

Status: requirements and integration review complete. Exchange deadline and
cross-product eligibility await the owner's decision. Existing Phase 13 changes
remain intact; no exchange processing is enabled by this preparation.

## Decisions required

REQUIREMENTS.md / Remaining Client Decisions and PRD.md section 61 separately
leave the exchange period and permission to exchange for a different product as
TBD. The approved three-day merchandise-return policy does not automatically
resolve the exchange deadline. REQUIREMENTS.md requires these decisions before
implementing their feature.

- Exchange deadline: proposed three days after purchase, matching returns, or
  another explicitly approved limit.
- Replacement eligibility: any available product, or only another size/colour
  of the same product.

The previously approved refund policy remains in force: salespeople request money
refunds; administrators approve, choose the method and complete them. Exchange
refund differences must not provide a bypass around that authorization.

## Confirmed workflow

Find the original sale, select original items and quantities, record condition,
select replacement variants, calculate the difference on the server, review and
complete atomically. Preserve original sale prices, discounts and cost snapshots.
Use separate EXCHANGE_IN and EXCHANGE_OUT inventory movements. Only sellable
returned merchandise increases sellable stock. Unavailable replacement stock or
any later failure must roll back the whole exchange.

Replacement value above returned value requires additional customer payment;
lower replacement value produces a refund due. Record the settlement explicitly
without treating the browser as provider verification or paying money automatically.

## Integration work identified

- Add exchanges/exchange_items with original sale/item links, separate returned
  and replacement lines, numbering, retry protection, actor history and audit.
- Coordinate original-item eligibility across completed returns and exchanges.
  ReturnService currently counts completed returns only; adding an isolated
  exchange counter would permit the same original unit to be returned twice.
- Coordinate financial eligibility with RefundService. Its existing caps count
  approved/completed refunds; exchange credit and refund differences must not
  permit the same sale value to be reimbursed again.
- Keep refund approval holds and exchange settlement consistent under sale-level
  locks; preserve the established product/variant/inventory lock order.
- Use backend services for quantities, decimal price differences, authorization,
  historical costs, settlement and stock changes. Controllers remain thin.
- Add a guided review/completion UI and link history to the original sale.

## Verification required

Same-value exchange; extra payment; refund due with administrator controls;
discounted original value; invalid/expired eligibility; partial and repeated
exchanges; previously returned/refunded items; wrong-sale items; unavailable or
reserved replacement stock; non-sellable returns; historical cost integrity;
duplicate requests; unauthorized access; audit and multi-line rollback.

Use independent MySQL workers to test competing exchanges and exchange-versus-
return/refund operations. Verify desktop/mobile flows, then run the full regression
suite before applying the development migration.

This preparation changes documentation only. No tests were rerun, no database
changes were made, and no commit or push was performed.
