# ARCHITECTURE.md

# Mo Fashion Store Business Management System — MFBMS

## 1. Purpose

This document defines the technical architecture for the **Mo Fashion Store Business Management System (MFBMS)**.

The architecture is based on the approved requirements and PRD for a single-branch fashion retail business managing:

- Products and variants
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
- Profit calculations
- Reports
- Staff access

Future phases will introduce WhatsApp integration, payment-provider integration, automated customer communication, and AI customer service.

The original business case specifically requires the system to combine sales, inventory, customer management, and eventually WhatsApp customer service.

---

# 2. Architecture Goals

The system architecture shall prioritize:

1. Correct inventory calculations
2. Reliable financial calculations
3. Transaction consistency
4. Maintainability
5. Security
6. Simple deployment
7. Reasonable hosting cost
8. Future extensibility
9. Easy development by a small development team
10. Avoidance of unnecessary complexity

---

# 3. Architecture Style

MFBMS shall use a:

# Modular Monolith Architecture

All core modules shall run inside one Laravel application while remaining logically separated.

Example:

```text
MFBMS Application
│
├── Authentication
├── Users
├── Products
├── Suppliers
├── Purchasing
├── Inventory
├── Sales
├── Customers
├── Orders
├── Returns
├── Refunds
├── Exchanges
├── Expenses
├── Reporting
└── Audit
```

A microservices architecture shall **not** be used for Version 1.

---

# 4. Why Modular Monolith

A modular monolith is appropriate because:

- Mo Fashion Store currently operates one branch.
- The expected initial user count is relatively small.
- Inventory, sales, orders, returns, and payments require strong database transactions.
- Deployment remains simple.
- Infrastructure costs remain low.
- Development and debugging remain manageable.
- Future APIs and integrations can still be added.

Microservices would introduce unnecessary:

- Network communication
- Deployment complexity
- Distributed transactions
- Monitoring requirements
- Infrastructure costs

without providing meaningful Version 1 benefits.

---

# 5. Recommended Technology Stack

## Backend

**PHP 8.3+**

Framework:

**Laravel**

Laravel shall provide:

- Routing
- Authentication
- Authorization
- Validation
- ORM
- Database transactions
- Queues
- Scheduling
- Events
- Logging
- API endpoints
- Webhooks
- Security protections

---

# 6. Frontend

Recommended stack:

- Laravel Blade
- Livewire
- Alpine.js
- Tailwind CSS

Architecture:

```text
Blade
  ↓
Livewire Components
  ↓
Application Services
  ↓
Domain Logic
  ↓
Database
```

A separate React/Vue frontend is not required.

---

# 7. Why Blade + Livewire

The system is primarily:

- Dashboard based
- Form heavy
- CRUD heavy
- POS focused
- Report focused

Using Livewire avoids introducing a separate SPA architecture.

This reduces:

- API complexity
- Authentication complexity
- Frontend duplication
- Development time

while still supporting dynamic interfaces.

---

# 8. Database

Recommended database:

**MySQL 8+**

Alternative:

**MariaDB**

MySQL is preferred because:

- Excellent Laravel support
- Good transactional support
- Widely available hosting
- Reliable indexing
- Mature backup tooling
- Low infrastructure requirements

---

# 9. Database Engine

Tables shall use:

**InnoDB**

because the system requires:

- Foreign keys
- Transactions
- Row-level locking
- Crash recovery

---

# 10. High-Level Architecture

```text
                    ┌───────────────────────┐
                    │       Browser         │
                    │ Desktop / Tablet /    │
                    │        Mobile         │
                    └───────────┬───────────┘
                                │ HTTPS
                                ▼
                    ┌───────────────────────┐
                    │      Web Server       │
                    │   Nginx / Apache      │
                    └───────────┬───────────┘
                                │
                                ▼
                    ┌───────────────────────┐
                    │  Laravel Application  │
                    │                       │
                    │ Blade + Livewire      │
                    │ Application Services  │
                    │ Domain Rules          │
                    │ Queues / Events       │
                    └───────────┬───────────┘
                                │
                                ▼
                    ┌───────────────────────┐
                    │     MySQL Database    │
                    └───────────────────────┘
```

Future:

```text
WhatsApp
   │
   ▼
Meta API
   │
   ▼
Laravel Webhook API
   │
   ├── Orders
   ├── Customers
   └── Messaging

Payment Provider
   │
   ▼
Webhook
   │
   ▼
Laravel Payment Service
```

---

# 11. Application Layers

The system shall be divided into:

```text
Presentation Layer
        ↓
Application Layer
        ↓
Domain Layer
        ↓
Persistence / Infrastructure Layer
```

---

# 12. Presentation Layer

Responsible for:

- Web pages
- Forms
- Livewire components
- Validation feedback
- Dashboard rendering
- POS interface
- Reports UI

It shall not contain complex business rules.

---

# 13. Application Layer

Responsible for coordinating system operations.

Examples:

```text
CreateSaleService
CreatePurchaseService
CreateOrderService
ReserveInventoryService
ProcessReturnService
ProcessRefundService
ProcessExchangeService
CalculateProfitService
```

Application services shall coordinate domain logic and database transactions.

---

# 14. Domain Layer

Responsible for core business rules.

Examples:

- Prevent negative stock
- Calculate weighted-average inventory cost
- Reserve inventory
- Release inventory
- Complete sales
- Calculate COGS
- Process exchanges
- Validate returns
- Calculate profit

Critical business rules must not depend on UI code.

---

# 15. Persistence Layer

Laravel Eloquent shall handle persistence.

Repositories should only be introduced where they provide clear architectural value.

Avoid unnecessary repository abstractions around every model.

---

# 16. Core Modules

## Authentication

Responsibilities:

- Login
- Logout
- Password management
- Session management

---

## User & Staff

Responsibilities:

- Users
- Roles
- Permissions
- Activation/deactivation

---

## Product Catalog

Responsibilities:

- Categories
- Products
- Sizes
- Colours
- Variants
- SKUs

---

## Supplier Management

Responsibilities:

- Supplier records
- Contact information
- Purchase history

---

## Purchasing

Responsibilities:

- Supplier purchases
- Purchase items
- Buying costs
- Stock receipt

---

## Inventory

Responsibilities:

- Physical quantity
- Reserved quantity
- Available quantity
- Inventory movements
- Low-stock alerts
- Cost calculations

---

## Sales

Responsibilities:

- Counter sales
- Sale items
- Payment method
- Sale completion
- COGS snapshots

---

## Customer CRM

Responsibilities:

- Customer profiles
- Preferences
- Purchase history
- Spending totals

The business case requires customer records including contact information, preferences, purchase history, and total spending.

---

## Orders

Responsibilities:

- Customer orders
- Order items
- Status changes
- Stock reservations

---

## Returns

Responsibilities:

- Returned products
- Return reasons
- Product condition
- Inventory restoration

---

## Refunds

Responsibilities:

- Refund transactions
- Refund authorization
- Financial adjustments

---

## Exchanges

Responsibilities:

- Returned item
- Replacement item
- Price difference
- Stock adjustments

---

## Expenses

Responsibilities:

- Expense categories
- Expense records
- Operating expense reporting

---

## Reporting

Responsibilities:

- Sales reports
- Inventory reports
- Customer reports
- Supplier reports
- Expenses
- Profit reports

The original business case specifically requests daily, weekly, monthly, inventory, expense, profit, product, and customer reports.

---

## Audit

Responsibilities:

- Critical operation history
- User actions
- Before/after state where practical

---

# 17. Core Domain Relationships

```text
Category
   │
   └── Product
         │
         └── ProductVariant
                │
                ├── Inventory
                │
                ├── PurchaseItem
                │
                ├── SaleItem
                │
                ├── OrderItem
                │
                ├── ReturnItem
                │
                └── ExchangeItem
```

---

# 18. Inventory Architecture

Inventory is one of the most critical components.

Every product variant shall have:

```text
physical_quantity
reserved_quantity
available_quantity
weighted_average_cost
low_stock_threshold
```

Where logically:

```text
available_quantity =
physical_quantity - reserved_quantity
```

`available_quantity` may be calculated rather than independently trusted.

---

# 19. Inventory Ledger

Inventory changes shall always create an inventory movement.

Example:

```text
Product Variant: Jeans / Black / M

PURCHASE       +10
SALE            -2
RETURN          +1
DAMAGE          -1
ADJUSTMENT      +2
```

Result:

