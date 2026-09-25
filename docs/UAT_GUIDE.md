# Phase 22 — Store acceptance walkthrough

Status: **Prepared; owner/staff execution pending.** Use this together with
[UAT_RESULTS.md](UAT_RESULTS.md). Automated QA results are in
[Phase 21](PHASE_21.md); they do not replace your acceptance results.

## Before entering practice transactions

The owner confirmed the current local database contains only practice data. Use
`mfbms`; keep existing practice records. Do not use `mfbms_testing`: automated tests
use that database. See the recorded [starting totals](UAT_BASELINE.md).

Record the confirmed practice database, application address, Git revision, browser,
test date and testers in the results sheet. Start the new UAT variants with zero
stock and use the normal seeded roles/permissions. Keep passwords out of the sheet.
Environment setup and administrator sign-in must be verified before UAT-01.

For the existing configured local application, `scripts/serve.cmd` starts
<http://127.0.0.1:8000>. This launcher uses the current `.env`; it does **not** create
or switch to a practice database. See [local setup](LOCAL_SETUP.md).

Use an administrator and a salesperson account with their default permissions.
The administrator can add the salesperson through **Staff & access**; the
salesperson must change their temporary password on first sign-in. Use separate
browser profiles or sign out when switching roles. If only the owner is available,
the owner can try both accounts, recording that actual staff feedback is pending.

Run the sequence once, preferably on the same day. All values below are **TZS**;
enter numeric amounts without commas. Use today's purchase/expense dates. Complete
returns and exchanges within **72 hours of the original sale**. If the session
crosses midnight, use the full session date range for report reconciliation;
today's dashboard will only contain today's activity.

Payment acknowledgements below simulate cash handling in the confirmed practice
environment. No real payment or customer contact is needed. Use fictional names
and leave optional phone/email details blank. Stop at a mismatch, record a defect,
and resolve it before continuing dependent steps. Repeating a new sale creates
another real record in that database; do not repeat the whole sequence to retry
one screen or delete completed records to fix totals.

## UAT-01 — Receive stock (administrator)

1. Under **Catalogue setup**, create an active category named `UAT Clothing` and
   a colour named `UAT Black`. Use active size M.
2. Create the two active products and variants below under **Products**. Set each
   variant's selling price explicitly and its low-stock threshold to 2.
3. Register supplier `UAT Supplier`, code `UAT-SUP-01`.
4. Create purchase **P1** from that supplier: 10 Jeans at unit cost 20,000. Save
   the draft: stock must still be zero. Review, then **Confirm purchase → Confirm
   and receive stock**. Jeans physical/available becomes 10; average cost 20,000.
5. Create purchase **P2**: 10 Jeans at unit cost 30,000 and 10 Dresses at unit cost
   30,000. Confirm it. Record both generated purchase numbers in the results sheet.
6. Open **Inventory** and movement history; confirm the balances below. Reload
   the completed purchase and confirm it stays confirmed with no extra receipt.

| Item | Product code | Variant SKU | Size / colour | Selling price |
| --- | --- | --- | --- | ---: |
| UAT Jeans | UAT-JEANS | UAT-JEANS-M | M / UAT Black | 45,000 |
| UAT Dress | UAT-DRESS | UAT-DRESS-M | M / UAT Black | 50,000 |

Expected: P1 total **200,000**, P2 total **600,000**. Jeans has **20 physical,
0 reserved, 20 available**, average cost **25,000**; Dress has **10 / 0 / 10**,
average cost **30,000**. Jeans WAC = `(10 × 20,000 + 10 × 30,000) / 20`.
Purchases total **800,000** and must not appear as operating expenses.

## UAT-02 — Counter sale (salesperson)

1. Open **Point of sale**, search `UAT-JEANS-M`, and select the correct size/colour.
2. Add **3 Jeans**, each at 45,000, with no discount. Leave customer as **Walk-in**.
3. Select Cash, choose **Review sale total**, acknowledge the simulated payment
   and **Complete sale**. Save its generated number as **S1**.
4. Inspect the sale summary/receipt: item, quantity, price, total, payment method,
   salesperson and reference must be correct. Reopen it from **Sales history**.

Expected: S1 total **135,000**; Jeans **17 physical, 0 reserved, 17 available**.
The administrator sees original unit cost **25,000**, COGS **75,000**, gross
profit **60,000**. The salesperson must not see cost/profit figures. Walk-in
requires no customer profile. Reopening the sale must not change stock.

## UAT-03 — Customer registration (salesperson)

1. Open **Customers → Add customer**, or **Quick create**.
2. Register `UAT Customer One`. Leave optional contact details blank and marketing
   consent unchecked; this fictional customer has not given marketing consent.
3. Search for the saved customer, reopen it and record its generated customer code.

Expected: one profile, consent off, no purchase history/spending yet. The customer
is available for selection in an order. A salesperson cannot edit existing profiles.

