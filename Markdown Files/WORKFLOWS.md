# WORKFLOWS.md

# Mo Fashion Store Business Management System — MFBMS

## 1. Purpose

This document defines the approved operational and system workflows for the **Mo Fashion Store Business Management System (MFBMS)**.

It aligns the:

- PRD
- Architecture
- Database
- API
- Security
- UI/UX

The goal is to ensure developers implement the same business behavior across the system without inventing missing process rules.

---

# 2. Workflow Principles

Every workflow shall follow these principles:

1. Validate user permissions.
2. Validate input.
3. Validate business rules.
4. Use database transactions for stock/financial operations.
5. Lock inventory where concurrency matters.
6. Create audit records for sensitive actions.
7. Preserve historical transactions.
8. Never directly modify inventory without an inventory movement.
9. Never trust frontend financial calculations.
10. Prevent duplicate transaction processing.

---

# 3. Actors

## Administrator

Can manage:

- Products
- Suppliers
- Purchases
- Inventory
- Sales
- Orders
- Returns
- Refunds
- Exchanges
- Expenses
- Reports
- Staff
- Settings

## Salesperson

Can perform permitted operational activities such as:

- Counter sales
- Customer registration
- Order creation
- Product lookup
- Stock lookup

Exact sensitive permissions remain role-controlled.

## Customer

Does not log into Version 1.

Future customer interaction occurs mainly through WhatsApp.

---

# 4. Product Creation Workflow

## Actor

Administrator

## Flow

```text
Products
→ Add Product
→ Enter product information
→ Save Product
→ Add product variants
→ Product becomes available for inventory operations
```

## Required Information

- Product name
- Product code
- Category

Optional:

- Description
- Default selling price
- Product images

## System Validation

The system shall verify:

- Product code is unique.
- Category exists.
- Required fields are present.

## Result

Product record created.

No inventory is created from product creation alone.

---

# 5. Product Variant Creation Workflow

## Actor

Administrator

## Flow

```text
Open Product
→ Add Variant
→ Select Size
→ Select Colour
→ Enter SKU
→ Enter Selling Price
→ Set Low Stock Threshold
→ Save Variant
```

## Validation

The system shall ensure:

- SKU is unique.
- Product exists.
- Size exists.
- Colour exists.
- Duplicate product/size/colour combination does not exist.
- Selling price is not negative.

## Inventory Effect

No stock increase occurs.

The variant begins with:

```text
Physical Quantity = 0
Reserved Quantity = 0
Available Quantity = 0
```

---

# 6. Supplier Creation Workflow

## Actor

Administrator

## Flow

```text
Suppliers
→ Add Supplier
→ Enter supplier details
→ Save
```

## Result

Supplier becomes available for stock-purchase transactions.

---

# 7. Opening Stock Workflow

Used when deploying the system into a store that already has existing stock.

## Actor

Administrator only

## Flow

```text
Opening Stock
→ Select Variant
→ Enter Existing Quantity
→ Enter Unit Cost
→ Review
→ Confirm Opening Stock
```

## Validation

The system shall ensure:

- Variant exists.
- Quantity > 0.
- Unit cost >= 0.
- Opening stock has not already been improperly duplicated.

## Inventory Effect

```text
physical_quantity += opening_quantity
```

## Inventory Movement

Create:

```text
OPENING_BALANCE
```

## Cost Effect

Opening inventory cost shall initialize the variant's weighted-average cost.

Example:

```text
10 units
Unit Cost = TZS 25,000

Weighted Average Cost = TZS 25,000
```

## Audit

Opening-stock action shall be audited.

Phase 8 implementation: setup is permitted once per active variant, before any
inventory movement, with physical/reserved quantities and existing WAC all zero.
This prevents opening initialization from overwriting costs after trading has
started. Duplicate or no-longer-eligible submissions return 409. Administrator
role and inventory.adjust permission are both required. Cost, balance, movement
and audit writes commit atomically; a failed attempt may be retried.

---

# 8. Supplier Stock Purchase Workflow

## Actor

Administrator or authorized inventory user

## Flow

