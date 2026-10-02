# Spanish Fork Addresses

Validate already-standardized US addresses against City of Spanish Fork GIS address points.

This package does **not** normalize raw input. Use [`cdburgess/addressing-standards`](https://github.com/cdburgess/addressing-standards) first, then pass the `Address` object into the validator.

## Installation

```bash
composer require cdburgess/spanish-fork-addresses
```

The service provider is auto-discovered by Laravel.

Publish the migration and create the table in your application's default database:

```bash
php artisan vendor:publish --tag=spanish-fork-addresses-migrations
php artisan migrate --force
```

The migration creates an empty `gis_addresses` table with the address fields and lookup indexes. It is not loaded automatically; publish it before running migrations. The package does not create or use a separate SQLite file.

Populate the table using a Utah address points CSV export (not shipped with the package):

```bash
php artisan spanish-fork:import /path/to/UtahAddressPoints.csv
```

## Refresh GIS data

```bash
php artisan spanish-fork:import /path/to/UtahAddressPoints.csv
```

Only rows whose `Address System` is `SPANISH FORK` are imported (case-insensitive, ignoring surrounding whitespace). The `City` field is not used for filtering. DBF import is no longer supported.

Required headers are `Address System`, `Full Address`, `Address Number`, `Prefix Direction`, `Street Name`, `Street Type`, and `Suffix Direction`. The reader supports UTF-8 BOMs and quoted CSV fields. Rows with neither a full address nor a street name are skipped.

Address components are imported directly, including optional `Address Number Suffix` and `Unit ID`. Optional `Utah Address Point ID`, `Structure`, and `Point Type` populate `location_id`, `is_built`, and `address_type`; missing metadata remains null. Optional `x`/`y` coordinates must be EPSG:3857 (Web Mercator) and are converted to longitude/latitude.

When a new CSV export is available, rerun the same command. Each successful import replaces all rows in `gis_addresses` in a transaction without recreating the table; a failed import preserves its previous data. An export with no eligible records successfully empties the table. Other application tables are not modified. You do not need a code change unless the CSV headers change.

Use a transactional database engine (such as InnoDB on MySQL). Read visibility and locking during a live refresh depend on your database's transaction isolation.

You can get Utah GIS data from: https://opendata.gis.utah.gov/

## Zero-downtime deployments and upgrading from SQLite

For the initial upgrade, publish and run the migration, then import the CSV **before switching traffic to the new release**. The migration does not copy data from an old SQLite file; existing installations need a one-time CSV reimport. Keep the old SQLite file available while the old release still serves traffic.

After that cutover, `gis_addresses` persists in the application's database across release-directory changes. Run normal pending migrations on later deployments, but do not run the import command on every deploy. Reimport only when updating GIS data.

This is a breaking change from the SQLite-backed version: the standalone `bin/import-address-points.php` script, the `--database` import option, and the `spanish-fork-addresses-config` / `spanish-fork-addresses-db` publish tags have been removed. The old `spanish-fork-addresses.database` path setting is no longer read; you can remove its published config file.

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

Or construct the validator directly with a Laravel database connection:

```php
use Cdburgess\SpanishForkAddresses\SpanishForkValidator;
use Illuminate\Support\Facades\DB;

$validator = new SpanishForkValidator(DB::connection());
$result = $validator->validate($address);
```

`SpanishForkValidator` and `GazetteerImporter` now take an `Illuminate\Database\ConnectionInterface`, not a file path. For direct imports, use `app(GazetteerImporter::class)->import($csvPath)`; there is no output-path argument.

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

## Database

The validator and importer both use Laravel's default database connection and the fixed table name `gis_addresses`. Configure the connection in your application's normal database configuration; there are no package-specific database settings.

An empty table returns an unmatched result. A missing migration or unavailable database surfaces a database error rather than being treated as an address mismatch. The import command reports errors and exits with a failure status.

## Testing

```bash
./vendor/bin/pest
```

Tests use the package migration and deterministic CSV fixtures in an in-memory application database. No generated gazetteer file is needed.

## Limits

- Coverage is Spanish Fork, Utah only.
- Spellings follow city GIS, not USPS ZIP+4 / CASS.
- This package does not call the USPS API.
- A missing ZIP does not prevent a GIS match.

## License

MIT
