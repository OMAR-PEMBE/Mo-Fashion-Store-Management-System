<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckDatabase extends Command
{
    protected $signature = 'app:check-database';

    protected $description = 'Verify the configured MySQL connection without changing data';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('Configure DB_CONNECTION=mysql for the application database.');

            return self::FAILURE;
        }

        try {
            $version = DB::selectOne('SELECT VERSION() AS version')->version;

            if (str_contains(strtolower($version), 'mariadb') || version_compare($version, '8.0.0', '<')) {
                $this->error('The configured server does not meet the project requirement: MySQL 8+.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('Database connection failed. Check the server and your local .env credentials. See docs/LOCAL_SETUP.md.');

            return self::FAILURE;
        }

        $this->info('MySQL connection verified. No data was changed.');

        return self::SUCCESS;
    }
}
