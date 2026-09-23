# Mo Fashion Store BMS

Foundation, staff authentication, catalogue, suppliers, inventory and purchasing for the single-branch retail management system.

Stack: PHP 8.4+, Laravel 13, Livewire 4 (bundled Alpine), Tailwind CSS 4,
Vite and MySQL 8+. Typography: locally bundled Manrope 400/500/600/700.

See [local setup](docs/LOCAL_SETUP.md) for the Windows toolchain, creating MySQL
databases, running the application and executing tests.

Implemented: base shell, reusable Blade components, currency/timezone defaults,
daily logging, environment templates, framework migrations/factory infrastructure,
and automated tests. Staff authentication includes roles/permissions, active-account
checks, login throttling, logout, password reset and password change. The protected
workspace contains no financial metrics. Administrators can create, edit, search
and deactivate categories, sizes and colours under **Catalogue setup**. Starter
sizes, expense categories and system settings are seeded without overwriting
customizations. Products include searchable listings, size/colour variants, unique
codes/SKUs, selling prices, status controls and archive/restore. Salespeople have
read-only access to available items and cannot see costs. See the
[Phase 4 report](docs/PHASE_4.md). Creating catalogue items does not add stock.
Administrators can also create, search, edit, deactivate and reactivate suppliers;
see the [Phase 5 report](docs/PHASE_5.md). Purchase history connects in the purchasing phase.
Business modules follow in [IMPLEMENTATION_PLAN.md](<Markdown Files/IMPLEMENTATION_PLAN.md>).

Inventory now provides physical/reserved/available balances, low-stock filtering
and administrator movement history. Transactional stock services enforce quantity
limits, reservation ownership and idempotent retries using MySQL row locks.
See the [Phase 6 report](docs/PHASE_6.md). New variants start with zero stock.
Supplier purchases now support drafts, review, confirmation, stock receiving,
weighted-average costs and supplier history. Confirmation is atomic and rejects
duplicates. See the [Phase 7 report](docs/PHASE_7.md). Administrators can now enter
opening stock once per unused variant, with review, initial costing and audit
history. See the [Phase 8 report](docs/PHASE_8.md). Customer profiles now include
search, quick registration, preferences, explicit marketing consent and duplicate
contact warnings. See the [Phase 9 report](docs/PHASE_9.md). Sales and automatic
customer statistics are now connected through the counter-sales workflow.
Point of sale supports carts, walk-ins, registered customers, payment recording,
review, stock deduction, historical cost snapshots and sales history. See the
[Phase 10 report](docs/PHASE_10.md). Completed-sale cancellation remains disabled
pending approval of stock-return and refund rules.

Orders now support registered customers, stock reservations, manual payment,
sale conversion, unpaid cancellation and delivery tracking. Open **Orders** in
the sidebar. See the [Phase 11 report](docs/PHASE_11.md).

Returns now support a three-day limit, receipt or sale-record proof, approval by
administrators and salespeople, partial returns, sellable-stock restoration and
historical cost adjustments. Open **Returns** or **Start return** on a sale.
See the [Phase 12 report](docs/PHASE_12.md).

Money refunds now support salesperson requests, administrator approval and manual
completion, flexible refund methods, partial amounts and duplicate protection.
Open **Refunds**, or request one from the original sale/completed return. See the
[Phase 13 report](docs/PHASE_13.md).

Exchanges now support any available replacement product within three days of the
original sale, equal-value swaps, additional payments and administrator-completed
refund differences. Open **Exchanges** or **Start exchange** on a sale. See the
[Phase 14 report](docs/PHASE_14.md).

Administrators can now record and correct operating expenses, manage expense
categories, filter entries and inspect before/after audit history. Open **Expenses**.
Supplier stock stays in Purchases. See the [Phase 15 report](docs/PHASE_15.md).

Read [AGENTS.md](<Markdown Files/AGENTS.md>) before contributing. The screen-by-screen `UI_SPEC.md`
is not yet supplied; the UI uses the approved [UI.md](<Markdown Files/UI.md>) design tokens.

## Development commands

```text
composer install
npm ci
php artisan key:generate
php artisan app:check-database
php artisan migrate
php artisan db:seed
php artisan app:create-administrator
npm run build
php artisan test
php vendor/bin/pint --test
php artisan serve --host=127.0.0.1
```

Use `public/` as the web document root. Do not serve the repository root.
Do not deploy this foundation as a finished operational system.
