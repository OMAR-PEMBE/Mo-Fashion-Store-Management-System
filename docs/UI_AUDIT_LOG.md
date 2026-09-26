# UI/UX audit and redesign log

Screen-by-screen review of MFBMS against UI.md, done by auditing each screen in a
real browser (laptop 1440 px and phone 390 px, owner and salesperson roles) with a
disposable sample store, then fixing and re-checking. Business rules and stored
values are unchanged unless a section says otherwise. Each section is its own commit.

## Shared building blocks (used by every section below)

- **Status badges** (`<x-status>`, `App\Support\Status`): plain labels instead of codes
  (`PAYMENT_RECEIVED` → "Paid", `PENDING` → "Waiting for approval") and one meaning per
  colour: amber = needs someone's action, blue = in progress, green = finished,
  grey = closed, red = rejected.
- **Page header** (`<x-page-header>`): back link, title, status badge, one-line
  description, actions on the right.
- **Status tabs with counts** (`<x-filter-tabs>`): one tap filters a list and keeps the search.
- **List tables** (`.data-table`, `.row-link`): charcoal headers, champagne hover,
  right-aligned amounts, whole row clickable. Phones get one card per record instead.
- **Confirmation banner**: 49 success messages existed but only 16 screens showed them.
  Every page now shows them in one place (green, dismissible, announced to screen readers).
- **Outline button** (`variant="outline"`) for secondary actions such as Cancel, so they
  no longer compete with the main action.

## Earlier sections (already committed)

- **Sign-in**: clear error banner, show/hide password, loading state, brand panel, favicon.
- **Dashboard**: three key figures, "Needs attention" list, month statement, best sellers.
- **Sidebar and top bar**: grouped by daily task, icons, account menu, New sale shortcut.
- **Money format**: `TZS 175,000` on screen everywhere; exports stay exact.
- **Point of sale**: two-column counter screen, running total, scanner-friendly search,
  inline customer registration, catalogue-price rule (PRD §21).

## Orders

**Found:** raw status codes (`PAYMENT RECEIVED`, `UNPAID`); no dates in the list; a
dropdown plus button to filter; the next action buried under the items table; no view of
the order's progress; timeline with raw timestamps; the payment dialog silently
preselected Cash; no confirmation messages after actions; "Fulfilment status updated."

**Changed:**
- List: search box, status tabs with counts, date and item count, badges, whole row
  clickable, phone cards, helpful empty states.
- Detail: progress tracker (compact "Step 4 of 6" bar on phones), a "Next step" panel
  at the top with the single action that moves the order forward, items with product
  names first and reservation badges (Reserved / Sold / Released / Not reserved),
  customer, payment and a readable history.
- Payment dialog: one-tap methods, nothing preselected, reference only for non-cash.
- Messages now name the step: "Order marked as preparing."

## Sales history and receipts

**Found:** four filter fields but no quick date choices; no total for the filtered
view; only the sale number clickable; no phone layout. The sale page was titled "Sale
completed", had no way to print a receipt, put the business details in a lone box at the
bottom, and showed three gold after-sales buttons each followed by its own history box.

**Changed:**
- List: Today / This week / This month / All dates shortcuts, search and filters folded
  into one expandable panel, a running line "10 sales · Completed value TZS 1,065,000",
  whole-row links and phone cards.
- Sale page is now the receipt: business header, receipt number, date, staff, customer,
  items (product names first), totals, payment and the receipt footer from Business
  settings. **Print receipt** prints only the receipt (sidebar, header and side panels
  are hidden when printing).
- One "After the sale" panel: Return items / Refund money / Exchange items with a short
  explanation each, and a single combined history with status badges.
- Owners see cost and gross profit for the sale in a separate panel.

## After-sales: returns, refunds and exchanges