```text
Purchases
→ New Purchase
→ Select Supplier
→ Enter Purchase Information
→ Add Variants
→ Enter Quantity
→ Enter Unit Cost
→ Save Draft
→ Review
→ Confirm Purchase
```

---

# 9. Purchase Draft Workflow

While status is:

```text
DRAFT
```

the user may:

- Edit supplier
- Add/remove items
- Modify quantity
- Modify unit cost
- Add notes

Inventory shall not change.

---

# 10. Purchase Confirmation Workflow

When user selects:

```text
Confirm Purchase
```

the system shall:

1. Check permission.
2. Confirm purchase status is `DRAFT`.
3. Validate purchase items.
4. Begin database transaction.
5. Lock affected inventory records.
6. Read existing quantity and weighted-average cost.
7. Calculate new weighted-average cost.
8. Increase physical inventory.
9. Create inventory movements.
10. Mark purchase `CONFIRMED`.
11. Record confirmation user/time.
12. Commit transaction.

---

# 11. Weighted Average Cost Workflow

For each affected variant:

```text
Old Stock Value =
Old Physical Quantity × Old Average Cost
```

```text
New Purchase Value =
Purchase Quantity × Purchase Unit Cost
```

Then:

```text
New Weighted Average =
(Old Stock Value + New Purchase Value)
/
(Old Physical Quantity + Purchase Quantity)
```

Example:

```text
Existing:
10 × 25,000 = 250,000

New Purchase:
10 × 30,000 = 300,000

New Total:
20 units
TZS 550,000

Weighted Average:
TZS 27,500
```

---

# 12. Purchase Failure Workflow

If any validation or stock update fails:

```text
ROLLBACK
```

No partial purchase confirmation is allowed.

---

# 13. Purchase Duplicate Protection

A confirmed purchase cannot be confirmed twice.

Repeated confirmation must return:

```text
409 Conflict
```

and inventory must remain unchanged.

---

# 14. Counter Sale Workflow

## Actor

Salesperson / Administrator

## Goal

Complete a physical store sale quickly.

## Flow

```text
POS
→ Search Product
→ Select Variant
→ Enter Quantity
→ Add to Cart
→ Attach Customer or use Walk-in
→ Select Payment Method
→ Review Total
→ Complete Sale
```

---

# 15. Counter Sale Validation

Before completion:

- Variant must be active.
- Quantity must be greater than zero.
- Available inventory must be sufficient.
- Selling price must be valid.
- Payment method must be selected.

---

# 16. Counter Sale Completion

When user clicks:

```text
Complete Sale
```

the system shall:

1. Check permission.
2. Begin database transaction.
3. Lock all affected inventory rows.
4. Re-check available quantity.
5. Read current weighted-average cost.
6. Create sale.
7. Create sale items.
8. Store sale price snapshot.
9. Store unit cost snapshot.
10. Calculate COGS.
11. Calculate gross profit.
12. Reduce physical inventory.
13. Create inventory movement records.
14. Update customer statistics if applicable.
15. Mark sale `COMPLETED`.
16. Commit.

---

# 17. Walk-In Sale Workflow

Customer registration shall not be mandatory for normal counter sales.

Flow:

```text
Customer = Walk-in
→ Complete Sale
```

Database:

```text
customer_id = NULL
```

This allows fast counter operations.

---

# 18. Registered Customer Sale Workflow

If customer exists:

```text
Search Name / Phone
→ Select Customer
→ Complete Sale
```

After sale:

Update:

- First purchase date if first purchase
- Last purchase date
- Total purchase count
- Total amount spent

These values remain recalculable from completed sales.

---

# 19. Manual Counter Payment Workflow

Counter payment methods may include:

- Cash
- M-Pesa
- Airtel Money
- Mixx by Yas
- HaloPesa
- Bank
- Other

Flow:

```text
Customer Pays Outside System
→ Staff Selects Payment Method
→ Optional Reference Entered
→ Staff Confirms Sale
```

The system records the payment method but does not automatically verify these manual counter payments.

---

# 20. Sale Inventory Effect

For each sale item:

```text
physical_quantity -= sold_quantity
```

Create movement:

```text
SALE
```

Reserved quantity is unaffected for normal counter sales.

