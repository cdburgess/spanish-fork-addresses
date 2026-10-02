<?php

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\SpanishForkValidator;
use Cdburgess\SpanishForkAddresses\Support\CsvReader;
use Cdburgess\SpanishForkAddresses\Support\GazetteerImporter;
use Cdburgess\SpanishForkAddresses\Tests\Support\AddressPointsCsv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/spanish-fork-csv-'.uniqid();
    mkdir($this->directory);
    $this->csv = $this->directory.'/addresses.csv';
});

afterEach(function () {
    foreach (glob($this->directory.'/*') as $path) {
        unlink($path);
    }

    rmdir($this->directory);
});

it('streams BOM-aware CSV with trimmed headers and quoted multiline values', function () {
    AddressPointsCsv::write($this->csv, [
        [' Address System ' => ' spanish fork ', ' Full Address ' => "814 S \"WEST\", PARK\nDR"],
    ], bom: true);
    file_put_contents($this->csv, "\n", FILE_APPEND);

    $records = iterator_to_array((new CsvReader($this->csv, ['Address System']))->records());

    expect($records)->toBe([
        ['Address System' => 'spanish fork', 'Full Address' => "814 S \"WEST\", PARK\nDR"],
    ]);
});

it('rejects unreadable CSV paths', function () {
    new CsvReader($this->csv);
})->throws(InvalidArgumentException::class, 'CSV file is not readable');

it('rejects invalid CSV headers and record widths', function (string $content, string $message) {
    file_put_contents($this->csv, $content);

    expect(fn () => iterator_to_array((new CsvReader($this->csv, ['Address System']))->records()))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'empty' => ['', 'CSV header is missing'],
    'missing header' => ["City\nSPANISH FORK\n", 'CSV is missing required headers'],
    'duplicate header' => ["Address System,Address System\n", 'CSV headers must be nonempty and unique'],
    'empty header' => ["Address System,\n", 'CSV headers must be nonempty and unique'],
    'short row' => ["Address System,Full Address\nSPANISH FORK\n", 'CSV record 2 has an unexpected number of fields'],
    'long row' => ["Address System\nSPANISH FORK,extra\n", 'CSV record 2 has an unexpected number of fields'],
]);

it('filters by address system regardless of city and maps CSV fields', function () {
    AddressPointsCsv::write($this->csv, [
        AddressPointsCsv::row([
            'Address System' => ' spanish fork ',
            'City' => 'SALEM',
            'Address Number Suffix' => 'a',
            'Street Type' => 'Drive',
            'Unit ID' => 'b-2',
        ]),
        AddressPointsCsv::row(['Address System' => 'SALEM', 'City' => 'SPANISH FORK', 'x' => 'invalid']),
        AddressPointsCsv::row(['Address System' => '', 'City' => 'SPANISH FORK']),
        AddressPointsCsv::row(['Full Address' => '', 'Street Name' => '']),
    ], bom: true);

    expect(app(GazetteerImporter::class)->import($this->csv))->toBe(1);

    $row = (array) DB::table('gis_addresses')->first();

    expect($row)->toMatchArray([
        'house_number' => '814A',
        'pre_directional' => 'S',
        'street_name' => 'WEST PARK',
        'suffix' => 'DR',
        'secondary_number' => 'B-2',
        'full_address' => '814 S WEST PARK DR',
        'street_key' => 'SWESTPARKDR',
        'street_key_loose' => 'PARKDR',
        'street_name_key' => 'WESTPARKDR',
        'location_id' => 'SPANISH FORK | 814 S WEST PARK DR',
        'is_built' => 'Yes',
        'address_type' => 'Residential',
    ]);
    expect($row['latitude'])->toEqualWithDelta(40.0951951258, 0.0000001);
    expect($row['longitude'])->toEqualWithDelta(-111.6540305424, 0.0000001);
});