**Found:** raw status codes everywhere (`PENDING`, `APPROVED`); lists without customers,
amounts or counts; staff had to type a sale number from memory to start anything; plain
number boxes and dropdowns for quantity and condition; accounting jargon ("Historical
COGS adjustment"); the approval, completion and rejection buttons below long tables; the
refund and exchange settlement dialogs preselected a payment method; no confirmation
after any action.

**Changed (same pattern for all three):**
- Lists: search, status tabs with counts, customer names, amounts or "Customer pays /
  Refund" differences, whole-row links and phone cards.
- Starting one: type a sale number **or tap a recent sale** (returns and exchanges only
  list sales still inside the 3-day window and show when each window closes).
- Returns: −/+ quantity per item and one-tap condition (Sellable / Damaged / Defective /
  Other) with a note saying what each does to stock; proof as one tap, receipt number
  only when a receipt is used; the save button explains what is missing.
- Refunds: per-item limits in plain words, a "Full amount" button, locked boxes for items
  with nothing left to refund, and the running total on the send button.
- Exchanges: items coming back and replacement tiles side by side, with a live estimate
  ("Customer pays about TZS 20,000"); the server still calculates the final amount.
- Detail pages: progress tracker (Requested → Approved → Completed / Money returned), a
  "Next step" panel that knows who may act (e.g. salespeople see "Waiting for an
  administrator" on refunds), clear blocked state when the 3-day window has closed,
  past-tense summaries once done, and "Refund the customer" straight from a completed return.
- Approval and settlement dialogs use one-tap methods with nothing preselected.
- Shared building blocks added: `<x-workflow>` (also used by Orders now; finished
  workflows show every step ticked), `<x-next-step>`, `<x-sale-picker>`, `RecentSales`.

## Stock: inventory and products

**Found:** "Out of stock" shown in the amber warning colour; a checkbox plus button to see
low stock; items sorted by code; stock history with raw codes (`RESERVATION RELEASE`),
references like `sale #12` and before/after columns for two quantities. The product
list had no stock, option count or price range and a separate "View" column; the product
page listed options by code without their stock; inactive items used the warning colour;
the option form used dropdowns and a status select, one option at a time.

**Changed:**
- Inventory: All items / Needs restock / Out of stock tabs with counts (the dashboard's
  "View stock" goes straight to Needs restock), sorted by product name, "Ready to sell /
  In the shop / Held for orders / Restock at" in plain words, red for out of stock.
- Stock history: plain movement names ("Received from supplier", "Held for an order"),
  the real document number linked (MFS-SAL-000009, MFS-PUR-…), a green + / red − change
  and the before → after count only where it changed.
- Products: price range, number of size/colour options and units ready to sell per
  product (computed in the same query); category and status filters apply immediately.
- Product page: options named by size and colour, with price, ready-to-sell count and
  Low / Out badges; archiving moved into a calm "Stop selling this product" panel.
- Forms: sizes and colours as tap chips, plain "Available for sale" switches, and
  **Save and add another**, which keeps the price and colour for the next size.

## Stock: purchases

**Found:** the purchase list had no status tabs, no unit counts and raw codes (`DRAFT`,
`CONFIRMED`); the draft page said "Review purchase draft" but did not explain that
confirming is what adds stock; the form needed a separate Search press for the supplier
and every item, showed no totals until after saving, and used a dropdown for payment.

**Changed:**
- List: search by purchase number, invoice or supplier; All / Drafts / Received /
  Cancelled tabs with counts; units per purchase; "Draft, not received" in amber so
  forgotten drafts stand out; payment and date filters folded away; phone cards.
- Draft page: a "Check the goods, then receive them" panel with **Receive stock**, Edit
  and Cancel; the confirm dialog repeats the units and cost. Received and cancelled
  purchases say what happened, when and by whom.
- Form: supplier and product search as you type, with a "no match" line instead of
  silence; a line total per item and a running units and cost total; Paid / Partly paid /
  Not yet chips; Save stays disabled with the reason shown until the draft is complete.
  On a phone, quantity and cost sit side by side.

## Stock: suppliers

**Found:** the list showed contact details only, with a separate View column and
"Inactive" in the warning colour; the supplier page listed purchases with raw codes
(`CONFIRMED`), phone numbers could not be tapped, and there was no way to start a purchase
from the supplier; the form asked the owner to invent a unique code and used a status
dropdown.

**Changed:**
- List: search with an icon, All / Active / Inactive tabs with counts, and per supplier
  the last date bought, number of received purchases and total bought (drafts and
  cancelled drafts are not counted). Whole rows are links; phone cards on small screens.
- Supplier page: Call and WhatsApp buttons, **New purchase** with the supplier already
  chosen (active suppliers only), four figures (total bought, purchases received, last
  bought, drafts not received, highlighted when there are any) and a history with the
  same plain status badges as Purchases.
- Form: grouped into Business / Who to contact, the next supplier code filled in for you
  in the store's own format (SUP-0001 → SUP-0004), and a plain "Still buying from them"
  switch explaining what switching off does.
- `Phone::international()` now builds tel: and wa.me links for customers and suppliers.

## Stock: opening stock

**Found:** every item took four screens (list → form → review → confirm → back to the
list), so a shop starting with 200 sizes and colours faced about 800 page loads. Items
were listed by code, so a product's sizes were split up and sorted L, M, S; there was no
sense of progress, and the empty state did not say whether you were finished or had no
products yet.

**Changed:**
- The list is now a **count sheet**: up to 30 items per page, grouped by product and
  ordered colour then size (S, M, L), each with a count and a cost-each box. Blank rows
  are skipped, so you can count part of the shop and come back.
- A sticky bar shows items entered, units and stock value at cost as you type; "Copy the
  first cost to the other counted sizes" fills the rest of a product in one tap; leaving
  the page with unreviewed counts asks first.
- One review page for the whole sheet, with each line's value and the total, then
  **Save opening stock**. "Go back and change" returns to the same page with everything
  still typed in. Saving is all-or-nothing: if one item was counted elsewhere in the
  meantime, nothing is saved and that line is named.
- A row with a count but no cost (or the reverse) is highlighted with a plain message.
- A progress bar ("13 counted · 0 still to count"), and empty states for "Everything is
  counted" (with a link to Purchases), "No products to count yet" and no search match.
- The single-item screens remain for old links, restyled to match.

## Money: expenses and expense categories

**Found:** the owner's main question, "how much did we spend this month, and on what?", was
not answered anywhere, because the list had no totals. Six filters were always open. The form
used a category dropdown and a bare number box; the detail page showed history as raw
before/after tables with database timestamps. Categories were loose cards with no sense of
how much each was used.

**Changed:**
- List: This month / Last month / This year / All time chips; a summary card with the
  total spent for the current filters and a bar per category (tap one to filter to it);
  search, with category, person and custom dates folded away; date-first table and
  phone cards.
- Form: a large amount box with a live "TZS 18,500" read-back, categories as tap chips,
  Today / Yesterday next to the date, and **Save and record another**, which keeps the date
  and category for the next receipt and says what was saved.
- The amount and date boxes now keep anything typed before the page's script finishes
  loading (it used to reset them). Other script-driven screens (point of sale, returns)
  still build their inputs from script state; worth the same change if slow phones show it.
- Detail page: the title says what and how much ("Marketing · TZS 12,000"); history is a
  timeline in words ("Amount: TZS 12,000 → TZS 11,000", "Date paid: 26 Sep → 25 Sep").
- Categories: In use / Switched off tabs with counts; each shows how often it was used,
  when last, and the all-time total; the form uses a plain "Available for new expenses"
  switch.
- `Money::round()` turns database sums into exact two-decimal strings.

## Money: reports

**Found:** the hub was nine identical "Open X report" cards that did not say what each one
answers. Report pages opened with every filter spread out and statuses in raw codes
(`COMPLETED`, `PARTIALLY_REFUNDED`). List reports had no totals. The profit report showed
twelve equal-weight boxes, so it was hard to see how "Estimated Net Profit" was reached.

**Changed:**
- Hub: grouped into Money / Stock / Customers / After-sales, each report described by the
  question it answers ("Did we make money this month?"), with Profit highlighted.
  "Inventory" is now called "Stock on hand".
- Report pages: the title plus the period in words; This month / Last month / This year
  chips beside the two dates; status as a picker that applies straight away; all other
  filters under "Narrow down" (opens when one is in use); Download CSV with an icon.
- Tables: status badges and payment names in words, readable dates, numbers
  right-aligned, and a **Total** row for every money column covering every match (not
  just the page).
- Profit is now a **profit statement**: Money in (sales + extra paid on exchanges − refunds
  = net sales), Cost of the goods sold (with returns back in stock subtracted), gross
  profit with its margin, running costs, and the estimated net profit in green or red.
  A side note says in words whether the shop made a profit, lists links to check each
  source, and explains the method in a fold-out.
- The explanation notes for list reports moved into an "About this report" fold-out.
- CSV downloads are unchanged (same columns, exact values).
