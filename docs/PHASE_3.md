# Phase 3 — Core reference data

## Implemented

Administrators can create, list, search, edit, deactivate and reactivate categories,
sizes and colours. Lists have status filters, pagination and deterministic ordering.
Sizes support a display order; colours support an optional validated hex value.
All forms use CSRF protection, escaped output and server-side validation.

ReferenceDataPolicy and the `reference-data.manage` gate protect every route.
The migration grants the new permission to existing administrators; new role
seeders grant it to new administrators. Salespeople receive no management access.
Undefined reference types return 404. Updating missing records returns 404.

Category slugs and size/colour codes have database unique constraints. Slugs are
lowercased and codes uppercased before validation. The service handles a concurrent
unique-constraint violation as a validation error. Only validated fields are saved.
Deactivation preserves record IDs and future historical relationships; there is
no hard-delete route. Category soft-delete storage follows the documented schema.

## Files created

- `database/migrations/2026_09_22_000002_create_reference_data_tables.php`
- `app/Models/{Category,Size,Colour,ExpenseCategory,SystemSetting}.php`
- `app/Enums/ReferenceType.php`
- `app/Policies/ReferenceDataPolicy.php`
- `app/Services/ReferenceDataService.php`
- `app/Http/Controllers/ReferenceDataController.php`
- `database/seeders/ReferenceDataSeeder.php`
- `resources/views/reference-data/{index,form}.blade.php`
- `tests/Feature/ReferenceDataTest.php`
- `tests/MySql/ReferenceDataDatabaseTest.php`

Updated permissions/provider registration, routes, sidebar, database seeder,
README and local setup documentation. Cleared password values from the tracked
environment example templates without modifying private environment credentials.
Existing user moves of specification files into `Markdown Files/` were preserved.

## Starter data

- Sizes: XS, S, M, L, XL, XXL.
- Expense categories: Rent, Electricity, Internet, Marketing, Packaging,
  Transport, Staff, Other.
- Settings: business_name, currency (TZS), timezone (Africa/Dar_es_Salaam),
  negative_stock_allowed (false).

Seeders create missing defaults without overwriting customized values. Categories
and colours are entered by the administrator. Settings are preparatory storage;
the existing fixed currency/timezone configuration remains authoritative until
the settings phase. No expense transactions or settings screens are added.

## Verification

- Application suite: **39 tests, 301 assertions passed**.
- Dedicated MySQL suite: **2 tests, 37 assertions passed** on `mfbms_testing`.
- Added coverage for all three resource types: create/update/deactivate/reactivate,
  unauthorized requests, duplicate codes, invalid values, mass assignment,
  missing records, filtering, pagination, escaping, seed preservation and CSRF.
- MySQL checks verify actual persistence, authorization and database-level unique
  constraints. Test records are rolled back.
- Pint, Vite production build, Blade compilation and route caching passed.
- Chrome: actual create/edit/deactivate workflows passed against a separate,
  disposable SQLite database. Lists and forms checked at 1440px and 390px;
  no page overflow or JavaScript exceptions. Desktop list and mobile form
  screenshots were visually reviewed.
- Development migration and starter seeder completed successfully.

## Limits and next phase

Product/variant relationships and preventing inactive reference selections are
Phase 4 work. No stock, sales, expense transactions, settings editor or public
REST endpoints were added. `UI_SPEC.md` remains absent; screens follow `UI.md`.
The example settings keys use the explicit initial values in DATABASE sections
80/initial seed data (`currency`, not the illustrative `default_currency`).

Changes remain local for review; no commit or push was performed in this phase.

## Framework references

- [Laravel routing and enum binding](https://laravel.com/docs/13.x/routing#implicit-enum-binding)
- [Laravel validation](https://laravel.com/docs/13.x/validation#rule-unique)