```text
Current Physical Stock = 10
```

The inventory movement ledger provides traceability.

---

# 20. Inventory Source of Truth

Inventory must not depend only on manually modifying a quantity field.

Every stock-changing business operation shall:

1. Validate the operation.
2. Create/update the business transaction.
3. Create an inventory movement.
4. Update inventory balances.
5. Commit everything inside one database transaction.

---

# 21. Database Transactions

Critical operations shall use:

```php
DB::transaction(...)
```

Transactions are mandatory for:

- Stock purchases
- Sales
- Order reservations
- Reservation releases
- Returns
- Refund-linked inventory changes
- Exchanges
- Stock adjustments

Example:

```text
START TRANSACTION

Create Sale
Create Sale Items
Reduce Inventory
Create Inventory Movements
Record Cost Snapshot
Update Customer Statistics

COMMIT
```

If any operation fails:

```text
ROLLBACK
```

---

# 22. Concurrency Control

The system must prevent two employees from selling the final item simultaneously.

Critical inventory operations should use row locking.

Example concept:

```text
SELECT inventory
FOR UPDATE
```

Laravel implementation may use:

```php
lockForUpdate()
```

inside a database transaction.

---

# 23. Negative Inventory Protection

Default rule:

```text
available_quantity >= requested_quantity
```

If false:

Sale/order reservation shall fail.

Negative inventory shall not be allowed.

---

# 24. Supplier Purchase Workflow

```text
Create Purchase
      ↓
Add Purchase Items
      ↓
Validate Supplier
      ↓
Confirm Purchase
      ↓
Update Weighted Average Cost
      ↓
Increase Physical Inventory
      ↓
Create Inventory Movements
      ↓
Complete Purchase
```

---

# 25. Weighted Average Cost

For each purchase:

```text
New Average Cost =
(
 Existing Stock Value
 +
 New Purchase Value
)
/
(
 Existing Quantity
 +
 New Quantity
)
```

Example:

Existing:

```text
10 units × 25,000 = 250,000
```

New purchase:

```text
10 units × 30,000 = 300,000
```

Total:

```text
20 units
TZS 550,000 value
```

Weighted average:

```text
550,000 / 20
= TZS 27,500
```

Future sales from this stock shall use:

```text
COGS per unit = TZS 27,500
```

until the weighted average changes again.

---

# 26. Sales Transaction Architecture

Counter sale:

```text
Select Variant
      ↓
Validate Available Stock
      ↓
Select Quantity
      ↓
Optional Customer
      ↓
Payment Method
      ↓
Confirm Sale
      ↓
Create Sale
      ↓
Create Sale Items
      ↓
Capture Cost Snapshot
      ↓
Reduce Inventory
      ↓
Create Inventory Movement
      ↓
Update Customer Statistics
```

---

# 27. Sale Cost Snapshot

Sale items shall store:

```text
unit_sale_price
unit_cost
quantity
discount
line_total
line_cost
```

This is critical.

Historical profit must not change when future stock purchases change the weighted average cost.

---

# 28. Order Architecture

Orders shall remain separate from sales.

An order does not automatically equal revenue.

Workflow:

```text
NEW
 ↓
CONFIRMED
 ↓
STOCK RESERVED
 ↓
PAYMENT RECEIVED
 ↓
SALE CREATED
 ↓
PREPARING
 ↓
OUT FOR DELIVERY
 ↓
DELIVERED
```

The business case originally proposes order states from new order through delivery.

---

# 29. Inventory Reservation

When an order is confirmed:

```text
reserved_quantity += order_quantity
```

Available stock becomes:

```text
physical - reserved
```

Physical inventory remains unchanged.

---

# 30. Reservation Completion

When payment is confirmed:

```text
physical_quantity -= quantity

reserved_quantity -= quantity
```

A completed sale shall then be created.

---

# 31. Reservation Cancellation

If an unpaid order is cancelled:

```text
reserved_quantity -= quantity
```

Physical quantity remains unchanged.

---

# 32. Customer Architecture

Customer summary fields may be cached for fast reporting:

```text
first_purchase_at
last_purchase_at
total_purchases
total_spent
```

The authoritative source remains completed sales.

Customer statistics shall be recalculable from transactional data.

---

# 33. Return Architecture

Returns shall reference:

