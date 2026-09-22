# DATABASE.md

# Mo Fashion Store Business Management System — MFBMS

## 1. Purpose

This document defines the complete relational database design for the **Mo Fashion Store Business Management System (MFBMS)**.

It covers:

- Users and roles
- Product categories
- Products
- Sizes
- Colours
- Product variants
- Suppliers
- Stock purchases
- Inventory
- Inventory movements
- Sales
- Customers
- Orders
- Stock reservations
- Returns
- Refunds
- Exchanges
- Expenses
- Profit calculations
- Audit logs
- System settings

The design supports the approved Version 1 requirements while leaving room for future WhatsApp, payment gateway, AI, and multi-branch integrations.

The underlying business case requires the system to manage products, stock, sales, customers, orders, expenses, reporting, and later WhatsApp functionality.

---

# 2. Database Technology

Recommended database:

**MySQL 8+**

Storage engine:

**InnoDB**

Character set:

```text
utf8mb4
```

Recommended collation:

```text
utf8mb4_unicode_ci
```

The database shall support:

- Transactions
- Foreign keys
- Row locking
- Indexing
- JSON fields where appropriate
- Decimal money values

---

# 3. General Database Principles

The database shall follow these rules:

1. Use surrogate numeric primary keys internally.
2. Use human-readable transaction numbers for business records.
3. Use foreign keys.
4. Use unique constraints where required.
5. Do not use floating-point data types for money.
6. Preserve historical transaction data.
7. Do not delete completed financial transactions.
8. Use status fields instead of destructive deletion.
9. Keep inventory-changing operations auditable.
10. Keep historical sales cost snapshots immutable.
11. Separate transactional tables from master/reference tables.
12. Avoid duplicate derived data unless there is a clear performance reason.

---

# 4. Primary Key Strategy

Recommended primary key:

```text
BIGINT UNSIGNED AUTO_INCREMENT
```

Laravel equivalent:

```php
$table->id();
```

This should be used for most entities.

---

# 5. Business Reference Numbers

Business documents shall have human-readable reference numbers separate from database IDs.

Examples:

```text
MFS-SAL-000001
MFS-ORD-000001
MFS-PUR-000001
MFS-RET-000001
MFS-RFD-000001
MFS-EXC-000001
```

Database IDs remain internal.

---

# 6. Money Data Type

All monetary values shall use:

```text
DECIMAL(15,2)
```

Examples:

```text
25000.00
40000.00
1200000.00
```

Never use:

```text
FLOAT
DOUBLE
```

for monetary values.

---

# 7. Quantity Data Type

For clothing inventory, recommended quantity type:

```text
INT UNSIGNED
```

If fractional inventory is introduced in the future, the schema may be changed accordingly.

---

# 8. Core Entity Overview

```text
users
roles
permissions

categories
sizes
colours
products
product_variants

suppliers
purchases
purchase_items

inventories
inventory_movements

customers

sales
sale_items

orders
order_items
stock_reservations

returns
return_items

refunds
refund_items

exchanges
exchange_items

expense_categories
expenses

audit_logs
system_settings
```

---

# 9. Entity Relationship Overview

```text
Category
   │
   └── Product
         │
         └── ProductVariant
              │
              ├── Inventory
              ├── PurchaseItem
              ├── SaleItem
              ├── OrderItem
              ├── StockReservation
              ├── ReturnItem
              └── ExchangeItem

Supplier
   │
   └── Purchase
         │
         └── PurchaseItem

Customer
   │
   ├── Sale
   └── Order

Sale
   │
   ├── SaleItem
   ├── Return
   ├── Refund
   └── Exchange
```

---

# 10. users

Stores users who can access the system.

## Fields

| Column | Type | Rules |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(150) | NOT NULL |
| username | VARCHAR(100) | UNIQUE, nullable if email login only |
| email | VARCHAR(191) | UNIQUE, nullable |
| phone | VARCHAR(30) | nullable |
| password | VARCHAR(255) | NOT NULL |
| role_id | BIGINT UNSIGNED | FK |
| is_active | BOOLEAN | default true |
| last_login_at | TIMESTAMP | nullable |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

# 11. roles

Stores system roles.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| name | VARCHAR(100) |
| slug | VARCHAR(100) |
| description | TEXT nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

## Initial Roles

```text
administrator
salesperson
```

Future:

```text
manager
cashier
inventory_officer
```

---

# 12. permissions

Optional if granular permissions are implemented.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| name | VARCHAR(150) |
| slug | VARCHAR(150) UNIQUE |
| description | TEXT nullable |

Examples:

```text
products.view
products.create
products.update

sales.create
sales.view_all

inventory.adjust

refunds.create
refunds.approve

reports.view

users.manage
```

---

# 13. role_permissions

Pivot table.

| Column | Type |
|---|---|
| role_id | BIGINT UNSIGNED |
| permission_id | BIGINT UNSIGNED |

Composite unique:

```text
(role_id, permission_id)
```

---

# 14. categories

Stores product categories.

## Fields

| Column | Type | Rules |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(150) | NOT NULL |
| slug | VARCHAR(160) | UNIQUE |
| description | TEXT | nullable |
| is_active | BOOLEAN | default true |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP | nullable |

Examples:

- Jeans
- Crop Tops
- Dresses
- Official Wear
- Accessories

The business case lists several fashion categories including jeans, official wear, dresses, jackets, skirts, and accessories.

---

# 15. sizes

Stores available sizes.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| name | VARCHAR(50) |
| code | VARCHAR(50) UNIQUE |
| sort_order | INT default 0 |
| is_active | BOOLEAN default true |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

Examples:

```text
XS
S
M
L
XL
XXL
```

Additional values may include:

```text
28
30
32
34
36
```

The system must support configurable sizes rather than hardcoding only S/M/L/XL.

---

# 16. colours

Stores available product colours.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| name | VARCHAR(100) |
| code | VARCHAR(100) UNIQUE |
| hex_code | VARCHAR(7) nullable |
| is_active | BOOLEAN |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

Examples:

```text
Black
Blue
White
Pink
Brown
```

