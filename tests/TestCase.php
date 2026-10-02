<?php

namespace Cdburgess\SpanishForkAddresses\Tests;

use Cdburgess\SpanishForkAddresses\SpanishForkAddressesServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $migration = require __DIR__.'/../database/migrations/2026_10_02_000000_create_gis_addresses_table.php';
        $migration->up();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'application');
        $app['config']->set('database.connections.application', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [SpanishForkAddressesServiceProvider::class];
    }
}
