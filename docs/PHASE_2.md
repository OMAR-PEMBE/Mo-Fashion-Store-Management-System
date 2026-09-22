# Phase 2 — Authentication and authorization

## Implemented

- Email/password staff sign-in, session rotation and last-login timestamp.
- POST logout with session invalidation and CSRF renewal.
- Administrator and Salesperson roles, permissions and role-permission pivot.
- Gates deny undefined permissions; UserPolicy restricts profile/password access
  to the account owner. No administrator bypass of unknown abilities.
- Protected workspace and profile with active-account and session-hash checks.
- Disabled and unassigned users cannot sign in, recover passwords or retain access.
- Five failed logins per account/IP per minute; additional IP request throttling.
- Generic credential/reset responses, expiring hashed single-use reset tokens.
- Password change requires the current password. New passwords require at least
  10 characters, letters, numbers and confirmation.
- Branded responsive login/reset/profile views, account menu and 403 page.
- Interactive administrator provisioning with hidden password input, validation
  and no seeded credentials or overwriting of existing users.

## Files

New authorization migration; Role/Permission models; UserPolicy; permission
catalog and seeder; active-account middleware; LoginRequest; session/password
controllers; administrator command; authentication and error views; feature tests.
Updated User, routes, middleware/provider configuration, base layout, database
seeder, foundation/MySQL tests, README and local setup instructions.

## Scope and decisions

Email is the Phase 2 login identifier; optional username is reserved in the schema.
Existing scaffold users retain a nullable role for migration compatibility and
receive no access until explicitly assigned one. The original name/email fields
are aligned to the documented lengths. Email is nullable in storage but required
for accounts provisioned by the command and for login/recovery.

Salespeople receive product/stock viewing, customer registration, sale creation
and order creation permissions. They receive no cost/profit, staff management,
inventory adjustment or refund approval access. Permissions do not create future
business screens. Future modules must add their own policies and permission gates.
Order-status and refund policy decisions remain for their respective phases.

Seeders initialize permissions for new roles only; reruns cannot silently undo
revocations. No UI for staff management or role editing is introduced here.
The workspace remains a placeholder, not the Phase 16 reporting dashboard.

## Verification

Authentication tests cover successful/invalid/inactive login, roleless accounts,
session rotation/logout, protected HTML/JSON requests, permission denials,
revocation, throttling and expiry, password reset/change, session invalidation,
administrator provisioning, CSRF and password validation. The dedicated MySQL
suite validates migrations and actual role/login queries on `mfbms_testing`,
rolling back generated staff data.

Results: 22 feature/foundation tests passed (151 assertions); MySQL integration
passed (1 test, 16 assertions). Pint, the production Vite build, Blade compilation
and route caching passed. Chrome checks passed at 1440px and 390px with locally
loaded Manrope, no horizontal overflow and no JavaScript exceptions. The mobile
login screenshot was visually reviewed. Development migrations and role seeding
also completed successfully.

## Remaining setup

Create your administrator interactively using `app:create-administrator`.
Local mail is logged; production mail delivery is not configured. No production
deployment or GitHub push is included. Detailed `UI_SPEC.md` remains absent.

## Framework references

- [Laravel authentication](https://laravel.com/docs/13.x/authentication)
- [Laravel password resets](https://laravel.com/docs/13.x/passwords)
- [Laravel authorization](https://laravel.com/docs/13.x/authorization)
