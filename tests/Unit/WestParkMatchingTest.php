<?php

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\Contracts\AddressValidator;
use Cdburgess\SpanishForkAddresses\SpanishForkValidator;
use Cdburgess\SpanishForkAddresses\Tests\Support\AddressPointsCsv;

beforeEach(function () {
    AddressPointsCsv::import([AddressPointsCsv::row()]);
});

function spanishForkValidator(): SpanishForkValidator
{
    return app(AddressValidator::class);
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
});

it('validates 814 West Park Dr', function () {
    $result = spanishForkValidator()->validate(
        normalizeSpanishFork('814 West Park Dr')
    );

    expect($result->matched())->toBeTrue();
    expect($result->address()->deliveryAddressLine())->toBe('814 S WEST PARK DR');
});

it('validates 814 W PARK DR', function () {
    $result = spanishForkValidator()->validate(
        normalizeSpanishFork('814 W PARK DR')
    );

    expect($result->matched())->toBeTrue();
    expect($result->address()->streetName)->toContain('PARK');
    expect($result->address()->primaryNumber)->toBe('814');
});

it('does not rewrite 814 Main St into West Park', function () {
    $result = spanishForkValidator()->validate(
        normalizeSpanishFork('814 Main St')
    );

    expect($result->matched())->toBeFalse();
    expect($result->address()->streetName)->not->toBe('WEST PARK');
});
