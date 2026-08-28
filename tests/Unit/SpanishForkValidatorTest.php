<?php

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\SpanishForkValidator;

$db = dirname(__DIR__, 2).'/database/spanish-fork-addresses.sqlite';

it('matches 814 W PARK DR to the city West Park record', function () use ($db) {
    $validator = new SpanishForkValidator($db);

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
})->skip(
    fn () => ! file_exists($db),
    'Gazetteer sqlite has not been generated yet.'
);

it('validates 814 westpark drive against Spanish Fork GIS', function () use ($db) {
    $validator = new SpanishForkValidator($db);

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
})->skip(
    fn () => ! file_exists($db),
    'Gazetteer sqlite has not been generated yet.'
);

it('rejects an address with no delivery information', function () {
    $validator = new SpanishForkValidator('/tmp/missing.sqlite');

    $validator->validate(new Address);
})->throws(InvalidArgumentException::class);

it('returns unmatched when the gazetteer file is missing', function () {
    $validator = new SpanishForkValidator('/tmp/does-not-exist.sqlite');

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
    expect($result->message())->toContain('not found');
});

it('validates 80 south 800 east', function () {
    $db = dirname(__DIR__, 2).'/database/spanish-fork-addresses.sqlite';
    $validator = new SpanishForkValidator($db);

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
})->skip(
    fn () => ! file_exists(dirname(__DIR__, 2).'/database/spanish-fork-addresses.sqlite')
);