---

# 17. products

Stores general product information.

## Fields

| Column | Type | Rules |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| category_id | BIGINT UNSIGNED | FK |
| name | VARCHAR(191) | NOT NULL |
| product_code | VARCHAR(100) | UNIQUE |
| description | TEXT | nullable |
| default_selling_price | DECIMAL(15,2) | nullable |
| is_active | BOOLEAN | default true |
| created_by | BIGINT UNSIGNED | FK users |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP | nullable |

---

# 18. Product vs Variant

A product represents the general item.

Example:

```text
Boyfriend Jeans
```

A variant represents the actual sellable combination.

Examples:

```text
Boyfriend Jeans / Blue / M
Boyfriend Jeans / Blue / L
Boyfriend Jeans / Black / M
```

Stock shall be tracked at **variant level**.

---

# 19. product_variants

## Fields

| Column | Type | Rules |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| product_id | BIGINT UNSIGNED | FK |
| size_id | BIGINT UNSIGNED | FK nullable |
| colour_id | BIGINT UNSIGNED | FK nullable |
| sku | VARCHAR(150) | UNIQUE |
| selling_price | DECIMAL(15,2) | NOT NULL |
| weighted_average_cost | DECIMAL(15,2) | default 0 |
| low_stock_threshold | INT UNSIGNED | default 2 |
| is_active | BOOLEAN | default true |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP | nullable |

---

# 20. Variant Uniqueness

Recommended unique constraint:

```text
product_id
size_id
colour_id
```

where appropriate.

The application must prevent duplicate active combinations such as:

```text
Boyfriend Jeans / Black / M
```

being created twice.

---

# 21. suppliers

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| supplier_code | VARCHAR(100) UNIQUE |
| name | VARCHAR(191) |
| contact_person | VARCHAR(150) nullable |
| phone | VARCHAR(30) nullable |
| email | VARCHAR(191) nullable |
| location | VARCHAR(255) nullable |
| notes | TEXT nullable |
| is_active | BOOLEAN default true |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP nullable |

---

# 22. purchases

Represents stock acquired from suppliers.

## Fields

| Column | Type | Rules |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| purchase_number | VARCHAR(100) | UNIQUE |
| supplier_id | BIGINT UNSIGNED | FK |
| purchase_date | DATE | NOT NULL |
| supplier_invoice_number | VARCHAR(150) | nullable |
| subtotal | DECIMAL(15,2) | |
| total_amount | DECIMAL(15,2) | |
| payment_status | VARCHAR(50) | |
| status | VARCHAR(50) | |
| notes | TEXT | nullable |
| created_by | BIGINT UNSIGNED | FK users |
| confirmed_by | BIGINT UNSIGNED | nullable |
| confirmed_at | TIMESTAMP | nullable |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

# 23. Purchase Status

Allowed:

```text
DRAFT
CONFIRMED
CANCELLED
```

Only:

```text
CONFIRMED
```

purchases affect inventory.

---

# 24. Supplier Payment Status

Until supplier credit rules are finalized:

```text
PAID
PARTIALLY_PAID
UNPAID
```

These values may initially be informational only.

Supplier accounts/payables shall not be implemented unless specifically approved later.

---

# 25. purchase_items

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| purchase_id | BIGINT UNSIGNED |
| product_variant_id | BIGINT UNSIGNED |
| quantity | INT UNSIGNED |
| unit_cost | DECIMAL(15,2) |
| line_total | DECIMAL(15,2) |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

Calculation:

```text
line_total = quantity × unit_cost
```

---

# 26. inventories

Stores current inventory balance per variant.

Because Version 1 has one branch, one inventory row shall exist per variant.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| product_variant_id | BIGINT UNSIGNED UNIQUE |
| physical_quantity | INT UNSIGNED default 0 |
| reserved_quantity | INT UNSIGNED default 0 |
| updated_at | TIMESTAMP |

Logical:

```text
available_quantity =
physical_quantity - reserved_quantity
```

Recommended:

Do not store `available_quantity` separately unless performance later requires it.

---

# 27. Inventory Integrity Rule

Always enforce:

```text
physical_quantity >= 0
```

and:

```text
reserved_quantity >= 0
```

and:

```text
reserved_quantity <= physical_quantity
```

---

# 28. inventory_movements

This table is the permanent stock ledger.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| product_variant_id | BIGINT UNSIGNED |
| movement_type | VARCHAR(50) |
| quantity_change | INT |
| physical_quantity_before | INT |
| physical_quantity_after | INT |
| reserved_quantity_before | INT |
| reserved_quantity_after | INT |
| reference_type | VARCHAR(100) nullable |
| reference_id | BIGINT UNSIGNED nullable |
| reason | VARCHAR(255) nullable |
| notes | TEXT nullable |
| created_by | BIGINT UNSIGNED |
| created_at | TIMESTAMP |

---

# 29. Inventory Movement Types

Recommended enum values:

```text
PURCHASE

SALE

RESERVATION

RESERVATION_RELEASE

RETURN

EXCHANGE_IN

EXCHANGE_OUT

DAMAGE

LOSS

ADJUSTMENT_IN

ADJUSTMENT_OUT

REVERSAL
```

---

# 30. Why Inventory Movements Are Required

The `inventories` table stores the current balance.

The `inventory_movements` table explains:

**how that balance was reached.**

Example:

```text
PURCHASE +20
SALE -3
SALE -2
RETURN +1
DAMAGE -1
```

Current physical stock:

```text
15
```

This gives the owner a full stock history.

---

# 31. Weighted Average Cost Storage

Current weighted-average cost shall be stored in:

```text
product_variants.weighted_average_cost
```

Historical purchase costs remain in:

```text
purchase_items.unit_cost
```

Historical sale costs remain in:

```text
sale_items.unit_cost
```

---

# 32. Weighted Average Cost Calculation

When confirming a purchase:

```text
old_stock_value =
existing_physical_quantity × old_average_cost
```

New stock value:

```text
purchase_quantity × purchase_unit_cost
```

New average:

```text
(old_stock_value + new_stock_value)
/
(existing_quantity + purchase_quantity)
```

Example:

Existing:

```text
10 × 25,000 = 250,000
```

New:

```text
10 × 30,000 = 300,000
```

Total value:

```text
550,000
```

Total units:

```text
20
```

Weighted average:

```text
27,500
```

---

# 33. Important Cost Rule

Selling products does **not** recalculate weighted-average cost.

A sale simply uses the current weighted-average cost as its historical cost snapshot.

Purchases affect weighted-average cost.

---

# 34. customers

The original business case requires storing customer contact details, preferences, purchase history, total purchases, and total spending.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| customer_code | VARCHAR(100) UNIQUE |
| full_name | VARCHAR(191) |
| phone | VARCHAR(30) nullable |
| whatsapp_number | VARCHAR(30) nullable |
| location | VARCHAR(255) nullable |
| preferred_size_id | BIGINT UNSIGNED nullable |
| preferred_colour_id | BIGINT UNSIGNED nullable |
| notes | TEXT nullable |
| first_purchase_at | DATETIME nullable |
| last_purchase_at | DATETIME nullable |
| total_purchases | INT UNSIGNED default 0 |
| total_spent | DECIMAL(15,2) default 0 |
| marketing_opt_in | BOOLEAN default false |
| is_active | BOOLEAN default true |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |
| deleted_at | TIMESTAMP nullable |

---

# 35. Customer Category Preferences

Because one customer may like multiple product categories, use:

```text
customer_category_preferences
```

Fields:

| Column | Type |
|---|---|
| customer_id | BIGINT UNSIGNED |
| category_id | BIGINT UNSIGNED |

Composite unique:

```text
(customer_id, category_id)
```

---

# 36. Walk-In Customer

Recommended design:

Sales may have:

```text
customer_id = NULL
```

This allows counter sales without mandatory customer registration.

Do not create fake customers such as:

```text
Walk-In Customer #1
Walk-In Customer #2
```

unless specifically needed.

---

# 37. sales

Stores completed or cancelled sales.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| sale_number | VARCHAR(100) UNIQUE |
| customer_id | BIGINT UNSIGNED nullable |
| order_id | BIGINT UNSIGNED nullable |
| salesperson_id | BIGINT UNSIGNED |
| sale_date | DATETIME |
| payment_method | VARCHAR(50) |
| payment_reference | VARCHAR(191) nullable |
| subtotal | DECIMAL(15,2) |
| discount_total | DECIMAL(15,2) default 0 |
| total_amount | DECIMAL(15,2) |
| total_cogs | DECIMAL(15,2) |
| gross_profit | DECIMAL(15,2) |
| status | VARCHAR(50) |
| notes | TEXT nullable |
| completed_at | DATETIME nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 38. Sale Status

Allowed:

```text
DRAFT
COMPLETED
CANCELLED
PARTIALLY_REFUNDED
REFUNDED
```

Only:

```text
COMPLETED
PARTIALLY_REFUNDED
REFUNDED
```

may remain part of historical revenue calculations depending on refund adjustments.

---

# 39. sale_items

This is one of the most important tables.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| sale_id | BIGINT UNSIGNED |
| product_variant_id | BIGINT UNSIGNED |
| quantity | INT UNSIGNED |
| unit_price | DECIMAL(15,2) |
| unit_cost | DECIMAL(15,2) |
| discount_amount | DECIMAL(15,2) default 0 |
| line_subtotal | DECIMAL(15,2) |
| line_total | DECIMAL(15,2) |
| line_cost | DECIMAL(15,2) |
| line_gross_profit | DECIMAL(15,2) |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 40. Sale Item Calculations

```text
line_subtotal =
unit_price × quantity
```

```text
line_total =
line_subtotal - discount_amount
```

```text
line_cost =
unit_cost × quantity
```

```text
line_gross_profit =
line_total - line_cost
```

---

# 41. Historical Cost Snapshot Rule

When a sale is completed:

```text
sale_items.unit_cost =
current product_variant.weighted_average_cost
```

After this is stored, it shall not change.

Even if weighted-average cost changes tomorrow, the historical sale remains correct.

---

# 42. Sale Summary Calculations

```text
subtotal =
SUM(sale_items.line_subtotal)
```

```text
total_amount =
SUM(sale_items.line_total)
```

```text
total_cogs =
SUM(sale_items.line_cost)
```

```text
gross_profit =
total_amount - total_cogs
```

---

# 43. orders

Stores customer orders before or during fulfillment.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| order_number | VARCHAR(100) UNIQUE |
| customer_id | BIGINT UNSIGNED |
| salesperson_id | BIGINT UNSIGNED nullable |
| status | VARCHAR(50) |
| payment_status | VARCHAR(50) |
| subtotal | DECIMAL(15,2) |
| discount_total | DECIMAL(15,2) default 0 |
| total_amount | DECIMAL(15,2) |
| delivery_address | VARCHAR(255) nullable |
| notes | TEXT nullable |
| confirmed_at | DATETIME nullable |
| payment_received_at | DATETIME nullable |
| delivered_at | DATETIME nullable |
| cancelled_at | DATETIME nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

The business case specifies an order lifecycle from New Order through Delivered.

---

# 44. Order Status

Recommended:

```text
NEW
CONFIRMED
PAYMENT_RECEIVED
PREPARING
OUT_FOR_DELIVERY
DELIVERED
CANCELLED
```

Reservation state should not be overloaded into order status.

It is tracked separately.

---

# 45. Order Payment Status

```text
UNPAID
PENDING
PAID
PARTIALLY_REFUNDED
REFUNDED
FAILED
```

---

# 46. order_items

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| order_id | BIGINT UNSIGNED |
| product_variant_id | BIGINT UNSIGNED |
| quantity | INT UNSIGNED |
| unit_price | DECIMAL(15,2) |
| discount_amount | DECIMAL(15,2) |
| line_total | DECIMAL(15,2) |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 47. stock_reservations

Do not rely only on `reserved_quantity`.

