# API_SPEC.md

# Mo Fashion Store Business Management System — MFBMS

## 1. Purpose

This document defines the API architecture, endpoint conventions, authentication model, request and response structures, validation rules, error handling, pagination, filtering, security, webhook design, and external integration strategy for the **Mo Fashion Store Business Management System (MFBMS)**.

The API shall support the approved business modules:

- Authentication
- Users and staff
- Product categories
- Products
- Product variants
- Suppliers
- Stock purchases
- Inventory
- Inventory movements
- Sales
- Customers
- Orders
- Reservations
- Returns
- Refunds
- Exchanges
- Expenses
- Reports
- Dashboard
- Audit logs
- System settings

Future integrations shall include:

- WhatsApp Business Platform
- Payment providers
- Automated notifications
- AI customer service

The original business case requires sales, inventory, customer management, orders, reporting, and later WhatsApp customer service within the same business system.

---

# 2. API Architecture

The system shall use a versioned REST-style API.

Base URL:

```text
/api/v1
```

Example:

```text
/api/v1/products
/api/v1/sales
/api/v1/orders
```

External integrations shall use separate webhook routes.

Example:

```text
/api/v1/webhooks/payments/{provider}
/api/v1/webhooks/whatsapp
```

---

# 3. API Usage Strategy

MFBMS is primarily a Laravel + Livewire application.

Therefore:

- not every internal screen must call REST endpoints;
- core business logic must live in reusable services;
- API controllers and Livewire components must call the same application services.

Example:

```text
Livewire Component
        │
        ▼
SaleService
        ▲
        │
API Controller
```

This prevents duplicate business logic.

---

# 4. API Principles

The API shall follow these principles:

1. Version all external-facing endpoints.
2. Validate all input server-side.
3. Never trust frontend financial calculations.
4. Never trust frontend stock calculations.
5. Perform critical operations transactionally.
6. Make external callbacks idempotent.
7. Return predictable response structures.
8. Use proper HTTP status codes.
9. Protect sensitive endpoints using authorization.
10. Keep provider-specific integration logic isolated.
11. Never expose internal exceptions directly.
12. Preserve historical business transactions.

---

# 5. Content Type

Requests:

```http
Content-Type: application/json
Accept: application/json
```

File uploads may use:

```http
multipart/form-data
```

---

# 6. Authentication

Internal API authentication shall support Laravel session authentication for the main web application.

Future external clients may use:

```text
Laravel Sanctum
```

if API token authentication becomes necessary.

Version 1 should not expose unnecessary public token-based APIs.

---

# 7. Authentication Endpoints

## POST /api/v1/auth/login

Authenticate a staff user.

### Request

```json
{
  "login": "admin@example.com",
  "password": "********"
}
```

### Successful Response

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "role": "administrator"
    }
  }
}
```

### Errors

```text
401 Invalid credentials
403 Account disabled
422 Validation error
429 Too many attempts
```

---

# 8. POST /api/v1/auth/logout

Ends the authenticated session.

Response:

```json
{
  "success": true,
  "message": "Logged out successfully."
}
```

---

# 9. GET /api/v1/auth/me

Returns the currently authenticated user.

Response:

```json
{
  "success": true,
  "data": {
    "id": 3,
    "name": "Asha",
    "role": "salesperson",
    "permissions": [
      "sales.create",
      "products.view",
      "customers.create"
    ]
  }
}
```

---

# 10. Authorization

Authorization shall use:

- Roles
- Permissions
- Laravel Policies
- Laravel Gates

Examples:

```text
administrator
salesperson
```

Possible permissions:

```text
products.view
products.create
products.update

inventory.view
inventory.adjust

sales.create
sales.view_all

returns.create

refunds.create
refunds.approve

expenses.view
expenses.create

reports.view

users.manage
```

---

# 11. Standard Success Response

Recommended format:

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

---

# 12. Standard Error Response

```json
{
  "success": false,
  "message": "Unable to complete the operation.",
  "errors": {
    "field": [
      "Validation message."
    ]
  }
}
```

---

# 13. Standard Domain Error Response

Example:

```json
{
  "success": false,
  "code": "INSUFFICIENT_STOCK",
  "message": "Only 2 units of Black / Size M are available."
}
```

---

# 14. HTTP Status Codes

Use:

```text
200 OK
201 Created
204 No Content

400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
429 Too Many Requests

