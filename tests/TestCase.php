<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Run before RefreshDatabase or any test fixture can write to the database.
        $connection = $app['db']->connection();
        TestDatabaseGuard::check($app->environment(), $connection->getDriverName(), $connection->getDatabaseName());

        return $app;
    }
}
