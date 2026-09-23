<?php

namespace Tests\Support;

use RuntimeException;

class TestDatabaseGuard
{
    public static function check(string $environment, string $driver, string $database): void
    {
        if ($environment !== 'testing' || ! (($driver === 'sqlite' && $database === ':memory:')
            || ($driver === 'mysql' && $database === 'mfbms_testing'))) {
            throw new RuntimeException('Tests require APP_ENV=testing and SQLite :memory: or MySQL mfbms_testing. Clear cached configuration and check the test environment before retrying.');
        }
    }
}
