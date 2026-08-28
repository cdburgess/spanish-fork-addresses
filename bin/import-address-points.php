#!/usr/bin/env php
<?php

declare(strict_types=1);

use Cdburgess\SpanishForkAddresses\Support\GazetteerImporter;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (! file_exists($autoload)) {
    fwrite(STDERR, "Run composer install in the package root first.\n");
    exit(1);
}

require $autoload;

$dbf = $argv[1] ?? null;
$database = $argv[2] ?? $root . '/database/spanish-fork-addresses.sqlite';

if ($dbf === null || $dbf === '-h' || $dbf === '--help') {
    fwrite(STDOUT, "Usage: php bin/import-address-points.php /path/to/AddressPoints.dbf [output.sqlite]\n");
    exit($dbf === null ? 1 : 0);
}

try {
    $imported = new GazetteerImporter()->import($dbf, $database);
    fwrite(STDOUT, "Imported {$imported} addresses into {$database}\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}