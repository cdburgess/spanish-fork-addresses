<?php

namespace Cdburgess\SpanishForkAddresses\Support;

use Cdburgess\AddressingStandards\Tables\StreetSuffixes;
use InvalidArgumentException;
use PDO;

class GazetteerImporter
{
    public function import(string $dbfPath, string $databasePath): int
    {
        if (! is_readable($dbfPath)) {
            throw new InvalidArgumentException("DBF file is not readable: {$dbfPath}");
        }

        $directory = dirname($databasePath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new InvalidArgumentException("Unable to create directory: {$directory}");
        }

        $pdo = new PDO('sqlite:'.$databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createSchema($pdo);
        $pdo->exec('DELETE FROM addresses');

        $insert = $pdo->prepare('
            INSERT INTO addresses (
                house_number, pre_directional, street_name, suffix,
                secondary_number, full_address, street_key, street_key_loose,
                street_name_key, latitude, longitude, location_id, is_built, address_type
            ) VALUES (
                :house_number, :pre_directional, :street_name, :suffix,
                :secondary_number, :full_address, :street_key, :street_key_loose,
                :street_name_key, :latitude, :longitude, :location_id, :is_built, :address_type
            )
        ');

        $imported = 0;
        $pdo->beginTransaction();

        foreach ((new DbfReader($dbfPath))->records() as $row) {
            $full = trim((string) ($row['FullAddres'] ?? ''));
            $street = trim((string) ($row['StreetName'] ?? ''));
            $label = trim((string) ($row['LabelAddre'] ?? ''));

            if ($full === '' && $street === '') {
                continue;
            }

            [$streetName, $suffix] = $this->splitStreetName($street);
            [$number, $pre, $secondary] = $this->parseLabel($label);

            $insert->execute([
                'house_number' => $number,
                'pre_directional' => $pre,
                'street_name' => $streetName,
                'suffix' => $suffix,
                'secondary_number' => $secondary,
                'full_address' => $full !== '' ? strtoupper($full) : null,
                'street_key' => StreetKey::compact($pre, $streetName, $suffix),
                'street_key_loose' => StreetKey::loose($streetName, $suffix),
                'street_name_key' => StreetKey::compact($streetName, $suffix),
                'latitude' => $this->toFloat($row['WGS84_Lat'] ?? null),
                'longitude' => $this->toFloat($row['WGS84_Long'] ?? null),
                'location_id' => ($row['LocationID'] ?? '') !== '' ? $row['LocationID'] : null,
                'is_built' => ($row['IsBuilt'] ?? '') !== '' ? $row['IsBuilt'] : null,
                'address_type' => ($row['AddressTyp'] ?? '') !== '' ? $row['AddressTyp'] : null,
            ]);

            $imported++;
        }

        $pdo->commit();

        return $imported;
    }

    protected function createSchema(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS addresses');
        $pdo->exec('
            CREATE TABLE addresses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                house_number TEXT,
                pre_directional TEXT,
                street_name TEXT,
                suffix TEXT,
                secondary_number TEXT,
                full_address TEXT,
                street_key TEXT,
                street_key_loose TEXT,
                street_name_key TEXT,
                latitude REAL,
                longitude REAL,
                location_id TEXT,
                is_built TEXT,
                address_type TEXT
            )
        ');

        $pdo->exec('CREATE INDEX addresses_house_number_index ON addresses (house_number)');
        $pdo->exec('CREATE INDEX addresses_street_key_index ON addresses (street_key)');
        $pdo->exec('CREATE INDEX addresses_street_key_loose_index ON addresses (street_key_loose)');
        $pdo->exec('CREATE INDEX addresses_street_name_key_index ON addresses (street_name_key)');
    }

    /**
     * Official GIS street names keep words like WEST/PARK.
     * Only the trailing suffix is split off.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function splitStreetName(string $street): array
    {
        $street = strtoupper(trim($street));

        if ($street === '') {
            return [null, null];
        }

        $tokens = preg_split('/\s+/', $street) ?: [];
        $suffix = StreetSuffixes::standardize((string) end($tokens));

        if ($suffix && count($tokens) > 1) {
            array_pop($tokens);

            return [implode(' ', $tokens), $suffix];
        }

        return [$street, null];
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    protected function parseLabel(string $label): array
    {
        $label = strtoupper(trim($label));

        if (preg_match('/^(\d+[A-Z]?)\s*([NSEW]{1,2})?(?:\s*#\s*([A-Z0-9\-]+))?$/', $label, $match)) {
            return [
                $match[1] ?? null,
                ($match[2] ?? '') !== '' ? $match[2] : null,
                ($match[3] ?? '') !== '' ? $match[3] : null,
            ];
        }

        return [null, null, null];
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