Create explicit reservation records.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| order_id | BIGINT UNSIGNED |
| order_item_id | BIGINT UNSIGNED |
| product_variant_id | BIGINT UNSIGNED |
| quantity | INT UNSIGNED |
| status | VARCHAR(50) |
| reserved_at | DATETIME |
| expires_at | DATETIME nullable |
| released_at | DATETIME nullable |
| completed_at | DATETIME nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 48. Reservation Status

```text
ACTIVE
RELEASED
COMPLETED
EXPIRED
```

---

# 49. Reservation Rules

When an order is confirmed:

```text
inventories.reserved_quantity += quantity
```

Reservation:

```text
status = ACTIVE
```

When sale is completed:

```text
physical_quantity -= quantity
reserved_quantity -= quantity
```

Reservation:

```text
status = COMPLETED
```

If cancelled:

```text
reserved_quantity -= quantity
```

Reservation:

```text
status = RELEASED
```

---

# 50. returns

Represents return events.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| return_number | VARCHAR(100) UNIQUE |
| sale_id | BIGINT UNSIGNED |
| customer_id | BIGINT UNSIGNED nullable |
| reason | TEXT nullable |
| status | VARCHAR(50) |
| processed_by | BIGINT UNSIGNED |
| return_date | DATETIME |
| notes | TEXT nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 51. Return Status

```text
PENDING
APPROVED
COMPLETED
REJECTED
CANCELLED
```

---

# 52. return_items

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| return_id | BIGINT UNSIGNED |
| sale_item_id | BIGINT UNSIGNED |
| product_variant_id | BIGINT UNSIGNED |
| quantity | INT UNSIGNED |
| condition | VARCHAR(50) |
| refund_amount | DECIMAL(15,2) default 0 |
| returned_to_stock | BOOLEAN default false |
| notes | TEXT nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 53. Return Condition

Allowed:

```text
SELLABLE
DAMAGED
DEFECTIVE
OTHER
```

Only:

```text
SELLABLE
```

may return directly to available inventory.

---

# 54. Return Quantity Validation

System must enforce:

```text
total returned quantity
<=
original sold quantity
-
previously returned quantity
```

A customer cannot return more units than were originally purchased.

---

# 55. refunds

Represents money returned to customers.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| refund_number | VARCHAR(100) UNIQUE |
| sale_id | BIGINT UNSIGNED |
| return_id | BIGINT UNSIGNED nullable |
| amount | DECIMAL(15,2) |
| refund_method | VARCHAR(50) |
| reason | TEXT nullable |
| status | VARCHAR(50) |
| approved_by | BIGINT UNSIGNED nullable |
| processed_by | BIGINT UNSIGNED |
| processed_at | DATETIME nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 56. Refund Status

```text
PENDING
APPROVED
COMPLETED
REJECTED
CANCELLED
```

---

# 57. refund_items

Recommended for accurate partial refunds.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| refund_id | BIGINT UNSIGNED |
| sale_item_id | BIGINT UNSIGNED |
| quantity | INT UNSIGNED nullable |
| amount | DECIMAL(15,2) |
| created_at | TIMESTAMP |

---

# 58. Refund Validation

System must enforce:

```text
total completed refunds
<=
original sale total
```

Refunds shall not exceed the amount actually charged.

---

# 59. exchanges

Represents product exchange transactions.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| exchange_number | VARCHAR(100) UNIQUE |
| sale_id | BIGINT UNSIGNED |
| customer_id | BIGINT UNSIGNED nullable |
| status | VARCHAR(50) |
| total_return_value | DECIMAL(15,2) |
| total_replacement_value | DECIMAL(15,2) |
| amount_due | DECIMAL(15,2) default 0 |
| refund_due | DECIMAL(15,2) default 0 |
| processed_by | BIGINT UNSIGNED |
| processed_at | DATETIME nullable |
| notes | TEXT nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 60. Exchange Status

```text
PENDING
COMPLETED
CANCELLED
```

---

# 61. exchange_items

Recommended structure:

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| exchange_id | BIGINT UNSIGNED |
| item_type | VARCHAR(20) |
| product_variant_id | BIGINT UNSIGNED |
| sale_item_id | BIGINT UNSIGNED nullable |
| quantity | INT UNSIGNED |
| unit_value | DECIMAL(15,2) |
| condition | VARCHAR(50) nullable |
| line_total | DECIMAL(15,2) |
| created_at | TIMESTAMP |

`item_type`:

```text
RETURNED
REPLACEMENT
```

---

# 62. Exchange Calculation

```text
total_return_value =
SUM(returned items)
```

```text
total_replacement_value =
SUM(replacement items)
```

If:

```text
replacement > returned
```

then:

```text
amount_due =
replacement - returned
```

If:

```text
returned > replacement
```

then:

```text
refund_due =
returned - replacement
```

---

# 63. Exchange Inventory Handling

Returned sellable item:

```text
inventory + quantity
```

Replacement item:

```text
inventory - quantity
```

Each shall create an independent inventory movement.

---

# 64. expense_categories

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| name | VARCHAR(150) |
| description | TEXT nullable |
| is_active | BOOLEAN |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

Examples:

```text
Rent
Electricity
Internet
Marketing
Packaging
Transport
Staff
Other
```

---

# 65. expenses

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| expense_number | VARCHAR(100) UNIQUE |
| expense_category_id | BIGINT UNSIGNED |
| amount | DECIMAL(15,2) |
| expense_date | DATE |
| description | TEXT nullable |
| recorded_by | BIGINT UNSIGNED |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 66. Important Expense Rule

Stock purchases shall not be stored in `expenses`.

Supplier purchases are inventory acquisition.

Operating expenses belong in `expenses`.

This prevents double-counting inventory costs.

---

# 67. Profit Calculation

## Revenue

```text
Completed Sales
-
Completed Refunds
```

---

# 68. COGS

Base COGS:

```text
SUM(sale_items.line_cost)
```

Adjustments from valid sellable returns must be handled correctly.

A completed product return should reverse the corresponding item's COGS for the returned quantity.

---

# 69. Return Cost Reversal

For each returned quantity:

```text
returned_cogs =
sale_item.unit_cost × return_quantity
```

Adjusted COGS:

```text
sales_cogs - returned_cogs
```

Do not use the product's current weighted-average cost for historical returns.

Use:

```text
sale_item.unit_cost
```

from the original transaction.

---

# 70. Gross Profit

```text
Net Revenue - Adjusted COGS
```

---

# 71. Estimated Net Profit

```text
Gross Profit
-
Operating Expenses
```

---

# 72. Financial Reporting Source of Truth

Revenue comes from:

```text
sales
sale_items
refunds
```

COGS comes from:

```text
sale_items
return_items
```

Expenses come from:

```text
expenses
```

Do not derive profit directly from product master prices.

---

# 73. payments

Even though automated payment integration comes later, it is wise to prepare a generic payment table.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| payment_number | VARCHAR(100) UNIQUE |
| payable_type | VARCHAR(100) |
| payable_id | BIGINT UNSIGNED |
| provider | VARCHAR(100) nullable |
| payment_method | VARCHAR(50) |
| amount | DECIMAL(15,2) |
| currency | VARCHAR(3) default 'TZS' |
| external_reference | VARCHAR(191) nullable |
| status | VARCHAR(50) |
| paid_at | DATETIME nullable |
| metadata | JSON nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

---

# 74. Payment Status

```text
PENDING
SUCCESSFUL
FAILED
CANCELLED
REFUNDED
```

---

# 75. Why payments Uses Polymorphic Relation

A payment may eventually belong to:

```text
Order
Sale
Exchange
```

Using:

```text
payable_type
payable_id
```

allows flexibility.

---

# 76. Future Payment Provider Transactions

Future integration may use:

```text
payment_transactions
```

Fields:

```text
id
payment_id
provider
external_transaction_id
request_payload
response_payload
status
received_at
created_at
```

This should only be added when payment integration begins.

---

# 77. audit_logs

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| user_id | BIGINT UNSIGNED nullable |
| action | VARCHAR(100) |
| entity_type | VARCHAR(150) |
| entity_id | BIGINT UNSIGNED nullable |
| old_values | JSON nullable |
| new_values | JSON nullable |
| ip_address | VARCHAR(45) nullable |
| user_agent | TEXT nullable |
| created_at | TIMESTAMP |

---

# 78. Audit Actions

Examples:

```text
CREATE
UPDATE
DEACTIVATE
ADJUST_STOCK
CONFIRM_PURCHASE
COMPLETE_SALE
CANCEL_SALE
PROCESS_RETURN
PROCESS_REFUND
PROCESS_EXCHANGE
CHANGE_PERMISSION
```

---

# 79. system_settings

Stores configurable business-level values.

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| key | VARCHAR(191) UNIQUE |
| value | TEXT nullable |
| type | VARCHAR(50) |
| description | TEXT nullable |
| updated_by | BIGINT UNSIGNED nullable |
| created_at | TIMESTAMP |
| updated_at | TIMESTAMP |

Examples:

```text
business_name
business_phone
default_currency
low_stock_default
timezone
receipt_footer
```

---

# 80. Business Settings

Initial:

```text
business_name = Mo Fashion Store

currency = TZS

timezone = Africa/Dar_es_Salaam

negative_stock_allowed = false
```

---

# 81. Transaction Number Sequence

Recommended table:

```text
document_sequences
```

## Fields

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| document_type | VARCHAR(50) UNIQUE |
| prefix | VARCHAR(50) |
| current_number | BIGINT UNSIGNED |
| updated_at | TIMESTAMP |

Examples:

```text
SALE       MFS-SAL-
ORDER      MFS-ORD-
PURCHASE   MFS-PUR-
RETURN     MFS-RET-
REFUND     MFS-RFD-
EXCHANGE   MFS-EXC-
```

Numbers must be generated atomically.

---

# 82. Required Unique Constraints

Recommended:

```text
users.email
users.username

roles.slug

permissions.slug

categories.slug

sizes.code

colours.code

products.product_code

product_variants.sku

suppliers.supplier_code

purchases.purchase_number

customers.customer_code

sales.sale_number

orders.order_number

returns.return_number

refunds.refund_number

exchanges.exchange_number

expenses.expense_number

payments.payment_number
```

---

# 83. Required Foreign Keys

Examples:

```text
products.category_id
→ categories.id

product_variants.product_id
→ products.id

product_variants.size_id
→ sizes.id

product_variants.colour_id
→ colours.id

purchase_items.purchase_id
→ purchases.id

purchase_items.product_variant_id
→ product_variants.id

sales.customer_id
→ customers.id

sale_items.sale_id
→ sales.id

sale_items.product_variant_id
→ product_variants.id

orders.customer_id
→ customers.id

order_items.order_id
→ orders.id

returns.sale_id
→ sales.id

refunds.sale_id
→ sales.id

expenses.expense_category_id
→ expense_categories.id
```

---

# 84. Foreign Key Delete Rules

Master data:

Prefer:

```text
RESTRICT
```

or soft deletes.

Examples:

Do not allow deletion of a product that already exists in historical sales.

---

# 85. Transaction Data Delete Policy

Never hard-delete completed:

- Purchases
- Sales
- Inventory movements
- Returns
- Refunds
- Exchanges
- Payments

Use status changes or reversals.

---

# 86. Recommended Indexes

## product_variants

```text
product_id
sku
size_id
colour_id
is_active
```

## purchases

```text
supplier_id
purchase_date
status
```

## inventory_movements

```text
product_variant_id
movement_type
created_at
reference_type
reference_id
```

## customers

```text
phone
whatsapp_number
full_name
```

## sales

```text
sale_date
customer_id
salesperson_id
status
payment_method
```

## orders

```text
customer_id
status
payment_status
created_at
```

## expenses

```text
expense_date
expense_category_id
```

---

# 87. Composite Indexes

Recommended:

```text
sales(status, sale_date)

orders(status, created_at)

inventory_movements(product_variant_id, created_at)

purchases(supplier_id, purchase_date)

sale_items(product_variant_id, sale_id)

order_items(product_variant_id, order_id)
```

