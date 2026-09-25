<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseBackupService
{
    public const FILE_PATTERN = '/^mfbms-(\d{8}-\d{6})\.sql\.gz$/';

    // Restored data must include these for the application to operate.
    public const CRITICAL_TABLES = ['migrations', 'users', 'roles', 'permissions', 'system_settings', 'products', 'product_variants',
        'inventories', 'inventory_movements', 'suppliers', 'purchases', 'customers', 'sales', 'sale_items', 'orders',
        'returns', 'refunds', 'exchanges', 'expenses', 'audit_logs'];

    private const DUMP_TRAILER = '-- Dump completed';

    private const RESTORE_CONNECTION = 'backup_restore';

    public function directory(): string
    {
        return rtrim((string) config('backup.path'), '\\/');
    }

    public function create(): string
    {
        $db = $this->liveConnection();
        $dir = $this->directory();
        File::ensureDirectoryExists($dir, 0700);
        $name = 'mfbms-'.now()->format('Ymd-His').'.sql.gz';
        $final = $dir.DIRECTORY_SEPARATOR.$name;
        if (File::exists($final)) {
            throw new RuntimeException('A backup with this timestamp already exists.');
        }
        $sql = $final.'.sql.partial';
        $gz = $final.'.partial';
        $credentials = $this->credentialsFile($db, $db['username'], $db['password']);

        try {
            $result = Process::forever()->run([config('backup.mysqldump_binary'), '--defaults-extra-file='.$credentials,
                '--single-transaction', '--quick', '--no-tablespaces', '--set-gtid-purged=OFF', '--triggers', '--hex-blob',
                '--default-character-set=utf8mb4', '--result-file='.$sql, $db['database']]);
            if ($result->failed()) {
                throw new RuntimeException('mysqldump failed: '.trim($result->errorOutput() ?: 'exit code '.$result->exitCode()));
            }
            if (! File::exists($sql) || ! str_contains($this->tail($sql), self::DUMP_TRAILER)) {
                throw new RuntimeException('mysqldump produced an incomplete dump.');
            }
            $this->compress($sql, $gz);
            File::move($gz, $final);
            File::put($final.'.sha256', hash_file('sha256', $final).'  '.$name."\n");
        } finally {
            File::delete([$credentials, $sql, $gz]);
        }

        return $final;
    }

    /** @return list<string> deleted backup names */
    public function prune(): array
    {
        $days = (int) config('backup.retention_days');
        if ($days < 7 || $days > 30) {
            throw new RuntimeException('BACKUP_RETENTION_DAYS must be between 7 and 30.');
        }
        $cutoff = now()->subDays($days);
        $deleted = [];
        // The newest backup is always kept, even if the schedule has stalled.
        foreach (array_slice($this->backups(), 1) as $name => $takenAt) {
            if ($takenAt->lt($cutoff)) {
                File::delete([$this->directory().DIRECTORY_SEPARATOR.$name, $this->directory().DIRECTORY_SEPARATOR.$name.'.sha256']);
                $deleted[] = $name;
            }
        }

        return $deleted;
    }

    /** @return array<string, Carbon> backup name => time taken, newest first */
    public function backups(): array
    {
        $backups = [];
        foreach (File::isDirectory($this->directory()) ? File::files($this->directory()) : [] as $file) {
            if (preg_match(self::FILE_PATTERN, $file->getFilename(), $match)) {
                $backups[$file->getFilename()] = Carbon::createFromFormat('Ymd-His', $match[1]);
            }
        }
        uasort($backups, fn (Carbon $a, Carbon $b) => $b <=> $a);

        return $backups;
    }

    /** @return array<string, int> critical table => restored row count */
    public function verify(?string $name = null): array
    {
        $name ??= array_key_first($this->backups()) ?? throw new RuntimeException('No backups found in '.$this->directory().'.');
        if (! preg_match(self::FILE_PATTERN, $name)) {
            throw new RuntimeException('Not a recognised backup file name.');
        }
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        $this->assertChecksum($path, $name);
        $db = $this->liveConnection();
        $restore = $this->restoreDatabase($db);
        config(['database.connections.'.self::RESTORE_CONNECTION => ['database' => $restore, 'username' => config('backup.restore.username'),
            'password' => config('backup.restore.password')] + $db]);
        DB::purge(self::RESTORE_CONNECTION);
        $schema = Schema::connection(self::RESTORE_CONNECTION);
        $sql = $path.'.restore.partial';
        $credentials = $this->credentialsFile($db, config('backup.restore.username'), config('backup.restore.password'));

        try {
            $schema->dropAllTables();
            $this->decompress($path, $sql);
            $input = fopen($sql, 'rb');
            try {
                $result = Process::forever()->input($input)->run([config('backup.mysql_binary'), '--defaults-extra-file='.$credentials,
                    '--default-character-set=utf8mb4', $restore]);
            } finally {
                fclose($input);
            }
            if ($result->failed()) {
                throw new RuntimeException('Restore import failed: '.trim($result->errorOutput() ?: 'exit code '.$result->exitCode()));
            }
            $missing = array_diff(self::CRITICAL_TABLES, $schema->getTableListing($restore, false));
            if ($missing) {
                throw new RuntimeException('Restored backup is missing critical tables: '.implode(', ', $missing).'.');
            }

            return collect(self::CRITICAL_TABLES)->mapWithKeys(fn ($table) => [$table => DB::connection(self::RESTORE_CONNECTION)->table($table)->count()])->all();
        } finally {
            File::delete([$credentials, $sql]);
            // Never leave a readable copy of business data in the scratch database.
            rescue(fn () => $schema->dropAllTables(), report: false);
            DB::purge(self::RESTORE_CONNECTION);
        }
    }

    private function liveConnection(): array
    {
        $db = config('database.connections.'.config('database.default'));
        if (($db['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('Database backups require the MySQL connection (DB_CONNECTION=mysql).');
        }

        return $db;
    }

    private function restoreDatabase(array $live): string
    {
        $restore = (string) config('backup.restore.database');
        if (! preg_match('/^[A-Za-z0-9_]+_restore_check$/', $restore) || $restore === $live['database']) {
            throw new RuntimeException('BACKUP_RESTORE_DATABASE must be a separate scratch database whose name ends in _restore_check.');
        }

        return $restore;
    }

    private function assertChecksum(string $path, string $name): void
    {
        if (! File::exists($path) || ! File::exists($path.'.sha256')) {
            throw new RuntimeException('Backup or its checksum file is missing: '.$name.'.');
        }
        if (strtok(File::get($path.'.sha256'), ' ') !== hash_file('sha256', $path)) {
            throw new RuntimeException('Backup checksum does not match; the file is damaged or was changed: '.$name.'.');
        }
    }

    // Keeps the password out of the process list; the file lives only for one command.
    private function credentialsFile(array $db, ?string $username, ?string $password): string
    {
        $quote = fn (?string $value) => '"'.addcslashes((string) $value, '\\"').'"';
        $lines = ['[client]', 'user='.$quote($username), 'password='.$quote($password)];
        $lines[] = ! empty($db['unix_socket']) ? 'socket='.$quote($db['unix_socket']) : 'host='.$quote($db['host']);
        $lines[] = 'port='.(int) $db['port'];
        $file = tempnam(sys_get_temp_dir(), 'mfbms-db-');
        chmod($file, 0600);
        File::put($file, implode("\n", $lines)."\n");

        return $file;
    }

    private function tail(string $path): string
    {
        $handle = fopen($path, 'rb');
        fseek($handle, -min(512, filesize($path)), SEEK_END);
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        return $tail;
    }

    private function compress(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = gzopen($to, 'wb6');
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1048576));
        }
        fclose($in);
        gzclose($out);
    }

    private function decompress(string $from, string $to): void
    {
        $in = gzopen($from, 'rb');
        $out = fopen($to, 'wb');
        while (! gzeof($in)) {
            fwrite($out, (string) gzread($in, 1048576));
        }
        gzclose($in);
        fclose($out);
    }
}
