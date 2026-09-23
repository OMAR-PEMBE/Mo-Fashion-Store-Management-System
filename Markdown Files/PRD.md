# PRD.md

# Mo Fashion Store Business Management System — MFBMS

## 1. Document Purpose

This Product Requirements Document defines the product scope, users, modules, workflows, business rules, user stories, acceptance criteria, constraints, risks, and phased delivery plan for the **Mo Fashion Store Business Management System (MFBMS)**.

The system is intended to centralize the core operations of Mo Fashion Store, including:

- Products
- Product variants
- Suppliers
- Stock purchases
- Inventory
- Sales
- Customers
- Orders
- Returns
- Refunds
- Exchanges
- Expenses
- Profit tracking
- Reports
- Staff management

Future phases will introduce WhatsApp integration, automated payments, customer messaging, marketing automation, and AI customer service.

---

# 2. Product Background

Mo Fashion Store is a women's fashion retail business selling products such as:

- Crop tops
- Boyfriend jeans
- Skinny jeans
- Straight jeans
- Official pants
- Shirts
- T-shirts
- Dresses
- Two-piece outfits
- Official wear
- Jackets
- Skirts
- Accessories

The business case identifies growing difficulty in managing sales records, stock, customer history, WhatsApp inquiries, customer follow-up, and business reporting manually.

The system is intended to provide one centralized source of business information.

---

# 3. Problem Statement

Mo Fashion Store currently faces operational problems including:

- Incomplete or slow sales record keeping
- Manual stock counting
- Difficulty identifying fast-moving products
- Lack of a structured customer database
- Lack of customer purchase history
- Difficulty tracking repeat customers
- Manual customer follow-up
- Slow responses to customer inquiries
- Lack of reliable reports for decision-making

These problems are explicitly identified in the original business case.

---

# 4. Product Vision

Build a centralized retail-management platform that enables Mo Fashion Store to manage daily business operations accurately and efficiently while creating a strong foundation for future customer automation.

Long-term customer journey:

`Customer Inquiry → Product Recommendation → Order → Payment → Delivery → Sale Record → Customer Follow-Up → Repeat Purchase`

This follows the long-term vision defined in the supplied business case.

---

# 5. Product Goals

The product shall help Mo Fashion Store:

1. Maintain accurate stock records.
2. Maintain accurate sales records.
3. Track product variants by size and colour.
4. Record supplier purchases.
5. Maintain customer profiles and purchase history.
6. Manage customer orders.
7. Handle returns, refunds, and exchanges.
8. Track business expenses.
9. Calculate gross and estimated net profit.
10. Identify best-selling products.
11. Identify valuable and repeat customers.
12. Provide useful business reports.
13. Provide reliable information for future WhatsApp automation.
14. Reduce manual administrative work.

---

# 6. Non-Goals for Version 1

Version 1 shall not attempt to become:

- A full accounting system
- A payroll system
- An ERP
- A multi-branch management system
- An e-commerce website
- A customer mobile application
- An AI chatbot platform
- A WhatsApp marketing platform
- A full delivery-management system

These capabilities may be considered separately in future phases.

---

# 7. Business Structure

Version 1 is designed for:

**One Mo Fashion Store branch.**

Multi-branch functionality is not required for the initial release.

The internal design should avoid unnecessary assumptions that would make future branch expansion impossible.

---

# 8. Primary Users

## 8.1 Business Owner / Administrator

The owner shall have full access to the system.

The owner can:

- View the dashboard
- Manage staff
- Manage products
- Manage variants
- Manage suppliers
- Record supplier purchases
- Manage inventory
- Record sales
- View all sales
- Manage customers
- Manage orders
- Approve sensitive actions
- Manage returns
- Manage refunds
- Manage exchanges
- Record expenses
- View profit
- View reports
- View audit logs
- Configure system settings

---

## 8.2 Salesperson

Salespeople shall have restricted operational access.

They may:

- View products
- View available inventory
- Search stock
- Register customers
- Record counter sales
- Create orders
- Update permitted order statuses
- Initiate returns/exchanges where permitted
- View their permitted sales information

They shall not automatically have access to:

- User administration
- System configuration
- Audit-log modification
- Sensitive owner-only financial functions
- Unauthorized refunds
- Unauthorized inventory adjustments

Exact permissions shall be implemented through role-based access control.

---

## 8.3 Customer

Customers shall not directly log into Version 1.

Their information may be stored in the CRM.

Future versions may interact with customers through WhatsApp.

---

# 9. Product Modules

Version 1 shall contain the following core modules:

1. Authentication
2. Dashboard
3. Users & Staff
4. Product Categories
5. Products
6. Product Variants
7. Suppliers
8. Stock Purchases
9. Inventory
10. Inventory Movements
11. Sales / POS
12. Customers / CRM
13. Orders
14. Returns
15. Refunds
16. Exchanges
17. Expenses
18. Profit Tracking
19. Reports
20. Audit Logs
21. System Settings

---

# 10. Authentication Module

## Purpose

Protect the system and ensure users only access permitted functionality.

## Features

- Login
- Logout
- Password change
- Password reset
- Account activation/deactivation
- Role-based access control
- Session management

## User Story

As a staff member, I want to securely log in so that I can access only the functions I am authorized to use.

## Acceptance Criteria

- Invalid credentials are rejected.
- Inactive users cannot log in.
- Passwords are securely hashed.
- Users can only access functions permitted by their role.
- Sessions expire according to configured security rules.

---

# 11. Dashboard Module

Phase 16 is implemented; see docs/PHASE_16.md. Owner-approved profit policy keeps
the original cost of damaged/defective non-sellable returns. Only sellable completed
returns/exchange receipts reverse historical cost. Exchange replacements add their
captured cost. The dashboard displays today/month-to-date figures using local
business time and excludes restricted financial data from salesperson payloads.

## Purpose

Provide the owner with an immediate view of business performance.

## Dashboard Metrics

### Today

- Sales revenue
- Number of sales
- Gross profit
- Expenses
- Estimated net profit
- Orders
- New customers

### Current Month

- Sales revenue
- COGS
- Gross profit
- Expenses
- Estimated net profit
- Number of orders

### Inventory

- Number of products
- Total stock
- Low-stock variants
- Out-of-stock variants

### Performance

- Top-selling products
- Top-selling variants
- Highest-value customers
- Recent sales
- Recent orders

The original business case requires dashboard visibility into sales, expenses, estimated gross profit, top products, and customer performance.

---

# 12. Product Category Module

## Features

Authorized users can:

- Create categories
- Update categories
- View categories
- Deactivate categories

Example categories:

- Jeans
- Crop Tops
- Dresses
- Official Wear
- T-Shirts
- Accessories

---

# 13. Product Module

## Product Fields

Each product shall include:

- Product ID
- Product name
- Product code
- Category
- Description
- Default selling price
- Status
- Date created
- Date updated

Products shall support multiple variants.

---

# 14. Product Variant Module

## Purpose

Track clothing combinations accurately.

Variants shall primarily use:

- Size
- Colour

Example:

Product:

`Boyfriend Jeans`

Variants:

- Blue / S
- Blue / M
- Blue / L
- Black / M
- Black / L

The original business case explicitly requires product tracking by product, size, colour, buying price, selling price, and quantity.

## Variant Fields

- Variant ID
- Product ID
- Variant SKU
- Size
- Colour
- Selling price
- Available quantity
- Reserved quantity
- Weighted average cost
- Low-stock threshold
- Status

---

# 15. Supplier Module

## Purpose

Maintain supplier information and provide a source for stock-purchase records.

## Supplier Fields

- Supplier ID
- Supplier/business name
- Contact person
- Phone
- Email
- Location
- Notes
- Status

