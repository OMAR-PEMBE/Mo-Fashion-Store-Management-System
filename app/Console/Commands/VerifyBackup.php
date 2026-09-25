<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyBackup extends Command
{
    protected $signature = 'app:verify-backup {file? : Backup file name; defaults to the newest backup}';

    protected $description = 'Restore a backup into the scratch restore-check database, confirm critical data is readable, then wipe it';

    public function handle(DatabaseBackupService $backups): int
    {
        $name = $this->argument('file') ?? array_key_first($backups->backups());
        try {
            $counts = $backups->verify($name);
        } catch (Throwable $e) {
            Log::error('Backup restore check failed.', ['file' => $name, 'error' => $e->getMessage()]);
            $this->error('Backup restore check failed: '.$e->getMessage());

            return self::FAILURE;
        }
        Log::info('Backup restore check passed.', ['file' => $name, 'rows' => $counts]);
        $this->table(['Table', 'Restored rows'], collect($counts)->map(fn ($rows, $table) => [$table, $rows])->values()->all());
        $this->info('Restore check passed for '.$name.'. The scratch database has been wiped; live data was not changed.');

        return self::SUCCESS;
    }
}