## UAT-04 — Order, reservation and delivery (salesperson)

1. Create order **O1** for UAT Customer One: **2 Jeans** at 45,000, no discount.
   Use a fictional delivery address. The new order alone changes no stock/revenue.
2. **Confirm and reserve stock**. Jeans must show **17 physical, 2 reserved,
   15 available**. No new sale should exist yet.
3. **Record full payment**, selecting Cash for the simulated 90,000 payment.
4. **Convert to sale**. Record its linked sale as **S2**. Reload the order and
   check it still links one sale, with Jeans **15 physical, 0 reserved, 15 available**.
5. Advance through **Preparing → Out for delivery → Delivered**.
6. Open the customer: purchase history should contain S2, with gross spending
   **90,000**. Walk-in S1 must not belong to this customer.

Expected: O1 and S2 total **90,000**. S2 COGS **50,000**, gross profit **40,000**
when viewed by the administrator. Delivery status changes must not deduct stock again.

## UAT-05 — Sellable return (salesperson, own S1)

1. Open S1 and **Start return**. Enter quantity **1** Jeans, condition **SELLABLE**,
   a reason, and the recorded sale as proof. Verify the displayed deadline.
2. **Save for review**, then **Approve return**. No stock should change yet.
3. Simulate receiving/inspecting the item, then **Complete return**. Record **R1**.

Expected: Jeans **16 physical, 0 reserved, 16 available**; original cost reversal
**25,000**. S1 remains a completed 135,000 sale with its original cost snapshot.
No refund has been paid merely by completing the return.

## UAT-06 — Refund (salesperson requests; administrator completes)

1. As salesperson, request a refund from completed R1. Check S1 and R1 are linked;
   use **Find sale / update limits** when necessary. Enter **45,000** for its Jeans
   line and submit a reason. Record **F1**.
2. Verify the salesperson has no refund approval/payment-completion action.
3. As administrator, review F1, choose Cash, and approve it. Approval alone must
   not count as a completed refund.
4. Simulate returning the money, then **Record payment completion** and acknowledge
   the payment. Reopen the completed refund.

Expected: one completed refund of **45,000**; Jeans stays **16 / 0 / 16**. No
second stock restoration, repeated payment, or rewrite of S1. The administrator
can choose a method different from the original payment if required by the store.

## UAT-07 — Damaged return and refund (both roles)

1. As salesperson, create a second return on S1 for **1 Jeans**, this time condition
   **DAMAGED**. Choose receipt proof and enter S1's recorded receipt/sale reference.
   Save, approve and complete it; record **R2**.
2. Request **45,000** against R2, recording **F2**. The administrator approves and
   records its simulated cash payment as in UAT-06.

Expected: Jeans stays **16 / 0 / 16**. The damaged unit does not re-enter sellable
stock and its original **25,000 cost remains a business cost**. S1 now has two
returned units and completed refunds totalling **90,000**. One S1 unit remains
eligible for the next exchange. A receipt still needs the matching recorded sale.

## UAT-08 — Exchange with an additional payment (salesperson, own S1)

1. Open S1 and **Start exchange**. Return its remaining **1 Jeans**, condition
   **SELLABLE**, and select **1 UAT Dress** as the replacement. Enter a reason.
2. Save for review, recording **E1**. Returned value should be **45,000**,
   replacement value **50,000**, customer pays **5,000**, refund due **0**.
3. Choose **Complete exchange**, select Cash, acknowledge the simulated additional
   payment and confirm completion. Inspect both sides and inventory movements.

Expected: Jeans **17 / 0 / 17**, Dress **9 / 0 / 9**. Original returned cost
**25,000** is reversed; replacement cost **30,000** is recorded. E1 adds **5,000**
revenue, with no refund and no separate new sale. All 3 S1 units have now been
returned/exchanged: another return or exchange of those units must be blocked.

## UAT-09 — Expense (administrator)

1. Open **Expenses → Record expense**. Choose an active operating category such
   as Packaging, today's date, amount **10,000**, description `UAT packaging`.
2. Save once, record **X1**, and reopen it. Inspect its audit history.

Expected: one operating expense **10,000**. Stock and customer spending are unchanged.
The **800,000** supplier purchases must not be added to these operating expenses.

## UAT-10 — Owner's profit and stock reconciliation

Open **Reports → Profit**, using dates that include all this session's transactions.
The following values are the walkthrough's contribution. For the existing practice
database, add the [recorded starting totals](UAT_BASELINE.md); that document lists
the expected combined report values. Compare source documents as well as totals.
If everything happened today, **Overview** today should agree after refreshing.