## Features

- Create supplier
- Edit supplier
- View supplier
- Deactivate supplier
- View purchase history

---

# 16. Stock Purchase Module

## Purpose

Record inventory acquired from suppliers.

## Purchase Fields

- Purchase ID
- Supplier
- Purchase date
- Reference/invoice number
- Purchase items
- Total purchase amount
- Payment status where applicable
- Notes
- Recorded by
- Created date

## Purchase Item Fields

- Product variant
- Quantity
- Unit buying cost
- Line total

## Business Rule

Confirming a stock purchase shall increase physical inventory.

---

# 17. Inventory Costing

Version 1 shall use:

**Weighted Average Cost**

When additional stock is purchased at a different cost, the system shall recalculate the weighted average cost of the remaining inventory.

The system shall not simply overwrite historical costs with the latest supplier price.

---

# 18. Inventory Module

## Purpose

Maintain accurate physical and available stock.

## Inventory Values

Each variant shall track:

- Physical quantity
- Reserved quantity
- Available quantity

Where:

`Available Quantity = Physical Quantity − Reserved Quantity`

## Inventory Operations

The system shall support:

- Stock purchase
- Sale deduction
- Stock reservation
- Reservation release
- Return to stock
- Damaged stock
- Lost stock
- Manual adjustment

---

# 19. Inventory Movement History

Every inventory-changing event shall create an inventory movement record.

Movement types may include:

- PURCHASE
- SALE
- RESERVATION
- RESERVATION_RELEASE
- RETURN
- EXCHANGE_IN
- EXCHANGE_OUT
- DAMAGE
- LOSS
- ADJUSTMENT

Each movement shall record:

- Variant
- Quantity
- Movement type
- Reference transaction
- User
- Date/time
- Notes

---

# 20. Low Stock Management

Each variant shall have a configurable low-stock threshold.

Example:

Boyfriend Jeans  
Black  
Size M  
Stock Remaining: 2

The original business case explicitly requires low-stock alerts.

The system shall flag:

- Low stock
- Out of stock

---

# 21. Counter Sales / POS Module

## Purpose

Allow staff to record physical in-store sales.

## Counter Sale Workflow

`Select Product → Select Variant → Enter Quantity → Select/Register Customer if needed → Select Payment Method → Confirm Payment → Complete Sale`

After completion:

- Sale is created.
- Inventory is reduced.
- COGS is recorded.
- Customer history is updated if a customer is attached.
- Staff performance is updated.
- Profit information becomes available.

The original business case requires sales to reduce stock automatically.

---

# 22. Sale Fields

Each sale shall include:

- Sale ID
- Customer where applicable
- Salesperson
- Date/time
- Payment method
- Sale items
- Subtotal
- Discount if applicable
- Total
- Status
- Notes

---

# 23. Sale Items

Each sale item shall preserve:

- Product variant
- Quantity
- Selling price snapshot
- Cost snapshot
- Discount
- Line total

Historical transaction data shall not change when product or supplier prices later change.

---

# 24. Payment Methods

Version 1 counter sales shall support manually recorded methods such as:

- Cash
- M-Pesa
- Airtel Money
- Mixx by Yas
- HaloPesa
- Bank
- Other

Counter-sale payments shall be marked manually by authorized staff.

---

# 25. Customer / CRM Module

## Purpose

Maintain customer information and purchasing history.

## Customer Fields

- Customer ID
- Full name
- Phone
- WhatsApp number
- Location
- Preferred category
- Preferred size
- Preferred colour
- First purchase date
- Last purchase date
- Total purchases
- Total amount spent
- Notes

The original business case specifies customer information including preferences, purchase dates, spending, and purchase history.

---

# 26. Customer History

The system shall automatically generate customer purchase history from completed sales.

Customer profile should show:

- Previous purchases
- Products purchased
- Dates
- Quantities
- Amount spent
- Total purchases
- Total spending
- Last purchase

