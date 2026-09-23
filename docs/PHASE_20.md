# Phase 20 — Security Hardening

Application hardening implemented and verified on 2026-09-23. This is not a
production deployment or a claim that the eventual hosting environment is secure.

## Changes

Created `SecurityHeaders` middleware, `ProductionSecurity` configuration checks,
`app:check-security` command and `SecurityHardeningTest`; registered the global
middleware in bootstrap/app.php. No database migration or dependency changes.

Every application response receives MIME-sniffing protection, same-origin frame
protection, a referrer policy, restrictions on camera/microphone/geolocation and
private/no-store caching. The CSP restricts base URLs, plugin objects, framing and
form targets. It is deliberately not a full script-source CSP: the current
Alpine/Livewire integration requires separate compatibility work before stricter
script execution rules can be claimed.

In production, unsafe application configuration returns a generic 503 before
request handling. The checks require debug off, a valid encryption key, HTTPS
APP_URL, secure/HTTP-only cookies, lax/strict SameSite, persistent server-side
sessions and JSON serialization. Mail transports must deliver mail, not log reset
links; nested failover/round-robin transports are checked too.

HTTP GET/HEAD requests redirect to the configured HTTPS URL; HTTP mutations are
rejected instead of replayed. HTTPS requests must match the configured host/port.
HTTPS production responses include HSTS (180 days, without subdomain/preload).
Local HTTP development and its environment settings were left unchanged.

## Review evidence

- Existing authentication tests cover session rotation, inactive accounts, current
  passwords, strong passwords, reset expiry/single use, CSRF and throttling.
- Existing ownership/permission tests cover sales, orders, returns, refunds,
  exchanges, financial visibility and administration. User-supplied record IDs
  are checked server-side.
- Services validate and allowlist writes; protected financial models are guarded.
  New regression checks reject privileged User mass assignment and SQL-like search
  input. Report raw expressions use fixed internal mappings and bound filter values.
- Blade escapes values and audit detail redacts sensitive keys. No new raw HTML
  output or upload handling was introduced.
- Product-image uploads are not implemented. A regression verifies that an
  executable-file submission to the product-image endpoint is rejected. This is
  not a completed image-upload security implementation; a future uploader must
  validate decoded content/MIME/size, randomize names and prevent execution.
- Tracked environment files are example templates only. Local secrets and .tools
  remain ignored. This filename check is not a historical secret-scanning audit.
- Composer locked-dependency audit: zero advisories and no abandoned packages.
- npm dependency audit: zero known vulnerabilities. Audit results are point-in-time.

## Verification

- Full application suite: **213 tests, 1,888 assertions passed**.
- Full MySQL suite: **34 tests, 492 assertions passed**.
- Six security tests cover public/error headers, production misconfiguration,
  HTTPS redirect/mutation rules, host rejection, mail fallbacks, production checks,
  bound search input, privileged mass assignment and unavailable uploads.
- Browser checks used disposable SQLite fixtures and verified security headers,
  Alpine navigation, login, settings forms, audit filtering/details and staff denial
  at desktop/mobile widths. No JavaScript errors or page overflow.
- Pint, route caching/clearing, view compilation and Git whitespace checks passed.
- Local app:check-security correctly fails because local development deliberately
  uses HTTP/debug/development mail; no local credentials or settings were changed.

Temporary browser database/credentials were removed and test processes stopped.
No commit, push, dependency upgrade or production deployment was performed.

## Deployment verification still required

Use only public/ as the web root. Verify HTTPS certificates and HTTP redirect at
the web server, explicitly trusted proxy addresses/forwarded headers if applicable,
private MySQL/firewall access, least-privileged database credentials, filesystem and
log permissions, external mail delivery, dependency advisories and backup restore.
The application check cannot prove those infrastructure properties. Direct static
files and server-generated errors also need equivalent web-server headers.

Do not set APP_ENV=production until its configuration is ready; otherwise the
application intentionally returns 503. Run `php artisan app:check-security` against
the final configuration/cache. A successful result is not a deployment approval.

Next: **Phase 21 — Automated Testing & QA**.