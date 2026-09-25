# Practice database baseline

The owner confirmed on 2026-09-24 that local data is practice data. Real data will
be entered on the production server. Use local `mfbms` for UAT; existing practice
records have not been deleted or reset.

Read-only capture: **2026-09-24 03:19:54 Africa/Dar_es_Salaam**. Report dates:
**2026-09-24 to 2026-09-24**. No UAT-JEANS/UAT-DRESS products,
UAT-JEANS-M/UAT-DRESS-M variants or UAT-SUP-01 supplier existed at capture time.
Create those new records for the walkthrough instead of using existing Jeans.

| Component (TZS) | Before UAT | Walkthrough change | Expected after UAT-10 |
| --- | ---: | ---: | ---: |
| Gross sales | 50,000 | 225,000 | 275,000 |
| Completed refunds | 0 | 90,000 | 90,000 |
| Extra exchange payments | 0 | 5,000 | 5,000 |
| Net sales | 50,000 | 140,000 | 190,000 |
| Original sales COGS | 40,000 | 125,000 | 165,000 |
| Sellable return cost reversal | 0 | 25,000 | 25,000 |
| Exchange replacement cost | 0 | 30,000 | 30,000 |
| Exchange returned cost reversal | 0 | 25,000 | 25,000 |
| Adjusted COGS | 40,000 | 105,000 | 145,000 |
| Gross profit | 10,000 | 35,000 | 45,000 |
| Operating expenses | 10,000 | 10,000 | 20,000 |
| Estimated Net Profit | 0 | 25,000 | 25,000 |

Expected combined results apply only when the walkthrough is completed on this
date with no unrelated financial changes after capture. For another date or a
session spanning dates, capture a fresh baseline for the full report range before
starting. Reconcile unrelated activity separately if it occurs.

The final 17 Jeans, 9 Dresses and TZS 695,000 inventory value refer only to the two
new UAT variants; existing stock is additional. The two walkthrough sales are two
new UAT sales, not the entire database's sale count. This is a starting snapshot
and expected calculation, not an owner acceptance pass.
