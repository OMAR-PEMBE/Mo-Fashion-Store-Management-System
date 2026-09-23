# Phase 18 — Users & Administration

Implemented on 2026-09-23. Administrators can manage staff under **Staff & access**.

## Delivered

- Search/filter paginated accounts; create/edit name, email, phone, role and status.
- Deactivate/reactivate accounts while retaining transaction and audit relationships.
- Assign existing Administrator/Salesperson roles and edit their shared permissions.
- Set temporary passwords for new staff or reset another account's password.
  Staff must change the temporary password in Profile before using business features.
- Require the acting administrator's current password for administrative mutations.
- Revoke sessions/reset links after security changes; stale sessions remain revoked
  after reactivation. Self-service password changes retain the current session.
- Reject stale edits, self-deactivation, self-role changes and removal of all
  administrator access. Staff administration and refund approval/completion remain
  administrator-only, even with incorrectly assigned permissions.
- Record transactional, allowlisted audit events without passwords or tokens.

## Files

Created `StaffService`, `StaffController`, four `resources/views/staff` templates,
`2026_09_23_000015_add_staff_management_controls`, `StaffTest`,
`StaffConcurrencyTest` and `StaffRaceWorker`.

Updated User/Role casts, login/session middleware, password handling, Profile,
navigation, routes, authentication/foundation tests, implementation plan, API,
security, database and workflow documentation, README and local setup guide.
Earlier dashboard/report work in the workspace was preserved.

## Verification

- Full application suite: **201 tests, 1,762 assertions passed**.
- Full MySQL suite: **33 tests, 477 assertions passed**.
- Nine staff tests cover authorization, password confirmation, forced password
  changes, token/session revocation, stale writes, restricted permissions, seed
  preservation, immutable expense/recorder history, CSRF and audit-failure rollback.
- Real independent MySQL workers verified that competing administrators cannot
  both deactivate each other: one succeeds, one is denied, one active admin remains.
- Chrome verified creation, editing, deactivation/reactivation, permission saves,
  required password change and administrator password reset. Desktop (1440px) and
  mobile (390px) layouts passed without overflow or JavaScript errors.
- Visual review prompted readable permission labels in place of raw slugs.
  Final label/browser check and focused **9 tests, 86 assertions** passed afterward.
- Pint, production asset build, Blade compilation and staff-template PHP syntax,
  route caching/clearing, database connectivity and Git whitespace checks passed.
- The additive migration was applied successfully to the local development database.

Browser checks used a disposable SQLite database; MySQL tests used `mfbms_testing`.
Temporary browser credentials/database were removed and the browser/server stopped.
No commit, push or production deployment was performed.

## Limits and next phase

Credential sharing is manual and private; no automated credential email, custom
role creation, permission-definition editing or staff deletion is included.
Existing accounts are not forced to change passwords by the migration.
No unresolved business-policy decision blocks this phase.

Next: **Phase 19 — Audit Logs & Settings**. Existing audit records are already
written; the review screens and business-settings interface are the next work.