# AGENTS.md

# Mo Fashion Store Business Management System — MFBMS

## 1. Purpose

This file defines the mandatory working rules for any AI coding agent or developer working on the **Mo Fashion Store Business Management System (MFBMS)**.

Examples include:

- Codex
- Claude Code
- GitHub Copilot coding agents
- Cursor agents
- Other automated coding tools
- Human developers

The agent must follow this file together with the approved project documentation.

---

# 2. Source of Truth

Before implementing any feature, read the relevant project documentation.

Primary project files:

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
AGENTS.md
```

These documents define the intended system.

Do not replace documented decisions with assumptions.

---

# 3. Conflict Resolution

If documentation appears inconsistent:

1. Do not guess.
2. Identify the conflicting requirements.
3. Prefer the most recently approved explicit decision where clear.
4. Mark unresolved conflicts as:

```text
TBD
```

5. Do not silently invent a solution.

Business behavior must remain consistent across:

- Database
- Services
- API
- UI
- Tests

---

# 4. Project Architecture

MFBMS uses a:

**Laravel Modular Monolith**

Approved stack:

```text
PHP 8.3+
Laravel
Blade
Livewire
Alpine.js
Tailwind CSS
MySQL 8+
```

Do not introduce microservices unless the architecture is formally changed.

Do not introduce unnecessary frameworks.

---

# 5. Architectural Layers

Keep responsibilities separated.

Preferred structure:

```text
Presentation
    ↓
Application / Services
    ↓
Domain / Business Rules
    ↓