---

# 27. Customer Registration Rule

Whether every sale must have a registered customer remains:

`TBD`

Recommended implementation:

Allow walk-in sales without customer registration.

Registered customers should be attached whenever available so CRM and repeat-purchase data remain useful.

---

# 28. Order Management Module

## Purpose

Manage customer orders before completion.

## Order Fields

- Order ID
- Customer
- Order items
- Total
- Payment status
- Order status
- Delivery information if needed
- Salesperson
- Notes
- Created date
- Updated date

---

# 29. Order Status Workflow

Core workflow:

`New → Confirmed → Payment Received → Preparing → Out for Delivery → Delivered`

Additional status:

`Cancelled`

This follows the order lifecycle proposed in the original business case.

---

# 30. Order Reservation Workflow

The approved internal workflow shall be:

`New Order`

↓

`Confirmed`

↓

`Stock Reserved`

↓

`Payment Received`

↓

`Sale Confirmed`

↓

`Stock Permanently Deducted`

↓

`Preparing`

↓

`Out for Delivery`

↓

`Delivered`

If cancelled before completion:

`Reserved Stock → Released`

---

# 31. Delivery

Delivery information may be stored when needed.

Delivery fees shall **not be tracked in Version 1 accounting**.

Delivery fees shall therefore not affect:

- Revenue
- COGS
- Gross profit
- Net profit

---

# 32. Returns Module

## Purpose

Record merchandise returned after a sale.

## Return Fields

- Return ID
- Original sale
- Customer
- Product variant
- Quantity
- Reason
- Item condition
- Return date
- Staff member
- Notes

## Item Conditions

Possible conditions:

- Sellable
- Damaged
- Defective
- Other

---

# 33. Return Inventory Rule

If returned item condition is:

### Sellable

The quantity may return to available inventory.

### Damaged / Defective

The item shall not return to sellable inventory.

The inventory movement history shall reflect the result.

---

# 34. Refund Module

Refunds shall reference the original transaction.

Fields:

- Refund ID
- Original sale
- Return reference where applicable
- Amount
- Refund method
- Reason
- Approved by
- Processed by
- Date
- Notes

Refunding a customer shall not delete the original sale.

---

# 35. Exchange Module

The system shall support exchanging one item for another.

Example:

Return:

Boyfriend Jeans / Blue / M

Receive:

Boyfriend Jeans / Blue / L

The system shall:

1. Reference the original sale.
2. Record returned item.
3. Record replacement item.
4. Adjust stock correctly.
5. Calculate price differences.
6. Record additional payment or refund.
7. Preserve the full transaction history.

---

# 36. Expense Module

Authorized users shall be able to record operating expenses.

Possible expense categories:

- Rent
- Electricity
- Internet
- Packaging
- Marketing
- Transport
- Staff
- Other

Fields:

- Expense ID
- Category
- Description
- Amount
- Date
- Recorded by
- Notes

---

# 37. Accounting Model

The system is **not intended to replace professional accounting software**.

It shall provide operational profitability estimates using retail sales and recorded expenses.

---

# 38. Cost of Goods Sold

Stock purchases shall not automatically count as operating expenses.

Instead:

Purchased stock becomes inventory.

When inventory is sold, its weighted-average cost becomes:

**Cost of Goods Sold — COGS**

---

# 39. Gross Profit

The system shall calculate:

`Gross Profit = Sales Revenue − COGS`

Example:

Sales Revenue: TZS 40,000

COGS: TZS 25,000

Gross Profit:

TZS 15,000

---

# 40. Estimated Net Profit

The system shall calculate:

`Estimated Net Profit = Gross Profit − Recorded Operating Expenses`

Example:

Sales Revenue: TZS 10,000,000

COGS: TZS 6,000,000

Gross Profit: TZS 4,000,000

Operating Expenses: TZS 1,200,000

Estimated Net Profit:

TZS 2,800,000

---

