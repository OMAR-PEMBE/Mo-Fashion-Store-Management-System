# Local development

This foundation uses PHP 8.4+, Composer 2, Node LTS and MySQL 8+.
PHP 8.4 meets the project's PHP 8.3+ requirement; the dependency lock was
resolved on PHP 8.4 and must be used with that runtime or newer.
XAMPP's existing PHP 8.2 and MariaDB 10.4 do not meet the locked project stack.
Use the project-local PHP and Node tools when present; XAMPP is unchanged.

## Create your databases on Windows

**This workstation already has MySQL96 running on port 3306.** Use
`DB_PORT=3306` in its local `.env` and keep XAMPP's database service stopped.
Set `DB_PASSWORD` to the password assigned to `mfbms_app`; the root password
is only for administration. A blank application password causes error 1045
when that account requires a password. The fresh-install instructions below
use port 3307 only when installing a separate server alongside XAMPP.

1. Download **MySQL Community Server 8.4 LTS, Windows MSI** from
   <https://dev.mysql.com/downloads/mysql/>. Follow the official Windows guide:
   <https://dev.mysql.com/doc/refman/8.4/en/windows-installation.html>.
2. Install the server and run MySQL Configurator. Choose development use,
   TCP/IP port **3307** (to coexist with XAMPP), and a Windows service. For local
   development, do not open the port to other computers. Set a strong root
   password and save it privately.
3. Open the MySQL command line client, sign in as root, and copy the commands
   from `database/setup-local.sql`. Replace the two password placeholders with
   different strong passwords before executing. Do not save real passwords in
   the tracked SQL file. This creates `mfbms` and `mfbms_testing`, each with its
   own restricted database user. Run user creation only once.
4. Put the development account password into your ignored `.env` file. Copy
   `.env.testing.example` to `.env.testing` and put the test account password
   there. Never use the development database for automated database tests.
   Generate its key with `php artisan key:generate --env=testing`.
5. Run the connection check and migrations below. Do not run migrations against
   an existing unrelated database.

## Project tools

In PowerShell, from the project root:

```powershell
$taskRoot = (Get-Location).Path
$taskNode = Get-ChildItem .tools/node -Directory | Select-Object -First 1
$env:Path = "$taskRoot\.tools\php;$($taskNode.FullName);$env:Path"
php artisan key:generate
php artisan app:check-database
php artisan migrate
php artisan test
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open <http://127.0.0.1:8000>. The development server uses the supported local
PHP runtime. For Apache, configure a dedicated virtual host whose document root
is this project's `public` directory and use PHP 8.4+. The root `.htaccess`
intentionally denies access to the project directory itself.

Once dependencies and assets are installed, double-click `scripts/serve.cmd`
to start the local server without changing PowerShell's execution policy.

If your PowerShell policy allows local scripts, `. .\scripts\dev-env.ps1` is a
shortcut for the tool-path setup and also defines a `composer` command. Otherwise
use `php .tools/composer.phar` in place of `composer`. Use `npm.cmd` on Windows
when PowerShell blocks npm's script shim. No machine-wide PATH changes are needed.

Fresh checkouts with globally installed tools:

```powershell
Copy-Item .env.example .env
composer install
npm ci
php artisan key:generate
npm run build
```

The font files are bundled locally using Fontsource. No Google Fonts request
is needed at runtime. Alpine is loaded once by Livewire.

## Verification

```powershell
php artisan test
php vendor/bin/pint --test
composer validate --strict
composer audit
npm run build
```

The default tests use isolated in-memory SQLite for foundation, authentication
and permission checks. They do not certify MySQL row locking. With `.env.testing` set up,
run `php vendor/bin/phpunit --configuration phpunit.mysql.xml` for the dedicated MySQL
suite. That suite rejects any database other than `mfbms_testing` before migrations.

The database queue uses the scaffold's jobs tables after migration. Start a
worker with `php artisan queue:work` when jobs are introduced. Logging uses daily
files with 14-day development retention. Production infrastructure and backup
policy remain separate deployment work.

## Staff sign-in (Phase 2)

After configuring your development database, run these commands from the project root:

```powershell
& '.tools/php/php.exe' artisan migrate
& '.tools/php/php.exe' artisan db:seed --class=RolePermissionSeeder
& '.tools/php/php.exe' artisan app:create-administrator
```

The last command asks for your name, email and password twice. Password entry is
hidden. Use a unique password of at least 10 characters with letters and numbers.
There is no default account and no public registration. Existing accounts are
never overwritten by this command. Open <http://127.0.0.1:8000/login> and sign in
with the email and password you chose. Use **Your account → Profile & password**
to change your password, or **Sign out** to end your session.

The seeder initializes Administrator and Salesperson roles and their permissions.
Rerunning it preserves existing permission assignments. Legacy scaffold users
without a role cannot sign in. Staff account administration screens are Phase 18.

Password-reset email uses the configured mail transport. With the local
`MAIL_MAILER=log`, emails appear only in the private `storage/logs` directory;
they are not delivered to an inbox. Reset links in those logs are sensitive.
Configure a real mail transport before using password recovery in production,
and set `APP_URL` to the correct application URL. Reset links expire after
60 minutes and can be used once. Password changes invalidate other sessions
on their next protected request. Production requires HTTPS and secure cookies.

## Catalogue setup (Phase 3)

For a fresh checkout or upgrade:

```powershell
& '.tools/php/php.exe' artisan migrate
& '.tools/php/php.exe' artisan db:seed
```

Sign in as an administrator and use **Catalogue setup → Categories / Sizes /
Colours** in the sidebar (open **Menu** on mobile). Each list supports search,
status filtering and pagination. Use **Add** to create an entry and **Edit** to
change its details or set its status to Inactive. Entries can be reactivated.
No permanent delete action is exposed.

Category slugs are unique lowercase words separated by hyphens. Size and colour
codes are unique uppercase identifiers with optional hyphens or underscores.
Size display order is configurable; lower values appear first. Optional colour
hex values use six digits, for example `#123ABC`.

