<?php

namespace Cdburgess\SpanishForkAddresses\Console;

use Cdburgess\SpanishForkAddresses\Support\GazetteerImporter;
use Illuminate\Console\Command;

class ImportAddressPointsCommand extends Command
{
    protected $signature = 'spanish-fork:import
        {dbf : Path to AddressPoints.dbf}
        {--database= : Output SQLite path}';

    protected $description = 'Import Spanish Fork AddressPoints DBF into the gazetteer SQLite database';

    public function handle(GazetteerImporter $importer): int
    {
        $dbf = $this->argument('dbf');
        $database = $this->option('database')
            ?: config('spanish-fork-addresses.database')
                ?: database_path('spanish-fork-addresses.sqlite');

        try {
            $imported = $importer->import($dbf, $database);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Imported {$imported} addresses into {$database}");

        return self::SUCCESS;
    }
}