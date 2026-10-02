<?php

use Cdburgess\SpanishForkAddresses\SpanishForkAddressesServiceProvider;
use Cdburgess\SpanishForkAddresses\Tests\Support\AddressPointsCsv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

it('creates the GIS columns and lookup indexes in the application database', function () {
    expect(Schema::getColumnListing('gis_addresses'))->toBe([
        'id', 'house_number', 'pre_directional', 'street_name', 'suffix',
        'secondary_number', 'full_address', 'street_key', 'street_key_loose',
        'street_name_key', 'latitude', 'longitude', 'location_id', 'is_built', 'address_type',
    ]);
    $indexes = array_column(Schema::getIndexes('gis_addresses'), 'name');
    foreach (['house_number', 'street_key', 'street_key_loose', 'street_name_key'] as $column) {
        expect($indexes)->toContain("gis_addresses_{$column}_index");
    }
    expect(DB::table('gis_addresses')->count())->toBe(0);
});

it('rolls back only the GIS table', function () {
    Schema::create('addresses', function ($table) {
        $table->string('name');
    });
    DB::table('addresses')->insert(['name' => 'Application record']);
    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_02_000000_create_gis_addresses_table.php';
    $migration->down();

    expect(Schema::hasTable('gis_addresses'))->toBeFalse();
    expect(DB::table('addresses')->value('name'))->toBe('Application record');
});

it('publishes a migration that runs once and preserves data on later deployments', function () {
    $directory = sys_get_temp_dir().'/spanish-fork-migration-'.uniqid();
    mkdir($directory);
    $this->app->useDatabasePath($directory);
    (new SpanishForkAddressesServiceProvider($this->app))->boot();
    $path = $directory.'/migrations/2026_10_02_000000_create_gis_addresses_table.php';

    try {
        $this->artisan('vendor:publish', ['--tag' => 'spanish-fork-addresses-migrations'])
            ->assertSuccessful();
        expect(file_exists($path))->toBeTrue();
        Schema::drop('gis_addresses');

        $this->artisan('migrate', ['--path' => $path, '--realpath' => true, '--force' => true])
            ->assertSuccessful();
        AddressPointsCsv::import([AddressPointsCsv::row()]);
        $before = DB::table('gis_addresses')->get()->toArray();

        $this->artisan('migrate', ['--path' => $path, '--realpath' => true, '--force' => true])
            ->assertSuccessful();
        expect(DB::table('gis_addresses')->get()->toArray())->toEqual($before);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
        if (is_dir($directory.'/migrations')) {
            rmdir($directory.'/migrations');
        }
        rmdir($directory);
    }
});

it('does not automatically load the migration or publish SQLite files or config', function () {
    Schema::drop('gis_addresses');

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('gis_addresses'))->toBeFalse();
    expect(ServiceProvider::pathsToPublish(SpanishForkAddressesServiceProvider::class, 'spanish-fork-addresses-db'))->toBe([]);
    expect(ServiceProvider::pathsToPublish(SpanishForkAddressesServiceProvider::class, 'spanish-fork-addresses-config'))->toBe([]);
});
