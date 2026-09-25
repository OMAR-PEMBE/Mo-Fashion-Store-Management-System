# Phase 22 — Owner/staff acceptance results

**Overall status: PENDING — no owner/staff acceptance results recorded.**

Follow [UAT_GUIDE.md](UAT_GUIDE.md). This is a worksheet, not evidence of execution.
Use fictional practice data; do not record credentials or real customer details.

## Session

| Field | Recorded value |
| --- | --- |
| Confirmed practice database (not mfbms_testing) | Local mfbms; owner confirmed practice-only data on 2026-09-24 |
| Starting financial/stock baseline | Existing records retained; see [baseline](UAT_BASELINE.md); new UAT codes unused at capture |
| Application address | http://127.0.0.1:8000 (scripts/serve.cmd) |
| Git revision being accepted | PENDING |
| Test start/end dates (Africa/Dar_es_Salaam) | PENDING |
| Administrator tester | PENDING |
| Salesperson tester (or owner testing that role) | PENDING |
| Browser/version and device | PENDING |
| Actual store staff feedback, if owner tests both roles | PENDING |

## Scenario results

Replace PENDING only after execution with PASS, FAIL or PASS WITH NOTES. Include
actual observations, tester/date and any defect ID in the evidence column.

| ID | Scenario | Result | Actual observations / tester / date / defects |
| --- | --- | --- | --- |
| UAT-01 | Receive stock and weighted-average cost | PENDING | |
| UAT-02 | Counter sale and receipt | PENDING | |
| UAT-03 | Register and select customer | PENDING | |
| UAT-04 | Reserve, pay, convert and deliver order | PENDING | |
| UAT-05 | Sellable return, salesperson approval | PENDING | |
| UAT-06 | Request refund; administrator approve/complete | PENDING | |
| UAT-07 | Damaged return retains cost; separate refund | PENDING | |
| UAT-08 | Exchange, both stock movements and extra payment | PENDING | |
| UAT-09 | Operating expense | PENDING | |
| UAT-10 | Owner's profit/stock reconciliation and CSV | PENDING | |
| UAT-11 | Access, oversell prevention, cancellation, device usability | PENDING | |
| UAT-12 | Name-only colours, automatic SKU, administrator correction after sale | PENDING | |

## Generated document references

These aliases refer to documents created through the walkthrough, not database IDs
to enter manually. Copy the actual reference displayed by the application.

| Alias | Meaning | Actual reference |
| --- | --- | --- |
| P1 | Initial 10 Jeans purchase | |
| P2 | 10 Jeans + 10 Dresses purchase | |
| S1 | Walk-in sale: 3 Jeans | |
| Customer | UAT Customer One | |
| O1 | Delivered order: 2 Jeans | |
| S2 | O1's linked sale | |
| R1 / F1 | Sellable return / refund | |
| R2 / F2 | Damaged return / refund | |
| E1 | Jeans-to-Dress exchange | |
| X1 | Packaging expense | |
| Cancelled order | UAT-11 reservation release | |

## Reconciliation at UAT-10

Record values before any optional extra transactions. Use a report date range
covering the complete session and note the range: **PENDING**.

Values below are the UAT sample's changes, not combined database totals. Subtract
the matching [baseline](UAT_BASELINE.md) from observed financial reports when
completing this table. Stock figures refer only to the new UAT variants.

| Metric | Expected | Observed |
| --- | ---: | --- |
| Gross sales (TZS) | 225,000 | |
| Completed refunds (TZS) | 90,000 | |
| Extra exchange payment (TZS) | 5,000 | |
| Net sales (TZS) | 140,000 | |
| Adjusted COGS (TZS) | 105,000 | |
| Gross profit (TZS) | 35,000 | |
| Operating expenses (TZS) | 10,000 | |
| Estimated Net Profit (TZS) | 25,000 | |
| Jeans physical / reserved / available | 17 / 0 / 17 | |
| Dress physical / reserved / available | 9 / 0 / 9 | |
| Jeans / Dress WAC (TZS) | 25,000 / 30,000 | |
| Total inventory value (TZS) | 695,000 | |
| Original sale count | 2 | |
| Customer gross spending (TZS) | 90,000 | |

## Defects and retests

Catalogue feedback has been implemented: missing colour choices, automatic SKUs,
name-only colour setup, and administrator corrections after sales with salesperson
editing denied. Latest developer verification: 242 application tests and the MySQL
catalogue correction check passed. Owner retest remains PENDING; see
[correction notes](VARIANT_CORRECTIONS.md). These are not acceptance passes.

Copy the following block for each issue:

```text
Defect ID:
Scenario / role / browser:
Transaction references:
Steps to reproduce:
Expected:
Observed:
Impact (stock / money / access / usability):
Release blocker (yes/no and reason):
Fix reference / regression evidence:
Retested by / date / result:
Owner's decision on remaining notes:
```

## Owner acceptance

| Decision | Recorded value |
| --- | --- |
| All core scenarios executed | PENDING |
| Stock/financial/access blockers resolved and retested | PENDING |
| Notes and known limitations reviewed | PENDING |
| Additional actual-store workflows requested | PENDING |
| Owner name and date | PENDING |
| Explicit acceptance: PASS / FAIL / PASS WITH NOTES | PENDING |

Known scope limits to review: manual payments, disabled completed-sale cancellation,
no product-image uploads, no public/provider integrations, and current store details
on sale summaries rather than immutable historical receipt headers. Deployment,
real mail delivery and backup restoration have separate later checks. Record any
limitation that prevents store use as an issue; do not silently accept it.
