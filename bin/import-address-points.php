#!/usr/bin/env php
<?php

declare(strict_types=1);

use Cdburgess\SpanishForkAddresses\Support\GazetteerImporter;

$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';

if (! file_exists($autoload)) {
    fwrite(STDERR, "Run composer install in the package root first.\n");
    exit(1);
}

require $autoload;

$csv = $argv[1] ?? null;
$database = $argv[2] ?? $root.'/database/spanish-fork-addresses.sqlite';

if ($csv === null || $csv === '-h' || $csv === '--help') {
    fwrite(STDOUT, "Usage: php bin/import-address-points.php /path/to/UtahAddressPoints.csv [output.sqlite]\n");
    exit($csv === null ? 1 : 0);
}

try {
    $imported = (new GazetteerImporter)->import($csv, $database);
    fwrite(STDOUT, "Imported {$imported} addresses into {$database}\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