500 Internal Server Error
503 Service Unavailable
```

---

# 15. Pagination

List endpoints shall support:

```text
?page=1
&per_page=20
```

Default:

```text
20
```

Maximum:

```text
100
```

Response:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

---

# 16. Sorting

Convention:

```text
?sort=name
?sort=-created_at
```

Prefix:

```text
-
```

means descending.

Only whitelisted fields shall be sortable.

---

# 17. Filtering

Example:

```text
/products?category_id=2&status=active
```

Example:

```text
/sales?date_from=2026-09-01&date_to=2026-09-30
```

Unknown filters shall be ignored or rejected consistently according to implementation policy.

---

# 18. Search

Search parameter:

```text
?q=
```

Example:

```text
/products?q=boyfriend
```

Customer:

```text
/customers?q=0712345678
```

---

# 19. Categories API

## GET /api/v1/categories

List product categories.

Filters:

```text
is_active
q
```

---

## POST /api/v1/categories

Create category.

Request:

```json
{
  "name": "Jeans",
  "description": "Women's jeans"
}
```

Permission:

```text
products.create
```

---

## GET /api/v1/categories/{id}

Retrieve category.

---

## PATCH /api/v1/categories/{id}

Update category.

---

## DELETE /api/v1/categories/{id}

This should normally soft-delete or deactivate the category.

Historical relationships must remain intact.

---

# 20. Sizes API

## GET /api/v1/sizes

## POST /api/v1/sizes

Request:

```json
{
  "name": "Medium",
  "code": "M",
  "sort_order": 3
}
```

---

# 21. Colours API

## GET /api/v1/colours

## POST /api/v1/colours

Request:

```json
{
  "name": "Black",
  "code": "BLACK",
  "hex_code": "#000000"
}
```

---

# 22. Products API

## GET /api/v1/products

Filters:

```text
q
category_id
is_active
size_id
colour_id
stock_status
```

Example:

```text
/api/v1/products?category_id=1&stock_status=low
```

---

# 23. POST /api/v1/products

Creates a product.

Request:

```json
{
  "name": "Boyfriend Jeans",
  "product_code": "MF-JN-001",
  "category_id": 1,
  "description": "Women's boyfriend jeans",
  "default_selling_price": 40000
}
```

---

# 24. GET /api/v1/products/{id}

Returns product with variants.

Example:

```json
{
  "success": true,
  "data": {
    "id": 10,
    "name": "Boyfriend Jeans",
    "category": {
      "id": 1,
      "name": "Jeans"
    },
    "variants": []
  }
}
```

---

# 25. PATCH /api/v1/products/{id}

Updates product master data.

Changing product pricing must not alter historical sale prices.

---

# 26. DELETE /api/v1/products/{id}

Deactivates or soft-deletes product.

Products appearing in historical transactions must never be physically removed.

---

# 27. Product Variants API

## POST /api/v1/products/{product}/variants

Request:

```json
{
  "size_id": 3,
  "colour_id": 1,
  "sku": "MF-JN-001-BLK-M",
  "selling_price": 40000,
  "low_stock_threshold": 2
}
```

Validation:

- product exists;
- size exists if supplied;
- colour exists if supplied;
- SKU unique;
- duplicate active size/colour combination prohibited;
- selling price non-negative.

---

# 28. GET /api/v1/variants/{id}

Response may include:

```json
{
  "id": 25,
  "sku": "MF-JN-001-BLK-M",
  "product": "Boyfriend Jeans",
  "size": "M",
  "colour": "Black",
  "selling_price": 40000,
  "inventory": {
    "physical_quantity": 12,
    "reserved_quantity": 2,
    "available_quantity": 10
  }
}
```

---

# 29. PATCH /api/v1/variants/{id}

Allowed changes may include:

- selling price;
- low-stock threshold;
- status.

Direct stock edits shall not be accepted through this endpoint.

---

# 30. Supplier API

## GET /api/v1/suppliers

Filters:

```text
q
is_active
```

---

## POST /api/v1/suppliers

Request:

```json
{
  "name": "Supplier A",
  "contact_person": "Jane",
  "phone": "07XXXXXXXX",
  "location": "Dar es Salaam"
}
```

---

## GET /api/v1/suppliers/{id}

May include purchase summary.

---

## PATCH /api/v1/suppliers/{id}

---

# 31. Purchases API

Supplier purchases are transactional records.

## GET /api/v1/purchases

Filters:

```text
supplier_id
status
payment_status
date_from
date_to
```

---

# 32. POST /api/v1/purchases

Creates draft purchase.

Request:

```json
{
  "supplier_id": 4,
  "purchase_date": "2026-09-21",
  "supplier_invoice_number": "INV-8492",
  "payment_status": "PAID",
  "items": [
    {
      "product_variant_id": 25,
      "quantity": 10,
      "unit_cost": 25000
    },
    {
      "product_variant_id": 26,
      "quantity": 5,
      "unit_cost": 26000
    }
  ]
}
```

Server calculates:

```text
line totals
subtotal
total
```

Frontend totals shall not be trusted.

---

# 33. POST /api/v1/purchases/{purchase}/confirm

This is a critical transactional endpoint.

Operations:

1. Validate purchase is DRAFT.
2. Validate purchase items.
3. Lock affected inventory rows.
4. Calculate weighted-average cost.
5. Increase inventory.
6. Create inventory movements.
7. Mark purchase CONFIRMED.
8. Store confirmation user/time.
9. Commit transaction.

Response:

```json
{
  "success": true,
  "message": "Purchase confirmed and inventory updated.",
  "data": {
    "purchase_number": "MFS-PUR-000125"
  }
}
```

Repeated confirmation shall return:

```text
409 Conflict
```

and must not increase stock twice.

---

# 34. POST /api/v1/purchases/{purchase}/cancel

Allowed only under business rules.

A confirmed purchase shall not simply be deleted.

Reversal rules must be used if inventory has already been affected.

---

# 35. Inventory API

## GET /api/v1/inventory

Filters:

```text
q
category_id
product_id
size_id
colour_id
status
```

Stock statuses:

```text
in_stock
low_stock
out_of_stock
```

---

# 36. GET /api/v1/inventory/{variant}

Response:

```json
{
  "success": true,
  "data": {
    "variant_id": 25,
    "sku": "MF-JN-001-BLK-M",
    "physical_quantity": 12,
    "reserved_quantity": 2,
    "available_quantity": 10,
    "weighted_average_cost": 27500,
    "low_stock_threshold": 2
  }
}
```

The original business case requires stock to be tracked by product, size, colour, and quantity, with low-stock warnings. 
---

# 37. Inventory Adjustment API

## POST /api/v1/inventory/{variant}/adjust

Permission:

```text
inventory.adjust
```

Request:

```json
{
  "direction": "DECREASE",
  "quantity": 1,
  "reason": "DAMAGED",
  "notes": "Item damaged during handling"
}
```

Allowed reasons may include:

```text
DAMAGED
LOSS
COUNT_CORRECTION
OTHER
```

The endpoint shall:

- lock inventory;
- validate result;
- update balance;
- create inventory movement;
- create audit record.

---

# 38. Inventory Movements API

## GET /api/v1/inventory/{variant}/movements

Filters:

```text
movement_type
date_from
date_to
```

Movement history is read-only.

---

# 39. Sales API

## GET /api/v1/sales

Filters:

```text
q
customer_id
salesperson_id
payment_method
status
date_from
date_to
product_id
variant_id
```

---

# 40. POST /api/v1/sales

Creates a counter sale.

Request:

```json
{
  "customer_id": 12,
  "payment_method": "M_PESA",
  "payment_reference": "ABC123",
  "items": [
    {
      "product_variant_id": 25,
      "quantity": 2,
      "unit_price": 40000,
      "discount_amount": 0
    }
  ]
}
```

`customer_id` may be null for walk-in customers.

---

# 41. Sale Creation Rules

The server must:

1. Validate variant.
2. Lock inventory.
3. Validate available stock.
4. Read current weighted-average cost.
5. Capture historical unit cost.
6. Calculate totals.
7. Create sale.
8. Create sale items.
9. Deduct inventory.
10. Create inventory movements.
11. Update customer statistics.
12. Commit transaction.

The original business case requires completed sales to reduce stock automatically.

---

# 42. Sale Response

```json
{
  "success": true,
  "data": {
    "sale_number": "MFS-SAL-000125",
    "total_amount": 80000,
    "total_cogs": 50000,
    "gross_profit": 30000,
    "payment_method": "M_PESA",
    "status": "COMPLETED"
  }
}
```

---

# 43. Insufficient Stock Error

HTTP:

```text
409 Conflict
```

Response:

```json
{
  "success": false,
  "code": "INSUFFICIENT_STOCK",
  "message": "Requested quantity exceeds available stock.",
  "data": {
    "variant_id": 25,
    "requested": 3,
    "available": 2
  }
}
```

---

# 44. POST /api/v1/sales/{sale}/cancel

Sensitive operation.

Permission:

```text
sales.cancel
```

A completed sale shall not be physically deleted.

Cancellation must follow reversal rules and create audit records.

Detailed cancellation policy remains dependent on final business rules.

---

# 45. GET /api/v1/sales/{sale}

Returns:

- sale;
- items;
- customer;
- salesperson;
- totals;
- payment;
- related return/refund/exchange information.

---

# 46. Customers API

## GET /api/v1/customers

Search:

```text
name
phone
whatsapp number
```

---

# 47. POST /api/v1/customers

Request:

```json
{
  "full_name": "Neema Joseph",
  "phone": "07XXXXXXXX",
  "whatsapp_number": "07XXXXXXXX",
  "location": "Morogoro",
  "preferred_size_id": 3,
  "preferred_colour_id": 1,
  "marketing_opt_in": true
}
```

The business case requires customer details, clothing preferences, purchase history, first/last purchase dates, total purchases, and total spending.

---

# 48. GET /api/v1/customers/{customer}

Response includes:

- profile;
- preferences;
- statistics;
- recent purchases.

---

# 49. GET /api/v1/customers/{customer}/purchases

Returns customer purchase history.

Filters:

```text
date_from
date_to
product_id
```

---

# 50. Orders API

## GET /api/v1/orders

Filters:

```text
q
customer_id
salesperson_id
status
payment_status
date_from
date_to
```

---

# 51. POST /api/v1/orders

Request:

```json
{
  "customer_id": 12,
  "items": [
    {
      "product_variant_id": 25,
      "quantity": 2,
      "unit_price": 40000
    }
  ],
  "delivery_address": "Morogoro",
  "notes": "Customer contacted via WhatsApp"
}
```

Delivery fees shall not be included in Version 1 accounting.

---

# 52. POST /api/v1/orders/{order}/confirm

Critical transactional endpoint.

Operations:

1. Validate NEW order.
2. Lock affected inventory.
3. Verify stock availability.
4. Create stock reservations.
5. Increase reserved quantity.
6. Set order CONFIRMED.
7. Commit.

---

# 53. Order Confirmation Response

```json
{
  "success": true,
  "message": "Order confirmed and stock reserved.",
  "data": {
    "order_number": "MFS-ORD-001255",
    "status": "CONFIRMED"
  }
}
```

---

# 54. POST /api/v1/orders/{order}/cancel

Operations:

1. Validate cancellation allowed.
2. Lock inventory.
3. Release active reservations.
4. Update reserved quantities.
5. Mark reservations RELEASED.
6. Mark order CANCELLED.
7. Commit.

---

# 55. POST /api/v1/orders/{order}/mark-paid

For manual payment confirmation.

Request:

```json
{
  "payment_method": "M_PESA",
  "payment_reference": "MP1234567"
}
```

Permission depends on user role.

For integrated payment transactions, this endpoint shall not replace provider confirmation.

---

# 56. POST /api/v1/orders/{order}/convert-to-sale

Creates a completed sale from a paid order.

Operations:

- validate order payment;
- validate reservations;
- create sale;
- capture cost snapshots;
- deduct physical inventory;
- reduce reserved inventory;
- complete reservation;
- create inventory movements;
- update customer history;
- commit.

Endpoint must be idempotent.

One order shall not create multiple primary sales in Version 1.

---

# 57. PATCH /api/v1/orders/{order}/status

Request:

```json
{
  "status": "PREPARING"
}
```

Allowed state transitions must be enforced.

Do not allow arbitrary jumps.

---

# 58. Valid Order Transitions

Typical:

```text
NEW
→ CONFIRMED
→ PAYMENT_RECEIVED
→ PREPARING
→ OUT_FOR_DELIVERY
→ DELIVERED
```

Cancellation rules apply separately.

The original business case defines this order journey.

---

# 59. Returns API

## POST /api/v1/returns

Request:

```json
{
  "sale_id": 100,
  "reason": "Wrong size",
  "items": [
    {
      "sale_item_id": 280,
      "quantity": 1,
      "condition": "SELLABLE"
    }
  ]
}
```

---

# 60. Return Processing Rules

Phase 12 policy: a return must be processed within three days (72 hours) of sale
completion, inclusive. Receipt or recorded sale is sufficient proof, but every
return must reference the recorded original sale/items. Both initial roles may
approve within existing sale access scope. `returns.create` protects initiation
and reading; `returns.approve` protects approval, rejection and completion.

The current Blade application uses authenticated CSRF-protected `/returns`
routes, not a deployed public `/api/v1` integration. Its create input additionally
requires request_key, reason and proof_type (SALE_RECORD or RECEIPT); RECEIPT
requires proof_reference. For SALE_RECORD, the server stores the actual sale
number. Zero-quantity form rows are ignored; at least one positive line is required.
Costs, totals, actor IDs and stock-restored flags are server-owned.

Creation persists PENDING without stock effects. Approval persists APPROVED;
completion atomically restores only sellable stock and records the historical
cost adjustment. State changes recheck eligibility and remaining quantities.
Identical creation retries return the original record; conflicting keys and
repeated transitions fail safely. Rejection records a reason without stock effects.
Refund processing remains separate. The following stock-processing steps apply
to completion, rather than to draft creation.

The server shall:

1. Validate original sale.
2. Validate sale item.
3. Validate remaining returnable quantity.
4. Create return transaction.
5. Process each return item.
6. Restore sellable inventory where appropriate.
7. Use historical sale cost.
8. Create inventory movements.
9. Commit.

---

# 61. Return Quantity Error

```json
{
  "success": false,
  "code": "RETURN_QUANTITY_EXCEEDED",
  "message": "The requested return quantity exceeds the remaining returnable quantity."
}
```

---

# 62. GET /api/v1/returns/{return}

Returns return details.

---

# 63. Refunds API

## POST /api/v1/refunds

Permission:

```text
refunds.create
```

Potential approval permission:

```text
refunds.approve
```

Request:

```json
{
  "sale_id": 100,
  "return_id": 20,
  "refund_method": "M_PESA",
  "reason": "Returned item",
  "items": [
    {
      "sale_item_id": 280,
      "quantity": 1,
      "amount": 40000
    }
  ]
}
```

---

# 64. Refund Rules

The system shall ensure:

```text
completed refunds <= refundable sale value
```

The original sale must remain unchanged.

---

# 65. POST /api/v1/refunds/{refund}/approve

Used if owner approval is enabled.

Exact authorization remains `TBD`.

---

# 66. POST /api/v1/refunds/{refund}/complete

Marks refund completed after money is actually returned.

Manual refunds remain supported.

Integrated refund APIs may be added later if the selected provider supports them.

---

# 67. Exchanges API

## POST /api/v1/exchanges

Request:

```json
{
  "sale_id": 100,
  "returned_items": [
    {
      "sale_item_id": 280,
      "quantity": 1,
      "condition": "SELLABLE"
    }
  ],
  "replacement_items": [
    {
      "product_variant_id": 305,
      "quantity": 1,
      "unit_price": 45000
    }
  ]
}
```

---

# 68. Exchange Processing

Server shall:

1. Validate original sale.
2. Validate returned quantity.
3. Lock returned/replacement inventory.
4. Validate replacement availability.
5. Calculate returned value.
6. Calculate replacement value.
7. Determine amount due or refund due.
8. Process inventory adjustments.
9. Create exchange records.
10. Create payment/refund requirement if applicable.
11. Commit.

---

# 69. Exchange Response

```json
{
  "success": true,
  "data": {
    "exchange_number": "MFS-EXC-000019",
    "returned_value": 40000,
    "replacement_value": 45000,
    "amount_due": 5000,
    "refund_due": 0
  }
}
```

---

# 70. Expenses API

## GET /api/v1/expenses

Filters:

```text
category_id
date_from
date_to
recorded_by
```

---

## POST /api/v1/expenses

Request:

```json
{
  "expense_category_id": 2,
  "expense_date": "2026-09-21",
  "amount": 25000,
  "description": "Electricity"
}
```

Stock purchases shall not use this endpoint.

---

# 71. PATCH /api/v1/expenses/{expense}

Authorization required.

Changes should be audited.

---

# 72. Expense Categories API

```text
GET /api/v1/expense-categories
POST /api/v1/expense-categories
PATCH /api/v1/expense-categories/{id}
```

---

# 73. Dashboard API

## GET /api/v1/dashboard

Response example:

```json
{
  "success": true,
  "data": {
    "today": {
      "sales_revenue": 850000,
      "sales_count": 21,
      "gross_profit": 320000,
      "expenses": 50000,
      "estimated_net_profit": 270000,
      "orders": 21,
      "new_customers": 6
    },
    "month": {
      "sales_revenue": 12600000,
      "cogs": 7800000,
      "gross_profit": 4800000,
      "expenses": 1100000,
      "estimated_net_profit": 3700000
    },
    "inventory": {
      "low_stock": 7,
      "out_of_stock": 2
    }
  }
}
```

The business case expects the dashboard to expose sales, orders, customers, expenses, estimated profit, top products, and top customers.

---

# 74. Reports API

## GET /api/v1/reports/sales

Filters:

```text
date_from
date_to
salesperson_id
payment_method
product_id
variant_id
category_id
```

---

# 75. GET /api/v1/reports/inventory

Filters:

```text
category_id
product_id
size_id
colour_id
stock_status
```

---

# 76. GET /api/v1/reports/purchases

Filters:

```text
supplier_id
date_from
date_to
product_id
```

---

# 77. GET /api/v1/reports/customers

Filters:

```text
date_from
date_to
purchase_count_min
spent_min
```

---

# 78. GET /api/v1/reports/expenses

Filters:

```text
category_id
date_from
date_to
```

---

# 79. GET /api/v1/reports/profit

Request:

```text
?date_from=2026-09-01&date_to=2026-09-30
```

Response:

```json
{
  "success": true,
  "data": {
    "gross_sales": 10000000,
    "refunds": 200000,
    "net_sales": 9800000,
    "cogs": 6000000,
    "gross_profit": 3800000,
    "operating_expenses": 1200000,
    "estimated_net_profit": 2600000
  }
}
```

The business case explicitly includes profit and expense reporting.

---

# 80. Report Export

Future/optional:

```text
GET /api/v1/reports/sales/export?format=csv
GET /api/v1/reports/profit/export?format=pdf
```

Large exports should use queued jobs.

---

# 81. Users API

Administrator only.

```text
GET /api/v1/users
POST /api/v1/users
GET /api/v1/users/{id}
PATCH /api/v1/users/{id}
```

---

# 82. POST /api/v1/users/{user}/deactivate

Safer than deleting staff accounts.

Historical sales must remain linked to the user.

---

# 83. Roles and Permissions API

Administrator only.

```text
GET /api/v1/roles
GET /api/v1/permissions
PATCH /api/v1/roles/{role}/permissions
```

---

# 84. Audit API

Administrator only.

## GET /api/v1/audit-logs

Filters:

```text
user_id
action
entity_type
entity_id
date_from
date_to
```

Read-only.

---

# 85. Settings API

Administrator only.

```text
GET /api/v1/settings
PATCH /api/v1/settings
```

Sensitive integration secrets shall not be returned in plaintext.

---

# 86. Payment Architecture

Payments shall be separated from business order logic.

Core abstraction:

```php
interface PaymentGatewayInterface
{
    public function createPayment(...);
    public function verifyPayment(...);
    public function handleWebhook(...);
    public function queryTransaction(...);
}
```

Provider-specific implementations shall implement the interface.

Example:

```text
PaymentGatewayInterface
        │
        ├── SnippeGateway
        ├── SelcomGateway
        └── FutureGateway
