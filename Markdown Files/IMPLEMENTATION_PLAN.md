# IMPLEMENTATION_PLAN.md

# Mo Fashion Store Business Management System — MFBMS

## 1. Purpose

This document defines the implementation sequence for the **Mo Fashion Store Business Management System (MFBMS)**.

It translates the approved:

- REQUIREMENTS.md
- PRD.md
- ARCHITECTURE.md
- DATABASE.md
- API_SPEC.md
- SECURITY.md
- UI.md
- UI_SPEC.md
- WORKFLOWS.md
- TESTING.md

into a practical development roadmap.

The objective is to build MFBMS in a controlled order where each phase depends on stable work from the previous phase.

---

# 2. Implementation Strategy

MFBMS shall be developed as a:

**Laravel Modular Monolith**

using:

- PHP 8.3+
- Laravel
- Blade
- Livewire
- Alpine.js
- Tailwind CSS
- MySQL 8+

Development shall follow:

```text
Foundation
→ Core Data
→ Inventory
→ Sales
→ Orders
→ After-Sales
→ Finance
→ Reports
→ Security Hardening
→ Testing
→ Deployment
→ Future Integrations
```

---

# 3. Main Implementation Principle

Do not build modules purely by page order.

Build according to **business dependency order**.

Example:

Sales depends on:

```text
Products
→ Variants
→ Inventory
→ Costing
```

Therefore POS must not be implemented before inventory and weighted-average costing are stable.

---

# 4. Development Phases

Version 1 shall be implemented in the following phases:

1. Project Foundation
2. Authentication & Authorization
3. Core Reference Data
4. Products & Variants
5. Suppliers
6. Inventory Foundation
7. Stock Purchasing
8. Opening Stock
9. Customers
10. Counter Sales / POS
11. Orders & Reservations
12. Returns
13. Refunds
14. Exchanges
15. Expenses
16. Dashboard
17. Reports
18. Users & Administration
19. Audit & Settings
20. Security Hardening
21. Testing & QA
22. Production Preparation
23. Deployment
24. Post-Deployment Validation

Future phases:

25. Payment Integration
26. WhatsApp Integration
27. AI Customer Service

---

# 5. Phase 1 — Project Foundation

## Goal

Create the stable application foundation.

## Tasks

- Create Laravel project.
- Configure Git repository.
- Configure `.gitignore`.
- Create `.env.example`.
- Configure MySQL connection.
- Configure application timezone:

```text
Africa/Dar_es_Salaam
```

- Configure default currency:

```text
TZS
```

- Install/configure Livewire.
- Configure Tailwind CSS.
- Configure Alpine.js.
- Add Manrope font.
- Create base application layout.
- Create standard UI components.
- Configure application logging.
- Configure testing environment.
- Configure database factories infrastructure.

## Deliverables

- Application boots successfully.
- Database connection works.
- UI shell renders.
- Test environment works.
- Git repository clean.

## Exit Criteria

```text
php artisan test
```

runs successfully with initial test suite.

---

# 6. Phase 2 — Authentication & Authorization

## Goal

Secure application access before business modules are added.

## Implement

- User model.
- Roles.
- Permissions.
- Role-permission relationships.
- Login.
- Logout.
- Password reset/change.
- Active/inactive accounts.
- Authentication middleware.
- Authorization policies.
- Permission gates.
- Login throttling.

## Initial Roles

```text
Administrator
Salesperson
```

## UI

- Login page.
- Unauthorized page.
- User profile/logout menu.

## Tests

- Valid login.
- Invalid login.
- Inactive user.
- Protected route.
- Role restrictions.
- Login rate limiting.

## Exit Criteria

No protected module can be accessed without authentication.

---

# 7. Phase 3 — Core Reference Data

## Goal

Create reference data required by products and future transactions.

## Implement

### Categories

- Migration
- Model
- CRUD service
- Controller/Livewire
- Policies
- Tests

### Sizes

Examples:

```text
XS
S
M
L
XL
XXL
```

### Colours

Examples:

```text
Black
Blue
White
Pink
Brown
```

### Expense Categories

Prepare base data for later finance module.

## Seeders

Create initial:

- Roles
- Permissions
- Sizes
- Expense categories
- System settings

## Exit Criteria

Admin can manage categories, sizes, and colours.

