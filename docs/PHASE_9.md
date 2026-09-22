# Phase 9 — Customer management

## Implemented

Customer profiles store the documented contact details, preferences, consent,
active status and statistics fields. Categories use the unique customer/category
pivot. Preferred size and colour are optional. Codes are allocated atomically from
the existing document sequence as MFS-CUS-000001. No sample customer is seeded.

The list supports name/code/phone/WhatsApp search, status filtering and pagination.
Full registration and a quick-create modal share CustomerService validation.
Profiles display contact/preferences/consent/status and read-only statistics.
Purchase history is explicitly a placeholder until sales exist.

Phone and WhatsApp numbers are normalized to digits; a leading 00 prefix is removed.
Cross-field duplicate checks include inactive and soft-deleted customers. A likely
match returns a validation warning naming the existing code/profile. The user must
explicitly acknowledge a separate profile before saving. Contact columns are not
unique because shared numbers are legitimate. The service locks the customer
sequence before checking matches and generating a code, serializing registrations.
Country codes are not inferred; national/international variants can still need
manual review. Name-only duplicates are not automatically treated as identical.

customers.create protects registration and browsing for administrators/salespeople.
The new customers.manage permission allows administrators to edit, deactivate,
reactivate and withdraw marketing consent. Both HTTP routes and service mutations
authorize access. Existing inactive preferences can be retained; new preferences
must refer to active records. There is no deletion endpoint.

Consent defaults false, including quick-create. Customer/profile preferences,
number allocation and audit insertion are transactional. CREATE_CUSTOMER and
UPDATE_CUSTOMER audits record actor, entity, consent/status, changed field names,
category IDs and duplicate acknowledgement; contact values are not copied into
the audit payload. Client-supplied totals, purchase dates and customer codes are
ignored. Customer statistics initialize to database zero/null defaults.

## Files

Created the customer migration, Customer model, CustomerService, CustomerController,
three customer Blade views, CustomerTest and CustomerDatabaseTest. Modified the
permission catalogue, routes/sidebar, README and local setup guide. Existing
uncommitted Phase 8 work was preserved. No dependency was added.

## Tests and verification

- Application suite: **121 tests, 878 assertions passed**.
- Dedicated MySQL suite: **13 tests, 192 assertions passed**.
- Eight feature tests cover registration/preferences/consent/statistics, contact
  normalization, duplicate warning/acknowledgement, roles, revocation, editing and
  inactive preference retention, search/pagination/escaping, validation, audit
  rollback, no dummy walk-in records, guest access and CSRF.
- MySQL verifies profile persistence, duplicate warnings/override and unique codes,
  with generated records rolled back. Simultaneous customer registration was not
  separately exercised by a multi-process test in this phase.
- Chrome verified full registration, preferences, consent withdrawal, deactivation,
  quick-create and duplicate acknowledgement against a disposable database.
  List/detail/form screens passed 1440px and 390px checks with no page overflow or
  JavaScript exceptions. Desktop detail and mobile form screenshots were inspected.
- Pint, production assets, Blade compilation, route caching and diff checks passed.
- Development migration applied. Browser fixtures were removed; no real customer
  data was used or created for testing.

## Integration boundaries

Phase 10 must add the real customer/sales relationship and update first/last
purchase dates, purchase count and spending atomically with completed sales.
These updates are not implemented before sales exist. Walk-in behavior currently
means no dummy customers are seeded or required by this foundation; an actual
walk-in sale with customer_id NULL must be tested in Phase 10.

No portal, marketing automation, messaging, bulk import or data-erasure policy was
invented. UI_SPEC remains unavailable; screens use existing UI.md components.
Next: Phase 10 — Counter Sales / POS. Changes remain local; no commit or push.