```

Actual provider selection remains:

```text
TBD
```

No provider-specific assumptions shall be hardcoded into core order logic.

---

# 87. Payment Creation Endpoint

Future:

## POST /api/v1/orders/{order}/payment-request

Request:

```json
{
  "provider": "TBD",
  "payment_method": "MOBILE_MONEY"
}
```

The server shall:

1. Validate order.
2. Validate unpaid amount.
3. Generate internal payment record.
4. Send request to gateway.
5. Store external reference.
6. Return payment instructions.

---

# 88. Payment Response

Example generic response:

```json
{
  "success": true,
  "data": {
    "payment_number": "MFS-PAY-000119",
    "status": "PENDING",
    "provider": "TBD",
    "amount": 80000,
    "currency": "TZS",
    "instructions": {}
  }
}
```

Provider-specific fields shall be contained under structured metadata.

---

# 89. Payment Webhook Endpoint

```text
POST /api/v1/webhooks/payments/{provider}
```

This endpoint is public but strongly authenticated using provider verification mechanisms.

---

# 90. Payment Webhook Flow

```text
Provider Callback
      ↓
Verify Provider
      ↓
Validate Signature
      ↓
Parse External Transaction
      ↓
Locate Internal Payment
      ↓
Check Idempotency
      ↓
Verify Amount
      ↓