---

# 88. Database Transaction Boundaries

The following operations must execute inside a database transaction:

## Purchase Confirmation

```text
Lock inventory
Create/update inventory
Calculate weighted average cost
Create inventory movements
Confirm purchase
```

---

## Sale Completion

```text
Lock inventory
Validate stock
Create sale
Create sale items
Store cost snapshots
Reduce inventory
Create movements
Update customer stats
Commit
```

---

## Order Reservation

```text
Lock inventory
Validate availability
Create reservations
Increase reserved_quantity
Update order
Commit
```

---

## Reservation Release

```text
Lock inventory
Reduce reserved quantity
Mark reservation released
Update order
Commit
```

---

## Return

```text
Validate original sale
Validate quantity
Create return
Create return items
Adjust inventory where applicable
Create inventory movements
Commit
```

---

## Exchange

```text
Lock returned variant
Lock replacement variant
Validate stock
Create exchange
Return old item
Issue replacement
Adjust inventory
Create movements
Create payment/refund difference
Commit
```

---

# 89. Row Locking

Before changing inventory:

```php
Inventory::where(...)
    ->lockForUpdate()
    ->first();
```

This prevents race conditions.

Example problem avoided:

Two salespeople try to sell the last Size M jeans simultaneously.

---

# 90. Derived Customer Statistics

The following may be cached:

```text
first_purchase_at
last_purchase_at
total_purchases
total_spent
```

But these shall be recoverable from sales.

If inconsistency occurs, the system must be able to recalculate them.

---

# 91. Derived Product Statistics

Do not initially store:

```text
total_units_sold
total_revenue
```

directly in products.

Calculate them through reporting queries.

Add summary tables later only if performance requires them.

---

# 92. Low Stock Query

Logical query:

```text
physical_quantity - reserved_quantity
<=
low_stock_threshold
```

Out of stock:

```text
physical_quantity - reserved_quantity <= 0
```

---

# 93. Top Selling Product Query

Use completed sale items.

Logical:

```text
SUM(sale_items.quantity)
GROUP BY product
ORDER BY quantity DESC
```

Returns/refunds should be accounted for in reporting.

---

# 94. Best Customer Calculation

Recommended initial definition:

Customer with highest:

```text
Net Completed Sales Value
```

within selected reporting period.

Do not use raw lifetime `total_spent` when producing filtered period reports.

---

# 95. Customer Purchase History

Purchase history shall not require a separate history table.

Use:

```text
customers
→ sales
→ sale_items
→ product_variants
→ products
```

This avoids duplicated data.

---

# 96. Supplier Purchase History

Use:

```text
suppliers
→ purchases
→ purchase_items
```

No duplicate history table is needed.

---

# 97. Product Images

Recommended future table:

```text
product_images
```

Fields:

| Column | Type |
|---|---|
| id | BIGINT UNSIGNED |
| product_id | BIGINT UNSIGNED |
| path | VARCHAR(500) |
| sort_order | INT |
| is_primary | BOOLEAN |
| created_at | TIMESTAMP |

This can be included in Version 1 if product images are needed in POS or future WhatsApp functionality.

---

# 98. Image Recommendation

Include `product_images` from the beginning.

Reason:

Future WhatsApp functionality is intended to show customers available designs/products. The original business case anticipates showing customers available products and designs through WhatsApp.

---

# 99. Product Attributes Beyond Size and Colour

Do not create a fully generic EAV attribute system in Version 1.

Primary attributes are:

```text
Size
Colour
```

If another essential attribute is discovered later, extend the schema deliberately.

Avoid overengineering.

---

# 100. Currency

Default:

```text
TZS
```

Payments may contain:

```text
currency CHAR(3)
```

to allow future expansion.

---

# 101. Timestamps

All important transaction tables shall include:

```text
created_at
updated_at
```

Important business dates should have dedicated fields.

Examples:

```text
sale_date
purchase_date
return_date
expense_date
paid_at
delivered_at
```

Do not rely solely on `created_at` for business reporting.

---

# 102. Timezone

Application timezone:

```text
Africa/Dar_es_Salaam
```

Recommended database approach:

Store timestamps consistently.

Laravel should handle timezone conversion at application level.

---

# 103. Soft Delete Strategy

Recommended soft-delete tables:

```text
categories
products
product_variants
suppliers
customers
```

Do not soft-delete financial ledger records unless there is an exceptional administrative requirement.

---

# 104. Table Naming Convention

Use Laravel standard:

```text
snake_case
plural
```

Examples:

```text
product_variants
inventory_movements
purchase_items
sale_items
stock_reservations
expense_categories
```

---

# 105. Column Naming Convention

Use:

```text
snake_case
```

Foreign keys:

```text
product_id
customer_id
supplier_id
```

Boolean fields:

```text
is_active
is_primary
returned_to_stock
```

---

# 106. Enums

Where possible, use application-level PHP Enums rather than rigid database ENUM columns.

Examples:

```php
SaleStatus
OrderStatus
PurchaseStatus
ReturnStatus
RefundStatus
ExchangeStatus
PaymentStatus
InventoryMovementType
```

Database column:

```text
VARCHAR
```

This makes future status additions easier.

---

# 107. Recommended Laravel Models

```text
User
Role
Permission

Category
Size
Colour
Product
ProductVariant
ProductImage

Supplier
Purchase
PurchaseItem

Inventory
InventoryMovement

Customer

Sale
SaleItem

Order
OrderItem
StockReservation

ReturnTransaction
ReturnItem

Refund
RefundItem

Exchange
ExchangeItem

ExpenseCategory
Expense

Payment

AuditLog
SystemSetting
DocumentSequence
```

Avoid model name:

```text
Return
```

because `return` is a PHP keyword concept.

Use:

```text
ReturnTransaction
```

instead.

---

# 108. Main Laravel Relationships

## Product

```text
belongsTo Category
hasMany ProductVariant
hasMany ProductImage
```

## ProductVariant