---

# 21. Sale COGS Workflow

For each item:

```text
unit_cost =
current weighted_average_cost
```

Then store permanently in:

```text
sale_items.unit_cost
```

Historical cost must never change later.

---

# 22. Sale Profit Workflow

For each sale item:

```text
Line Revenue =
Unit Price × Quantity − Discount
```

```text
Line Cost =
Unit Cost × Quantity
```

```text
Line Gross Profit =
Line Revenue − Line Cost
```

Sale:

```text
Gross Profit =
Total Revenue − Total COGS
```

---

# 23. Sale Failure Workflow

If inventory becomes insufficient between product selection and confirmation:

```text
Sale fails
Transaction rolls back
```

User receives:

```text
Only X units are currently available.
```

---

# 24. Customer Registration Workflow

## Actor

Salesperson / Administrator

## Flow

```text
Customers
or POS Quick Customer
→ Enter Basic Details
→ Save
```

Minimum recommended:

- Full name
- Phone

Optional:

- WhatsApp
- Location
- Preferred size
- Preferred colour
- Category preferences
- Notes

---

# 25. Customer Duplicate Check

Before customer creation, search by:

- Phone
- WhatsApp number

If a likely duplicate exists:

Warn the user before creating another record.

---

# 26. Customer Order Workflow

## Actor

Salesperson / Administrator

## Flow

```text
Orders
→ New Order
→ Select Customer
→ Add Products
→ Save Order
```

Initial status:

```text
NEW
```

At this point inventory is not necessarily reserved.

---

# 27. Order Confirmation Workflow

When staff confirms the order:

```text
NEW
→ CONFIRMED
```

The system shall:

1. Begin transaction.
2. Lock affected inventory.
3. Check available quantity.
4. Create stock reservations.
5. Increase reserved quantity.
6. Mark order confirmed.
7. Commit.

---

# 28. Reservation Calculation

For each item:

```text
reserved_quantity += order_quantity
```

Available stock becomes:

```text
physical_quantity - reserved_quantity
```

Physical quantity remains unchanged.

---

# 29. Reservation Inventory Movement

Create reservation movement/event showing:

```text
RESERVATION
```

Even though physical quantity does not change, reservation history must remain traceable.

---

# 30. Order Cancellation Workflow

For an unpaid confirmed order:

```text
CONFIRMED
→ CANCELLED
```

System shall:

1. Begin transaction.
2. Lock inventory.
3. Find active reservations.
4. Reduce reserved quantity.
5. Mark reservation `RELEASED`.
6. Mark order `CANCELLED`.
7. Commit.

Physical quantity does not change.

---

# 31. Manual Order Payment Workflow

Used when staff manually confirms a payment.

Flow:

```text
Order Confirmed
→ Customer Pays
→ Staff Opens Order
→ Mark Payment Received
→ Select Payment Method
→ Enter Reference if applicable
→ Confirm
```

Order payment status:

```text
PAID
```

Order status:

```text
PAYMENT_RECEIVED
```

---

# 32. Order-to-Sale Conversion Workflow

After payment is confirmed:

```text
Paid Order
→ Convert to Sale
```

System shall:

1. Verify payment status.
2. Verify no previous primary sale exists.
3. Begin transaction.
4. Lock inventory.
5. Verify reservations.
6. Create sale.
7. Create sale items.
8. Capture weighted-average cost snapshots.
9. Decrease physical quantity.
10. Decrease reserved quantity.
11. Mark reservations `COMPLETED`.
12. Create inventory movements.
13. Update customer history.
14. Commit.

---

# 33. Order Fulfillment Workflow

After sale creation:

```text
PAYMENT_RECEIVED
→ PREPARING
→ OUT_FOR_DELIVERY
→ DELIVERED
```

Only valid next-state actions should be available.

---

# 34. Integrated WhatsApp Order Workflow — Future Phase

```text
Customer sends WhatsApp message
→ System identifies customer
→ Product inquiry
→ Stock lookup
→ Product selected
→ Order created
→ Order confirmed
→ Stock reserved
→ Payment request created
```

The AI/chat system does not mark payment successful.

---