---

# 8. Phase 4 — Products & Product Variants

## Goal

Create the complete product catalogue structure.

## Implement Products

- Product migration.
- Product model.
- Category relationship.
- Product code validation.
- Product status.
- Soft delete.
- Images if included from Version 1.

## Implement Variants

- Product variant migration.
- Size relationship.
- Colour relationship.
- Unique SKU.
- Selling price.
- Low-stock threshold.
- Weighted-average-cost field.
- Status.

## Business Rules

- Product creation does not create stock.
- Duplicate variant combination prohibited.
- Duplicate SKU prohibited.

## UI

- Product list.
- Product form.
- Product detail.
- Variant list.
- Add/edit variant.

## Tests

- Product creation.
- Duplicate code.
- Variant creation.
- Duplicate combination.
- Invalid price.

## Exit Criteria

Administrator can create full product/size/colour combinations.

---

# 9. Phase 5 — Supplier Management

## Goal

Prepare supplier records before purchasing.

## Implement

- Supplier migration.
- Supplier model.
- Supplier CRUD.
- Search.
- Deactivation.
- Supplier purchase-history relation placeholder.

## UI

- Supplier list.
- Supplier creation.
- Supplier detail.
- Supplier edit.

## Tests

- Create supplier.
- Update supplier.
- Deactivate supplier.
- Historical relationships preserved.

---

# 10. Phase 6 — Inventory Foundation

## Goal

Build the most important business foundation before sales.

## Implement

### Inventory Table

Per variant:

```text
physical_quantity
reserved_quantity
```

Available quantity calculated as:

```text
physical - reserved
```

### Inventory Movement Ledger

Implement movement types:

