<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mfbms-backup-test-'.uniqid();
        config(['backup.path' => $this->dir, 'backup.retention_days' => 14, 'backup.restore.database' => 'mfbms_restore_check',
            // Backups never touch the SQLite test database; these values only reach the faked client.
            'database.default' => 'mysql', 'database.connections.mysql.database' => 'mfbms', 'database.connections.mysql.password' => 'secret-pa"ss\\word']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function fakeDump(string $contents, int $exitCode = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($contents, $exitCode) {
            $result = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--result-file='));
            if ($exitCode === 0) {
                File::put(substr($result, 14), $contents);
            }

            return Process::result(errorOutput: $exitCode ? 'Access denied' : '', exitCode: $exitCode);
        });
    }

    private function backupFiles(): array
    {
        return collect(File::files($this->dir))->map->getFilename()->sort()->values()->all();
    }

    public function test_backup_is_compressed_checksummed_and_keeps_password_off_the_command_line(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 26)->setTime(1, 30, 5));
        $this->fakeDump("CREATE TABLE `users` (id int);\n-- Dump completed on 2026-09-26  1:30:05\n");

        $this->artisan('app:backup-database')->assertSuccessful();

        $this->assertSame(['mfbms-20260926-013005.sql.gz', 'mfbms-20260926-013005.sql.gz.sha256'], $this->backupFiles());
        $path = $this->dir.'/mfbms-20260926-013005.sql.gz';
        $this->assertStringContainsString('CREATE TABLE `users`', gzdecode(File::get($path)));
        $this->assertSame(hash_file('sha256', $path).'  mfbms-20260926-013005.sql.gz'."\n", File::get($path.'.sha256'));
        Process::assertRan(function (PendingProcess $process) {
            $args = implode(' ', $process->command);
            $credentials = substr(collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--defaults-extra-file=')), 22);

            return str_contains($args, '--single-transaction') && str_contains($args, '--no-tablespaces')
                && end($process->command) === 'mfbms' && ! str_contains($args, 'secret')
                && ! File::exists($credentials);
        });
    }

    public function test_failed_or_incomplete_dumps_leave_no_backup_and_are_logged(): void
    {
        Log::spy();
        $this->fakeDump('', 2);
        $this->artisan('app:backup-database')->expectsOutputToContain('Access denied')->assertFailed();

        $this->fakeDump("CREATE TABLE `users` (id int);\n");
        $this->artisan('app:backup-database')->expectsOutputToContain('incomplete dump')->assertFailed();

        $this->assertSame([], $this->backupFiles());
        Log::shouldHaveReceived('error')->twice()->withArgs(fn ($message) => $message === 'Database backup failed.');
    }

    public function test_backup_requires_the_mysql_connection(): void
    {
        config(['database.default' => 'sqlite']);
        Process::fake();

        $this->artisan('app:backup-database')->expectsOutputToContain('DB_CONNECTION=mysql')->assertFailed();

        Process::assertNothingRan();
    }

    public function test_prune_removes_only_expired_backups_and_always_keeps_the_newest(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 26)->setTime(12, 0));
        File::ensureDirectoryExists($this->dir);
        foreach (['20260901-013000', '20260911-013000', '20260920-013000', '20260926-013000'] as $stamp) {
            File::put($this->dir."/mfbms-$stamp.sql.gz", 'x');
            File::put($this->dir."/mfbms-$stamp.sql.gz.sha256", 'x');
        }
        File::put($this->dir.'/unrelated.sql.gz', 'x');
        $service = app(DatabaseBackupService::class);

        $this->assertSame(['mfbms-20260911-013000.sql.gz', 'mfbms-20260901-013000.sql.gz'], $service->prune());
        $this->assertSame(['mfbms-20260920-013000.sql.gz', 'mfbms-20260920-013000.sql.gz.sha256', 'mfbms-20260926-013000.sql.gz',
            'mfbms-20260926-013000.sql.gz.sha256', 'unrelated.sql.gz'], $this->backupFiles());

        // A stalled schedule must never delete the only remaining backups.
        $this->travel(60)->days();
        $this->assertSame(['mfbms-20260920-013000.sql.gz'], $service->prune());
        $this->assertArrayHasKey('mfbms-20260926-013000.sql.gz', $service->backups());
    }

    public function test_retention_outside_the_approved_range_is_rejected(): void
    {
        foreach ([6, 31] as $days) {
            config(['backup.retention_days' => $days]);
            $this->travel(1)->seconds();
            $this->fakeDump("-- Dump completed\n");
            $this->artisan('app:backup-database')->expectsOutputToContain('between 7 and 30')->assertFailed();
        }
    }

    public function test_restore_check_refuses_damaged_backups_and_unsafe_target_databases(): void
    {
        Process::fake();
        File::ensureDirectoryExists($this->dir);
        $name = 'mfbms-20260926-013000.sql.gz';
        File::put($this->dir.'/'.$name, gzencode("-- Dump completed\n"));
        File::put($this->dir.'/'.$name.'.sha256', str_repeat('0', 64).'  '.$name."\n");
        $this->artisan('app:verify-backup')->expectsOutputToContain('checksum does not match')->assertFailed();

        File::put($this->dir.'/'.$name.'.sha256', hash_file('sha256', $this->dir.'/'.$name).'  '.$name."\n");
        foreach (['mfbms', 'mfbms_testing', 'shop_restore_check; DROP DATABASE mfbms'] as $target) {
            config(['backup.restore.database' => $target]);
            $this->artisan('app:verify-backup', ['file' => $name])->expectsOutputToContain('_restore_check')->assertFailed();
        }
        $this->artisan('app:verify-backup', ['file' => '../.env'])->expectsOutputToContain('Not a recognised backup')->assertFailed();

        Process::assertNothingRan();
    }

    public function test_backup_and_weekly_restore_check_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())->mapWithKeys(fn ($event) => [trim(strstr($event->command, 'app:')) => $event->expression]);

        $this->assertSame('30 1 * * *', $events['app:backup-database']);
        $this->assertSame('0 3 * * 0', $events['app:verify-backup']);
    }
}
