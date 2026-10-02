<?php

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\SpanishForkValidator;
use Cdburgess\SpanishForkAddresses\Support\CsvReader;
use Cdburgess\SpanishForkAddresses\Support\GazetteerImporter;
use Cdburgess\SpanishForkAddresses\Tests\Support\AddressPointsCsv;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->directory = sys_get_temp_dir().'/spanish-fork-csv-'.uniqid();
    mkdir($this->directory);
    $this->csv = $this->directory.'/addresses.csv';
    $this->database = $this->directory.'/addresses.sqlite';
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

    expect((new GazetteerImporter)->import($this->csv, $this->database))->toBe(1);

    $row = (new PDO('sqlite:'.$this->database))->query('SELECT * FROM addresses')->fetch(PDO::FETCH_ASSOC);

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
    (new GazetteerImporter)->import($this->csv, $this->database);

    $result = (new PDO('sqlite:'.$this->database))->query('SELECT * FROM addresses')->fetch(PDO::FETCH_ASSOC);

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
    (new GazetteerImporter)->import($this->csv, $this->database);
    $validator = new SpanishForkValidator($this->database);

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
    $row = (new PDO('sqlite:'.$this->database))->query("SELECT * FROM addresses WHERE house_number = '814'")->fetch(PDO::FETCH_ASSOC);
    expect($row['latitude'])->toBeNull();
    expect($row['longitude'])->toBeNull();
});

it('replaces old rows on repeated imports', function () {
    $importer = new GazetteerImporter;
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row()]);
    $importer->import($this->csv, $this->database);
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row(['Address Number' => '900'])]);

    expect($importer->import($this->csv, $this->database))->toBe(1);
    $pdo = new PDO('sqlite:'.$this->database);
    expect($pdo->query('SELECT count(*) FROM addresses')->fetchColumn())->toBe(1);
    expect($pdo->query('SELECT house_number FROM addresses')->fetchColumn())->toBe('900');
});

it('rolls back schema and rows when a CSV import fails', function (string $failure) {
    $importer = new GazetteerImporter;
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row()]);
    $importer->import($this->csv, $this->database);
    AddressPointsCsv::write($this->csv, [
        AddressPointsCsv::row(['Address Number' => '900']),
        AddressPointsCsv::row(['Address Number' => '901', 'x' => $failure === 'coordinate' ? 'invalid' : '0']),
    ]);

    if ($failure === 'width') {
        file_put_contents($this->csv, "SPANISH FORK,too,few\n", FILE_APPEND);
    } elseif ($failure === 'header') {
        file_put_contents($this->csv, "City\nSPANISH FORK\n");
    }

    expect(fn () => $importer->import($this->csv, $this->database))->toThrow(InvalidArgumentException::class);
    $pdo = new PDO('sqlite:'.$this->database);
    expect($pdo->query('SELECT count(*) FROM addresses')->fetchColumn())->toBe(1);
    expect($pdo->query('SELECT house_number FROM addresses')->fetchColumn())->toBe('814');
})->with(['coordinate', 'width', 'header']);

it('imports CSV through the standalone entry point and reports errors', function () {
    AddressPointsCsv::write($this->csv, [AddressPointsCsv::row()]);
    $script = dirname(__DIR__, 2).'/bin/import-address-points.php';
    $process = new Process([PHP_BINARY, $script, $this->csv, $this->database]);
    $process->run();

    expect($process->getExitCode())->toBe(0);
    expect($process->getOutput())->toContain('Imported 1 addresses');

    $process = new Process([PHP_BINARY, $script, $this->directory.'/missing.csv', $this->database]);
    $process->run();
    expect($process->getExitCode())->toBe(1);
    expect($process->getErrorOutput())->toContain('CSV file is not readable');

    $process = new Process([PHP_BINARY, $script, '--help']);
    $process->run();
    expect($process->getExitCode())->toBe(0);
    expect($process->getOutput())->toContain('UtahAddressPoints.csv');
});
