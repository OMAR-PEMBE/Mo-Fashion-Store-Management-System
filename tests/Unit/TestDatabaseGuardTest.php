<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

class TestDatabaseGuardTest extends TestCase
{
    public static function configurations(): array
    {
        return [
            'memory' => ['testing', 'sqlite', ':memory:', true],
            'dedicated mysql' => ['testing', 'mysql', 'mfbms_testing', true],
            'development database' => ['testing', 'mysql', 'mfbms', false],
            'production environment' => ['production', 'mysql', 'mfbms_testing', false],
            'persistent sqlite' => ['testing', 'sqlite', 'database/database.sqlite', false],
            'other driver' => ['testing', 'pgsql', 'mfbms_testing', false],
        ];
    }

    #[DataProvider('configurations')]
    public function test_only_isolated_testing_databases_are_allowed(string $environment, string $driver, string $database, bool $allowed): void
    {
        if (! $allowed) {
            $this->expectException(RuntimeException::class);
        }
        TestDatabaseGuard::check($environment, $driver, $database);
        $this->assertTrue($allowed);
    }
}
