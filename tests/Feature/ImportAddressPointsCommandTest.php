<?php

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\Contracts\AddressValidator;
use Cdburgess\SpanishForkAddresses\Tests\Support\AddressPointsCsv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/spanish-fork-command-'.uniqid();
    mkdir($this->directory);
    $this->csv = $this->directory.'/addresses.csv';
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

it('imports CSV into the default application database and validates imported records', function () {
    $validator = app(AddressValidator::class);
    $address = Address::normalize([
        'street_line' => '814 westpark drive', 'city' => 'Spanish Fork', 'state' => 'UT',
    ]);
    expect($validator->validate($address)->matched())->toBeFalse();

    $this->artisan('spanish-fork:import', ['csv' => $this->csv])
        ->expectsOutput('Imported 1 addresses into gis_addresses')
        ->assertSuccessful();

    expect(DB::connection()->getName())->toBe('application');
    expect(DB::table('gis_addresses')->count())->toBe(1);
    $result = $validator->validate($address);
    expect($result->matched())->toBeTrue();
    expect($result->address()->deliveryAddressLine())->toBe('814 S WEST PARK DR');
    expect($result->record())->toBeArray();
    expect($result->record()['location_id'])->toBe('SPANISH FORK | 814 S WEST PARK DR');
});

it('ignores legacy SQLite configuration and files', function () {
    $legacy = $this->directory.'/legacy.sqlite';
    $pdo = new PDO('sqlite:'.$legacy);
    $pdo->exec('CREATE TABLE addresses (house_number TEXT)');
    $pdo->exec("INSERT INTO addresses VALUES ('999')");
    config(['spanish-fork-addresses.database' => $legacy]);

    $this->artisan('spanish-fork:import', ['csv' => $this->csv])
        ->expectsOutput('Imported 1 addresses into gis_addresses')
        ->assertSuccessful();

    $result = app(AddressValidator::class)->validate(new Address(primaryNumber: '814', streetName: 'WEST PARK', suffix: 'DR'));
    expect($result->matched())->toBeTrue();
    expect(DB::table('gis_addresses')->value('house_number'))->toBe('814');
    expect($pdo->query('SELECT house_number FROM addresses')->fetchColumn())->toBe('999');
});

it('returns a failure with a useful CSV error', function () {
    $missing = $this->directory.'/missing.csv';

    $this->artisan('spanish-fork:import', ['csv' => $missing])
        ->expectsOutput("CSV file is not readable: {$missing}")
        ->assertFailed();
});

it('reports database setup errors when the migration has not been run', function () {
    Schema::drop('gis_addresses');

    $this->artisan('spanish-fork:import', ['csv' => $this->csv])
        ->expectsOutputToContain('gis_addresses')
        ->assertFailed();

    expect(Schema::hasTable('gis_addresses'))->toBeFalse();
});

it('returns failure and retains prior data after a malformed import', function () {
    $this->artisan('spanish-fork:import', ['csv' => $this->csv])->assertSuccessful();
    $before = DB::table('gis_addresses')->get()->toArray();
    AddressPointsCsv::write($this->csv, [
        AddressPointsCsv::row(['Address Number' => '900']),
        AddressPointsCsv::row(['x' => 'invalid']),
    ]);

    $this->artisan('spanish-fork:import', ['csv' => $this->csv])
        ->expectsOutputToContain('Invalid CSV x coordinate')
        ->assertFailed();

    expect(DB::table('gis_addresses')->get()->toArray())->toEqual($before);
});
