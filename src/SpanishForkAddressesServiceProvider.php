<?php

namespace Cdburgess\SpanishForkAddresses;

use Cdburgess\SpanishForkAddresses\Console\ImportAddressPointsCommand;
use Cdburgess\SpanishForkAddresses\Contracts\AddressValidator;
use Cdburgess\SpanishForkAddresses\Support\GazetteerImporter;
use Illuminate\Support\ServiceProvider;

class SpanishForkAddressesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AddressValidator::class, fn ($app) => new SpanishForkValidator(
            $app['db']->connection()
        ));
        $this->app->singleton(GazetteerImporter::class, fn ($app) => new GazetteerImporter(
            $app['db']->connection()
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportAddressPointsCommand::class,
            ]);
            $this->publishes([
                __DIR__.'/../database/migrations/2026_10_02_000000_create_gis_addresses_table.php' => database_path('migrations/2026_10_02_000000_create_gis_addresses_table.php'),
            ], 'spanish-fork-addresses-migrations');
        }
    }
}
