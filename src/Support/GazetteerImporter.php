<?php

namespace Cdburgess\SpanishForkAddresses\Support;

use Cdburgess\AddressingStandards\Tables\StreetSuffixes;
use InvalidArgumentException;
use PDO;

class GazetteerImporter
{
    public function import(string $csvPath, string $databasePath): int
    {
        $reader = new CsvReader($csvPath, [
            'Address System', 'Full Address', 'Address Number',
            'Prefix Direction', 'Street Name', 'Street Type', 'Suffix Direction',
        ]);

        $directory = dirname($databasePath);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new InvalidArgumentException("Unable to create directory: {$directory}");
        }

        $pdo = new PDO('sqlite:'.$databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->beginTransaction();

        try {
            $this->createSchema($pdo);

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

            foreach ($reader->records() as $row) {
                if (strtoupper($row['Address System']) !== 'SPANISH FORK') {
                    continue;
                }

                $full = strtoupper($row['Full Address']);
                $street = strtoupper($row['Street Name']);

                if ($full === '' && $street === '') {
                    continue;
                }

                $number = strtoupper($row['Address Number'].($row['Address Number Suffix'] ?? ''));
                $pre = strtoupper($row['Prefix Direction']);
                $post = strtoupper($row['Suffix Direction']);
                $streetName = trim($street.' '.$post);
                $type = strtoupper($row['Street Type']);
                $suffix = StreetSuffixes::standardize($type) ?: ($type !== '' ? $type : null);
                [$latitude, $longitude] = $this->coordinates($row);

                $insert->execute([
                    'house_number' => $number !== '' ? $number : null,
                    'pre_directional' => $pre !== '' ? $pre : null,
                    'street_name' => $streetName !== '' ? $streetName : null,
                    'suffix' => $suffix,
                    'secondary_number' => ($row['Unit ID'] ?? '') !== '' ? strtoupper($row['Unit ID']) : null,
                    'full_address' => $full !== '' ? $full : null,
                    'street_key' => StreetKey::compact($pre, $streetName, $suffix),
                    'street_key_loose' => StreetKey::loose($streetName, $suffix),
                    'street_name_key' => StreetKey::compact($streetName, $suffix),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location_id' => ($row['Utah Address Point ID'] ?? '') !== '' ? $row['Utah Address Point ID'] : null,
                    'is_built' => ($row['Structure'] ?? '') !== '' ? $row['Structure'] : null,
                    'address_type' => ($row['Point Type'] ?? '') !== '' ? $row['Point Type'] : null,
                ]);

                $imported++;
            }

            $pdo->commit();

            return $imported;
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
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
     * @return array{0: ?float, 1: ?float}
     */
    protected function coordinates(array $row): array
    {
        foreach (['x', 'y'] as $field) {
            $value = $row[$field] ?? '';

            if ($value !== '' && (! is_numeric($value) || ! is_finite((float) $value))) {
                throw new InvalidArgumentException("Invalid CSV {$field} coordinate for address: {$row['Full Address']}");
            }
        }

        // Inverse EPSG:3857 projection using the WGS84 equatorial radius.
        $latitude = ($row['y'] ?? '') !== ''
            ? rad2deg(atan(sinh((float) $row['y'] / 6378137)))
            : null;
        $longitude = ($row['x'] ?? '') !== ''
            ? rad2deg((float) $row['x'] / 6378137)
            : null;

        return [$latitude, $longitude];
    }
}