Initial sizes are XS, S, M, L, XL and XXL. Product categories and colours start
empty so you can add your store's actual choices. Expense categories and basic
system settings are prepared for later phases; they have no editing screens yet.
Rerunning seeders preserves existing values and permission revocations.

Keep database passwords only in `.env` and `.env.testing`. Their `.example`
templates are tracked by Git and must contain no credentials.

## Products and variants (Phase 4)

Run `& '.tools/php/php.exe' artisan migrate` after pulling this phase. Existing
administrators receive the new cost-viewing permission through the migration.

1. Sign in as an administrator and open **Products → Add product**.
2. Enter a name, unique product code and active category. A default selling price
   and description are optional.
3. Save, then select **Add variant** on the product detail page.
4. Select size/colour, enter a unique SKU, selling price and low-stock threshold.
   For accessories you may choose **No size / one size** and/or **No colour**.
5. Save. Repeat for each combination. Creating a product or variant adds no stock.

Codes and SKUs are stored in uppercase. Prices must be nonnegative with no more
than two decimal places. The default product price only prefills new variant
forms; changing it does not change existing variant prices. Average cost starts
at zero and cannot be manually edited here.

Use **Edit** to update or deactivate an item. **Archive** asks for confirmation
and preserves records. Choose the **Archived** filter to find and restore items;
restored records are inactive until reviewed. Codes, SKUs and combinations stay
reserved for archived records, so restore an existing item instead of duplicating
it. Product archival preserves its variants. No hard-delete action is exposed.

Salespeople can browse available products, variants and selling prices. They
cannot create/edit/archive entries or see average cost. Inactive categories,
sizes and colours are not available for new selections. A previously linked
inactive reference can be retained while deactivating the existing item.

Product image uploads are optional in the specifications and are not included
in this phase. Stock receiving, opening balances, sales and costing calculations
remain in their scheduled phases.

## Suppliers (Phase 5)

After pulling this phase, run `& '.tools/php/php.exe' artisan migrate`. The migration
adds supplier storage and grants supplier management to existing administrators.
New installations receive the permission through normal role seeding.

Open **Suppliers → Add supplier**. A business name and unique supplier code are
required. Contact person, phone, email, location and notes are optional. Supplier
codes use letters/numbers separated by hyphens or underscores and save in uppercase.

The list supports search by name, code, contact person, phone or email, plus status
filters and pagination. Open **View → Edit supplier** to change details or select
Inactive. Deactivation retains the same record and contact details; change the
status back to Active to reactivate it. There is no permanent deletion action.
Salespeople cannot access supplier records.

The purchase-history panel is a placeholder until stock purchasing is implemented
in Phase 7. No purchase records, balances or supplier credit rules are introduced.