Verify Currency
      ↓
Update Payment
      ↓
If Successful:
   Mark Order Paid
      ↓
Convert Order to Sale
      ↓
Complete Inventory Reservation
```

---

# 91. Payment Webhook Security

The implementation must verify whichever mechanisms the selected provider supports, such as:

- HMAC signatures;
- API secret validation;
- public-key signatures;
- IP restrictions where reliable;
- external transaction lookup;
- amount verification;
- currency verification.

Provider-specific requirements shall be defined only after the provider is selected.

---

# 92. Payment Idempotency

Payment webhook processing must enforce:

```text
(provider, external_reference)
```

uniqueness.

Duplicate callback:

```json
{
  "success": true,
  "message": "Webhook already processed."
}
```

It must never:

- create another payment;
- create another sale;
- deduct stock twice.

---

# 93. Payment Status Mapping

Providers may use different statuses.

The integration adapter shall map them to internal statuses:

```text
PENDING
SUCCESSFUL
FAILED
CANCELLED
REFUNDED
```

Core application logic shall use only internal statuses.

---

# 94. Provider Failure

If provider API is temporarily unavailable:

- keep internal payment `PENDING`;
- log integration failure;
- allow safe retry;
- do not complete sale;
- do not permanently deduct reserved inventory.

---

# 95. Payment Verification

Webhook success should normally be sufficient only after cryptographic/provider verification.

Where appropriate, the system may additionally call the provider transaction-verification API before completing the order.

Never trust:

- customer screenshot;
- browser redirect;
- query parameter alone.

---

# 96. WhatsApp Integration Architecture

The original business case expects WhatsApp to eventually answer product questions, stock questions, prices, sizes, colours, offers, delivery questions, and order status.

Integration flow:

```text
Customer
   ↓