```text
Sale
 ↓
SaleItem
 ↓
Return
 ↓
ReturnItem
```

The original sale shall remain immutable.

---

# 34. Returned Item Condition

Return item:

```text
SELLABLE
DAMAGED
DEFECTIVE
OTHER
```

If:

```text
SELLABLE
```

Inventory may increase.

Otherwise the quantity shall not become sellable stock.

---

# 35. Refund Architecture

Refunds shall be independent transactions.

```text
Sale
 ↓
Refund
```

or:

```text
Sale
 ↓
Return
 ↓
Refund
```

Original sales shall not be deleted.

---

# 36. Exchange Architecture

An exchange combines:

```text
Return
+
Replacement Sale/Exchange Item
```

Workflow:

```text
Validate Original Sale
      ↓
Accept Returned Product
      ↓
Determine Returned Condition
      ↓
Select Replacement
      ↓
Calculate Price Difference
      ↓
Receive Extra Payment
        OR
      Create Refund
      ↓
Adjust Inventory
      ↓
Complete Exchange
```

---

# 37. Expense Architecture

Expenses shall be separate from inventory purchases.

Examples:

```text
Rent
Electricity
Internet
Packaging
Marketing
Transport
```

Supplier stock purchases shall **not** be recorded as operating expenses.

---

# 38. Profit Architecture

## Sales Revenue

```text
Sum of completed sales
-
applicable refunds
```

---

## COGS

Calculated from stored sale-item cost snapshots.

```text
COGS =
Σ(quantity × unit_cost)
```

---

## Gross Profit

```text
Gross Profit =
Net Sales Revenue - COGS
```

---

## Estimated Net Profit

```text
Estimated Net Profit =
Gross Profit - Operating Expenses
```

---

# 39. Financial Precision

Money shall never use floating-point types.

Database values should use:

```text
DECIMAL
```

Example:

```text
DECIMAL(15,2)
```

TZS typically uses whole values operationally, but decimal storage provides safer future compatibility.

---

# 40. Status Handling

Statuses should use enums or controlled application constants.

Examples:

## Sale

```text
DRAFT
COMPLETED
CANCELLED
REFUNDED
PARTIALLY_REFUNDED
```

## Order

```text
NEW
CONFIRMED
PAYMENT_RECEIVED
PREPARING
OUT_FOR_DELIVERY
DELIVERED
CANCELLED
```

## Purchase

```text
DRAFT
CONFIRMED
CANCELLED
```

---

# 41. Soft Deletes

Soft deletes may be used for master/reference records such as:

- Products
- Categories
- Suppliers
- Customers where appropriate

Financial and inventory transaction records should normally **not be deleted**.

Examples:

- Sales
- Purchases
- Refunds
- Returns
- Inventory movements

Instead use:

- Cancellation
- Reversal
- Deactivation

---

# 42. Event Architecture

Laravel domain/application events may be used for secondary operations.

Examples:

```text
SaleCompleted
PurchaseConfirmed
OrderConfirmed
OrderCancelled
StockLow
ReturnCompleted
RefundProcessed
```

Listeners may then:

- Update statistics
- Trigger alerts
- Write audit events
- Queue notifications

Core inventory changes shall still occur inside the main transaction.

---

# 43. Queue Architecture

Version 1 may initially use:

```text
Database Queue Driver
```

Jobs suitable for queues:

- Report exports
- Future WhatsApp messages
- Future marketing messages
- Payment callback processing
- Email notifications
- AI requests

Redis shall not be mandatory initially.

---

# 44. Scheduled Tasks

Laravel Scheduler may handle:

- Backup jobs
- Reservation expiry
- Report preparation
- Future follow-ups
- Future customer segmentation

Cron executes:

```text
php artisan schedule:run
```

---

# 45. Notifications

Version 1 internal notifications may include:

- Low stock alerts
- Failed operations
- Administrative alerts

Later notification channels may include:

- WhatsApp
- Email

---

# 46. Reporting Architecture

Reports shall query transactional data rather than maintaining separate duplicated reporting databases.

Indexes shall support common report filters.

Possible future optimization:

```text
Summary tables
Materialized reporting tables
Cached dashboards
```

Only introduce these if performance requires them.

---

# 47. Dashboard Caching

Initially:

Dashboard metrics may be calculated directly.

