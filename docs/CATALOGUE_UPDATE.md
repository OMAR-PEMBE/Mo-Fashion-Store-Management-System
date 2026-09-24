# Colour choices and automatic SKUs — 2026-09-24

The local colour table had zero records, so the variant selector could only show
No colour. Added 12 common starter colours through ColourSeeder and included it
in normal reference-data seeding. Applied only ColourSeeder to the local database;
verified 12 active colours afterward. Existing names, codes and inactive choices
are preserved on repeat runs. Other colours can be managed through Catalogue setup.

New variants generate a SKU when the submitted SKU is blank or omitted, using
product code, colour code and size code. Absent attributes are omitted; unusually
long codes are shortened to fit the column while retaining each supplied attribute.
Existing and archived SKUs trigger a numeric suffix. Database uniqueness remains
the final protection against concurrent collisions; a conflicting concurrent
submission receives a validation error and can be retried. Custom SKUs remain
supported. A blank SKU on an existing variant preserves its current identifier.
Duplicate size/colour combinations remain prohibited.

## Files

- Added database/seeders/ColourSeeder.php.
- Updated database/seeders/ReferenceDataSeeder.php and
  app/Services/ProductCatalogueService.php.
- Updated resources/views/products/variant-form.blade.php with optional SKU,
  colour management link and empty-list guidance.
- Updated ProductCatalogueTest, ReferenceDataTest and CustomerTest fixtures.
- Updated Markdown Files/REQUIREMENTS.md and docs/LOCAL_SETUP.md.

## Verification

- Full application suite: 231 tests, 1,964 assertions passed.
- Following the final generator edge-case changes, focused catalogue/reference/
  customer regression: 53 tests, 420 assertions passed, including the additional
  zero-code regression not present when the full run started.
- MySQL catalogue integration: 1 test, 21 assertions passed.
- Changed PHP files passed Pint; Blade compiled successfully and its cache was
  cleared afterward. Git whitespace checks passed.

Regression coverage includes optional attributes, omitted/blank SKUs, preserving
existing identifiers, archived collisions, duplicate combinations, long codes,
the valid code 0, colour empty state and seed customization preservation.
No schema migration, financial transaction or stock balance was changed. Refresh
the variant form to see the colours; leave SKU blank and save to generate it.
Actual owner/staff acceptance in Phase 22 remains pending. No new policy decision
is required for this requested catalogue change.
