# Phase 22 — User Acceptance Testing

Status: **Preparation complete; execution and owner acceptance pending.**

## Prepared

- [Store walkthrough](UAT_GUIDE.md): receive stock, counter sale, customer,
  order/reservation/delivery, sellable and damaged returns, refund approval,
  exchange, expense, reporting, permissions and counter-device usability.
- [Results sheet](UAT_RESULTS.md): session details, transaction references,
  observed totals, defects/retests and explicit owner acceptance.
- A connected sample with independently calculated expectations: TZS 140,000
  net sales, 105,000 adjusted COGS, 35,000 gross profit and 25,000 Estimated Net
  Profit. It includes the approved retained cost of a damaged return.

The walkthrough was checked against current service calculations and existing
screen workflows. It requires an empty practice financial/stock baseline and
does not treat its expected figures as observed test results.

## Verification and data

This change contains documentation only; no application code, migration, account,
stock or financial transaction was changed. Link/whitespace and sample arithmetic
checks were performed. Application suites were not rerun for documentation changes;
the preceding evidence is in [Phase 21](PHASE_21.md).

The owner has been asked whether to use a separate practice database or a confirmed
disposable current local database. No practice data has been inserted while that
choice is pending. The standard local launcher uses the existing `.env` and does
not isolate a UAT database. `mfbms_testing` is reserved for automated tests.

## Remaining work

1. Confirm and prepare the practice environment; verify administrator/staff access.
2. Owner/staff execute the walkthrough and record actual results.
3. Fix reported defects, add relevant regression coverage and retest.
4. Record explicit owner acceptance before moving to production preparation.

All acceptance results remain PENDING. Automated QA and developer checks do not
substitute for actual business acceptance. No production deployment is performed.

## Resumed after catalogue feedback — 2026-09-24

Implemented the owner's colour/SKU simplifications and administrator corrections
after sales. Added UAT-12 for owner retesting of these changes and updated the
feedback log. Latest developer results: 242 application tests passed, with the
MySQL catalogue/correction test passing 30 assertions. A fresh read-only connection
check verified local MySQL connectivity. Actual owner acceptance is still pending.

The current local database contains entered stock, so it cannot be assumed to be
an empty practice baseline. Asked the owner whether this is real or practice data
before preparing the acceptance environment. No transactions were inserted/reset.