WhatsApp
   ↓
WhatsApp Business Platform
   ↓
MFBMS Webhook
   ↓
Conversation Service
   ↓
Customer / Product / Inventory / Order Services
   ↓
Response
   ↓
WhatsApp
```

---

# 97. WhatsApp Webhook Endpoint

Future:

```text
POST /api/v1/webhooks/whatsapp
```

Verification endpoint may also be required depending on provider/platform requirements.

---

# 98. WhatsApp Webhook Responsibilities

The webhook layer shall:

1. Verify request authenticity.
2. Parse incoming message.
3. Identify customer by WhatsApp number.
4. Store message event if Phase 2 schema supports it.
5. Route message to customer-service logic.
6. Send response.
7. Prevent duplicate event processing.

---

# 99. WhatsApp Product Lookup

Internal service example:

```text
ProductAvailabilityService
```

Input:

```text
Product
Size
Colour
```

Output:

```json
{
  "available": true,
  "product": "Boyfriend Jeans",
  "size": "M",
  "colour": "Black",
  "available_quantity": 7,
  "selling_price": 40000
}
```

The chatbot must retrieve actual database stock rather than generate availability from AI memory.

---

# 100. WhatsApp Order Creation

Future internal endpoint/service:

```text
POST /api/v1/integrations/whatsapp/orders
```

or preferably direct application service invocation from webhook processing.

The system shall:

- identify/create customer;
- select variant;
- validate stock;
- create order;
- reserve inventory when order confirmation criteria are met.

---

# 101. WhatsApp Payment Journey

Future flow:

```text
Customer asks for item
      ↓
