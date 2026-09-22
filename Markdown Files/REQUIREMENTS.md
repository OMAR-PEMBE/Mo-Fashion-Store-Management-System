# REQUIREMENTS.md

# Mo Fashion Store Business Management System — MFBMS

## Confirmed Business Requirements

The following decisions have now been confirmed for Version 1.

### Business Structure

Mo Fashion Store currently operates from a **single branch**.

Version 1 shall therefore be designed primarily for one business location.

The architecture should avoid unnecessary multi-branch complexity while keeping future expansion technically possible.

---

## Product Variants

Products may have multiple combinations of important attributes.

For clothing products, the primary variant attributes shall include:

- Size
- Colour

Example:

**Product:** Boyfriend Jeans

Variants:

- Blue / S
- Blue / M
- Blue / L
- Black / S
- Black / M
- Black / L

Each variant shall have its own:

- Variant ID
- Product
- SKU
- Size
- Colour
- Selling price where applicable
- Current stock
- Reserved stock
- Low-stock threshold
- Status

Inventory shall be tracked **per variant**, not only per product.

---

# Supplier Management

Version 1 shall include supplier management.

Each supplier may contain:

- Supplier ID
- Supplier/business name
- Contact person
- Phone number
- Email where applicable
- Location
- Notes
- Status
- Date created

Authorized users shall be able to:

- Register suppliers
- Edit supplier information
- View supplier history
- Deactivate suppliers

---

# Stock Purchase Management

All stock purchased from suppliers shall be recorded.

Each stock purchase shall contain:

- Purchase ID
- Supplier
- Purchase date
- Products/variants purchased
- Quantity purchased
- Unit buying cost
- Total purchase cost
- Payment status where applicable
- Reference/invoice number where available
- Notes
- User who recorded the purchase
- Date created

A stock purchase may contain multiple product variants.

Example:

Purchase #PUR-001

Supplier: Supplier A

Items:

- Boyfriend Jeans / Black / M — 10 units × TZS 25,000
- Boyfriend Jeans / Black / L — 5 units × TZS 26,000
- Crop Top / White / M — 12 units × TZS 12,000

Confirming a valid stock purchase shall increase inventory automatically.

---

# Inventory Cost Tracking

Inventory shall preserve the cost at which stock was purchased.

Buying prices may change between supplier purchases.

Example:

First purchase:

10 Jeans × TZS 25,000

Second purchase:

10 Jeans × TZS 28,000

The system shall not overwrite historical purchase costs when a newer supplier purchase has a different price.

This information shall be used when calculating Cost of Goods Sold and profit.

The exact stock-costing method shall be defined in the database and accounting specification.

Recommended method for Version 1:

**Weighted Average Cost**

This keeps implementation manageable while maintaining reliable profit estimates for a retail clothing business.

---

# Counter Sales

Mo Fashion Store shall support normal physical/counter sales.

For counter sales:

1. Salesperson selects the product variant.
2. Salesperson selects quantity.
3. Customer may optionally be selected or registered.
4. Salesperson selects payment method.
5. Payment is received outside the system.
6. Salesperson records/confirms the payment manually.
7. Sale becomes completed.
8. Inventory is deducted automatically.
9. Customer purchase history is updated where a customer is attached.

Counter sales do not require automatic payment-gateway verification.

---

# WhatsApp Customer Payments

WhatsApp orders shall support integrated payment processing in a future WhatsApp-enabled phase.

Intended workflow:

Customer WhatsApp Inquiry  
→ Product Selection  
→ Order Created  
→ Stock Reserved  
→ Payment Request  
→ Customer Pays  
→ Payment Provider Confirmation  
→ Payment Marked Successful  
→ Sale Created  
→ Stock Deducted  
→ Order Processing

The system shall **not rely only on a staff member manually marking an integrated payment as successful**.

Where payment-provider integration exists, successful payment shall be confirmed using the provider's API/webhook/callback.

The exact payment provider remains:

`TBD`

---

# Payment Recording

The system shall therefore support two payment verification modes.

### Manual Payment

Used primarily for counter sales.

Possible methods:

- Cash
- M-Pesa
- Airtel Money
- Mixx by Yas
- HaloPesa
- Bank
- Other configured methods

A staff member confirms the transaction manually.

### Integrated Payment

Used for supported digital/WhatsApp transactions.

The system receives confirmation from the payment provider before marking the payment as successful.

---

# Returns Management

The system shall support product returns.

Each return shall record:

- Return ID
- Original sale
- Customer where applicable
- Returned product variant
- Quantity
- Reason
- Condition
- Refund status
- Staff member
- Date
- Notes

A return shall reference the original sale whenever possible.

The system shall maintain complete history rather than deleting the original transaction.

---

# Refund Management

The system shall support refunds.

A refund shall include:

- Refund ID
- Original sale
- Return reference where applicable
- Refund amount
- Refund method
- Reason
- Approved by
- Processed by
- Date
- Notes

A refund shall **not delete or alter the historical sale record**.

Instead, the system shall create a separate refund transaction linked to the sale.

Refund permissions should be restricted to authorized users.

---

# Exchange Management

The system shall support product exchanges.

Example:

Customer returns:

Boyfriend Jeans / Blue / M

