<?php

namespace App\Console\Commands;

use App\Support\ProductionSecurity;
use Illuminate\Console\Command;

class CheckSecurity extends Command
{
    protected $signature = 'app:check-security';

    protected $description = 'Check production application security configuration without displaying secrets';

    public function handle(ProductionSecurity $security): int
    {
        $failures = $security->failures();
        foreach ($failures as $failure) {
            $this->error($failure);
        }
        if ($failures) {
            return self::FAILURE;
        }
        $this->info('Application configuration checks passed. No data was changed.');
        $this->warn('Deployment still requires verified TLS/proxy setup, public-only web root, private database, restricted logs and tested backups.');

        return self::SUCCESS;
    }
}
