<?php

namespace Tests\MySql;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Throwable;

class BackupRestoreTest extends TestCase
{
    public function test_real_backup_restores_into_scratch_database_and_is_wiped(): void
    {
        $this->assertSame('mfbms_testing', DB::connection()->getDatabaseName());
        $this->assertSame('mfbms_testing_restore_check', config('backup.restore.database'));
        try {
            DB::connection()->getPdo()->exec('SELECT 1 FROM mfbms_testing_restore_check.dummy_probe');
        } catch (Throwable $e) {
            // 1146 (missing table) proves access; 1044/1049 means the scratch database is not set up.
            if (! str_contains($e->getMessage(), '1146')) {
                $this->markTestSkipped('Run the restore-check statements in database/setup-local.sql to enable this test.');
            }
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mfbms-backup-mysql-'.uniqid();
        config(['backup.path' => $dir]);
        // mysqldump reads committed data on its own connection, so this row is committed and removed afterwards.
        $supplier = Supplier::create(['name' => 'Backup probe', 'supplier_code' => 'BACKUP-PROBE-'.uniqid(), 'is_active' => true]);
        try {
            $this->artisan('app:backup-database', ['--no-prune' => true])->assertSuccessful();
            $name = basename(File::files($dir)[0]->getFilename());

            $this->artisan('app:verify-backup', ['file' => $name])->assertSuccessful();

            $this->assertSame(0, (int) DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = 'mfbms_testing_restore_check'")->n);
            $this->assertTrue(Supplier::whereKey($supplier->id)->exists(), 'Live test data must be untouched by the restore check.');
        } finally {
            $supplier->forceDelete();
            File::deleteDirectory($dir);
        }
    }
}
