# Phase 19 — Audit Logs & Settings

Implemented 2026-09-23.

## Delivered

- Administrator-only, read-only audit list/detail with staff, action, record type,
  record ID and inclusive local-date filters. Results paginate at 30 entries.
- Escaped before/after values with recursive sensitive-field redaction. Existing
  entries remain intact; absent historical fields are displayed as unavailable.
- Editable business name, phone, address, receipt footer and default low-stock
  threshold. Current store details appear in the workspace and sale summaries.
- Settings password confirmation, fresh authorization, serialized updates,
  stale-form detection and atomic audit records. Only approved fields are writable.
- Default threshold prefills new variant forms; existing thresholds are unchanged.
- Transactional product/variant price audits and stock adjustment/damage/loss
  audits, including before/after balances. Idempotent retries create no duplicate
  audit records. Existing purchase/refund/exchange/expense/permission audits remain.

Currency and timezone retain the established Version 1 values (TZS and
Africa/Dar_es_Salaam). The optional question about relaxing those defaults had no
reply during implementation; no currency conversion or historical date migration
was introduced. Sale cancellation remains disabled pending its separate reversal
policy. Store details are current display values, not historical receipt snapshots.

## Files

Created AuditService, BusinessSettingsService, AuditController, SettingsController,
three audit/settings Blade views, migration 000016 and AdministrationTest.

Updated catalogue/inventory audit writers, variant form defaults and controller,
application view composer, navigation and sale summary, staff permission guards,
permission catalogue, routes, business configuration, inventory MySQL race test and
worker. Updated implementation plan, API, database, security, workflow, README and
local setup documentation. Earlier uncommitted phases were preserved.

## Verification

- Full application suite: **207 tests, 1,826 assertions passed**.
- Full MySQL suite before the new adjustment race case: **33 tests, 477 assertions**.
- Additional real-MySQL adjustment retry case: **1 test, 15 assertions passed**;
  concurrent identical requests produce one movement and one audit entry.
- Six administration tests cover settings validation, authorization, stale forms,
  seed preservation, current-password/CSRF checks, financial history preservation,
  default thresholds, audit filtering, redaction, escaping, read-only routes,
  transactional price/stock audits and rollback on audit failure.
- Final focused suite after the header refinement: **6 tests, 64 assertions**.
- Chrome exercised settings saves, before/after audit values, filtering and direct
  staff denial at 1440px and 390px with no page overflow or JavaScript errors.
  Desktop/mobile screenshots were inspected and the header refined for store names.
- Pint, production assets, Blade compilation/compiled audit and settings syntax,
  route caching/clearing, local migration, database connectivity and whitespace
  checks passed.

Browser tests used disposable SQLite data; MySQL checks used mfbms_testing. The
browser/server were stopped and temporary database/credentials removed. No business
settings were changed in the development database beyond the additive migration.
No commit, push or production deployment was performed.

Next: **Phase 20 — Security Hardening**.