and receives:

Boyfriend Jeans / Blue / L

The exchange workflow shall:

1. Reference the original sale.
2. Record the returned variant.
3. Record the replacement variant.
4. Update inventory.
5. Calculate any price difference.
6. Record additional payment or refund where required.
7. Preserve the complete exchange history.

---

# Returned Stock Rules

Returned products shall not automatically return to sellable inventory.

Staff shall specify the condition.

Possible conditions:

- Sellable
- Damaged
- Defective
- Other

If marked **Sellable**, inventory may be restored.

If marked **Damaged/Defective**, the item shall not be added to available-for-sale inventory.

---

# Damaged and Lost Stock

Inventory adjustments shall support reasons including:

- Damaged
- Lost
- Theft
- Count correction
- Supplier issue
- Other

All such adjustments shall require:

- Reason
- Quantity
- User
- Date
- Notes where necessary

These changes shall appear in the audit trail.

---

# Delivery Fees

Delivery fees shall **not be tracked as part of Version 1 business accounting**.

Orders may contain delivery information where necessary, but delivery charges shall not affect:

- Sales revenue
- COGS
- Gross profit
- Net profit

unless this requirement is changed later.

---

# Profit Calculation

The system shall calculate both:

## Gross Profit

Gross Profit measures profitability from products sold.

**Gross Profit = Sales Revenue − Cost of Goods Sold**

Example:

Selling price: TZS 40,000  
Recorded stock cost: TZS 25,000

Gross Profit:

TZS 40,000 − TZS 25,000 = **TZS 15,000**

---

## Net Profit

Net Profit shall account for recorded operational expenses.

**Net Profit = Gross Profit − Business Expenses**

Example:

Monthly Sales Revenue: TZS 10,000,000

COGS: TZS 6,000,000

Gross Profit: TZS 4,000,000

Recorded Expenses: TZS 1,200,000

Estimated Net Profit:

**TZS 2,800,000**

---

# Profit Reporting Rules

Only **completed sales** shall contribute to sales revenue.

The following shall reduce or adjust revenue/profit where applicable:

- Refunds
- Returns
- Exchanges involving price differences

Cancelled orders shall not count as revenue.

Unpaid orders shall not count as revenue.

Stock purchases shall not immediately be treated as an expense against profit.

Instead, inventory cost shall become **Cost of Goods Sold when the product is sold**.

Operational expenses shall be deducted separately when calculating net profit.

---

# Dashboard Profit Information

The owner dashboard should display:

### Today

- Sales revenue
- Gross profit
- Expenses
- Estimated net profit

### This Month

- Sales revenue
- Cost of goods sold
- Gross profit
- Expenses
- Estimated net profit

Reports shall allow the owner to inspect how these figures were calculated.

---

# Confirmed Order and Stock Workflow

For customer orders:

**New Order**

↓

**Confirmed**

↓

**Stock Reserved**

↓

**Payment Received**

↓

**Sale Confirmed**

↓

**Stock Permanently Deducted**

↓

**Preparing**

↓

**Out for Delivery**

↓

**Delivered**

If the order is cancelled before completion:

**Reserved Stock → Released Back to Available Stock**

---

# Updated Version 1 Scope

Version 1 shall now include:

1. Authentication
2. User & Staff Management
3. Product Management
4. Product Categories
5. Product Variants
6. Supplier Management
7. Stock Purchase Management
8. Inventory Management
9. Inventory Movement History
10. Counter Sales
11. Customer Management / CRM
12. Order Management
13. Returns
14. Refunds
15. Exchanges
16. Expense Management
17. Profit Calculation
18. Dashboard
19. Reports
20. Audit Logs
21. System Settings

WhatsApp and automated payment integration shall be implemented in the appropriate integration phase.

---

# Remaining Client Decisions

The following still require confirmation.

### Returns

- Approved: returns are allowed for a maximum of three days after purchase.
  Implementation uses 72 elapsed hours from sale completion, inclusive, and
  rechecks the deadline at creation, approval and completion.
- Approved: a receipt or the original sale recorded in the system is sufficient
  proof. Every return must match its original recorded sale and sale items.
- Approved: administrators and salespeople may both approve returns. Existing
  sale visibility still applies; salespeople handle their own sales.

### Refunds

- Can salespeople issue refunds, or only the owner?
- Can refunds be cash/mobile money regardless of the original payment method?

### Exchanges

- Is there a deadline for exchanges?
- Can customers exchange an item for a completely different product?

### Stock Purchasing

- Are supplier purchases always paid immediately?
- Should supplier debt/payables be tracked?

### Customers

- Is customer registration mandatory?
- Should walk-in customers be allowed without customer registration?

### Staff

- Can salespeople see one another's sales?
- Should each salesperson's performance appear separately in reports?

### WhatsApp Payments

- Payment provider/API: `TBD`

### Deployment

- Hosting provider: `TBD`
- Domain: `TBD`
- Backup schedule: `TBD`

---

# Requirements Status

**Status: Draft — Core Business Requirements Confirmed**

The core inventory, product variation, supplier purchasing, counter sales, returns, refunds, exchanges, and profit requirements have now been defined.

Remaining `TBD` requirements must be finalized before their respective features are implemented.