it('keeps absent metadata null and converts the coordinate origin', function () {
    $row = AddressPointsCsv::row(['x' => '0', 'y' => '0']);
    unset($row['Utah Address Point ID'], $row['Structure'], $row['Point Type'], $row['Unit ID']);
    AddressPointsCsv::write($this->csv, [$row]);
    app(GazetteerImporter::class)->import($this->csv);

    $result = (array) DB::table('gis_addresses')->first();

    expect($result)->toMatchArray([
        'latitude' => 0.0, 'longitude' => 0.0, 'location_id' => null,
        'is_built' => null, 'address_type' => null, 'secondary_number' => null,
    ]);
});

it('preserves grid directionals and West Park matching without coordinates', function () {
    AddressPointsCsv::write($this->csv, [
        AddressPointsCsv::row(['x' => '', 'y' => '']),
        AddressPointsCsv::row([
            'Full Address' => '80 S 800 E', 'Address Number' => '80',
            'Street Name' => '800', 'Street Type' => '', 'Suffix Direction' => 'E',
        ]),
    ]);
    app(GazetteerImporter::class)->import($this->csv);
    $validator = new SpanishForkValidator(DB::connection());

    foreach (['814 westpark drive', '814 W PARK DR', '80 south 800 east'] as $input) {
        $result = $validator->validate(Address::normalize([
            'street_line' => $input, 'city' => 'Spanish Fork', 'state' => 'UT',
        ]));
        expect($result->matched())->toBeTrue();
        expect($result->address()->deliveryAddressLine())->toBe(
            str_starts_with($input, '814') ? '814 S WEST PARK DR' : '80 S 800 E'
        );
    }

    expect($validator->validate(Address::normalize([
        'street_line' => '814 Main St', 'city' => 'Spanish Fork', 'state' => 'UT',
    ]))->matched())->toBeFalse();
    $row = (array) DB::table('gis_addresses')->where('house_number', '814')->first();
    expect($row['latitude'])->toBeNull();
    expect($row['longitude'])->toBeNull();
});

it('replaces old rows on repeated imports', function () {
    $importer = app(GazetteerImporter::class);
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row()]);
    $importer->import($this->csv);
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row(['Address Number' => '900'])]);

    expect($importer->import($this->csv))->toBe(1);
    expect(DB::table('gis_addresses')->count())->toBe(1);
    expect(DB::table('gis_addresses')->value('house_number'))->toBe('900');
});

it('preserves schema and rolls back rows when a CSV import fails', function (string $failure) {
    $importer = app(GazetteerImporter::class);
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row()]);
    $importer->import($this->csv);
    $before = DB::table('gis_addresses')->get()->toArray();
    $indexes = Schema::getIndexes('gis_addresses');
    AddressPointsCsv::write($this->csv, [
        AddressPointsCsv::row(['Address Number' => '900']),
        AddressPointsCsv::row(['Address Number' => '901', 'x' => $failure === 'coordinate' ? 'invalid' : '0']),
    ]);

    if ($failure === 'width') {
        file_put_contents($this->csv, "SPANISH FORK,too,few\n", FILE_APPEND);
    } elseif ($failure === 'header') {
        file_put_contents($this->csv, "City\nSPANISH FORK\n");
    }

    expect(fn () => $importer->import($this->csv))->toThrow(InvalidArgumentException::class);
    expect(DB::table('gis_addresses')->get()->toArray())->toEqual($before);
    expect(Schema::getIndexes('gis_addresses'))->toBe($indexes);
})->with(['coordinate', 'width', 'header']);

it('preserves unrelated application tables and lookup indexes during replacement', function () {
    Schema::create('addresses', function ($table) {
        $table->string('name');
    });
    DB::table('addresses')->insert(['name' => 'Application record']);
    $indexes = Schema::getIndexes('gis_addresses');
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row()]);
    app(GazetteerImporter::class)->import($this->csv);
    app(GazetteerImporter::class)->import($this->csv);

    expect(DB::table('addresses')->value('name'))->toBe('Application record');
    expect(Schema::getIndexes('gis_addresses'))->toBe($indexes);
});

it('replaces the table with no rows when an export has no eligible addresses', function () {
    $importer = app(GazetteerImporter::class);
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row()]);
    $importer->import($this->csv);
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row(['Address System' => 'SALEM'])]);

    expect($importer->import($this->csv))->toBe(0);
    expect(DB::table('gis_addresses')->count())->toBe(0);
});