```text
belongsTo Product
belongsTo Size
belongsTo Colour
hasOne Inventory
hasMany PurchaseItem
hasMany SaleItem
```

## Supplier

```text
hasMany Purchase
```

## Purchase

```text
belongsTo Supplier
hasMany PurchaseItem
```

## Customer

```text
hasMany Sale
hasMany Order
```

## Sale

```text
belongsTo Customer
belongsTo User as salesperson
hasMany SaleItem
hasMany Refund
hasMany ReturnTransaction
```

---

# 109. Inventory Consistency Rule

Application logic must never directly execute arbitrary:

```text
inventory.quantity = X
```

without creating an inventory movement.

Manual adjustments must use dedicated inventory service logic.

---

# 110. Recommended Inventory Service

Example responsibility:

```text
InventoryService
```

Methods may include:

```text
increaseFromPurchase()

reserve()

releaseReservation()

completeReservation()

deductForSale()

returnSellableStock()

markDamaged()

adjust()
```

All methods must:

- lock inventory
- validate quantities
- create movements
- update balance
- operate inside transactions

---

# 111. Recommended Costing Service

```text
InventoryCostService
```

Responsibilities:

```text
calculateWeightedAverageCost()

captureSaleCost()

calculateReturnedCost()
```

---

# 112. Recommended Sales Service

```text
SaleService
```

Responsibilities:

```text
createDraft()
completeSale()
cancelSale()
recalculateTotals()
```

---

# 113. Recommended Order Service

```text
OrderService
```

Responsibilities:

```text
createOrder()
confirmOrder()
reserveStock()
markPaid()
convertToSale()
cancelOrder()
```

---

# 114. Order-to-Sale Relationship

One order should produce at most one primary sale in Version 1.

Recommended:

```text
sales.order_id
```

with unique constraint if appropriate.

If future partial fulfillment is introduced, this assumption may change.

---

# 115. Payment Idempotency

For future integrated payments, enforce unique:

```text
external_reference
```

per provider where possible.

Recommended unique composite:

```text
(provider, external_reference)
```

This prevents duplicate webhook processing.

---

# 116. Audit Requirements

Audit critical changes including:

- stock adjustments
- product price changes
- weighted cost changes caused by purchases
- purchase confirmation
- sale cancellation
- returns
- refunds
- exchanges
- expense edits
- staff permission changes

---

# 117. Sensitive Audit Data

Never store in audit JSON:

- Passwords
- Authentication tokens
- API secrets
- Payment provider secrets

---

# 118. Data Retention

Transactional data should be retained indefinitely unless business/legal policy later specifies otherwise.

This includes:

- Sales
- Purchases
- Inventory movements
- Returns
- Refunds
- Exchanges
- Expenses

---

# 119. Data Backup

Database backup shall include all business tables.

Recommended:

```text
Daily database backup
```

Retention:

```text
7–30 days
```

Final policy remains deployment-specific.

---

# 120. Core Reporting Queries

The schema must efficiently support:

### Sales

```text
daily sales
weekly sales
monthly sales
sales by salesperson
sales by payment method
sales by product
sales by variant
```

### Inventory

```text
current stock
low stock
out of stock
stock movement history
stock purchased
damaged stock
```

### Customers

```text
purchase history
repeat customers
highest spenders
inactive customers
```

### Finance

```text
gross revenue
net revenue
COGS
gross profit
expenses
estimated net profit
refund totals
```

---

# 121. Core Database Workflow — Supplier Purchase

```text
Supplier
   ↓
Purchase
   ↓
Purchase Items
   ↓
Confirm
   ↓
Lock Variant Inventory
   ↓
Calculate Weighted Average Cost
   ↓
Increase Physical Stock
   ↓
Inventory Movement
```

---

# 122. Core Database Workflow — Counter Sale

```text
Sale
   ↓
Sale Items
   ↓
Lock Inventory
   ↓
Check Available Stock
   ↓
Capture Unit Cost
   ↓
Decrease Physical Stock
   ↓
Inventory Movement
   ↓
Update Customer Statistics
```

---

# 123. Core Database Workflow — Order

```text
Order
   ↓
Order Items
   ↓
Confirm
   ↓
Stock Reservations
   ↓
Increase Reserved Quantity
```

After payment:

```text
Create Sale
   ↓
Reduce Physical Stock
   ↓
Reduce Reserved Quantity
   ↓
Complete Reservation
```

---

# 124. Core Database Workflow — Return

```text
Original Sale
   ↓
Return
   ↓
Return Items
   ↓
Validate Quantity
   ↓
Check Condition
   ↓
If Sellable → Inventory Increase
   ↓
Inventory Movement
```

---

# 125. Core Database Workflow — Refund

```text
Sale
   ↓
Return optional
   ↓
Refund
   ↓
Refund Items
   ↓
Financial Adjustment
```

Original sale remains unchanged.

---

# 126. Core Database Workflow — Exchange

```text
Original Sale
   ↓
Exchange
   ↓
Returned Item
   ↓
Replacement Item
   ↓
Inventory In
   ↓
Inventory Out
   ↓
Calculate Difference
   ↓
Extra Payment OR Refund
```

---

# 127. Database Integrity Constraints

System shall enforce:

```text
quantity > 0
```

```text
price >= 0
```

```text
expense >= 0
```

```text
reserved <= physical
```

```text
refund <= eligible amount
```

```text
return quantity <= remaining returnable quantity
```

```text
sale quantity <= available stock
```

---

# 128. Critical Database Anti-Patterns to Avoid

Do not:

1. Store stock only in `products`.
2. Store size and colour directly as free text on sales.
3. Replace historical purchase costs.
4. Recalculate old sale costs using today's cost.
5. Delete sales after refunds.
6. Delete stock movements.
7. Record stock purchases as operating expenses.
8. Allow direct inventory updates without ledger records.
9. Use FLOAT for money.
10. Use one generic transactions table for every business process.
11. Store customer purchase history as duplicated text or JSON.
12. Mix orders and sales into the same table.

---

# 129. Recommended Migration Order

Laravel migrations should approximately follow:

