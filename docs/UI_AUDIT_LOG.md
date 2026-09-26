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
