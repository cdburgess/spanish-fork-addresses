<?php

namespace Cdburgess\SpanishForkAddresses;

use Cdburgess\SpanishForkAddresses\Console\ImportAddressPointsCommand;
use Cdburgess\SpanishForkAddresses\Contracts\AddressValidator;
use Illuminate\Support\ServiceProvider;

class SpanishForkAddressesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/spanish-fork-addresses.php',
            'spanish-fork-addresses'
        );

        $this->app->singleton(AddressValidator::class, function ($app) {
            $configured = $app['config']->get('spanish-fork-addresses.database');
            $fallback = __DIR__.'/../database/spanish-fork-addresses.sqlite';

            $path = (is_string($configured) && file_exists($configured))
                ? $configured
                : $fallback;

            return new SpanishForkValidator($path);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportAddressPointsCommand::class,
            ]);
            $this->publishes([
                __DIR__.'/../config/spanish-fork-addresses.php' => config_path('spanish-fork-addresses.php'),
            ], 'spanish-fork-addresses-config');

            $database = __DIR__.'/../database/spanish-fork-addresses.sqlite';

            if (file_exists($database)) {
                $this->publishes([
                    $database => database_path('spanish-fork-addresses.sqlite'),
                ], 'spanish-fork-addresses-db');
            }
        }
    }
}
