# Phase 22 — Store acceptance walkthrough

Status: **Ready for owner/staff execution.** Updated on 2026-09-26 for the redesigned
screens. Record your results in [UAT_RESULTS.md](UAT_RESULTS.md). Automated checks and
the developer dry run (see the results sheet) do **not** replace your acceptance.

## Before you start

- **Database.** Use the practice database `mfbms` (it holds practice data only). Never
  use `mfbms_testing`; automated tests use it. Existing practice records stay; their
  totals are in [UAT_BASELINE.md](UAT_BASELINE.md).
- **Start the app.** `scripts/serve.cmd` opens <http://127.0.0.1:8000> (see
  [local setup](LOCAL_SETUP.md)).
- **Two roles.** An administrator (the owner) and a salesperson. The administrator can
  add the salesperson under **Staff & access → Add staff member**; **Suggest one** creates
  a temporary password. On first sign-in the salesperson is taken to **Your profile** to
  choose their own password. Use two browsers (or a private window) so both can stay
  signed in. If only the owner is available, the owner can play both roles and note
  that real staff feedback is still pending.
- **Session rules.** Run it on one day if possible. Amounts are **TZS**; type numbers
  without commas. Returns and exchanges must be done within **3 days** of the sale.
  Payments are simulated; no real money moves. Use fictional names and leave phone
  numbers blank. Do not tick **Send the receipt on WhatsApp** (it is in test mode).
- **If something is wrong,** stop, note it (scenario, steps, what you expected, what
  happened, document numbers), and do not continue with the steps that depend on it.
  Do not delete records or repeat whole scenarios to "fix" totals.

Write down every number the system gives (purchase, sale, return numbers and so on) in
the results sheet as you go; later steps refer to them as P1, S1, R1, etc.

## UAT-01 — Receive stock (administrator)

1. **Catalogue setup → Categories → Add category**: `UAT Clothing`. The short code fills
   in by itself. Then **Colours → Add colour**: `UAT Black` (name only, no code). Size M
   already exists.
2. **Products → Add product**: name `UAT Jeans`, category `UAT Clothing`, usual selling
   price `45000`. The product code fills in by itself (for example `UAT-001`); record it.
   Press **Save and add sizes**: you go straight to the size/colour form. Choose **M** and
   **UAT Black**, leave the price as filled in (45,000), set **Restock at** to `2`, leave
   the option code blank and **Save**. The option code is made for you (for example
   `UAT-001-UAT-BLACK-M`).
3. Repeat for `UAT Dress` with price `50000` (code e.g. `UAT-002`), size M, UAT Black.
4. **Suppliers → Add supplier**: `UAT Supplier`. The supplier code fills in by itself.
5. **Purchases → New purchase (P1)**: type `UAT` in Supplier and pick UAT Supplier;
   Supplier paid? **Paid**; type `UAT Jeans` in Item 1 and pick it; How many `10`, Cost
   each `20000`. The running total shows **TZS 200,000**. **Save draft**. Stock is still
   zero. Press **Receive stock**, then **Receive stock** again in the box that opens.
6. **P2**: same supplier, Paid, Item 1 `UAT Jeans` 10 × `30000`, **Add another item**,
   Item 2 `UAT Dress` 10 × `30000`. Total **TZS 600,000**. Save draft, then receive it.
7. Open **Inventory** and search `UAT`.

**Expected:** P1 **TZS 200,000**, P2 **TZS 600,000**. Inventory: Jeans **20** ready to sell,
**20** in the shop, **0** held; Dress **10 / 10 / 0**. On each product page the average
cost is Jeans **25,000** (10 × 20,000 + 10 × 30,000, divided by 20) and Dress **30,000**.
Reopening a received purchase shows "Stock received" and does not add stock again. The
**800,000** of purchases never appears under Expenses.

## UAT-02 — Counter sale (salesperson)

1. **Point of sale**: type `UAT Jeans` and tap the result. Press **+** twice so the
   quantity is **3** (45,000 each, no discount). Customer stays **Walk-in customer**.
2. Choose **Cash** and press **Review sale**. Check the amount to collect is
   **TZS 135,000**. Tick **I have collected TZS 135,000** and press **Complete sale**.
   Record the sale number as **S1**.
3. Check the receipt on the sale page (item, 3 × 45,000, total, Cash, served by) and
   reopen it from **Sales history**.