If data volume grows:

Laravel Cache may be introduced.

Possible cache duration:

```text
1–5 minutes
```

for expensive dashboard metrics.

---

# 48. Search Architecture

Initial search shall use SQL indexes.

Searchable values:

- Product name
- SKU
- Customer name
- Phone
- Supplier name
- Order number
- Sale number

External search engines such as Elasticsearch are unnecessary.

---

# 49. Audit Architecture

Audit entries shall contain:

```text
id
user_id
action
entity_type
entity_id
old_values
new_values
ip_address
created_at
```

Sensitive values such as passwords must never be logged.

---

# 50. Logging

Laravel application logs shall record:

- Application errors
- Integration failures
- Payment callback failures
- Unexpected exceptions
- Background job failures

Logs must not contain:

- Passwords
- Tokens
- Sensitive credentials

---

# 51. Authentication Architecture

Laravel authentication shall provide:

- Secure password hashing
- Session authentication
- CSRF protection
- Login throttling

Recommended password hashing:

```text
Argon2id
```

or Laravel's secure configured default.

---

# 52. Authorization Architecture

Authorization shall use:

- Roles
- Permissions
- Laravel Policies / Gates

Example:

```text
Administrator
Salesperson
```

Future roles may include:

```text
Manager
Inventory Officer
Cashier
```

without rewriting core modules.

---

# 53. API Architecture

Version 1 does not require a full public API.

Internal web application routes may use standard Laravel controllers/Livewire.

However:

```text
/api/v1/
```

shall be reserved for future integrations.

Examples:

```text
/api/v1/webhooks/payments/{provider}

/api/v1/webhooks/whatsapp

/api/v1/orders

/api/v1/products
```

Detailed endpoints shall be specified in `API_SPEC.md`.

---

# 54. WhatsApp Integration Architecture

Future WhatsApp flow:

```text
Customer
   ↓
WhatsApp
   ↓
Meta WhatsApp Business Platform
   ↓
Webhook
   ↓
MFBMS
   ↓
Customer Service Layer
   ↓
Products / Inventory / Orders
   ↓
WhatsApp Response
```

The original business case expects the system eventually to answer product availability, pricing, sizes, colours, offers, delivery, and other customer questions through WhatsApp.

---

# 55. Payment Integration Architecture

Payment providers shall be integrated through an abstraction.

Example:

```text
PaymentGatewayInterface
        │
        ├── ProviderAService
        ├── ProviderBService
        └── FutureProviderService
```

Application logic shall not depend directly on one provider.

---

# 56. Payment Workflow

```text
Create Payment Request
        ↓
Send to Provider
        ↓
Customer Pays
        ↓
Provider Webhook
        ↓
Verify Signature
        ↓
Verify Transaction
        ↓
Update Payment
        ↓
Complete Order/Sale
```

Payment success must not depend on the frontend redirect alone.

---

# 57. Webhook Security

Future webhooks shall validate:

- Provider signature
- Request authenticity
- Transaction identifier
- Expected amount
- Duplicate callbacks

Callbacks must be idempotent.

---

# 58. Idempotency

External payment callbacks may arrive multiple times.

The system must ensure:

```text
One external payment
=
One internal payment transaction
```

Duplicate callbacks shall not:

- Create duplicate sales
- Deduct inventory twice
- Mark payments multiple times

---

# 59. AI Integration Architecture

AI functionality is Phase 3.

Architecture:

```text
WhatsApp Message
      ↓
Customer Service Orchestrator
      ↓
Business Data Retrieval
      ↓
AI Provider
      ↓
Validated Response
      ↓
WhatsApp
```

AI shall not directly modify:

- Inventory
- Payments
- Refunds
- Financial records

without controlled application services.

---

# 60. File Storage

Product images and documents may use Laravel filesystem abstraction.

Development:

```text
Local Storage
```

Production options:

```text
Local server storage
or
S3-compatible object storage
```

Storage provider remains:

`TBD`

---

# 61. Application Directory Structure

Recommended high-level structure:

```text
app/
├── Actions/
├── DTOs/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Livewire/
├── Models/
├── Notifications/
├── Policies/
├── Services/
│   ├── Inventory/
│   ├── Sales/
│   ├── Purchasing/
│   ├── Orders/
│   ├── Returns/
│   ├── Payments/
│   └── Reporting/
└── Support/
```

