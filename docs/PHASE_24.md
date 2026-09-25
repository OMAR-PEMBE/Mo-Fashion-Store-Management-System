# Phase 24 — Backup Setup

Backup tooling implemented and locally verified on 2026-09-26, ahead of Phase 23,
because it does not depend on owner UAT results. The phase is not complete until
the production schedule, off-site copy and server restore test are done (see below).

## Changes

Created `config/backup.php`, `DatabaseBackupService`, the `app:backup-database` and
`app:verify-backup` commands, scheduler entries in routes/console.php,
`DatabaseBackupTest` and the MySQL `BackupRestoreTest`. Added restore-check
databases to database/setup-local.sql and backup settings to .env.example. No
migration, dependency or business-data changes.

**Backup.** `mysqldump` takes a consistent InnoDB snapshot (`--single-transaction`,
no table locks) of the configured database, including triggers. Output is gzip
compressed as `mfbms-YYYYMMDD-HHMMSS.sql.gz` with a `.sha256` checksum beside it.
The dump must end with MySQL's completion marker; failed or incomplete dumps leave
no backup file and are logged as errors.

**Storage and secrets.** Backups are written to `storage/app/backups`, which is
outside every filesystem disk root, never web-served and git-ignored. Credentials
are passed through a temporary MySQL option file that is deleted after each run,
so the password never appears on the command line or in the process list.

**Retention.** Default 14 days (`BACKUP_RETENTION_DAYS`). Values outside the
approved 7–30 day range are rejected. The newest backup is never deleted, even
when the schedule has stalled, and pruning only touches files matching the backup
name pattern.

**Restore check.** `app:verify-backup [file]` verifies the checksum, loads the
backup into a scratch database, confirms the 20 critical tables exist, and reads
their row counts through the application's database layer. It then drops all
tables in the scratch database so no copy of customer data is left behind. The
scratch database name must end in `_restore_check` and cannot be the live
database. Live data is never written.

**Schedule.** Backup runs daily at 01:30 and the restore check runs Sundays at
03:00 (Africa/Dar_es_Salaam, both configurable). This requires the server cron
entry `* * * * * php artisan schedule:run`, which is part of Phase 23.

## Verification

- Full application suite: **249 tests, 2,089 assertions passed**, including 7
  new backup tests. They cover the compressed output and matching checksum, the
  password never appearing in command-line arguments and the credential file
  being deleted, failed and incomplete dumps, the MySQL-only requirement, the
  retention boundaries, keeping the newest backup, damaged checksums, unsafe
  restore targets, path traversal in file names, and the schedule.
- Real backup of the local practice `mfbms` database with MySQL 9.6 `mysqldump`:
  40 tables, completion marker present, and the checksum verified with `sha256sum`.
- Real restore check after the administrator created the scratch databases:
  `app:verify-backup` restored that backup into `mfbms_restore_check`. All 20
  critical tables were present and readable, including 12 audit logs, 3
  customers and 3 variants, and the scratch database was wiped afterwards.
- Full MySQL suite: **36 tests, 529 assertions passed**, including the new
  `BackupRestoreTest` round trip. That test backs up `mfbms_testing`, restores it,
  confirms the scratch database is empty afterwards and that live test data is
  untouched.
- Before this run, `ReferenceDataDatabaseTest` was still expecting the old colour
  `code` validation key from before the owner's name-only colour change (see
  VARIANT_CORRECTIONS.md). The test was corrected to expect `name` for colours.
  No application behaviour changed.

## Remaining work

1. Production: create `<DB_DATABASE>_restore_check` and grant the application
   account access to it, set `BACKUP_*` paths, confirm the cron entry, and run
   the first backup and restore check on the server.
2. **Off-site copy (TBD).** SECURITY.md §82 requires at least one copy outside the
   production server. The destination depends on the hosting choice (Phase 23).
   If it is object storage, enable encryption (§83).
3. **Failure alerts (TBD).** Failures are written to the error log only. Alerting
   an administrator needs production mail or another channel (SECURITY.md §105).
4. Document the full disaster-recovery procedure, including restoring into the
   live database, with the deployment runbook.
