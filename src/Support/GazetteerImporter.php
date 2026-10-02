<?php

namespace Cdburgess\SpanishForkAddresses\Support;

use Cdburgess\AddressingStandards\Tables\StreetSuffixes;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

class GazetteerImporter
{
    public function __construct(
        protected ConnectionInterface $connection,
    ) {}

    public function import(string $csvPath): int
    {
        $reader = new CsvReader($csvPath, [
            'Address System', 'Full Address', 'Address Number',
            'Prefix Direction', 'Street Name', 'Street Type', 'Suffix Direction',
        ]);

        return $this->connection->transaction(function () use ($reader): int {
            $this->connection->table('gis_addresses')->delete();

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

                $this->connection->table('gis_addresses')->insert([
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

            return $imported;
        });
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
