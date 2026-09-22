# Phase 10 — Counter sales / POS

## Implemented

Normal counter sales now support product/variant search, a multi-item cart, quantity
and selling-price controls, line discounts, walk-in or registered customers, manual
payment method/reference, server-calculated review, confirmation and a completed
sale page. Search results show current available stock; completion rechecks it.
Customer registration opens in another tab to preserve the cart. Lookup results
are capped at 20; carts at 100 distinct variants. JavaScript is required for POS.

SaleService.completeSale and calculateTotals use validated values and exact decimal
arithmetic. Unit prices and line discounts are staff-entered values as specified
in the sales request contract. Discounts cannot exceed line value; fractional
quantities, excessive decimal precision, negative prices and monetary overflow are
rejected. Costs and totals supplied by the client are ignored. No price-override
approval or minimum-margin rule was invented.

Completion authorizes the current actor and locks the sale-number sequence,
optional customer, sorted parent products, sorted variants and stock rows. It checks
availability, reads current WAC and writes immutable sale-item cost/price snapshots,
COGS/profit, stock movements, customer statistics and an audit record atomically.
Sales never recalculate WAC. Stock reductions cannot consume reservations.
Any failure rolls back the sale, items, movement, statistics and number allocation.

Sales use MFS-SAL-000001 references. A unique request key and canonical payload hash
prevent duplicate writes. An identical retry returns the original sale; a changed
payload or actor with that key receives 409. The single-branch sequence lock also
serializes counter completions; this is a deliberate simple consistency boundary,
not a high-throughput multi-branch design. Conflict pages preserve the cart and
show the available quantity where appropriate. No delete/update-sale endpoint exists.

Walk-in sales store customer_id NULL and never create placeholder customers.
Registered sales atomically update first/last purchase dates, completed purchase
count and total spending. Customer pages now show actual sales visible to the
current staff member. These cached statistics can be derived from sale records;
later refund/cancellation workflows must update them consistently with their rules.

sales.create protects POS, lookup and sales routes; the service authorizes writes.
Without sales.view_all staff can only list/open their own sales. Cost/profit fields
are hidden from model serialization and require products.view_cost in the UI.
Manual payment recording does not verify provider payment success. The browser's
payment acknowledgement is staff confirmation, not external proof of payment.

## Cancellation — pending business decision

API_SPEC.md section 44 and WORKFLOWS.md section 64 leave exact cancellation policy
dependent on physical returns and refunds. A clarification was requested; in the
absence of an approved policy, cancellation remains disabled. SaleService.cancelSale
checks sales.cancel and then rejects the operation with 409. No reversal, stock
return or financial change is performed. The permission and protected endpoint
are prepared; a functioning cancellation workflow is explicitly not claimed.

Normal-sale exit criteria are implemented. Approval of cancellation/refund rules
is still required before enabling that part of the phase plan.

## Files

Created the sales/sale_items migration, SaleStatus enum, Sale/SaleItem models,
SaleService, SaleController and five sales views (POS, review, history, detail,
conflict). Added SaleTest. Modified routes, sidebar, permission catalogue, Alpine
script, customer sales relationship/controller/history, foundation route test and
MySQL concurrency tests/worker. Updated README, setup instructions and database
implementation notes. Earlier uncommitted Phase 8/9 work was preserved.

The nullable order_id column is reserved for Phase 11; its foreign key comes with
the orders table. No order, refund, return, exchange, payment-provider or accounting
module was introduced. No dependency was added.

## Verification

- Application suite: **131 tests, 955 assertions passed**.
- Dedicated MySQL suite: **15 tests, 219 assertions passed**.
- Ten sale feature tests cover walk-ins, registered statistics/history, multiple
  lines, historical cost after later purchasing, exact totals/discounts, invalid
  input, insufficient available stock, archived/inactive references, retries and
  mismatched keys, audit rollback, staff visibility/cost secrecy, pending-cancellation
  rejection, CSRF, conflict recovery and untrusted line-total replacement.
- Two additional real MySQL races exercise counter-sale competition for the final
  item and simultaneous identical retries. The purchase-versus-sale race now calls
  the full SaleService, verifying quantity and WAC consistency with purchasing.
- Chrome verified walk-in and registered-customer sales, product/customer search,
  cart editing, review/back-to-cart preservation, confirmation, receipt values and
  customer history. POS/review/history/detail were checked at 1440px and 390px,
  with no measured page overflow or JavaScript exceptions. Desktop POS and mobile
  review screenshots were inspected.
- Pint, production asset build, Blade compilation, route caching and diff checks
  passed. Development migration applied. No real store sale or stock change was
  created for testing; disposable browser credentials/database were removed.

UI_SPEC.md remains unavailable; the approved UI.md design system is used. The
broader application is still under phased development. Next planned module:
Phase 11 — Orders & Stock Reservations. No commit or push performed.