System verifies stock
      ↓
Order created
      ↓
Stock reserved
      ↓
Payment request generated
      ↓
Customer pays
      ↓
Payment provider webhook
      ↓
Payment verified
      ↓
Sale created
      ↓
Stock permanently deducted
      ↓
WhatsApp confirmation sent
```

This ensures payment confirmation, not the chatbot, controls financial completion.

---

# 102. WhatsApp Outbound Messaging

Create an abstraction:

```php
interface MessagingGatewayInterface
{
    public function sendText(...);
    public function sendTemplate(...);
    public function sendMedia(...);
}
```

Implementation:

```text
WhatsAppBusinessGateway
```

This isolates Meta/provider-specific code.

---

# 103. WhatsApp Templates

Promotional and notification templates shall be handled according to the rules of the selected WhatsApp Business Platform configuration.

Template names and provider IDs shall not be hardcoded throughout application logic.

Store them centrally.

---

# 104. Customer Consent

Future outbound marketing shall check:

```text
marketing_opt_in = true
```

before sending promotional messages.

The original business case explicitly requires promotional messaging to customers who have agreed to receive it and a way to stop messages.

---

# 105. AI Integration

AI customer service belongs to Phase 3.

The AI layer shall never be considered the business source of truth.

Architecture:

```text
Customer Message
      ↓
Conversation Orchestrator
      ↓
Intent Detection
      ↓
Business Data Tools
      ↓
AI Model
      ↓
Validated Response
```

---

# 106. AI Business Data Access

AI may request controlled operations such as:

```text
findProduct()
checkVariantAvailability()
getPrice()
getOrderStatus()
getStoreInformation()
```

It shall not query unrestricted database tables directly.

---

# 107. AI Transaction Restrictions

AI must not independently:

- mark a payment successful;
- change stock;
- issue refunds;
- modify inventory;
- approve returns;
- alter financial records.

Instead it must invoke authorized application services.

---

# 108. AI Handoff

When the AI cannot safely answer:

```text
AI → Human Staff
```

The business case explicitly expects unresolved customer questions to be transferred to staff.

---

# 109. Integration Service Structure

Recommended Laravel organization:

```text
app/
└── Integrations/
    ├── Payments/
    │   ├── Contracts/
    │   │   └── PaymentGatewayInterface.php
    │   ├── DTOs/
    │   ├── Gateways/
    │   └── Webhooks/
    │
    ├── WhatsApp/
    │   ├── Contracts/
    │   ├── DTOs/
    │   ├── WhatsAppGateway.php
    │   └── Webhooks/
    │
    └── AI/
        ├── Contracts/
        ├── Providers/
        └── Tools/
```

---

# 110. DTO Strategy

External provider responses should be converted into internal DTOs.

Example:

```text
ProviderPaymentResponse
        ↓
PaymentResultDTO
```

Core application logic should not depend directly on raw provider JSON.

---

# 111. Example Internal Payment DTO

```php
PaymentResultDTO
{
    provider;
    externalReference;
    status;
    amount;
    currency;
    paidAt;
}
```

---

# 112. API Validation

Use Laravel Form Requests for HTTP validation.

Examples:

```text
CreateSaleRequest
CreateOrderRequest
CreatePurchaseRequest
CreateReturnRequest
CreateRefundRequest
CreateExchangeRequest
```

Domain services shall also validate business rules.

HTTP validation alone is insufficient.

---

# 113. Validation Example

Creating sale:

```text
items required
items must be array
items minimum 1

product_variant_id required
product_variant_id exists

quantity integer
quantity > 0

unit_price numeric
unit_price >= 0