```text
OPENING_BALANCE
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

### InventoryService

Implement:

```text
increase()
decrease()
reserve()
releaseReservation()
completeReservation()
adjust()
```

## Transaction Safety

Use:

```text
DB::transaction()
lockForUpdate()
```

## Tests

- Inventory calculation.
- No negative stock.
- Reservation logic.
- Movement creation.
- Concurrent modification behavior.

## Exit Criteria

Inventory can only change through controlled services.

---

# 11. Phase 7 — Stock Purchasing

## Goal

Allow real stock to enter the system through supplier purchases.

## Implement

- `purchases`
- `purchase_items`
- Purchase statuses.
- Supplier relationships.
- Purchase totals.
- Confirmation logic.
- Weighted-average costing.

### PurchaseService

Implement:

```text
createDraft()
updateDraft()
confirm()
cancelDraft()
```

## Confirmation Workflow

```text
Draft
→ Validate
→ Lock Inventory
→ Recalculate WAC
→ Increase Inventory
→ Create Movements
→ Confirm
```

## UI

- Purchases list.
- Create purchase.
- Draft purchase.
- Purchase review.
- Confirm purchase.
- Purchase detail.

## Tests

Highest priority:

- Initial WAC.
- Multiple-cost WAC.
- Duplicate confirmation.
- Rollback.
- Inventory increase.

## Exit Criteria

Supplier purchases correctly increase stock and costing.

---

# 12. Phase 8 — Opening Stock

## Goal

Load the client's existing inventory at system launch.

## Implement

Controlled opening-stock workflow.

Fields:

- Variant
- Quantity
- Unit cost

## Rules

Opening stock:

- Admin-only.
- Creates `OPENING_BALANCE`.
- Updates inventory.
- Initializes WAC.
- Creates audit record.

## UI

Dedicated:

```text
Opening Stock
```

page.

## Tests

- Correct stock.
- Correct WAC.
- Unauthorized access.
- Duplicate setup protection.

---

# 13. Phase 9 — Customer Management

## Goal

Build CRM foundation before sales and orders depend on it.

## Implement

- Customers.
- Category preferences.
- Preferred size.
- Preferred colour.
- Marketing opt-in.
- Customer search.
- Customer statistics.

## Rules

Walk-in customers remain supported.

Do not force customer registration for every sale.

## UI

- Customers list.
- Customer creation.
- Quick-create modal.
- Customer detail.
- Purchase history placeholder.

## Tests

- Customer creation.
- Search.
- Duplicate warning.
- Walk-in support.

---

# 14. Phase 10 — Counter Sales / POS

## Goal

Deliver the first major operational workflow.

## Implement

### Database

- `sales`
- `sale_items`

### SaleService

Implement:

```text
completeSale()
cancelSale()
calculateTotals()
```

## Completion Workflow

```text
Validate
→ Lock Inventory
→ Verify Stock
→ Read WAC
→ Capture Cost Snapshot
→ Create Sale
→ Create Items
→ Reduce Stock
→ Create Movement
→ Update Customer
→ Commit
```

## UI

Build POS according to `UI_SPEC.md`:

- Product search
- Variant search
- Cart
- Quantity controls
- Customer selection
- Walk-in customer
- Payment method
- Confirmation
- Success screen

## Tests

Critical:

- Normal sale.
- Walk-in sale.
- Registered customer.
- Multiple items.
- Insufficient stock.
- Historical cost.
- Duplicate submission.
- Concurrent final-item sale.

## Exit Criteria

Mo Fashion Store can reliably perform normal counter sales.

This is a major MVP milestone.

---

# 15. Phase 11 — Orders & Stock Reservations

## Goal

Handle customer orders separately from immediate counter sales.

## Implement

- `orders`
- `order_items`
- `stock_reservations`

### OrderService

Implement:

```text
create()
confirm()
reserveStock()
releaseStock()
markPaid()
convertToSale()
cancel()
changeStatus()
```

## Status Workflow

```text
NEW
→ CONFIRMED
→ PAYMENT_RECEIVED
→ PREPARING
→ OUT_FOR_DELIVERY
→ DELIVERED
```

## Reservation Logic

Confirmed order:

```text
reserved += quantity
```

Paid order converted to sale:

```text
physical -= quantity
reserved -= quantity
```

Cancelled:

```text
reserved -= quantity
```

## UI

- Orders list.
- Create order.
- Order detail.
- Status timeline.
- Reservation display.
- Valid-next-action buttons.

## Tests

- Reservation.
- Insufficient inventory.
- Cancellation.
- Paid conversion.
- Duplicate conversion.
- Invalid status transitions.

---

# 16. Phase 12 — Returns

## Goal

Support merchandise returned against original sales.

## Implement

- `returns`
- `return_items`
- Return validation.
- Remaining returnable quantity.

## ReturnService

Implement:

```text
create()
approve()
complete()
reject()
```

## Rules

Sellable:

```text
inventory += quantity
```

Damaged/defective:

No sellable inventory increase.

Historical cost:

Use:

```text
sale_item.unit_cost
```

## UI

Guided return workflow.

## Tests

- Valid return.
- Excess quantity.
- Partial return.
- Repeat return.
- Sellable stock restoration.
- Damaged return.
- Historical COGS reversal.

---

# 17. Phase 13 — Refunds

## Goal

Support controlled money refunds while preserving original sales.

## Implement

- `refunds`
- `refund_items`
- Refund eligibility.
- Refund status.

Recommended initial authorization:

```text
Administrator approval required
```

## Workflow

```text
Create
→ Approve
→ Complete
```

## Tests

- Partial refund.
- Full refund.
- Excess refund rejected.
- Unauthorized refund rejected.
- Duplicate processing prevented.

## UI

- Refund list.
- Refund creation.
- Approval.
- Completion.
- Maximum refundable amount.

---

# 18. Phase 14 — Exchanges

Implemented and verified. See `docs/PHASE_14.md` for the approved three-day,
cross-product policy, shared return/refund limits and verification results.

## Goal

Support exchanging sold products while maintaining correct stock and financial history.

## Implement

- `exchanges`
- `exchange_items`
- Returned/replacement items.
- Price difference calculation.

### ExchangeService

Implement atomic workflow.

## Rules

Returned sellable:

```text
inventory +
```

Replacement:

```text
inventory -
```

Difference:

```text
replacement - returned
```

## UI

Guided exchange flow.

## Tests

- Same-value exchange.
- Additional customer payment.
- Refund due.
- Unavailable replacement.
- Full rollback.

---

# 19. Phase 15 — Expense Management

Implemented. See `docs/PHASE_15.md` for expense/category management, permissions,
audit history, duplicate protection and verification results.

## Goal

Track operating expenses separately from stock purchases.

## Implement

- Expense categories.
- Expenses.
- Permissions.
- Audit changes.

## Important Rule

Supplier purchases:

```text
Purchases
```

not:

```text
Expenses
```

## UI

- Expenses list.
- Record expense.
- Edit authorized expense.

## Tests

- Valid expense.
- Negative amount rejected.
- Permission restriction.

---

# 20. Phase 16 — Dashboard

Implemented: scoped operational metrics, owner financial breakdowns, stock counts,
rankings and recent activity. See `docs/PHASE_16.md` for policy and verification.

## Goal

Give the owner useful operational visibility.

## Implement Metrics

### Today

- Sales
- Sales count
- Gross profit
- Expenses
- Estimated net profit
- Orders
- Customers

### Month

- Net sales
- COGS
- Gross profit
- Expenses
- Estimated net profit

### Stock

- Low stock
- Out of stock

### Performance

- Top products
- Recent sales
- Recent orders

## Role Differences

Salesperson dashboard shall exclude restricted financial data.

## Tests

- Metric correctness.
- Permission-based visibility.
- Date/time behavior.

---

# 21. Phase 17 — Reporting

Implemented the nine planned reports, applicable filters, paginated results,
source-document links and protected CSV downloads. See `docs/PHASE_17.md`.

## Goal

Provide trusted reports from transaction data.

## Implement

- Sales report
- Inventory report
- Purchases report
- Customer report
- Expenses report
- Profit report
- Returns report
- Refund report
- Exchange report

## Filtering

Support:

- Date range
- Product
- Category
- Variant
- Customer
- Supplier
- Salesperson
- Payment method
- Status

## Profit Report

Calculate:

```text
Gross Sales
− Refunds
= Net Sales

