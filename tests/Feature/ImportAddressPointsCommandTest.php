<?php

use Cdburgess\SpanishForkAddresses\Tests\Support\AddressPointsCsv;
use Cdburgess\SpanishForkAddresses\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/spanish-fork-command-'.uniqid();
    mkdir($this->directory);
    $this->csv = $this->directory.'/addresses.csv';
    $this->database = $this->directory.'/addresses.sqlite';
    AddressPointsCsv::write($this->csv, [
        AddressPointsCsv::row(),
        AddressPointsCsv::row(['Address System' => 'SALEM']),
    ]);
});

afterEach(function () {
    foreach (glob($this->directory.'/*') as $path) {
        unlink($path);
    }

    rmdir($this->directory);
});

it('imports CSV via the registered command with an explicit output path', function () {
    $this->artisan('spanish-fork:import', ['csv' => $this->csv, '--database' => $this->database])
        ->expectsOutput("Imported 1 addresses into {$this->database}")
        ->assertSuccessful();

    expect((new PDO('sqlite:'.$this->database))->query('SELECT count(*) FROM addresses')->fetchColumn())->toBe(1);
});

it('uses the configured database when no output option is provided', function () {
    config(['spanish-fork-addresses.database' => $this->database]);

    $this->artisan('spanish-fork:import', ['csv' => $this->csv])
        ->expectsOutput("Imported 1 addresses into {$this->database}")
        ->assertSuccessful();
});

it('uses the application database fallback when config is empty', function () {
    $this->app->useDatabasePath($this->directory);
    config(['spanish-fork-addresses.database' => null]);
    $database = $this->directory.'/spanish-fork-addresses.sqlite';

    $this->artisan('spanish-fork:import', ['csv' => $this->csv])
        ->expectsOutput("Imported 1 addresses into {$database}")
        ->assertSuccessful();
});

it('returns a failure with a useful CSV error', function () {
    $missing = $this->directory.'/missing.csv';

    $this->artisan('spanish-fork:import', ['csv' => $missing, '--database' => $this->database])
        ->expectsOutput("CSV file is not readable: {$missing}")
        ->assertFailed();
});