Avoid creating excessive architectural layers without clear purpose.

---

# 62. Frontend Structure

Example:

```text
resources/
├── views/
│   ├── layouts/
│   ├── dashboard/
│   ├── products/
│   ├── purchases/
│   ├── inventory/
│   ├── sales/
│   ├── customers/
│   ├── orders/
│   ├── returns/
│   ├── expenses/
│   └── reports/
│
├── css/
└── js/
```

Livewire components should be organized by business module.

---

# 63. Routing

Suggested structure:

```text
/dashboard

/products
/products/{product}

/inventory

/suppliers

/purchases

/sales

/pos

/customers

/orders

/returns

/refunds

/exchanges

/expenses

/reports

/users

/settings

/audit-logs
```

---

# 64. Unique Business Identifiers

Human-readable IDs should be generated independently from database primary keys.

Examples:

```text
Sale:
MFS-SAL-000001

Order:
MFS-ORD-000001

Purchase:
MFS-PUR-000001

Return:
MFS-RET-000001

Refund:
MFS-RFD-000001

Exchange:
MFS-EXC-000001
```

Database IDs remain internal.

---

# 65. Database Indexing

Indexes shall be created for commonly searched/filterable fields.

Examples:

```text
sku
product_id
variant_id
customer_id
supplier_id
sale_id
order_id
status
transaction_date
phone
created_at
```

Composite indexes may be added after query patterns are confirmed.

---

# 66. Data Integrity

Foreign-key constraints shall be used wherever practical.

Examples:

```text
product_variants.product_id
→ products.id

sale_items.sale_id
→ sales.id

sale_items.product_variant_id
→ product_variants.id
```

---

# 67. Backup Architecture

Production shall include automated backups.

Recommended:

```text
Database Backup:
Daily

Retention:
7–30 days

External Backup:
Periodic
```

Final backup infrastructure remains `TBD`.

---

# 68. Deployment Architecture

Recommended production architecture:

```text
Internet
   ↓
HTTPS
   ↓
Nginx
   ↓
PHP-FPM
   ↓
Laravel
   ↓
MySQL
```

Background workers:

```text
Laravel Queue Worker
Laravel Scheduler
```

---

# 69. Production Server Requirements

Minimum practical initial environment:

```text
2 CPU cores
2–4 GB RAM
SSD storage
PHP 8.3+
MySQL 8+
Nginx
SSL certificate
Cron
Supervisor or equivalent queue manager
```

Actual resources shall be finalized during deployment planning.

---

# 70. Hosting Strategy

The application shall avoid dependencies that unnecessarily require expensive infrastructure.

It should be deployable on:

- VPS
- Laravel-compatible managed hosting
- Capable shared hosting where queue/scheduler requirements are supported

Recommended production environment:

**Small VPS**

because future:

- WhatsApp webhooks
- Queues
- Payment webhooks
- Scheduled tasks

are easier to operate reliably on a VPS.

---

# 71. HTTPS

Production traffic shall use HTTPS only.

HTTP requests should redirect to HTTPS.

---

# 72. Environment Configuration

Secrets must exist only in server environment configuration.

Examples:

```text
APP_KEY
DB_PASSWORD
MAIL_PASSWORD
WHATSAPP_TOKEN
PAYMENT_API_KEY
PAYMENT_SECRET
```

They shall never be committed to Git.

---

# 73. Source Control

Git shall be used.

Recommended branches:

```text
main
develop
feature/*
fix/*
```

For a small team, an even simpler approach is acceptable:

```text
main
feature/*
```

---

# 74. CI/CD

Initial deployment may use:

```text
GitHub
     ↓
Deployment Process
     ↓
Production Server
```

Future CI can run:

- Automated tests
- Static analysis
- Code formatting checks
- Deployment

---

# 75. Testing Architecture

Testing shall include:

## Unit Tests

For:

- Weighted average costing
- Profit calculation
- Inventory calculations

## Feature Tests

For:

- Sales
- Purchases
- Orders
- Returns
- Refunds
- Exchanges
- Authentication

## Integration Tests

Future:

- WhatsApp
- Payment provider callbacks

---

# 76. Highest Priority Automated Tests

The following must receive strong test coverage:

1. Stock purchase increases inventory correctly.
2. Weighted average cost is correct.
3. Sale cannot exceed available stock.
4. Sale reduces inventory once.
5. Order reservation reduces available quantity.
6. Cancellation releases reservation.
7. Return restores stock correctly.
8. Damaged return does not become sellable.
9. Refund does not delete sale.
10. Exchange adjusts both products correctly.
11. Gross profit calculation is correct.
12. Net profit calculation is correct.
13. Duplicate payment webhook cannot create duplicate sale.

---

# 77. Error Handling

User-facing errors shall be:

- Clear
- Safe
- Actionable

Example:

Good:

```text
Only 2 units of Black / Size M are available.
```

Bad:

```text
SQLSTATE[45000]...
```

Technical errors shall be logged server-side.

---

# 78. Observability

Version 1 shall include:

- Application logs
- Failed-job monitoring
- Database health monitoring
- Backup verification

Advanced observability platforms are optional.

---

# 79. Scalability Strategy

Scale vertically first.

Example:

```text
Increase CPU
Increase RAM
Optimize DB indexes
Introduce cache
```

Only introduce:

- Redis
- Load balancers
- Multiple application servers

when actual usage requires them.

---

# 80. Future Multi-Branch Support

Multi-branch support is not part of Version 1.

However, core domain design should avoid hardcoding assumptions that make expansion impossible.

Future architecture may introduce:

```text
branches
branch_inventory
branch_users
stock_transfers
```

No multi-branch logic shall be implemented prematurely.

---

# 81. Security Boundaries

High-risk operations include:

- Refunds
- Stock adjustments
- Expense modification
- User permission modification
- Sale cancellation

These shall require explicit permissions.

Some may later require administrator approval.

---

# 82. Technical Decisions Summary

| Area | Decision |
|---|---|
| Architecture | Modular Monolith |
| Backend | Laravel |
| Language | PHP 8.3+ |
| Frontend | Blade + Livewire |
| UI Interaction | Alpine.js |
| CSS | Tailwind CSS |
| Database | MySQL 8+ |
| DB Engine | InnoDB |
| Authentication | Laravel Session Auth |
| Authorization | Roles + Policies |
| Queue | Laravel Database Queue initially |
| Scheduler | Laravel Scheduler |
| Inventory Costing | Weighted Average Cost |
| Money Storage | DECIMAL |
| Hosting | VPS recommended |
| Public API | Future `/api/v1` |
| WhatsApp | Phase 2 |
| Integrated Payments | Phase 2 |
| AI | Phase 3 |
| Multi-Branch | Future |

---

# 83. Architecture Principles

Development shall follow these principles:

1. Keep controllers and UI components thin.
2. Keep business rules in application/domain services.
3. Wrap inventory-changing operations in transactions.
4. Preserve financial history.
5. Never directly delete financial transactions.
6. Never trust frontend calculations for money or stock.
7. Validate everything server-side.
8. Make payment callbacks idempotent.
9. Use the database as the source of truth.
10. Avoid unnecessary infrastructure.
11. Optimize only when real performance evidence exists.
12. Keep external integrations isolated behind interfaces.

---

# 84. Architecture Decision

The approved architecture for Version 1 is:

```text
Laravel Modular Monolith

PHP 8.3+
Laravel
Blade
Livewire
Alpine.js
Tailwind CSS
MySQL
```

running as a centralized web application for the single Mo Fashion Store branch.

The design deliberately prioritizes **transaction correctness, inventory integrity, maintainability, security, and future integration capability** over unnecessary architectural complexity.

---

# 85. Next Technical Document

The next document shall be:

**`DATABASE.md`**

It shall define the complete database model, including:

- Tables
- Columns
- Data types
- Primary keys
- Foreign keys
- Unique constraints
- Indexes
- Relationships
- Enums
- Inventory ledger structure
- Weighted average costing
- Sales transactions
- Orders and reservations
- Returns
- Refunds
- Exchanges
- Expenses
- Audit records
- Data integrity rules

The database document shall serve as the primary implementation reference for the backend and future API.

---

# 86. Document Status

**Status: Architecture Approved for Database Design**

Outstanding provider-specific decisions such as payment gateway, WhatsApp configuration, final hosting provider, domain, and backup destination remain `TBD` and do not block Version 1 database design.