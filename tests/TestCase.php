<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Adithwidhiantara\Audit\AuditingServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            AuditingServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $migration = include __DIR__ . '/../src/Database/Migrations/2026_01_13_064903_create_audits_table.php';
        $migration->up();
    }
}
