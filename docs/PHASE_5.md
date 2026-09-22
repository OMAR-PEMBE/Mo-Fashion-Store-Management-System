# Phase 5 — Supplier management

## Implemented

Administrators can create, view, search, edit, deactivate and reactivate suppliers.
Required fields are name and unique supplier code. Optional fields are contact
person, phone, email, location and notes. Codes are trimmed and uppercased before
validation; emails are lowercased. Lists support status filtering, pagination and
deterministic sorting. Existing components and design tokens are reused.

SupplierPolicy and the `suppliers.manage` permission protect every implemented
route. Existing administrators receive the permission in the migration, and newly
seeded administrators receive it through the permission catalogue. Salespeople and
disabled accounts cannot access supplier records. Seeder reruns preserve revocations.

Deactivation updates status only and retains the supplier ID, creation timestamp
and details. There is no delete endpoint. The schema includes soft-delete support
as specified, and unique codes remain reserved for inactive/soft-deleted records.
The service whitelists validated input and catches database unique-constraint races.

## Files created

- `database/migrations/2026_09_22_000004_create_suppliers.php`
- `app/Models/Supplier.php`
- `app/Policies/SupplierPolicy.php`
- `app/Services/SupplierService.php`
- `app/Http/Controllers/SupplierController.php`
- `resources/views/suppliers/{index,form,show}.blade.php`
- `tests/Feature/SupplierTest.php`
- `tests/MySql/SupplierDatabaseTest.php`

Modified permission catalogue, web routes, sidebar, README and local setup guide.
No dependencies, accounts, sample suppliers or public REST API were added.

## Tests and verification

- Application suite: **68 tests, 535 assertions passed**.
- Dedicated MySQL suite: **4 tests, 67 assertions passed** on `mfbms_testing`.
- Nine supplier feature tests cover create/update, optional fields, normalization,
  duplicate/invalid input, deactivation/reactivation, retained identity, blocked
  deletion, access control, revocation, search/filter/pagination, escaping and CSRF.
- MySQL verifies persistence, deactivation and the database unique-code constraint;
  generated test data is rolled back.
- Pint, Vite production build, Blade compilation, route caching and diff checks passed.
- Chrome create/edit/deactivate/reactivate flows passed using a disposable database.
  Supplier list/detail/forms were checked at 1440px and 390px; no page overflow or
  JavaScript exceptions. Desktop detail and mobile form screenshots were reviewed.
- Development migration applied successfully. Existing store data was not used
  for browser testing.

## Purchase-history integration and limits

The model documents the Phase 7 `purchases(): HasMany` integration point using
`purchases.supplier_id`; the detail screen contains an explicitly labelled future
history panel. An executable relationship is deliberately not added before the
Purchase model/table exist. No fake history or premature purchasing module is present.
When Phase 7 lands, add the real relationship and tests that completed purchases
remain accessible after supplier deactivation. This phase proves supplier identity
preservation, not preservation of financial records that do not yet exist.

Supplier credit/payables policy remains TBD as documented. No financial operations
are implemented. Detailed UI_SPEC remains unavailable; screens follow UI.md.

Next: Phase 6 — Inventory Foundation. Changes remain local; no commit or push was
performed in this phase.