discount_amount >= 0
```

Then domain layer validates:

```text
stock availability
variant active
sale state
permissions
```

---

# 114. Race Condition Protection

For stock-affecting operations:

```text
DB Transaction
+
lockForUpdate()
```

must be used.

Applicable endpoints:

- purchase confirmation;
- sale completion;
- order confirmation;
- order cancellation;
- order-to-sale conversion;
- returns;
- exchanges;
- inventory adjustments.

---

# 115. Rate Limiting

Authentication endpoints:

Strict throttling.

Example:

```text
5 login attempts per minute
```

Public webhook endpoints:

Provider-aware rate limits that do not block legitimate callback retries.

Future public APIs:

API-key/user-based throttling.

---

# 116. CSRF

Browser session routes shall use Laravel CSRF protection.

External webhooks shall not rely on CSRF tokens.

They shall instead use:

- signature verification;
- secret verification;
- provider authentication mechanisms.

---

# 117. CORS

Default:

Do not enable broad cross-origin access.

If future external frontend clients exist:

Allow only approved origins.

Never use unrestricted production CORS without need.

---

# 118. API Logging

Log:

- endpoint errors;
- external integration failures;
- webhook verification failures;
- failed background jobs.

Do not log:

- passwords;
- authentication tokens;
- payment secrets;
- API secrets;
- full sensitive customer payloads unnecessarily.

---

# 119. Correlation IDs

Recommended for integrations:

Each external request may include/generate:

```text
correlation_id
```

This helps trace:

```text
Order
→ Payment Request
→ Provider Callback
→ Sale
```

---

# 120. Idempotency Keys

For future integration-facing POST requests, support:

```http
Idempotency-Key: unique-value
```

especially for:

- payment request creation;
- order creation from external systems;
- refund request;
- other retried financial operations.

---

# 121. Idempotent Request Response

If the same idempotency key is reused for the same operation:

Return the original successful response instead of executing again.

---

# 122. Webhook Processing Strategy

Recommended flow:

```text
HTTP Webhook
      ↓
Verify
      ↓
Store/identify event
      ↓
Return 200 quickly where appropriate
      ↓
Queue heavy processing
```

For payment completion involving stock, transaction processing must remain safe and deterministic.

---

# 123. Webhook Event Storage

When external integrations begin, recommended table:

```text
webhook_events
```

Fields:

```text
id
provider
external_event_id
event_type
payload
status
attempts
processed_at
error_message
created_at
```

Unique:

```text
(provider, external_event_id)
```

This provides robust idempotency and troubleshooting.

---

# 124. Integration Retry Strategy

Transient integration errors:

```text
Retry
```

Permanent validation errors:

```text
Do not retry indefinitely
```

Recommended queue retry pattern:

```text
1 minute
5 minutes
15 minutes
1 hour
```

Exact policy may be configured later.

---

# 125. Dead-Letter Handling

Failed integration jobs should eventually move to failed-job storage for administrator review.

Never silently discard failed payment or WhatsApp events.

---

# 126. Health Endpoint

Optional:

```text
GET /api/v1/health
```

Response:

```json
{
  "status": "ok"
}
```

Do not expose sensitive environment details.

---

# 127. API Versioning

Current:

```text
v1
```

Future breaking changes:

```text
/api/v2/
```

Non-breaking changes may remain within `v1`.

---

# 128. Breaking Change Examples

Require new API version when:

- endpoint semantics fundamentally change;
- required request structure changes incompatibly;
- response fields are removed;
- status logic changes incompatibly.

Adding optional fields normally does not require a new version.

---

# 129. Endpoint Summary

## Authentication

```text
POST   /auth/login
POST   /auth/logout
GET    /auth/me
```

## Categories

```text
GET    /categories
POST   /categories
GET    /categories/{id}
PATCH  /categories/{id}
DELETE /categories/{id}
```

## Products

```text
GET    /products
POST   /products
GET    /products/{id}
PATCH  /products/{id}
DELETE /products/{id}
```

## Variants

```text
POST   /products/{product}/variants
GET    /variants/{id}
PATCH  /variants/{id}
```

## Suppliers

```text
GET    /suppliers
POST   /suppliers
GET    /suppliers/{id}
PATCH  /suppliers/{id}
```

## Purchases

```text
GET    /purchases
POST   /purchases
GET    /purchases/{id}
POST   /purchases/{id}/confirm
POST   /purchases/{id}/cancel
```

## Inventory

```text
GET    /inventory
GET    /inventory/{variant}
POST   /inventory/{variant}/adjust
GET    /inventory/{variant}/movements
```

## Sales

```text
GET    /sales
POST   /sales
GET    /sales/{id}
POST   /sales/{id}/cancel
```

## Customers

```text
GET    /customers
POST   /customers
GET    /customers/{id}
PATCH  /customers/{id}
GET    /customers/{id}/purchases
```

## Orders

```text
GET    /orders
POST   /orders
GET    /orders/{id}
POST   /orders/{id}/confirm
POST   /orders/{id}/cancel
POST   /orders/{id}/mark-paid
POST   /orders/{id}/convert-to-sale
PATCH  /orders/{id}/status
```

## Returns

```text
GET    /returns
POST   /returns
GET    /returns/{id}
```

## Refunds

```text
GET    /refunds
POST   /refunds
GET    /refunds/{id}
POST   /refunds/{id}/approve
POST   /refunds/{id}/complete
```

## Exchanges

```text
GET    /exchanges
POST   /exchanges
GET    /exchanges/{id}
```

## Expenses

```text
GET    /expenses
POST   /expenses
PATCH  /expenses/{id}
```

## Reports

```text
GET    /reports/sales
GET    /reports/inventory
GET    /reports/purchases
GET    /reports/customers
GET    /reports/expenses
GET    /reports/profit
```

## Dashboard

```text
GET    /dashboard
```

## Users

```text
GET    /users
POST   /users
GET    /users/{id}
PATCH  /users/{id}
POST   /users/{id}/deactivate
```

## Audit

```text
GET    /audit-logs
```

## Settings

```text
GET    /settings
PATCH  /settings
```

## Future Integrations

```text
POST   /orders/{order}/payment-request