**Expected:** S1 **TZS 135,000**; Jeans **17 / 17 / 0**. The salesperson sees no cost or
profit. Signed in as the administrator, S1 shows **Profit on this sale**: cost of goods
**75,000**, gross profit **60,000**. Reopening the sale does not change stock.

## UAT-03 — Register a customer (salesperson)

1. **Customers → Add customer**: full name `UAT Customer One`. Leave phone, WhatsApp and
   marketing consent empty/unticked. Save.
2. Record the customer code (for example `MFS-CUS-000001`).

**Expected:** the profile shows no purchases and "Marketing messages: Not agreed". The
salesperson cannot edit an existing customer (opening its edit address is refused).

## UAT-04 — Order, reservation and delivery (salesperson)

1. **Orders → New order**: under Customer press **Change**, search `UAT Customer`, pick
   UAT Customer One. Add `UAT Jeans` with quantity **2**. Enter a fictional delivery
   address. Press **Save order** (record as **O1**). Stock does not change yet.
2. **Confirm and reserve stock** → confirm. Inventory: Jeans **15 ready to sell, 17 in
   the shop, 2 held**. No sale exists yet.
3. **Record full payment**: choose **Cash**, tick **I confirm the full payment was
   received**, **Record payment**.
4. **Complete sale** → confirm. Record the linked sale as **S2**.
5. Press **Start preparing**, then **Mark out for delivery**, then **Mark delivered**.
6. Open the customer: purchase history shows S2, **Total spent TZS 90,000**.

**Expected:** O1 and S2 **TZS 90,000**; Jeans **15 / 15 / 0** after the sale; delivery steps
do not change stock again. As administrator, S2 shows cost **50,000**, gross profit
**40,000**. S1 (walk-in) is not on this customer.

## UAT-05 — Sellable return (salesperson)

1. Open S1 → **Return items**. Check the return window shown (3 days after the sale).
   Quantity **1**, condition **Sellable**, reason `Wrong size`, proof **Sale in the
   system**. Press **Save return of 1 item**. Record as **R1**. Stock has not changed.
2. **Approve return** → confirm. Stock still unchanged.
3. **Complete return** → confirm.

**Expected:** the message says "Return completed: 1 item back in stock. No refund issued
yet." Jeans **16 / 16 / 0**. S1 is still a completed TZS 135,000 sale.

## UAT-06 — Refund for the sellable return

1. As salesperson, on R1 press **Refund the customer**. The form is linked to R1 and
   limits the refund to that item. Press **Full amount** (**45,000**), enter a reason,
   **Send for approval**. Record as **F1**. The salesperson has no approve button.
2. As administrator, open F1 → **Approve refund**, choose **Cash**, **Approve refund**.
   Approving does not count as money paid.
3. Give the (simulated) money back, then **Record money returned**, tick the
   confirmation, **Record money returned**.

**Expected:** F1 completed, **TZS 45,000**; Jeans stays **16 / 16 / 0**; S1 unchanged.

## UAT-07 — Damaged return and its refund

1. As salesperson, S1 → **Return items**: quantity **1**, condition **Damaged**, reason
   `Torn seam`, proof **Customer receipt** with receipt number = S1's number. Save,
   approve and complete. Record as **R2**. The message says "1 item kept out of stock
   (not sellable)".
2. Refund it as in UAT-06 (**Full amount** 45,000). Record as **F2**; the administrator
   approves and records the money returned.

**Expected:** Jeans stays **16 / 16 / 0** (the damaged item does not go back on sale, and
its 25,000 cost stays a cost). S1 now has two returns and refunds totalling **90,000**.

## UAT-08 — Exchange with extra payment (salesperson)

1. S1 → **Exchange items**. Under "1. What is the customer bringing back?" set the last
   Jeans to **1**, condition **Sellable**. Under "2. What are they taking instead?" search
   `UAT Dress` and tap it. "3. The difference" shows coming back **45,000**, going out
   **50,000**, customer pays about **5,000**. Enter a reason and **Save exchange**.
   Record as **E1**.
2. **Complete exchange**: choose **Cash**, tick the payment confirmation, **Complete
   exchange**.
3. On S1, **Return items** now shows "Nothing left to return".

**Expected:** Jeans **17 / 17 / 0**, Dress **9 / 9 / 0**. Customer paid **TZS 5,000**; no refund
and no new sale. All 3 units of S1 are now returned or exchanged.

## UAT-09 — Expense (administrator)

1. **Expenses → Record expense**: amount `10000`, category **Packaging**, date paid
   **Today**, details `UAT packaging`. **Record expense**. Record as **X1**.
