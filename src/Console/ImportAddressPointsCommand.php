<?php

namespace Cdburgess\SpanishForkAddresses\Console;

use Cdburgess\SpanishForkAddresses\Support\GazetteerImporter;
use Illuminate\Console\Command;

class ImportAddressPointsCommand extends Command
{
    protected $signature = 'spanish-fork:import
        {csv : Path to the Utah address points CSV}
        {--database= : Output SQLite path}';

    protected $description = 'Import Spanish Fork address-system CSV records into the gazetteer SQLite database';

    public function handle(GazetteerImporter $importer): int
    {
        $csv = $this->argument('csv');
        $database = $this->option('database')
            ?: config('spanish-fork-addresses.database')
                ?: database_path('spanish-fork-addresses.sqlite');

        try {
            $imported = $importer->import($csv, $database);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$imported} addresses into {$database}");

        return self::SUCCESS;
    }
}
