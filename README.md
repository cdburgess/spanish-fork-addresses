# Spanish Fork Addresses

Validate already-standardized US addresses against City of Spanish Fork GIS address points.

This package does **not** normalize raw input. Use [`cdburgess/addressing-standards`](https://github.com/cdburgess/addressing-standards) first, then pass the `Address` object into the validator.

## Installation

```bash
composer require cdburgess/spanish-fork-addresses
```

The service provider is auto-discovered by Laravel.

Publish the config (optional):

```bash
php artisan vendor:publish --tag=spanish-fork-addresses-config
```

Publish a generated SQLite gazetteer (optional, after you have built one):

```bash
php artisan vendor:publish --tag=spanish-fork-addresses-db
```

## Build the gazetteer

Use a Utah address points CSV export (not shipped with the package). From the package root:

```bash
php bin/import-address-points.php /path/to/UtahAddressPoints.csv database/spanish-fork-addresses.sqlite
```

In a Laravel app after the package is installed:

```bash
php artisan spanish-fork:import /path/to/UtahAddressPoints.csv
```

Only rows whose `Address System` is `SPANISH FORK` are imported (case-insensitive, ignoring surrounding whitespace). The `City` field is not used for filtering. DBF import is no longer supported.

For the CSV in this checkout:

```bash
php bin/import-address-points.php UtahAddressPoints_2778281154463100711.csv database/spanish-fork-addresses.sqlite
```

Required headers are `Address System`, `Full Address`, `Address Number`, `Prefix Direction`, `Street Name`, `Street Type`, and `Suffix Direction`. The reader supports UTF-8 BOMs and quoted CSV fields. Rows with neither a full address nor a street name are skipped.

Address components are imported directly, including optional `Address Number Suffix` and `Unit ID`. Optional `Utah Address Point ID`, `Structure`, and `Point Type` populate `location_id`, `is_built`, and `address_type`; missing metadata remains null. Optional `x`/`y` coordinates must be EPSG:3857 (Web Mercator) and are converted to longitude/latitude.

When a new CSV export is available, rerun the same command. Each successful import replaces the gazetteer; a failed import preserves the existing database. You do not need a code change unless the CSV headers change.

You can get Utah GIS data from: https://opendata.gis.utah.gov/ 

## Usage

```php
use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\Contracts\AddressValidator;

$address = Address::normalize([
    'street_line' => '814 westpark drive',
    'city' => 'Spanish Fork',
    'state' => 'UT',
]);

$result = app(AddressValidator::class)->validate($address);

if ($result->matched()) {
    echo $result->address()->deliveryAddressLine();
    // 814 S WEST PARK DR
}
```

Or construct the validator directly in tests / non-Laravel scripts:

```php
use Cdburgess\SpanishForkAddresses\SpanishForkValidator;

$validator = new SpanishForkValidator(__DIR__ . '/database/spanish-fork-addresses.sqlite');
$result = $validator->validate($address);
```

## What gets matched

The validator compares the standardized `Address` to Spanish Fork GIS records using house number plus collapsed street keys.

| Input | City-official result |
| --- | --- |
| `814 westpark drive` | `814 S WEST PARK DR` |
| `814 West Park Dr` | `814 S WEST PARK DR` |
| `814 W PARK DR` | `814 S WEST PARK DR` |
| `80 south 800 east` | `80 S 800 E` |

`WESTPARK`, `WEST PARK`, and `W PARK` can all resolve to the GIS street `WEST PARK`. Utah grid addresses (`80 S 800 E`) use a predirectional, a numeric street name, and a postdirectional.

The validator will not rewrite an address just because the house number exists on another street. `814 Main St` stays `MAIN` unless a real Main Street record wins.

## Validation result

| Method | Description |
| --- | --- |
| `matched()` | True when one GIS record is a clear winner |
| `address()` | City-official `Address` when matched, otherwise the input |
| `confidence()` | 0–1 score |
| `source()` | `spanish_fork_gis` |
| `record()` | Raw GIS row (lat/lng, `is_built`, `full_address`, …) |
| `alternatives()` | Other close GIS rows |
| `message()` | Short explanation |
| `toArray()` | Full payload |

## Config

```php
// config/spanish-fork-addresses.php
return [
    'database' => database_path('spanish-fork-addresses.sqlite'),
];
```

If that file does not exist, the package falls back to `database/spanish-fork-addresses.sqlite` inside the package.

## Testing

```bash
./vendor/bin/pest
```

Tests that need the gazetteer skip automatically when the SQLite file has not been generated.

## Limits

- Coverage is Spanish Fork, Utah only.
- Spellings follow city GIS, not USPS ZIP+4 / CASS.
- This package does not call the USPS API.
- A missing ZIP does not prevent a GIS match.

## License

MIT