# 41. Profit Adjustment Rules

Only completed sales count toward revenue.

The following shall affect profitability:

- Refunds
- Returns
- Exchange price differences

The following shall not count as revenue:

- Cancelled orders
- Unpaid orders
- Reserved orders

---

# 42. Reports Module

Version 1 shall include:

- Daily Sales Report
- Weekly Sales Report
- Monthly Sales Report
- Product Sales Report
- Product Variant Report
- Customer Report
- Customer Purchase History
- Supplier Purchase Report
- Inventory Report
- Stock Movement Report
- Low Stock Report
- Expense Report
- Gross Profit Report
- Estimated Net Profit Report
- Returns Report
- Refund Report
- Exchange Report

The original business case requires sales, product, customer, inventory, low-stock, expense, profit, and customer-history reports.

---

# 43. Report Filters

Reports should support filters including:

- Date range
- Product
- Variant
- Category
- Customer
- Supplier
- Salesperson
- Payment method
- Order status
- Transaction type

---

# 44. Users & Staff Module

Owner shall be able to:

- Create users
- Assign roles
- Activate users
- Deactivate users
- Reset access
- Manage permissions

Minimum roles:

- Administrator
- Salesperson

---

# 45. Audit Logs

Critical activities shall be logged.

Examples:

- Stock adjustment
- Price change
- Sale cancellation
- Refund
- Exchange
- Product deactivation
- Supplier modification
- User permission change
- Expense modification

Audit records should include:

- User
- Action
- Entity
- Old value where applicable
- New value where applicable
- Date/time

Normal users shall not be able to modify audit history.

---

# 46. Search

Users should be able to search by:

- Product name
- SKU
- Customer name
- Customer phone
- Supplier
- Sale ID
- Order ID
- Purchase ID

---

# 47. Filtering

Relevant screens should support:

- Date range
- Category
- Size
- Colour
- Stock level
- Transaction status
- Salesperson
- Payment method

---

# 48. Data Validation

Examples of required validation:

- Quantity must be greater than zero.
- Prices cannot be negative.
- Expenses cannot be negative.
- Order must contain at least one item.
- Sale must contain at least one item.
- Product variants must reference valid products.
- Supplier purchase items must reference valid variants.
- System shall prevent negative stock by default.

---

# 49. Security Requirements

The system shall include:

- Secure password hashing
- Authentication
- Authorization
- Role-based permissions
- Server-side validation
- CSRF protection where applicable
- Secure session handling
- Audit logs
- Data backups
- Protection of customer information

The original business case also calls for username/password authentication, different permissions, backups, audit records, and encryption where possible.

---

# 50. Customer Data Protection

Sensitive customer data includes:

- Name
- Phone number
- WhatsApp number
- Location
- Purchase history

Future marketing functionality shall require appropriate customer consent.

---

# 51. Usability Requirements

The product shall be:

- Easy for non-technical staff
- Responsive
- Mobile-friendly where practical
- Optimized for quick sales entry
- Easy to navigate
- Clear about transaction status
- Clear about errors and validation

---

# 52. Responsive Interfaces

Primary target devices:

1. Desktop
2. Laptop
3. Tablet

Mobile support shall be included for operational convenience.

The POS workflow should remain usable on a tablet or mobile screen.

---

# 53. Performance Requirements

Normal operations should feel responsive.

Critical operations include:

- Product search
- POS product selection
- Completing sales
- Customer lookup
- Inventory lookup
- Dashboard loading
- Report filtering

Exact SLA/performance benchmarks:

`TBD`

---

# 54. Backup Requirements

The system shall support automated database backups.

Final decisions required:

- Backup frequency: `TBD`
- Backup retention: `TBD`
- Off-site backup location: `TBD`

Recommended later:

- Daily automatic backup
- Minimum 7-day retention
- Additional periodic external backup

---

# 55. Phase 2 — WhatsApp Integration

Phase 2 may introduce:

- WhatsApp Business Platform integration
- Customer notifications
- Order confirmations
- Order-status notifications
- Marketing messages
- Customer consent management

The original business case places WhatsApp integration and customer notifications after the core MVP.

---

# 56. Phase 2 — Integrated Payments

WhatsApp orders may support integrated payments.

Expected workflow:

`WhatsApp Inquiry`

↓

`Order Created`

↓

`Stock Reserved`

↓

`Payment Request`

↓

`Customer Pays`

↓

`Payment Provider Callback/Webhook`

↓

`Payment Verified`

↓

`Sale Confirmed`

↓

`Stock Deducted`

The payment provider remains:

`TBD`

---

# 57. Phase 3 — AI & Automation

Phase 3 may introduce:

- AI customer-service assistant
- Automated WhatsApp responses
- Stock-aware responses
- Product recommendations
- Customer segmentation
- Automated campaigns
- Follow-up automation
- Advanced analytics

The original business case also proposes an AI assistant capable of answering questions about products, price, size, colour, stock, delivery, payment methods, offers, and store information.

---

# 58. Key User Stories

## Products

As an administrator, I want to create products and their variants so that stock can be tracked accurately.

## Inventory

As an administrator, I want to see available stock by size and colour so that I know exactly what is available.

## Purchases

As an administrator, I want to record supplier purchases so that stock and inventory costs remain accurate.

## Sales

As a salesperson, I want to record a customer sale quickly so that stock and business records update immediately.

## Customers

As a salesperson, I want to find customers using their phone number so that I can quickly access their history.

## Orders

As a salesperson, I want to reserve products for an order so that the same item is not sold twice.

## Returns

As an authorized user, I want to record returned items so that inventory and financial records remain accurate.

## Exchanges

As an authorized user, I want to exchange one variant for another while preserving the original transaction history.

## Profit

As the owner, I want to see gross and estimated net profit so that I can understand business performance.

## Reports

As the owner, I want to filter reports by date, product, and salesperson so that I can evaluate performance.

---

# 59. Critical Business Rules

1. Product variants shall be separately identifiable.
2. Inventory shall be maintained per variant.
3. Supplier purchases shall increase inventory.
4. Weighted Average Cost shall be used for inventory costing.
5. Completed sales shall reduce inventory.
6. Confirmed orders may reserve inventory.
7. Cancelled reservations shall restore availability.
8. Negative inventory shall not be allowed by default.
9. Historical sale prices shall not change.
10. Historical cost snapshots shall not change.
11. Refunds shall not delete original sales.
12. Exchanges shall preserve transaction history.
13. Returned damaged items shall not become sellable inventory.
14. Stock adjustments shall be auditable.
15. Only completed sales contribute to revenue.
16. Stock purchases are inventory, not immediate operating expenses.
17. COGS is recognized when products are sold.
18. Operating expenses reduce estimated net profit.
19. Delivery fees are excluded from Version 1 accounting.
20. Customers shall not require system login in Version 1.

---

# 60. Version 1 Acceptance Criteria

Version 1 shall be considered successful when the owner can:

1. Log into the system.
2. Create staff accounts.
3. Create products.
4. Create product variants.
5. Register suppliers.
6. Record supplier purchases.
7. Automatically increase inventory from purchases.
8. View available stock per variant.
9. Receive low-stock alerts.
10. Record counter sales.
11. Automatically reduce inventory from completed sales.
12. Register and manage customers.
13. View customer purchase history.
14. Create and manage orders.
15. Reserve inventory.
16. Release cancelled reservations.
17. Process returns.
18. Process authorized refunds.
19. Process exchanges.
20. Record business expenses.
21. View gross profit.
22. View estimated net profit.
23. Generate business reports.
24. Search and filter key records.
25. Review important audit activity.

---

# 61. Remaining Product Decisions

The following are still unresolved.

## Returns