2. Open it and check its **History** ("Recorded by …").

**Expected:** one expense of **TZS 10,000**. Stock and customer spending are unchanged.
Purchases (800,000) are not in Expenses.

## UAT-10 — Profit and stock reconciliation (administrator)

Open **Reports → Profit** with a period that covers today (**This month** usually does).
The figures below are this walkthrough's own contribution; with existing practice data,
add the starting totals in [UAT_BASELINE.md](UAT_BASELINE.md), which also lists the
combined figures to expect.

| Profit statement line | Worked out | TZS |
| --- | --- | ---: |
| Sales | S1 135,000 + S2 90,000 | 225,000 |
| + Extra paid on exchanges | E1 | 5,000 |
| − Refunds paid back | F1 + F2 | 90,000 |
| **Net sales** | 225,000 + 5,000 − 90,000 | **140,000** |
| Cost of items sold | 5 Jeans × 25,000 | 125,000 |
| + Cost of replacement items given | 1 Dress × 30,000 | 30,000 |
| − Returned items back in stock | R1 only (R2 was damaged) | 25,000 |
| − Exchanged items back in stock | 1 Jeans × 25,000 | 25,000 |
| **Cost of goods sold** | 125,000 + 30,000 − 25,000 − 25,000 | **105,000** |
| **Gross profit** (25% of net sales) | 140,000 − 105,000 | **35,000** |
| − Running costs (expenses) | X1 | 10,000 |
| **Estimated net profit** | 35,000 − 10,000 | **25,000** |

Also check:
- **Overview** (dashboard) today shows the same sales, profit and expenses.
- **Reports → Sales** with status "Any status": 2 sales, **Total 225,000**.
- **Reports → Purchases**: **Total 800,000**. **Reports → Expenses**: **Total 10,000**.
- **Download CSV** on one report and compare its rows with the screen.
- Final stock: **17 Jeans** (average cost 25,000) and **9 Dresses** (30,000), nothing held.
  Stock value 695,000 + cost of goods sold 105,000 = 800,000 = purchases.
- Customer UAT Customer One still shows **Total spent 90,000**.

## UAT-11 — Access, oversell and cancellation

1. As salesperson, the menu shows only Sell, After-sales and Stock (Inventory, Products).
   Paste these administrator addresses into the address bar; each must be refused
   ("Access not permitted"): `/reports/profit`, `/expenses`, `/purchases`, `/users`,
   `/audit-logs`, `/settings`, `/opening-stock`.
2. Point of sale: add UAT Jeans and type quantity **18** (only 17 are there). The **+**
   stops at the limit, "Only 17 in stock" appears and **Review sale** stays disabled.
   Leave the sale unfinished.
3. New order for UAT Customer One, **1 Jeans**, **Save order**, then **Confirm and reserve
   stock**: Jeans **16 ready, 17 in the shop, 1 held**. **Cancel order** with reason
   `UAT reservation release`: back to **17 / 17 / 0**, no sale or refund. Record its number.
4. **Refunds → Request refund** for S1 (not linked to a return): every line says "nothing
   more can be refunded".
5. On the device used at the counter, note speed, readability, and anything confusing.

## UAT-12 — Catalogue corrections (administrator)

1. **Catalogue setup → Colours → Add colour**: `UAT Navy`. On UAT Jeans press **Add size or
   colour**: M / UAT Navy, **Save**. Its option code is made for you. Do not add stock.
2. On UAT Jeans, edit the original **M · UAT Black** option: change colour to **White**,
   enter the reason `UAT administrator catalogue correction`, save. The option code stays
   the same.
3. Check: stock is still **17** for that option; the new Navy option shows 0 (Out of stock);
   **Reports → Profit** still shows net profit **25,000**; S1's receipt shows the corrected
   colour; **Activity log → What: "Corrected a size or colour"** shows Colour
   **UAT Black → White** and the reason.
4. As salesperson, open the option's edit address (`/products/<id>/variants/<id>/edit`):
   it must be refused.

## Recording acceptance

For each scenario write **PASS**, **FAIL** or **PASS WITH NOTES** in the results sheet with
the tester, date and what you saw. Leave anything not done as **PENDING**. Never write
passwords in the sheet.

Wrong stock, wrong money, changed history or access that should be refused are release
blockers: they are fixed, re-tested automatically, and the scenario is repeated. The owner
reviews all notes and open issues and records acceptance. Production preparation
(hosting, go-live) starts only after that sign-off.
