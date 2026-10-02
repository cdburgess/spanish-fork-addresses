<?php

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\Contracts\AddressValidator;
use Cdburgess\SpanishForkAddresses\SpanishForkValidator;
use Cdburgess\SpanishForkAddresses\Tests\Support\AddressPointsCsv;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    AddressPointsCsv::import([
        AddressPointsCsv::row(),
        AddressPointsCsv::row([
            'Full Address' => '80 S 800 E', 'Address Number' => '80',
            'Street Name' => '800', 'Street Type' => '', 'Suffix Direction' => 'E',
        ]),
    ]);
});

it('matches 814 W PARK DR to the city West Park record', function () {
    $validator = new SpanishForkValidator(DB::connection());

    $address = new Address(
        primaryNumber: '814',
        preDirectional: 'W',
        streetName: 'PARK',
        suffix: 'DR',
        city: 'SPANISH FORK',
        state: 'UT',
    );

    $result = $validator->validate($address);

    expect($result->matched())->toBeTrue();
    expect($result->address()->streetName)->toContain('WEST PARK');
    expect($result->address()->primaryNumber)->toBe('814');
});

it('validates 814 westpark drive against Spanish Fork GIS', function () {
    $validator = app(AddressValidator::class);

    $address = Address::normalize([
        'street_line' => '814 westpark drive',
        'city' => 'Spanish Fork',
        'state' => 'UT',
    ]);

    $result = $validator->validate($address);

    expect($result->matched())->toBeTrue();
    expect($result->address()->primaryNumber)->toBe('814');
    expect($result->address()->streetName)->toBe('WEST PARK');
    expect($result->address()->suffix)->toBe('DR');
    expect($result->address()->preDirectional)->toBe('S');
    expect($result->address()->deliveryAddressLine())->toBe('814 S WEST PARK DR');
    expect($result->source())->toBe('spanish_fork_gis');
});

it('rejects an address with no delivery information', function () {
    $validator = app(AddressValidator::class);

    $validator->validate(new Address);
})->throws(InvalidArgumentException::class);

it('returns unmatched when the application table is empty', function () {
    DB::table('gis_addresses')->delete();
    $validator = app(AddressValidator::class);

    $address = new Address(
        primaryNumber: '814',
        preDirectional: 'W',
        streetName: 'PARK',
        suffix: 'DR',
        city: 'SPANISH FORK',
        state: 'UT',
    );

    $result = $validator->validate($address);

    expect($result->matched())->toBeFalse();
    expect($result->address())->toBe($address);
    expect($result->message())->toBe('No Spanish Fork GIS match found.');
});

it('validates 80 south 800 east', function () {
    $validator = app(AddressValidator::class);

    $address = Address::normalize([
        'street_line' => '80 south 800 east',
        'city' => 'Spanish Fork',
        'state' => 'UT',
    ]);

    $result = $validator->validate($address);

    expect($result->matched())->toBeTrue();
    expect($result->address()->primaryNumber)->toBe('80');
    expect($result->address()->preDirectional)->toBe('S');
    expect($result->address()->deliveryAddressLine())->toBe('80 S 800 E');
});

it('surfaces a missing migration as a database error', function () {
    Schema::drop('gis_addresses');

    app(AddressValidator::class)->validate(new Address(primaryNumber: '814', streetName: 'WEST PARK'));
})->throws(QueryException::class);

it('retains alternate records and ambiguity handling', function () {
    AddressPointsCsv::import([
        AddressPointsCsv::row(['Street Name' => 'PARK']),
        AddressPointsCsv::row(['Street Name' => 'PARK', 'Suffix Direction' => 'E']),
    ]);
    $address = new Address(primaryNumber: '814', streetName: 'PARK', suffix: 'DR');
    $result = app(AddressValidator::class)->validate($address);

    expect($result->matched())->toBeFalse();
    expect($result->address())->toBe($address);
    expect($result->message())->toBe('Multiple Spanish Fork GIS records are close matches.');
    expect($result->alternatives())->toHaveCount(2);
    expect($result->alternatives()[0])->toBeArray();
});