- Approved return period: maximum three days (72 elapsed hours from completed
  sale, inclusive). Creation, approval and completion enforce this deadline.
- Approved proof: customer receipt or original sale record. Original sale/item
  linkage is required in either case.
- Administrators and salespeople may approve returns within their existing sale
  access scope. Refund authorization remains a separate, unresolved policy.

## Refunds

- Approved: salespeople may request; administrators approve and complete refunds.
- Approved: administrators may choose a different refund method from the original
  payment. The approved method is retained when recording manual completion.

## Exchanges

- Exchange period: approved three days (72 hours) from original sale completion,
  inclusive of the deadline; creation and completion both enforce eligibility.
- Completely different products: approved, subject to replacement availability.
- Refund differences: administrators approve and complete; salespeople may request.

## Supplier Payments

- Whether supplier credit/debt must be tracked: `TBD`

## Customers

- Whether customer registration is mandatory: `TBD`

Recommended:

Walk-in sales should be allowed without customer registration.

## Staff

- Whether salespeople can see other salespeople's sales: `TBD`

## Payment Integration

- Provider: `TBD`

## Deployment

- Hosting provider: `TBD`
- Domain: `TBD`
- Backup configuration: `TBD`

---

# 62. Product Risks

## Inventory Accuracy Risk

Incorrect stock adjustments could make the system unreliable.

### Mitigation

- Inventory movement ledger
- Restricted adjustment permissions
- Audit logs

---

## Profit Accuracy Risk

Incorrect COGS calculation could create misleading profit reports.

### Mitigation

- Weighted Average Cost
- Immutable historical transaction snapshots
- Strict transaction rules

---

## Staff Misuse Risk

Unauthorized refunds or adjustments could lead to fraud or loss.

### Mitigation

- Role permissions
- Approval rules
- Audit logs

---

## Scope Expansion Risk

WhatsApp, AI, e-commerce, accounting, and payment functionality could make Version 1 too large.

### Mitigation

Keep Version 1 focused on internal operations and implement automation in later phases.

---

# 63. Proposed Delivery Phases

## Phase 1A — Foundation

- Authentication
- Users
- Categories
- Products
- Variants
- Suppliers

## Phase 1B — Inventory

- Stock purchases
- Inventory
- Stock movements
- Low-stock alerts

## Phase 1C — Sales

- POS
- Customers
- Customer purchase history

## Phase 1D — Orders

- Orders
- Reservations
- Order statuses

## Phase 1E — After-Sales

- Returns
- Refunds
- Exchanges

## Phase 1F — Finance & Intelligence

- Expenses
- COGS
- Gross profit
- Estimated net profit
- Dashboard
- Reports

## Phase 1G — Security & Production Readiness

- Audit logs
- Permissions review
- Backup
- Validation
- Testing
- Deployment preparation

---

# 64. Future Roadmap

## Phase 2

- WhatsApp Business integration
- Integrated payments
- Customer notifications
- Promotional messaging
- Customer consent

## Phase 3

- AI customer service
- Customer segmentation
- Automated follow-up
- Automated marketing
- Product recommendations
- Advanced analytics

---

# 65. Product Success Indicators

Useful success indicators after deployment include:

- Reduced stock-record errors
- Faster sales recording
- Accurate product availability
- Accurate customer purchase history
- Reduced manual calculations
- Faster report generation
- Visibility into product profitability
- Better identification of best-selling products
- Better identification of repeat customers

---

# 66. Final Product Scope

The first production version of MFBMS shall primarily be an:

**Internal Retail Operations, Inventory, Sales, Customer, and Profit Management System for Mo Fashion Store.**

It shall establish reliable operational data before external customer automation, WhatsApp integration, payment automation, and AI functionality are introduced.

---

# 67. Document Status

**Status: Draft — Core Product Scope Approved**

Core product requirements are sufficiently defined to proceed to technical architecture.

Remaining `TBD` decisions shall be resolved before their affected features are implemented.