Net Sales
− Adjusted COGS
= Gross Profit

Gross Profit
− Operating Expenses
= Estimated Net Profit
```

## Tests

Compare to known manual sample calculations.

---

# 22. Phase 18 — Users & Administration

**Implemented:** staff creation/editing, activation, role assignment, permission
management and temporary-password resets, with audited changes and session
revocation. See [verification report](../docs/PHASE_18.md).

## Goal

Complete staff management.

## Implement

- Create user.
- Edit user.
- Deactivate user.
- Assign roles.
- Permissions.
- Password management.

## Tests

- Role enforcement.
- Deactivated login.
- Permission updates.
- Historical transaction preservation.

---

# 23. Phase 19 — Audit Logs & Settings

## Audit

Implement audit logging for:

- Inventory adjustments
- Purchase confirmation
- Price changes
- Refunds
- Exchanges
- Expense changes
- Permission changes
- Sale cancellation

## Settings

Implement:

- Business name
- Phone
- Address
- Currency
- Timezone
- Default low-stock threshold
- Receipt footer

## UI

- Audit list.
- Audit detail.
- Settings pages.

---

# 24. Phase 20 — Security Hardening

## Goal

Apply final production-level security controls.

## Review

- Authentication
- Authorization
- CSRF
- XSS
- SQL injection resistance
- File uploads
- Session security
- Mass assignment
- Rate limiting
- IDOR protection
- Secrets
- Logs
- Database exposure
- HTTPS

## Configuration

Ensure:

```text
APP_ENV=production
APP_DEBUG=false
```

before production.

---

# 25. Phase 21 — Automated Testing & QA

## Run Full Test Suite

Include:

- Unit
- Feature
- Security
- API
- Database
- Concurrency
- UI
- Workflow
- Regression

## Critical Areas

Must all pass:

- WAC
- Inventory
- Sales
- Reservations
- Returns
- Refunds
- Exchanges
- Profit
- Authorization

---

# 26. Phase 22 — User Acceptance Testing

Conduct UAT with actual Mo Fashion Store workflow.

Test:

```text
Receive Stock
Counter Sale
Customer Registration
Order
Return
Refund
Exchange
Expense
Profit Report
```

Record results:

```text
PASS
FAIL
PASS WITH NOTES
```

---

# 27. Phase 23 — Production Preparation

## Infrastructure

Prepare:

- VPS
- Nginx
- PHP-FPM
- PHP 8.3+
- MySQL
- SSL
- Cron
- Queue process manager

## Application

- Production `.env`
- APP_KEY
- Database credentials
- Storage permissions
- Migrations
- Seeders
- Cache configuration

---

# 28. Phase 24 — Backup Setup

Before launch:

- Automated daily DB backup.
- Secure storage.
- Backup retention.
- Restore test.

Recommended initial:

```text
Daily backups
7–30 day retention
```

---

# 29. Phase 25 — Production Deployment

Suggested process:

```text
Code Approved
→ Tests Pass
→ Backup Existing Environment
→ Pull Release
→ Install Production Dependencies
→ Build Assets
→ Run Migrations
→ Clear/Rebuild Cache
→ Restart Queue
→ Smoke Test
```

---

# 30. Production Commands

Typical Laravel deployment may include:

```text
composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan config:cache