| Component | Manual calculation | Expected TZS |
| --- | --- | ---: |
| Original gross sales | S1 135,000 + S2 90,000 | 225,000 |
| Completed refunds | F1 45,000 + F2 45,000 | 90,000 |
| Additional exchange payment | E1 | 5,000 |
| Net sales | 225,000 + 5,000 − 90,000 | **140,000** |
| Original sales COGS | 3 × 25,000 + 2 × 25,000 | 125,000 |
| Sellable return cost reversal | R1 only; R2 is damaged | 25,000 |
| Exchange replacement cost | 1 Dress × 30,000 | 30,000 |
| Exchange returned cost reversal | 1 sellable Jeans × 25,000 | 25,000 |
| Adjusted COGS | 125,000 − 25,000 + 30,000 − 25,000 | **105,000** |
| Gross profit | 140,000 − 105,000 | **35,000** |
| Operating expenses | X1 | 10,000 |
| Estimated Net Profit | 35,000 − 10,000 | **25,000** |

Final stock: **17 Jeans** at WAC **25,000** and **9 Dresses** at WAC **30,000**;
reserved **0** for both. These two variants have stock value **695,000**; existing
practice stock is additional. Their inventory value plus
adjusted COGS is `695,000 + 105,000 = 800,000`, matching purchases in this sample.

Verify sales, purchases, returns, refunds, exchanges and expense reports link to
the recorded source documents. Download a CSV for the same date range and compare
its rows/values. Sales history should still contain **two original UAT sales**, even
after the refunds and exchange. Customer gross spending remains **90,000**.

## UAT-11 — Access, usability and stock protection

After recording the reconciliation above:

1. Sign in as salesperson. Check purchase costs, profit reports, operating expenses,
   staff administration, audit logs and business settings are unavailable with
   default permissions. Try a copied administrator report URL in this session;
   access must be denied, not just hidden from the menu.
2. As salesperson, try selling **18 Jeans** while only 17 are available. Either the
   interface prevents the quantity or submission gives a clear stock error. No
   sale or stock deduction may occur. Leave the attempted cart uncompleted.
3. Create a separate unpaid order for the customer for **1 Jeans**, confirm it:
   Jeans becomes **17 physical, 1 reserved, 16 available**. Cancel with reason
   `UAT reservation release`: Jeans returns to **17 / 0 / 17**, with no sale/refund.
   Record its order number; order-count reports will include this cancelled order.
4. Recheck S1: no further merchandise return/exchange quantity remains. Trying
   another refund must not bypass credits already used by F1, F2 and E1.
5. Use the browser/device normally used at the counter. Check search speed,
   keyboard navigation, legibility, menu access and review/error messages. Record
   the actual device/browser and any awkward steps, even if the numbers are right.

Equal-value exchanges and cheaper replacements can be tried as additional cases
after this checkpoint, with new original sales and separately recorded expected
totals. A cheaper replacement requires administrator completion and one linked
refund; salespeople may request it. Additional transactions change report totals.
Deadline expiry can be observed on a separate sale after 72 hours; do not edit
historical timestamps or change the workstation clock to simulate expiry.
Automated boundary coverage remains documented in [QA_MATRIX.md](QA_MATRIX.md).

## Recording acceptance

### UAT-12 — Administrator catalogue corrections

Run after UAT-10 and UAT-11 so the earlier sample remains easy to follow.

1. As administrator, add a colour using only its name, `UAT Navy`. Confirm there
   are no code or hex-code inputs. Create an unused Jeans variant with that colour,
   size M and a blank SKU. Save and confirm a generated unique SKU is displayed.
   Do not enter stock for this additional variant.
2. Edit the original Jeans variant already used in S1/S2. Change its colour to
   another unused combination (for example White / M), enter reason
   `UAT administrator catalogue correction`, and save. Confirm the existing SKU
   stays unchanged unless explicitly edited. Do not change selling price here.
3. Verify stock remains 17 Jeans, zero reserved; the UAT-10 financial figures and
   original sale quantities, prices and costs remain unchanged. Linked documents
   show current corrected catalogue labels. Check Audit logs for the original/new
   attributes and correction reason.
4. Sign in as salesperson and try the copied variant edit URL. Access must be
   denied. There must be no catalogue edit actions in the product detail page.

Record observed results separately as UAT-12. The extra empty variant can change
catalogue/out-of-stock counts, but not the stock value or financial reconciliation.

### Sign-off

For each executed scenario record **PASS**, **FAIL**, or **PASS WITH NOTES** in
the results sheet, the tester/date and the observed evidence. Leave unexecuted
scenarios **PENDING**, not PASS. Report defects with the scenario ID, steps,
expected result, actual result and transaction references. Never include passwords.

Any incorrect stock, money, historical data or unauthorized access is a release
blocker. Fix it, add/run the appropriate regression checks, then have the tester
repeat the affected scenario. The owner must explicitly review notes and unresolved
issues before acceptance; developer automation cannot supply that sign-off.
Production preparation is the following phase.
