# Phase 4 — Products and product variants

## Implemented

Products: category relationship, unique normalized code, name, description,
optional default selling price, active status, creator, soft deletion and restore.
Variants: size/colour relationships, unique normalized SKU, exact decimal selling
price, zero-initialized weighted-average-cost storage, low-stock threshold,
active status, soft deletion and restore. No inventory records or stock increases
are introduced by catalogue creation.

Administrators have searchable, category/status-filtered product lists, product
forms/details, paginated variant lists, variant forms, archive confirmations and
restoration. Salespeople can read available catalogue entries and prices only.
Cost is gated using `products.view_cost` and hidden from default model serialization.
The migration grants that permission to existing administrators; initial role
seeding includes it for new administrators without granting it to salespeople.

## Files created

- `database/migrations/2026_09_22_000003_create_product_catalogue.php`
- `app/Models/Product.php`, `app/Models/ProductVariant.php`
- `app/Policies/ProductPolicy.php`
- `app/Services/ProductCatalogueService.php`
- `app/Http/Controllers/ProductController.php`, `ProductVariantController.php`
- `resources/views/products/{index,form,show,variant-form}.blade.php`
- `resources/views/components/action-link.blade.php`
- `tests/Feature/ProductCatalogueTest.php`
- `tests/MySql/ProductCatalogueDatabaseTest.php`

Modified Category/Size/Colour relationships, permissions, routes, sidebar, README
and local setup documentation. No dependencies or public REST API were added.

## Integrity and authorization

- Controllers authorize through policies/gates before calling the catalogue
  service. Variant IDs are resolved through the requested product relationship.
- Only validated attributes can be saved. Creator and parent product are assigned
  server-side; input cannot set cost, stock, deletion timestamps or generated keys.
- Monetary input is validated as decimal text within DECIMAL(15,2), without using
  floating-point arithmetic or silently rounding extra decimal places.
- Transactions lock the product during variant changes and archival, and lock
  selected reference records while validating their status.
- SKU/code unique indexes include archived records. Generated size/colour keys
  map NULL to zero only inside the unique combination index, so even a variant
  with neither size nor colour cannot be duplicated. Foreign keys remain nullable
  and refer to actual IDs. These generated columns are internal and hidden.
- A combination is reserved across active, inactive and archived records. Restore
  or edit an existing variant instead of creating another copy. Restore retains
  identifiers and relationships and sets the restored record inactive for review.
- Inactive references cannot be newly selected or used to activate an item. An
  existing link may be retained when deactivating it. Products whose category is
  inactive and variants with inactive size/colour are hidden from salespeople.
- Product price defaults do not propagate to existing variants.

## Verification

- Application suite: **59 tests, 458 assertions passed**.
- MySQL suite: **3 tests, 58 assertions passed** against `mfbms_testing`.
- Added 20 feature cases covering creation, updates, uniqueness including every
  NULL dimension combination, price limits, inactive references, permissions,
  cost redaction, mass assignment, wrong-parent access, archive/restore, searching,
  pagination, escaping and CSRF.
- MySQL integration independently verifies generated-column unique constraints,
  exact maximum decimal round-trips, restricted foreign-key deletion and retained
  variant relationships after archival. Test data is rolled back.
- Pint, production frontend build, Blade compilation and route caching passed.
- Browser create-product/create-variant/edit-price/archive-confirmation/restore
  flows passed using a disposable SQLite database and generated test administrator.
- Desktop 1440px and mobile 390px lists, detail and forms checked. A mobile overflow
  defect found in screenshot review was corrected by positioning the table's
  scroll container. Checks compare actual document width to the requested viewport;
  tables scroll inside their panels. No JavaScript exceptions occurred.
- Development migration completed; no sample products were added to the store.

## Limits and next steps

Optional image uploads are not included; the workflow/specifications mark them
optional and no upload schema is prescribed for Phase 4. Inventory tables and
quantities are Phase 6; stock receiving/opening balances and weighted-average-cost
calculations follow in later phases. No financial-history test is claimed before
sales/purchasing exist. Later transaction modules must enforce their own rules
for changing variant dimensions after use. Full audit reporting remains Phase 19.
Detailed `UI_SPEC.md` is still absent; views follow the existing design system.

Next implementation phase: supplier management. Changes are local; this turn
does not commit or push them.

## References checked

- [MySQL generated columns](https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html)
- [Laravel resource controllers and soft-deleted models](https://laravel.com/docs/13.x/controllers#soft-deleted-models)
