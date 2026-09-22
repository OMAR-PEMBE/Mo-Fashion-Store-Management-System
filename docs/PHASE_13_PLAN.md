# Phase 13 — Refunds implementation plan

Status: implemented and verified. See [the Phase 13 report](PHASE_13.md).

## Approved decisions

- Salespeople may request refunds; administrators approve and complete them.
- Administrators choose the refund method, which may differ from the original
  payment. The method is fixed at approval and retained during completion.
- Existing sale ownership remains in force. Refund approval differs from the
  separately approved merchandise-return permission.

## Confirmed scope

Create refunds and refund_items linked to the original sale, with optional
return linkage as specified in DATABASE.md. Use PENDING → APPROVED → COMPLETED,
with rejection/cancellation as controlled alternatives. Original sales remain
intact. Completion records money already returned outside the system; it does
not automatically pay a customer or verify a payment provider.

Use server-calculated decimal totals and cap completed refunds at the remaining
amount actually charged, accounting for discounts and previous completed refunds.
Validate that refund items and any linked return belong to the original sale.
Show remaining refundable amounts before confirmation, then recheck under locks
at completion. Pending requests must not permit concurrent completed payouts to
exceed the cap.

Refunds change financial history, not inventory. Stock restoration is already
handled by ReturnService and must never be repeated by refund processing. Preserve
original sale cost snapshots and avoid counting the same return's COGS adjustment
again. Completed refunds supply the reduction in net revenue for later reports.

## Implementation and verification

1. Record approved policies; assign explicit request/approval/completion permissions.
2. Add migrations, protected models, numbering, request retry protection, actor
   IDs and processing timestamps.
3. Implement RefundService with sale-level locking, eligibility checks, immutable
   completed records and transactional audit logging.
4. Add list, creation/review, approval and manual completion screens; link refund
   history to sales and returns while retaining existing record-access restrictions.
5. Test partial/full refunds, discounts and decimal boundaries, excess amounts,
   wrong-sale items/returns, authorization, duplicate requests, transaction failure,
   unchanged inventory and historical sale integrity.
6. Run independent MySQL races for competing refunds and duplicate completion;
   verify desktop/mobile workflows and run regression checks before local migration.

Implementation and verification results are recorded in PHASE_13.md. No commit
or push performed.