# 35. Integrated Payment Workflow — Future Phase

```text
Payment Request
→ Provider
→ Customer Pays
→ Provider Webhook
→ Verify Signature
→ Verify Payment Reference
→ Verify Amount
→ Verify Currency
→ Check Idempotency
→ Mark Payment Successful
→ Mark Order Paid
→ Convert Order to Sale
```

---

# 36. Integrated Payment Failure Workflow

If provider reports:

```text
FAILED
```

then:

- Payment marked failed.
- Order remains unpaid.
- Reserved stock remains reserved until cancellation/expiry policy applies.

If provider cannot be verified:

```text
Payment remains PENDING
```

Never assume success.

---

# 37. Duplicate Payment Webhook Workflow

If the same callback arrives again:

```text
Check provider + external reference
→ Already processed
→ Return success acknowledgment
→ Perform no additional business action
```

Inventory must never be deducted twice.

---

# 38. Return Workflow

## Actor

Authorized staff

## Flow

```text
Returns
→ Find Original Sale
→ Select Sale Item
→ Enter Return Quantity
→ Select Condition
→ Enter Reason
→ Review
→ Process Return
```

---

# 39. Return Validation

Owner-approved policy: process returns within three days (72 hours) of original
sale completion, inclusive. Validate this at creation, approval and completion.
A customer receipt or matching recorded sale is accepted as proof; retain the
proof type/reference and original sale/item linkage. Both administrators and
salespeople may approve. Existing sale access and cost visibility still apply.

The internal workflow is PENDING → APPROVED → COMPLETED. Pending or approved
returns can be rejected with a reason, including after the return window expires.
Only completed returns consume returnable quantity. Recheck quantities under an
original-sale lock at approval and completion; pending requests are not stock
reservations. No refund or sale-revenue rewrite occurs in this workflow.

The system shall check:

```text
Return Quantity
<=
Original Sold Quantity
-
Previously Returned Quantity
```

If false:

Reject transaction.

---

# 40. Return Condition Workflow

Possible conditions:

```text
SELLABLE
DAMAGED
DEFECTIVE
OTHER
```

---

# 41. Sellable Return Workflow

If condition:

```text
SELLABLE
```

then:

1. Create return record.
2. Restore physical inventory.
3. Create `RETURN` inventory movement.
4. Reverse associated COGS for financial reporting where applicable.

Use the original:

```text
sale_item.unit_cost
```

not the current weighted-average cost.

---

# 42. Damaged/Defective Return Workflow

If condition is:

```text
DAMAGED
or
DEFECTIVE
```

the item shall not return to sellable inventory.

Return history is still recorded.

---

# 43. Return Financial Effect

Returned quantity reverses relevant historical sale cost.

Example:

```text
Original Unit Cost = TZS 25,000
Return Quantity = 1
```

Returned COGS adjustment:

```text
TZS 25,000
```

---

# 44. Refund Workflow

## Actor

Authorized user

Recommended final policy:

```text
Salesperson may initiate
Administrator approves
```

or owner-only for initial deployment.

## Flow

```text
Find Original Sale
→ Determine Refundable Amount
→ Enter Refund Amount
→ Select Refund Method
→ Enter Reason
→ Approval if required
→ Complete Refund
```

---

# 45. Refund Validation

The system shall enforce:

```text
Completed Refund Total
<=
Eligible Sale Value
```

The original sale must remain intact.

---

# 46. Refund Status Workflow

```text
PENDING
→ APPROVED
→ COMPLETED
```

Alternative:

```text
PENDING
→ REJECTED
```

or:

```text
PENDING
→ CANCELLED
```

---

# 47. Refund Financial Effect

Completed refunds reduce net sales revenue.

Example:

```text
Original Revenue = 80,000
Refund = 40,000

Net Revenue = 40,000
```

---

# 48. Exchange Workflow

## Actor

Authorized staff

## Flow

```text
Find Original Sale
→ Select Returned Item
→ Enter Return Quantity
→ Select Item Condition
→ Select Replacement Variant
→ Check Stock
→ Calculate Price Difference
→ Review
→ Complete Exchange
```

---

# 49. Exchange Price Difference

If:

```text
Replacement Value > Returned Value
```

then:

```text
Customer Pays Difference
```

Example:

```text
Returned = TZS 40,000
Replacement = TZS 45,000

Amount Due = TZS 5,000
```

If:

```text
Returned Value > Replacement Value
```

then:

```text
Refund Due
```

---

# 50. Exchange Inventory Workflow

Returned sellable item:

```text
physical_quantity += returned_quantity
```

Replacement:

```text
physical_quantity -= replacement_quantity
```

Create separate inventory movements:

```text
EXCHANGE_IN
EXCHANGE_OUT
```

---

# 51. Exchange Failure Workflow

If replacement stock is unavailable:

Reject completion.

No partial exchange shall be committed.

---

# 52. Expense Entry Workflow

## Actor

Administrator / authorized finance user

## Flow

```text
Expenses
→ Record Expense
→ Select Category
→ Enter Date
→ Enter Amount
→ Enter Description
→ Save
```

Validation:

```text
amount >= 0
```

---

# 53. Expense Classification Rule

Do not record supplier stock purchases as expenses.

Correct:

```text
Supplier stock → Purchases
```

Correct operating expense:

```text
Rent
Electricity
Internet
Marketing
Transport
Packaging
```

---

# 54. Gross Profit Calculation Workflow

For reporting period:

```text
Net Sales Revenue
-
Adjusted COGS
=
Gross Profit
```

Where:

```text
Net Sales Revenue =
Completed Sale Revenue
-
Completed Refunds
```

and appropriate return effects are included.

---

# 55. Estimated Net Profit Workflow

```text
Gross Profit
-
Operating Expenses
=
Estimated Net Profit
```

MFBMS shall label this:

```text
Estimated Net Profit
```

because it is not full accounting software.

---

# 56. Low Stock Workflow

For every active variant:

```text
Available Quantity =
Physical − Reserved
```

If:

```text
Available Quantity <= Low Stock Threshold
```

status:

```text
LOW STOCK
```

If:

```text
Available Quantity <= 0
```

status:

```text
OUT OF STOCK
```

---

# 57. Low Stock UI Workflow

```text
Dashboard Alert
→ Click Low Stock
→ Inventory page filtered to Low Stock
→ User identifies product
→ Create supplier purchase if required
```

---

# 58. Manual Inventory Adjustment Workflow

## Actor

Authorized administrator

## Flow

```text
Inventory
→ Select Variant
→ Adjust Stock
→ Choose Increase/Decrease
→ Enter Quantity
→ Select Reason
→ Enter Notes
→ Confirm
```

---

# 59. Adjustment Reasons

Examples:

```text
DAMAGED
LOSS
COUNT_CORRECTION
OTHER
```

Every adjustment must create:

- Inventory movement
- Audit log

---

# 60. User Creation Workflow

## Actor

Administrator

## Flow

```text
Users
→ Add User
→ Enter Details
→ Assign Role
→ Set Temporary Password
→ Activate User
→ Save
```

---

# 61. User Deactivation Workflow

When staff leaves:

```text
Users
→ Select Staff
→ Deactivate
→ Confirm
```

User can no longer log in.

Historical transactions remain associated with that user.

---

# 62. Role/Permission Change Workflow

```text
Administrator
→ User/Role
→ Modify Permissions
→ Review
→ Save
```

Sensitive permission changes shall create audit records.

---

# 63. Audit Workflow

Critical actions automatically create audit records.

Examples:

```text
Stock Adjustment
Purchase Confirmation
Sale Cancellation
Refund
Exchange
Permission Change
Expense Modification
```

Audit logs are read-only for normal users.

---

# 64. Sale Cancellation Workflow

Completed sales should not be deleted.

If cancellation is allowed:

```text
Open Sale
→ Cancel Sale
→ Enter Reason
→ Authorized Confirmation
→ Reverse Required Stock/Financial Effects
→ Mark Sale CANCELLED
→ Audit Action
```

Exact cancellation rules may depend on whether goods have already left the store and whether a refund is required.

---

# 65. Transaction Status Rules

## Purchase

```text
DRAFT
→ CONFIRMED
```

or:

```text
DRAFT
→ CANCELLED
```

---

## Order

```text
NEW
→ CONFIRMED
→ PAYMENT_RECEIVED
→ PREPARING
→ OUT_FOR_DELIVERY
→ DELIVERED
```

Cancellation may occur at permitted stages.

---

## Sale

```text
DRAFT
→ COMPLETED
```

Possible later states:

```text
PARTIALLY_REFUNDED
REFUNDED
CANCELLED
```

---

## Return

```text
PENDING
→ APPROVED
→ COMPLETED
```

or rejected/cancelled.

---

## Refund

```text
PENDING
→ APPROVED
→ COMPLETED
```

---

## Exchange

```text
PENDING
→ COMPLETED
```

or:

```text
PENDING
→ CANCELLED
```

---

# 66. Invalid Status Transition Rule

The system must reject invalid transitions.

Example:

```text
NEW Order
→ DELIVERED
```

must not be allowed directly.

---

# 67. Duplicate Submission Workflow

For critical transactions:

```text
User clicks action twice
```

Frontend:

- Disable processing button.

Backend:

- Check transaction status.
- Use idempotency/constraints where necessary.

No duplicate sale, purchase, refund, or payment should be created.

---

# 68. Inventory Concurrency Workflow

Example:

Two salespeople attempt to sell the last item.

System:

```text
Transaction A locks inventory
→ validates quantity
→ sells item
→ commits

Transaction B waits
→ inventory now refreshed
→ insufficient stock
→ sale rejected
```

This is mandatory behavior.

---

# 69. Report Workflow

## Actor

Authorized user

```text
Reports
→ Choose Report
→ Choose Date Range
→ Apply Filters
→ System Calculates
→ Display Summary
→ Display Details
→ Optional Export
```

Reports must calculate from transactional source data.

---

# 70. Sales Report Workflow

Inputs may include:

- Date range
- Product
- Category
- Salesperson
- Payment method

Outputs:

- Sales total
- Sale count
- Product performance
- Transaction list

---

# 71. Profit Report Workflow

Inputs:

```text
Date From
Date To
```

System calculates:

1. Completed sales.
2. Refund adjustments.
3. Net revenue.
4. COGS.
5. Return adjustments.
6. Gross profit.
7. Operating expenses.
8. Estimated net profit.

---

# 72. Customer Purchase History Workflow

```text
Customer Profile
→ Purchase History
```

System reads:

```text
Customer
→ Completed Sales
→ Sale Items
```

Do not store duplicated textual history.

---

# 73. Best Customer Workflow

For selected period:

```text
Calculate net completed sales per customer
→ Sort descending
```

The highest valid net customer spend may be shown as top customer.

Walk-in sales are excluded from named-customer ranking.

---

# 74. Product Performance Workflow

For selected period:

```text
Completed Sale Items
→ Adjust Returns
→ Group by Product/Variant
→ Calculate Units Sold
→ Calculate Revenue
```

---

# 75. Backup Workflow

Production:

```text
Scheduled Backup
→ Export Database
→ Secure Storage
→ Verify Backup Job
```

Recommended daily.

Periodically:

```text
Backup
→ Restore Test Environment
→ Verify Data
```

---

# 76. Session Expiry Workflow

If authenticated session expires:

```text
User performs action
→ Authentication check fails
→ Redirect/login response
→ No transaction executed
```

Never partially execute protected actions before authentication validation.

---

# 77. Authorization Failure Workflow

If user lacks permission:

```text
Request
→ Authorization Check
→ Deny
```

Response:

```text
403 Forbidden
```

No business operation shall occur.

---

# 78. Validation Failure Workflow

Example:

```text
Sale Quantity = 0
```

System:

```text
Reject Request
→ Show field error
→ Preserve form state
```

No transaction begins.

---

# 79. System Error Workflow

If an unexpected error happens during a transaction:

```text
ROLLBACK
→ Log Technical Error
→ Show Safe User Message
```

Never expose stack traces to production users.

---

# 80. Future WhatsApp Product Inquiry Workflow

```text
Customer:
"Do you have black boyfriend jeans size M?"
```

System:

