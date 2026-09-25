<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database {--no-prune : Keep backups older than the retention period}';

    protected $description = 'Write a compressed, checksummed MySQL backup to private storage and remove expired backups';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $path = $backups->create();
            $deleted = $this->option('no-prune') ? [] : $backups->prune();
        } catch (Throwable $e) {
            Log::error('Database backup failed.', ['error' => $e->getMessage()]);
            $this->error('Database backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
        Log::info('Database backup created.', ['file' => basename($path), 'bytes' => filesize($path), 'expired_removed' => $deleted]);
        $this->info('Backup created: '.$path);
        foreach ($deleted as $name) {
            $this->line('Removed expired backup: '.$name);
        }
        $this->warn('Keep an off-site copy: backups on this server are lost if the server is lost.');

        return self::SUCCESS;
    }
}