POST   /webhooks/payments/{provider}

POST   /webhooks/whatsapp
```

---

# 130. Critical API Business Rules

The API must enforce:

1. Frontend never directly modifies stock.
2. Inventory-changing requests use transaction services.
3. Completed purchases cannot increase stock twice.
4. Sales cannot exceed available stock.
5. Sale cost snapshots use the current weighted-average cost.
6. Historical sale cost snapshots never change.
7. Orders and sales remain separate.
8. Confirmed orders reserve stock.
9. Cancelled orders release stock.
10. Integrated payments are verified by provider callback.
11. Payment callback processing is idempotent.
12. Returns cannot exceed sold quantity.
13. Refunds cannot exceed refundable value.
14. Exchanges validate replacement stock.
15. Financial records are not deleted.
16. AI cannot directly alter financial or inventory records.
17. External integrations must go through adapters.
18. Provider-specific statuses must be mapped to internal statuses.

---

# 131. API Security Checklist

Before production:

- HTTPS enforced
- Authentication enabled
- Authorization tested
- Login throttling enabled
- CSRF enabled for browser routes
- CORS restricted
- Secrets stored outside Git
- Webhook signatures verified
- Idempotency implemented
- Input validation enabled
- SQL injection protection maintained through parameterized ORM/query builder
- Sensitive logs avoided
- Rate limiting enabled
- Audit logging enabled
- Failed jobs monitored
- Backup process tested

---

# 132. Provider Integration Decision Rule

Before integrating any payment or messaging provider, the development team must obtain and verify:

- official API documentation;
- authentication method;
- sandbox/test environment;
- production onboarding requirements;
- webhook specification;
- signature verification mechanism;
- transaction query API;
- supported payment networks;
- settlement model;
- fee structure;
- refund support;
- uptime/retry expectations.

No integration shall be built from assumptions.

If provider information is unknown:

```text
TBD
```

shall be used.

---

# 133. Integration Testing Requirements

Payment integration tests must cover:

1. Successful payment.
2. Failed payment.
3. Pending payment.
4. Duplicate webhook.
5. Invalid signature.
6. Wrong payment amount.
7. Unknown transaction reference.
8. Payment after order cancellation.
9. Provider timeout.
10. Callback retry.
11. Sale creation after verified payment.
12. Inventory deduction exactly once.

---

# 134. WhatsApp Integration Tests

Must cover:

1. Incoming known customer.
2. Incoming unknown customer.
3. Product lookup.
4. Size lookup.
5. Colour lookup.
6. In-stock product.
7. Out-of-stock product.
8. Order creation.
9. Duplicate incoming webhook.
10. Human handoff.
11. Opt-out customer.
12. Failed outbound message.

---

# 135. API Documentation

Recommended:

OpenAPI / Swagger documentation.

Future implementation should expose internal developer documentation describing:

- path;
- method;
- authentication;
- permissions;
- parameters;
- request body;
- response;
- errors.

Production Swagger UI should be protected or disabled if it exposes sensitive internal functionality.

---

# 136. Recommended Laravel Components

Use:

```text
Controllers
Form Requests
API Resources
Policies
Services
DTOs
Enums
Events
Jobs
Listeners
```

Examples:

```text
SaleController
CreateSaleRequest
SaleResource
SalePolicy
SaleService
```

---

# 137. Controllers Must Stay Thin

Bad architecture:

```text
SaleController
→ 300 lines of inventory/accounting logic
```

Correct architecture:

```text
SaleController
      ↓
SaleService
      ↓
InventoryService
      ↓
CostingService
```

Controllers should primarily:

- receive request;
- authorize;
- validate;
- call service;
- return response.

---

# 138. API Implementation Priority

Recommended implementation order:

## Phase A

- Authentication
- Categories
- Sizes
- Colours
- Products
- Variants
- Suppliers

## Phase B

- Purchases
- Inventory
- Inventory movements

## Phase C

- Customers
- Sales

## Phase D

- Orders
- Reservations

## Phase E

- Returns
- Refunds
- Exchanges

## Phase F

- Expenses
- Dashboard
- Reports

## Phase G

- Audit
- Settings
- Hardening

## Phase H — Future

- Payment integration
- WhatsApp integration

## Phase I — Future

- AI integration

---

# 139. API Readiness

The core API specification is sufficiently defined for:

- Laravel route creation;
- controllers;
- Form Requests;
- API Resources;
- policies;
- business services;
- integration interfaces;
- automated tests.

Provider-specific payment and WhatsApp implementation must wait until official provider details are selected and verified.

---

# 140. Next Document

The next recommended document is:

**`SECURITY.md`**

It should define:

- authentication security;
- password rules;
- authorization;
- sensitive actions;
- session security;
- CSRF;
- XSS;
- SQL injection protection;
- webhook security;
- API secret management;
- payment security;
- audit strategy;
- logging;
- backups;
- file upload security;
- customer-data protection;
- incident handling;
- production hardening.

---

# 141. Document Status

**Status: API Architecture and Integration Specification Ready**

Core Version 1 APIs are defined.

Payment-provider-specific fields, WhatsApp credentials, exact webhook signatures, and production integration configuration remain:

```text
TBD
```

until the relevant official provider documentation is selected and reviewed.