1. Identify product.
2. Identify requested variant.
3. Query actual inventory.
4. Query selling price.
5. Generate response.

AI shall not invent stock.

---

# 81. Future WhatsApp Human Handoff Workflow

If system/AI cannot safely handle request:

```text
Customer Message
→ AI cannot resolve
→ Conversation marked NEEDS_STAFF
→ Staff notified
→ Staff takes over
```

---

# 82. Future WhatsApp Marketing Workflow

Before promotional message:

```text
Customer marketing_opt_in?
```

If:

```text
false
```

do not send.

If:

```text
true
```

customer may be included in approved campaign segment.

---

# 83. System Onboarding Workflow

Recommended initial deployment process:

```text
Configure Business Settings
→ Create Administrator
→ Add Categories
→ Add Sizes
→ Add Colours
→ Add Products
→ Add Variants
→ Add Suppliers
→ Enter Opening Stock
→ Create Staff
→ Verify Stock
→ Begin Operations
```

---

# 84. End-of-Day Operational Workflow

Recommended owner routine:

```text
Dashboard
→ Review Today's Sales
→ Review Orders
→ Check Low Stock
→ Review Expenses
→ Review Any Returns/Refunds
```

No formal day-closing accounting module is required in Version 1 unless later requested.

---

# 85. Critical Workflow Rules

The following rules are locked:

1. Product creation does not add stock.
2. Stock enters through purchase, opening stock, return, or authorized adjustment.
3. Stock leaves through sale, exchange, damage, loss, or adjustment.
4. Counter sales reduce physical inventory immediately on completion.
5. Orders reserve stock before sale completion.
6. Reservations reduce availability, not physical stock.
7. Paid orders convert into sales.
8. Sale cost snapshots never change later.
9. Supplier purchases affect weighted-average cost.
10. Sales do not recalculate weighted-average cost.
11. Returns reference original sales.
12. Refunds do not delete original sales.
13. Exchanges preserve both returned and replacement inventory movements.
14. Stock purchases are not operating expenses.
15. Profit is calculated from transactional records.
16. Delivery fees remain outside Version 1 accounting.
17. Integrated payments require provider verification.
18. AI cannot independently alter financial or inventory records.

---

# 86. Workflow Acceptance Criteria

The workflow design is considered implementation-ready when developers can answer for every transaction:

- Who initiates it?
- What status is required?
- What validations apply?
- Does stock change?
- Does reserved stock change?
- Does money/revenue change?
- Does COGS change?
- What transaction records are created?
- What audit record is required?
- What happens on failure?
- Can the request safely run twice?

This document defines those answers for the core Version 1 operations.

---

# 87. Next Document

The next document should be:

**`TESTING.md`**

It shall define:

- Unit tests
- Feature tests
- Integration tests
- Security tests
- Inventory tests
- Weighted-average-cost tests
- Sales tests
- Reservation tests
- Return/refund/exchange tests
- Permission tests
- Payment webhook tests
- WhatsApp tests
- UI workflow tests
- User acceptance testing
- Production readiness testing

After `TESTING.md`, create:

**`IMPLEMENTATION_PLAN.md`**

and:

**`TASKS.md`**

Then coding can begin with a controlled development sequence.

---

# 88. Document Status

### Phase 11 implementation clarification

Orders require a registered customer. Manual full-payment recording and sale
conversion are separate actions. Conversion completes the order's reservations
and creates one sale; fulfilment cannot begin before that sale exists. New order
creation is retry-safe; repeated confirmation, payment, conversion, cancellation
or invalid fulfilment transitions are rejected without repeating stock changes.
Unpaid cancellation releases only the cancelled order's reservation. Paid-order
cancellation remains blocked pending approved return/refund rules. Reservation
expiry is not automated. Existing paid reservations can still be fulfilled after
reference archival. The order salesperson retains sale attribution, while audits
identify each operator. See docs/PHASE_11.md for verification and limitations.

**Status: Core Business and System Workflows Locked**

The core Version 1 operational workflows now align with the approved PRD, architecture, database, API, security, and UI specifications.

Provider-specific WhatsApp/payment details remain `TBD` until their official integrations are selected.