php artisan route:cache

php artisan view:cache
```

Frontend:

```text
npm ci
npm run build
```

Exact deployment process shall depend on infrastructure.

---

# 31. Phase 26 — Post-Deployment Validation

Immediately verify:

1. Login.
2. Dashboard.
3. Products.
4. Inventory.
5. Customers.
6. POS.
7. Orders.
8. Reports.
9. Backups.
10. Logs.
11. Queue.
12. Scheduler.

---

# 32. Data Migration / Initial Client Setup

For Mo Fashion Store:

```text
Create Administrator
→ Business Settings
→ Categories
→ Sizes
→ Colours
→ Products
→ Variants
→ Suppliers
→ Opening Stock
→ Staff Accounts
→ Validate Inventory
→ Go Live
```

---

# 33. Initial Product Data Entry

Before launch, decide whether data is entered:

- Manually
- Via structured import

If product count is large, CSV import may be added as an onboarding tool.

Do not add generic import complexity unless necessary.

---

# 34. Go-Live Strategy

Recommended:

Use a controlled cutover.

Example:

```text
Close manual records at end of day
→ Record final opening stock
→ Verify balances
→ Start next business day in MFBMS
```

Avoid operating two conflicting stock systems for a long period.

---

# 35. Rollback Strategy

Before major production release:

- Backup database.
- Keep prior working code release.
- Document rollback procedure.

Database migrations must be reviewed carefully.

Never assume every migration is safely reversible.

---

# 36. Development Branch Strategy

For this project:

Recommended simple Git strategy:

```text
main
feature/*
fix/*
```

`main` should remain deployable.

Optional:

```text
develop
```

only if team workflow genuinely requires it.

---

# 37. Commit Strategy

Keep commits focused.

Good:

```text
feat: add supplier management
feat: implement weighted average costing
test: add concurrent sale stock protection
fix: release reservation on cancelled order
```

Avoid huge mixed commits.

---

# 38. Pull Request / Review Gate

Before merging important feature:

Check:

- Requirement implemented.
- Business rules respected.
- Tests added.
- Security considered.
- UI follows UI.md.
- No debug code.
- No credentials.
- No unnecessary dependencies.

---

# 39. Definition of Done — Feature

A feature is complete only when:

1. Database changes implemented.
2. Domain logic implemented.
3. Authorization implemented.
4. Validation implemented.
5. UI implemented.
6. Error states implemented.
7. Tests implemented.
8. Audit behavior implemented if required.
9. Documentation updated if behavior changed.

Coding alone does not mean complete.

---

# 40. Phase Dependency Map

```text
Foundation
   ↓
Authentication
   ↓
Reference Data
   ↓
Products / Variants
   ↓
Suppliers
   ↓
Inventory Foundation
   ↓
Purchases / Opening Stock
   ↓
Customers
   ↓
Sales
   ↓
Orders
   ↓
Returns / Refunds / Exchanges
   ↓
Expenses
   ↓
Dashboard / Reports
   ↓
Admin / Audit / Settings
   ↓
Security Hardening
   ↓
Testing
   ↓
Deployment
```

---

# 41. Parallel Work Opportunities

If multiple developers work on the system, some work may occur in parallel after foundations stabilize.

Example:

After Product/Variant and authentication:

Developer A:

```text
Inventory / Purchasing
```

Developer B:

```text
Customers / UI Components
```

But sales development must wait for inventory rules to be stable.

---

# 42. Do Not Parallelize These Prematurely

Avoid building independently:

```text
Sales
Inventory
Costing
```

without agreeing on transaction rules.

Similarly:

```text
Orders
Reservations
```

must remain tightly coordinated.

---

# 43. Technical Debt Rules

Do not leave temporary shortcuts in:

- Inventory calculations
- Financial calculations
- Authorization
- Payment processing
- Audit logic

Temporary styling imperfections are less dangerous than temporary financial logic.

---

# 44. MVP Priority Classification

## Critical

- Authentication
- Products
- Variants
- Suppliers
- Purchases
- Inventory
- POS
- Customers
- Orders
- Expenses
- Reports
- Security

## High

- Returns
- Refunds
- Exchanges
- Dashboard
- Audit

## Future

- WhatsApp
- Integrated payments
- AI
- Marketing automation

---

# 45. Future Phase — Payment Integration

Do not start until:

- Core Orders stable.
- Sales stable.
- Reservations stable.
- Provider selected.
- Official documentation reviewed.
- Sandbox available.

## Implementation

```text
PaymentGatewayInterface
→ Provider Adapter
→ Payment Request
→ Webhook
→ Verification
→ Order Payment
→ Sale Conversion
```

---

# 46. Future Payment Integration Gate

Before production:

Test:

- Success
- Failure
- Pending
- Duplicate callback
- Invalid signature
- Wrong amount
- Wrong currency
- Timeout
- Retry
- Cancelled order callback

---

# 47. Future Phase — WhatsApp Integration

Do not implement before:

- Products stable.
- Inventory stable.
- Customers stable.
- Orders stable.

## Flow

```text
WhatsApp
→ Webhook
→ Customer Identification
→ Product / Inventory Lookup
→ Order Service
→ Payment
→ Notifications
```

---

# 48. Future Phase — AI

AI comes after WhatsApp/business data works without AI.

Implement AI only as:

```text
Conversation Layer
```

over trusted system services.

AI must not own:

- Stock
- Payment
- Refund
- Financial state

---

# 49. Estimated Build Complexity by Module

Relative complexity:

| Module | Complexity |
|---|---|
| Authentication | Medium |
| Products | Low–Medium |
| Variants | Medium |
| Suppliers | Low |
| Inventory | High |
| Purchasing | High |
| POS | High |
| Customers | Medium |
| Orders | High |
| Returns | High |
| Refunds | High |
| Exchanges | High |
| Expenses | Low–Medium |
| Dashboard | Medium |
| Reports | High |
| Audit | Medium |
| Settings | Low |
| Payments | High |
| WhatsApp | High |
| AI | High |

High-complexity modules require stronger testing and code review.

---

# 50. Recommended Coding Order for Codex

If Codex is used, do not prompt it to:

```text
Build the entire MFBMS.
```

Instead provide one controlled implementation unit at a time.

Recommended order:

```text
1. Foundation
2. Auth
3. Roles/Permissions
4. Categories/Sizes/Colours
5. Products
6. Variants
7. Suppliers
8. Inventory
9. Purchasing
10. Opening Stock
11. Customers
12. POS/Sales
13. Orders/Reservations
14. Returns
15. Refunds
16. Exchanges
17. Expenses
18. Dashboard
19. Reports
20. Audit
21. Settings
22. Hardening
```

---

# 51. Coding Agent Rule

For every phase, Codex/AI coding agent shall be provided with the relevant project documents.

At minimum:

```text
REQUIREMENTS.md
PRD.md
ARCHITECTURE.md
DATABASE.md
API_SPEC.md
SECURITY.md
UI.md
UI_SPEC.md
WORKFLOWS.md
TESTING.md
IMPLEMENTATION_PLAN.md
```

The agent shall:

- Follow existing decisions.
- Not invent business rules.
- Mark unresolved requirements as `TBD`.
- Add tests with implementation.
- Preserve backward compatibility where required.

---

# 52. Implementation Prompt Pattern

Recommended prompt:

```text
Implement Phase X of MFBMS according to the project documentation.

Read all relevant MD files first.

Do not implement later phases.

Do not invent requirements.

Follow the database schema, workflows, API, security, UI design system and tests exactly.

Before coding, list the files you will create or modify.

After implementation, run the relevant automated tests and report any failures.
```

---

# 53. Phase Review Pattern

After each phase:

Review:

```text
Requirements
Implementation
Tests
UI
Security
Database
```

Then classify:

```text
APPROVED
NEEDS FIXES
BLOCKED
```

Do not continue into a dependent phase while a critical dependency is broken.

---

# 54. Suggested Milestones

## Milestone 1 — Foundation

Includes:

- Laravel
- Authentication
- Roles
- Products
- Variants
- Suppliers

## Milestone 2 — Stock System

Includes:

- Inventory
- Purchases
- Opening stock
- WAC

## Milestone 3 — Store Operations

Includes:

- Customers
- POS
- Sales

## Milestone 4 — Order Operations

Includes:

- Orders
- Reservations

## Milestone 5 — After-Sales

Includes:

- Returns
- Refunds
- Exchanges

## Milestone 6 — Business Intelligence

Includes:

- Expenses
- Dashboard
- Reports

## Milestone 7 — Production

Includes:

- Audit
- Settings
- Security hardening
- UAT
- Deployment

---

# 55. MVP Completion Criteria

MFBMS Version 1 is complete when Mo Fashion Store can:

1. Log in securely.
2. Manage staff.
3. Create products and variants.
4. Register suppliers.
5. Record stock purchases.
6. Load opening stock.
7. Track accurate inventory.
8. Perform counter sales.
9. Register/manage customers.
10. Create and fulfill orders.
11. Reserve and release stock.
12. Process returns.
13. Process refunds.
14. Process exchanges.
15. Record expenses.
16. View gross profit.
17. View estimated net profit.
18. Generate reports.
19. Review audit activity.
20. Operate from responsive UI.
21. Recover data from tested backup.

---

# 56. Production Blocking Conditions

Do not deploy if any of the following remain unresolved:

- Inventory inconsistency
- Negative stock bug
- WAC calculation bug
- COGS bug
- Profit calculation bug
- Duplicate sale issue
- Reservation issue
- Unauthorized access
- Broken refund logic
- Broken exchange logic
- Failed backup restoration
- Critical security vulnerability

---

# 57. Post-Launch Monitoring

After go-live closely monitor:

- Stock inconsistencies
- Staff usage errors
- Failed transactions
- Slow pages
- Sale totals
- Inventory adjustments
- Returns/refunds
- Backup success
- Application errors

Use real usage to prioritize improvements.

---

# 58. Post-Launch Change Control

New features shall not be inserted casually into Version 1.

For every major new request:

```text
Client Request
→ Requirement Review
→ Impact Analysis
→ Documentation Update
→ Implementation
→ Tests
→ Deployment
```

This protects the system from uncontrolled scope growth.

---

# 59. Documentation Maintenance

When implementation behavior changes, update the relevant document.

Example:

If order workflow changes:

Update:

```text
REQUIREMENTS.md
PRD.md
DATABASE.md if required
API_SPEC.md
WORKFLOWS.md
TESTING.md
```

Documentation should remain aligned with actual software behavior.

---

# 60. Next Document

The next document should be:

**`TASKS.md`**

Its purpose is to convert this implementation plan into granular coding tasks.

Example:

```text
Phase 4 — Products

TASK-PRD-001 Create products migration
TASK-PRD-002 Create Product model
TASK-PRD-003 Add ProductPolicy
TASK-PRD-004 Create ProductService
TASK-PRD-005 Build product list
TASK-PRD-006 Build product form
TASK-PRD-007 Write product feature tests
```

`TASKS.md` will be the most useful day-to-day execution file for Codex.

---

# 61. Document Status

**Status: Implementation Sequence Approved for Task Breakdown**

The architecture and development sequence are sufficiently defined to proceed into granular task planning.

Payment, WhatsApp, AI, final hosting provider, and provider-specific integrations remain future phases and do not block Version 1 core development.

## Phase 19 implementation status (2026-09-23)

Audit list/detail and business settings are implemented. Settings, price changes,
and manual stock adjustments create atomic audit records; existing transaction
audit writers remain in use. Currency/timezone retain the established Version 1
TZS / Africa/Dar_es_Salaam values. Completed-sale cancellation remains disabled
pending its separate reversal policy. See [Phase 19 report](../docs/PHASE_19.md).

## Phase 20 implementation status

Application security headers and production configuration/HTTPS guards implemented.
Verification and remaining deployment checks: [Phase 20](../docs/PHASE_20.md).
Next: Phase 21 — Automated Testing & QA.