```text
1. roles
2. permissions
3. users
4. role_permissions

5. categories
6. sizes
7. colours
8. products
9. product_variants
10. product_images

11. suppliers

12. customers
13. customer_category_preferences

14. purchases
15. purchase_items

16. inventories
17. inventory_movements

18. orders
19. order_items
20. stock_reservations

21. sales
22. sale_items

23. returns
24. return_items

25. refunds
26. refund_items

27. exchanges
28. exchange_items

29. expense_categories
30. expenses

31. payments

32. audit_logs
33. system_settings
34. document_sequences
```

Actual order may vary slightly depending on foreign-key dependencies.

---

# 130. Recommended Seed Data

Initial seeds:

## Roles

```text
Administrator
Salesperson
```

## Sizes

```text
XS
S
M
L
XL
XXL
```

## Expense Categories

```text
Rent
Electricity
Internet
Transport
Packaging
Marketing
Staff
Other
```

## System Settings

```text
business_name = Mo Fashion Store
currency = TZS
timezone = Africa/Dar_es_Salaam
negative_stock_allowed = false
```

---

# 131. Example Product Data

Product:

```text
Boyfriend Jeans
```

Category:

```text
Jeans
```

Variants:

```text
M / Blue
L / Blue
M / Black
L / Black
```

Each variant has its own:

```text
SKU
selling price
inventory
weighted average cost
low stock threshold
```

---

# 132. Example Supplier Purchase

```text
Purchase:
MFS-PUR-000001

Supplier:
Supplier A
```

Items:

```text
Black / M
10 units
TZS 25,000 each

Black / L
5 units
TZS 26,000 each
```

After confirmation:

```text
Inventory increases.

Weighted average costs recalculate.

Inventory movements are created.
```

---

# 133. Example Sale

```text
Sale:
MFS-SAL-000001

Customer:
Neema Joseph

Variant:
Boyfriend Jeans / Black / M

Quantity:
2

Selling Price:
TZS 40,000

Weighted Average Cost:
TZS 25,000
```

Revenue:

```text
80,000
```

COGS:

```text
50,000
```

Gross Profit:

```text
30,000
```

Inventory:

```text
-2
```

---

# 134. Example Return

Original:

```text
2 Jeans sold
```

Customer returns:

```text
1 Jeans
```

Original unit cost:

```text
TZS 25,000
```

The return reverses:

```text
TZS 25,000 COGS
```

If sellable:

```text
inventory +1
```

---

# 135. Example Exchange

Original:

```text
Blue / M = TZS 40,000
```

Replacement:

```text
Black / L = TZS 45,000
```

Difference:

```text
TZS 5,000
```

Customer pays:

```text
TZS 5,000
```

Both stock movements shall be recorded independently.

---

# 136. Future WhatsApp Database Extensions

Phase 2 may add:

```text
whatsapp_contacts
whatsapp_conversations
whatsapp_messages
message_templates
marketing_consents
```

Do not create these until Phase 2 requirements are finalized.

---

# 137. Future Marketing Extensions

May include:

```text
campaigns
campaign_recipients
customer_segments
campaign_messages
```

The business case anticipates customer segmentation and targeted marketing in later phases.

---

# 138. Future AI Extensions

Potential future tables:

```text
ai_conversations
ai_messages
ai_handoffs
```

AI-related database design shall be documented separately in Phase 3.

---

# 139. Future Multi-Branch Extensions

Not part of Version 1.

Potential future tables:

```text
branches
branch_users
branch_inventory
stock_transfers
stock_transfer_items
```

Current schema should not introduce them prematurely.

---

# 140. Database Design Decision Summary

| Area | Decision |
|---|---|
| Database | MySQL 8+ |
| Storage Engine | InnoDB |
| Primary Keys | BIGINT |
| Money | DECIMAL(15,2) |
| Inventory Level | Product Variant |
| Variant Attributes | Size + Colour |
| Inventory Ledger | Required |
| Current Stock Table | `inventories` |
| Costing | Weighted Average |
| Sale Cost | Historical Snapshot |
| Supplier Purchases | Separate from Expenses |
| Orders | Separate from Sales |
| Reservations | Explicit Table |
| Returns | Separate Transactions |
| Refunds | Separate Transactions |
| Exchanges | Separate Transactions |
| Walk-In Sales | Supported |
| Customer History | Derived from Sales |
| Audit Logs | Required |
| Soft Deletes | Master Data Only |
| Financial Records | Never Hard Deleted |
| Multi-Branch | Future |
| WhatsApp | Future |
| Automated Payments | Future |

---

# 141. Final Architecture Rule

The most important database rule is:

**Every physical or financial change must have a traceable transaction behind it.**

Examples:

```text
Stock increased
→ Purchase or Return

Stock decreased
→ Sale, Exchange, Damage, Loss, or Adjustment

Money received
→ Sale / Payment

Money returned
→ Refund

Profit generated
→ Sale revenue and historical cost

Inventory cost changed
→ Supplier purchase
```

There should be no unexplained changes to stock or financial records.

---

# 142. Database Implementation Readiness

The database design is sufficiently defined to proceed with:

- Laravel migrations
- Eloquent models
- Relationships
- Seeders
- Factory data
- Database constraints
- Transaction services
- API specification

No coding agent should invent additional core tables or accounting rules without updating this document.

---

# 143. Next Document

The next technical document shall be:

**`API_SPEC.md`**

It should define:

- API conventions
- Internal endpoints
- Authentication
- Product endpoints
- Inventory endpoints
- Purchase endpoints
- Sales endpoints
- Customer endpoints
- Order endpoints
- Return endpoints
- Refund endpoints
- Exchange endpoints
- Reports endpoints
- Error structures
- Pagination
- Filtering
- Validation
- Webhook architecture
- Future WhatsApp API handling
- Future payment callbacks

---

# 144. Document Status

**Status: Database Design Ready for API and Implementation Planning**

Core transactional schema is now defined for Version 1.

Remaining policy-level `TBD`s such as return period, refund authorization, exact payment provider, and hosting configuration do not block core database implementation.