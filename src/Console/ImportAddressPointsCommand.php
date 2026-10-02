<?php

namespace Cdburgess\SpanishForkAddresses\Console;

use Cdburgess\SpanishForkAddresses\Support\GazetteerImporter;
use Illuminate\Console\Command;

class ImportAddressPointsCommand extends Command
{
    protected $signature = 'spanish-fork:import
        {csv : Path to the Utah address points CSV}';

    protected $description = 'Import Spanish Fork address-system CSV records into gis_addresses on the application database';

    public function handle(GazetteerImporter $importer): int
    {
        $csv = $this->argument('csv');
        try {
            $imported = $importer->import($csv);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$imported} addresses into gis_addresses");

        return self::SUCCESS;
    }
}
