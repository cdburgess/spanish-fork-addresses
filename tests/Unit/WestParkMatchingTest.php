<?php

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\SpanishForkValidator;

$db = dirname(__DIR__, 2).'/database/spanish-fork-addresses.sqlite';

function spanishForkValidator(): SpanishForkValidator
{
    $db = dirname(__DIR__, 2).'/database/spanish-fork-addresses.sqlite';

    return new SpanishForkValidator($db);
}

function normalizeSpanishFork(string $streetLine): Address
{
    return Address::normalize([
        'street_line' => $streetLine,
        'city' => 'Spanish Fork',
        'state' => 'UT',
    ]);
}

it('validates 814 westpark drive', function () {
    $result = spanishForkValidator()->validate(
        normalizeSpanishFork('814 westpark drive')
    );

    expect($result->matched())->toBeTrue();
    expect($result->address()->deliveryAddressLine())->toBe('814 S WEST PARK DR');
})->skip(fn () => ! file_exists(dirname(__DIR__, 2).'/database/spanish-fork-addresses.sqlite'));

it('validates 814 West Park Dr', function () {
    $result = spanishForkValidator()->validate(
        normalizeSpanishFork('814 West Park Dr')
    );

    expect($result->matched())->toBeTrue();
    expect($result->address()->deliveryAddressLine())->toBe('814 S WEST PARK DR');
})->skip(fn () => ! file_exists(dirname(__DIR__, 2).'/database/spanish-fork-addresses.sqlite'));

it('validates 814 W PARK DR', function () {
    $result = spanishForkValidator()->validate(
        normalizeSpanishFork('814 W PARK DR')
    );

    expect($result->matched())->toBeTrue();
    expect($result->address()->streetName)->toContain('PARK');
    expect($result->address()->primaryNumber)->toBe('814');
})->skip(fn () => ! file_exists(dirname(__DIR__, 2).'/database/spanish-fork-addresses.sqlite'));

it('does not rewrite 814 Main St into West Park', function () {
    $result = spanishForkValidator()->validate(
        normalizeSpanishFork('814 Main St')
    );

    expect($result->matched())->toBeFalse();
    expect($result->address()->streetName)->not->toBe('WEST PARK');
})->skip(fn () => ! file_exists(dirname(__DIR__, 2).'/database/spanish-fork-addresses.sqlite'));