Persistence / Infrastructure
```

Controllers and Livewire components must remain thin.

Complex business logic belongs in services/domain logic.

---

# 6. Business Logic Rule

Do not place important business calculations directly inside:

- Blade templates
- Livewire views
- Controllers
- JavaScript

Examples:

- Weighted-average cost
- Inventory deduction
- Reservations
- Profit calculations
- Refund eligibility

must be handled by backend services.

---

# 7. Service-Oriented Business Operations

Use dedicated services for critical workflows.

Examples:

```text
CreateSaleService
ConfirmPurchaseService
CreateOrderService
ReserveInventoryService
ProcessReturnService
ProcessRefundService
ProcessExchangeService
InventoryService
ProfitCalculationService
```

Naming may vary, but responsibilities must remain explicit.

---

# 8. Database Transaction Rule

Every operation affecting multiple financial or inventory records must use:

```php
DB::transaction()
```

Examples:

- Purchase confirmation
- Sale completion
- Order reservation
- Order-to-sale conversion
- Returns
- Refunds
- Exchanges
- Inventory adjustments

Partial transactions are unacceptable.

---

# 9. Inventory Locking Rule

When stock may be modified concurrently, lock the relevant inventory row using:

```php
lockForUpdate()
```

The system must prevent:

- Overselling
- Negative stock
- Lost updates
- Duplicate reservation

---

# 10. Inventory Source Rule

Inventory cannot change through arbitrary direct editing.

Every stock change must originate from a valid business event such as:

```text
OPENING_BALANCE
PURCHASE
SALE
RETURN
EXCHANGE_IN
EXCHANGE_OUT
DAMAGE
LOSS
ADJUSTMENT_IN
ADJUSTMENT_OUT
REVERSAL
```

and must create an inventory movement record.

---

# 11. Inventory Quantities

Each product variant tracks:

```text
physical_quantity
reserved_quantity
```

Available quantity is:

```text
available_quantity =
physical_quantity - reserved_quantity
```

Do not store contradictory derived values unless explicitly required.

---

# 12. Negative Stock Rule

Never allow:

```text
physical_quantity < 0
```

or:

```text
reserved_quantity < 0
```

Also ensure:

```text
reserved_quantity <= physical_quantity
```

unless a future documented rule explicitly changes this behavior.

---

# 13. Product Variant Rule

Inventory belongs to the **product variant**, not the parent product.

Example:

```text
Boyfriend Jeans
Black
Size M
```

must have its own stock record independent of:

```text
Boyfriend Jeans
Blue
Size L
```

---

# 14. Weighted Average Cost

MFBMS uses:

**Weighted Average Cost**

Formula:

```text
New WAC =
(
Current Quantity × Current WAC
+
Incoming Quantity × Incoming Unit Cost
)
/
(
Current Quantity + Incoming Quantity
)
```

Supplier stock purchases update WAC.

Normal sales do not recalculate WAC.

---

# 15. Historical Cost Rule

At sale completion:

```text
sale_items.unit_cost
```

must store the current weighted-average cost snapshot.

Once stored, it must remain immutable.

Future purchase-cost changes must not alter historical sale profit.

---

# 16. Sales Rule

A completed sale must:

1. Validate stock.
2. Lock inventory.
3. Capture unit cost.
4. Create sale.
5. Create sale items.
6. Reduce physical inventory.
7. Create inventory movements.
8. Calculate COGS.
9. Calculate gross profit.
10. Update customer statistics where applicable.

All of these operations must succeed or fail together.

---

# 17. Walk-In Customer Rule

Customer registration is not mandatory for counter sales.

Walk-in sale:

```text
customer_id = NULL
```

Do not create fake customers such as:

```text
Walk In
Unknown
Cash Customer
```

unless a future documented requirement changes this rule.

---

# 18. Order Rule

Orders are separate from sales.

Do not treat a new order as completed revenue.

Order lifecycle:

```text
NEW
→ CONFIRMED
→ PAYMENT_RECEIVED
→ PREPARING
→ OUT_FOR_DELIVERY
→ DELIVERED
```

---

# 19. Reservation Rule

When an order is confirmed:

```text
reserved_quantity += order_quantity
```

Physical quantity remains unchanged.

When cancelled:

```text
reserved_quantity -= order_quantity
```

When converted to sale:

```text
physical_quantity -= order_quantity
reserved_quantity -= order_quantity
```

---

# 20. Order-to-Sale Rule

An order may create a sale only when the required payment condition is satisfied.

One order must not accidentally create multiple primary sales.

Duplicate conversion requests must be rejected safely.

---

# 21. Payment Rule

Version 1 counter payments are manually recorded.

Examples:

- Cash
- M-Pesa
- Airtel Money
- Mixx by Yas
- HaloPesa
- Bank

The system records the selected payment method.

It does not automatically verify these manual counter payments.

---

# 22. Future Integrated Payment Rule

When payment-provider integration is introduced, payment success must come from trusted provider verification.

Never treat any of the following as authoritative proof:

- Customer screenshot
- Frontend redirect
- Browser success screen
- AI statement
- User-edited request

Provider API/webhook verification is authoritative.

---

# 23. Payment Idempotency

Future payment callbacks must be idempotent.

One provider transaction/event must cause at most:

```text
One successful payment
One order payment update
One sale conversion
One inventory deduction
```

---

# 24. Return Rule

Returns must reference the original sale.

Validate:

```text
requested_return_quantity
<=
sold_quantity - previously_returned_quantity
```

Never allow excess return quantity.

---

# 25. Sellable Return Rule

If returned item is classified:

```text
SELLABLE
```

it may return to inventory.

Use the original sale item's unit cost for historical financial reversal.

Do not use current WAC for historical return calculations.

---

# 26. Damaged Return Rule

Items classified as:

```text
DAMAGED
DEFECTIVE
```

must not automatically return to sellable inventory.

---

# 27. Refund Rule

Refunds must not delete or rewrite the original sale.

A refund is a separate financial transaction linked to the original sale.

Completed refund value must not exceed eligible sale value.

---

# 28. Exchange Rule

Exchanges must preserve both sides of the transaction:

```text
Returned Item
Replacement Item
```

Use separate inventory movements such as:

```text
EXCHANGE_IN
EXCHANGE_OUT
```

The entire exchange must be atomic.

---

# 29. Supplier Purchase Rule

Supplier stock purchases are inventory acquisition transactions.

They are not normal operating expenses.

Do not duplicate a purchase into the expenses table.

---

# 30. Expense Rule

Operating expenses may include:

- Rent
- Electricity
- Internet
- Marketing
- Transport
- Packaging
- Other approved expenses

Expense handling must remain separate from inventory purchasing.

---

# 31. Profit Calculation Rule

Gross profit:

```text
Net Sales Revenue
-
COGS
=
Gross Profit
```

Estimated net profit:

```text
Gross Profit
-
Operating Expenses
=
Estimated Net Profit
```

Always label it:

```text
Estimated Net Profit
```

MFBMS is not full accounting software.

---

# 32. Delivery Fee Rule

Delivery fees are outside Version 1 accounting.

Do not introduce delivery-income or delivery-expense accounting unless requirements are updated.

---

# 33. Financial History Rule

Never hard-delete completed financial transactions.

Examples:

- Sales
- Purchases
- Refunds
- Returns
- Exchanges
- Payments
- Expenses where audit retention is required

Use statuses, reversals, or controlled cancellation mechanisms.

---

# 34. Audit Rule

Sensitive actions must create audit records.

Examples:

- Inventory adjustment
- Purchase confirmation
- Sale cancellation
- Refund
- Exchange
- Expense modification
- Permission changes
- Critical settings changes

Audit records should not be casually editable.

---

# 35. Authorization Rule

Never rely only on hidden UI elements.

Every protected backend action must perform server-side authorization.

Use:

- Policies
- Gates
- Middleware
- Role/permission checks

as appropriate.

---

# 36. Roles

Initial roles:

```text
Administrator
Salesperson
```

Administrator receives broader control.

Salesperson receives only required operational permissions.

Do not expose:

- Cost data
- Profit reports
- User administration
- Security settings

to salespeople unless explicitly authorized.

---

# 37. Validation Rule

All user-provided data must be validated server-side.

Frontend validation is supplementary only.

Use Laravel validation or Form Requests where suitable.

---

# 38. Client Calculation Rule

Never trust totals calculated by the browser.

Backend must calculate:

- Sale totals
- Discounts
- COGS
- Profit
- Refund values
- Exchange differences
- Purchase totals

---

# 39. API Rule

Public/integration API base:

```text
/api/v1
```

The Blade/Livewire application does not need to consume REST for every internal action.

UI and API should reuse the same backend services.

Do not duplicate business logic.

---

# 40. API Response Rule

Use consistent API response structures as defined in `API_SPEC.md`.

Correct HTTP status codes must be used.

Examples:

```text
200 OK
201 Created
422 Unprocessable Entity
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
429 Too Many Requests
```

---

# 41. API Compatibility

Do not silently introduce breaking API changes.

If an API contract must change:

1. Update `API_SPEC.md`.
2. Update affected tests.
3. Review integration impact.

---

# 42. UI Design System

All UI must follow `UI.md`.

Approved font:

```text
Manrope
```

Weights:

```text
400
500
600
700
```

---

# 43. Locked Colour Palette

Use:

```text
Primary Gold       #C9A227
Primary Hover      #B8962E
App Background     #FAF8F3
Card Surface       #FFFFFF
Primary Text       #18181B
Secondary Text     #71717A
Border             #E4E4E7
Selected / Hover   #F3EBD3
Info               #1F5F8B
Warning            #E08A00
Error              #C62828
Success            #2E7D32
```

---

# 44. Colour Usage Rule

Use:

```text
Cream / White
```

for main application surfaces.

Use:

```text
Gold
```

for branding and primary actions.

Use:

```text
Black / Charcoal
```

for typography, contrast, and strong controls.

Use:

```text
Champagne
```

for selected/hover states.

---

# 45. Semantic Colour Rule

Do not use gold for warning states.

Use:

```text
Success  #2E7D32
Warning  #E08A00
Error    #C62828
Info     #1F5F8B
```

consistently.

---

# 46. UI Component Rule

Prefer reusable components.

Examples:

```text
Button
Input
Select
Card
Table
Badge
Alert
Modal
Drawer
KPI Card
Empty State
Loading State
```

Do not duplicate large amounts of UI markup unnecessarily.

---

# 47. POS Priority

POS is one of the most important operational interfaces.

Prioritize:

- Speed
- Product search
- Clear variant selection
- Stock visibility
- Cart clarity
- Easy customer selection
- Clear payment method
- Strong sale total
- Preventing mistakes

Avoid excessive decorative UI.

---

# 48. Responsive Design

Core workflows must remain usable on:

- Desktop
- Laptop
- Tablet
- Mobile

Desktop is the primary operational target, but mobile layouts must not break.

---

# 49. Accessibility

Do not rely solely on colour for status.

Provide:

- Text
- Clear labels
- Icons where appropriate
- Keyboard focus
- Form labels
- Error messages

Maintain readable contrast.

---

# 50. Security Requirements

Follow `SECURITY.md`.

At minimum:

- Protect routes.
- Escape output.
- Use CSRF protection.
- Use secure password hashing.
- Protect secrets.
- Validate file uploads.
- Avoid SQL injection.
- Prevent mass assignment.
- Apply rate limiting.
- Prevent IDOR.
- Disable debug mode in production.

---

# 51. Secret Management

Never commit:

```text
.env
API keys
Passwords
Database credentials
Private tokens
Webhook secrets
```

Use environment variables.

Keep `.env.example` free of real credentials.

---

# 52. File Upload Rule

Only allow documented file types.

For product images, recommended:

```text
JPEG
PNG
WEBP
```

Validate:

- MIME type
- File size
- Extension
- Filename

Never allow executable uploads.

---

# 53. Error Handling

User-facing errors should be clear but safe.

Do not expose:

- Stack traces
- SQL errors
- Credentials
- Internal filesystem paths

Production:

```text
APP_DEBUG=false
```

---

# 54. Logging

Log meaningful application failures.

Do not unnecessarily log:

- Passwords
- API secrets
- Sensitive tokens
- Full financial credentials

Use correlation/reference IDs where useful.

---

# 55. Testing Rule

Every business-critical feature must include tests.

Do not mark a feature complete without testing.

Follow `TESTING.md`.

---

# 56. Minimum Test Cases

For important workflows test:

```text
Happy Path
Invalid Input
Unauthorized Access
Insufficient Stock
Duplicate Request
Transaction Failure
Historical Integrity
Concurrency where relevant
```

---

# 57. Regression Rule

When fixing a bug, add a regression test where practical.

Example:

Bug:

```text
Cancelled order fails to release stock reservation.
```

Required:

- Fix implementation.
- Add test proving reservation release.

---

# 58. Critical Test Areas

Highest priority:

1. Inventory
2. WAC
3. COGS
4. Sales
5. Reservations
6. Returns
7. Refunds
8. Exchanges
9. Profit
10. Authorization
11. Payment idempotency when integrated

---

# 59. Coding Style

Follow Laravel conventions.

Use:

- Clear naming
- Small focused methods
- Type hints
- Readable code
- Appropriate service classes
- Form Requests where helpful
- Policies for authorization

Avoid unnecessary abstraction.

---

# 60. Formatting

Use:

```text
Laravel Pint
```

for PHP formatting.

Recommended static analysis:

```text
PHPStan / Larastan
```

---

# 61. Database Migration Rule

Migrations must be:

- Clear
- Small enough to review
- Properly indexed
- Foreign-key safe
- Production-conscious

Do not casually change historical migrations after deployment.

Create new migrations for schema changes.

---

# 62. Database Naming

Use consistent Laravel-style naming.

Examples:

```text
products
product_variants
purchase_items
sale_items
inventory_movements
stock_reservations
```

Foreign keys:

```text
product_id
variant_id
customer_id
```

---

# 63. Money Data Type

Do not use floating-point values for financial amounts.

Use:

```text
DECIMAL
```

according to `DATABASE.md`.

---

# 64. Date/Time Rule

Business timezone:

```text
Africa/Dar_es_Salaam
```

Reports and business-day calculations must use the approved timezone consistently.

---

# 65. Currency Rule

Primary currency:

```text
TZS
```

Do not introduce multi-currency logic in Version 1 unless requirements change.

---

# 66. Search and Pagination

Large list pages should use:

- Search
- Filters
- Pagination

Do not load entire large tables into memory or render thousands of records at once.

---

# 67. Query Efficiency

Watch for:

```text
N+1 queries
```

Use eager loading appropriately.

Ensure frequently filtered foreign keys/status/date fields have suitable indexes.

---

# 68. Reporting Rule

Reports must derive values from transactional source data.

Do not create manually maintained totals that can become inconsistent unless explicitly documented as cached summaries.

---

# 69. Dashboard Rule

Dashboard metrics must be calculated from trusted backend data.

Do not hardcode dashboard statistics.

Role-specific visibility must be enforced server-side.

---

# 70. AI Coding Agent Scope

The agent must implement only the requested phase or feature.

Do not expand scope because something seems useful.

Example:

If asked to implement products:

Do not automatically implement:

- WhatsApp
- Payment integration
- AI assistant
- Marketing campaigns

---

# 71. Future Feature Boundary

The following are outside Version 1 core implementation:

```text
WhatsApp integration
Automated WhatsApp notifications
Integrated customer payments
AI customer service
Marketing automation
Advanced segmentation
Advanced analytics
```

Do not implement them unless explicitly instructed.

---

# 72. Third-Party Integration Rule

Never guess external API behavior.

For integrations such as:

- Payment providers
- WhatsApp providers
- AI services

use official documentation.

Unknown provider behavior must be marked:

```text
TBD
```

---

# 73. Dependency Rule

Do not add packages without clear need.

Before adding a dependency:

1. Check whether Laravel already provides the capability.
2. Check maintenance status.
3. Check security implications.
4. Avoid package bloat.

---

# 74. Git Rule

Recommended branches:

```text
main
feature/*
fix/*
```

`main` should remain deployable.

---

# 75. Commit Rule

Use focused commits.

Examples:

```text
feat: implement purchase confirmation
feat: add product variant management
test: cover weighted average costing
fix: prevent duplicate order conversion
```

Avoid combining unrelated changes.

---

# 76. Existing Code Rule

Before editing existing code:

1. Read related implementation.
2. Understand current behavior.
3. Check relevant tests.
4. Avoid unnecessary rewrites.

Prefer minimal correct changes over broad refactoring.

---

# 77. Refactoring Rule

Refactor only when it improves:

- Correctness
- Maintainability
- Security
- Testability
- Performance

Do not refactor unrelated modules during a focused task unless necessary.

---

# 78. Backward Compatibility

Do not break existing completed workflows without explicit approval.

When changing shared services, run all related tests.

---

# 79. Implementation Order

Follow `IMPLEMENTATION_PLAN.md`.

Current recommended order:

```text
Foundation
→ Authentication
→ Reference Data
→ Products / Variants
→ Suppliers
→ Inventory
→ Purchases
→ Opening Stock
→ Customers
→ POS / Sales
→ Orders / Reservations
→ Returns
→ Refunds
→ Exchanges
→ Expenses
→ Dashboard
→ Reports
→ Users / Admin
→ Audit / Settings
→ Security Hardening
→ Testing
→ Deployment
```

---

# 80. Phase Boundary Rule

Do not implement later phases prematurely.

Example:

Do not build payment integration before:

- Orders
- Reservations
- Sales
- Payment abstraction

are stable.

---

# 81. Pre-Coding Agent Procedure

Before writing code for a requested feature:

1. Read `AGENTS.md`.
2. Read the relevant specification files.
3. Inspect existing project structure.
4. Inspect related migrations/models/services/tests.
5. Identify dependencies.
6. Identify files likely to change.

Then implement.

---

# 82. Post-Coding Agent Procedure

After implementation:

1. Run formatting.
2. Run relevant tests.
3. Run broader regression tests if shared code changed.
4. Review migrations.
5. Review authorization.
6. Review transaction safety.
7. Check for debug code.
8. Check for exposed secrets.
9. Summarize changes.

---

# 83. Agent Completion Report

After completing a coding task, report:

```text
Implemented
Files Created
Files Modified
Tests Added
Tests Run
Test Results
Known Limitations
TBD Decisions
```

Do not claim success if tests failed.

---

# 84. Failure Reporting

If implementation cannot be completed correctly because of missing information:

Do not invent the requirement.

Report:

```text
BLOCKED / TBD
```

and clearly state the missing decision.

Continue with safe independent work where possible.

---

# 85. No Fake Completion

Never:

- Comment out failing tests to make CI green.
- Disable authorization to make a page work.
- Hardcode fake stock.
- Hardcode financial totals.
- Ignore failed database transactions.
- Mark TODO functionality as complete.
- Suppress important errors without fixing them.

---

# 86. Data Integrity Priority

When trade-offs occur, prioritize:

```text
Correct Inventory
Correct Financial History
Security
Data Integrity
```

over:

```text
Animation
Visual polish
Minor convenience
```

---

# 87. Production Safety

Before production changes involving database or critical workflows:

- Backup data.
- Review migration impact.
- Run tests.
- Verify rollback strategy.

Do not experiment directly on production data.

---

# 88. Deployment Rule

Production deployment must follow the approved implementation/deployment process.

At minimum verify:

```text
APP_DEBUG=false
HTTPS enabled
Database not publicly exposed
Queue running
Scheduler running
Backups running
Permissions correct
Tests passed
```

---

# 89. Documentation Rule

If implementation changes an approved behavior, update the relevant documentation.

Do not let code and documentation silently diverge.

---

# 90. Unresolved Policy Decisions

Do not invent final values for unresolved business policies.

Examples may include:

- Return period
- Exchange period
- Proof-of-purchase rules
- Refund approval details
- Supplier credit/payables
- Final payment provider
- Final deployment provider

Use:

```text
TBD
```

until approved.

---

# 91. Coding Agent Prompt Template

Recommended usage:

```text
Read AGENTS.md and all project documentation relevant to this task before writing code.

Implement only the requested phase/feature.

Follow the existing architecture, database schema, workflows, security rules, UI design system, and testing requirements.

Do not invent requirements.

Do not implement future phases.

Use database transactions and row locking for stock/financial operations where required.

Add or update automated tests.

Run the relevant test suite after implementation.

At the end, report:
- files created
- files modified
- tests added
- tests run
- test results
- unresolved TBD items
```

---

# 92. Final Agent Rule

When uncertain, prioritize:

```text
Documentation
→ Correctness
→ Data Integrity
→ Security
→ Tests
→ Maintainability
→ UI Polish
```

Never trade inventory or financial correctness for development speed.

---

# 93. Document Status

**Status: Mandatory Coding Agent Instructions**

Any coding agent working on MFBMS should read this file before making project changes.

This document acts as the permanent development guardrail for the project.