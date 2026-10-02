<?php

namespace Cdburgess\SpanishForkAddresses\Tests\Support;

class AddressPointsCsv
{
    public static function row(array $overrides = []): array
    {
        return array_replace([
            'Address System' => 'SPANISH FORK',
            'Utah Address Point ID' => 'SPANISH FORK | 814 S WEST PARK DR',
            'Full Address' => '814 S WEST PARK DR',
            'Address Number' => '814',
            'Address Number Suffix' => '',
            'Prefix Direction' => 'S',
            'Street Name' => 'WEST PARK',
            'Street Type' => 'DR',
            'Suffix Direction' => '',
            'Unit ID' => '',
            'City' => 'SPANISH FORK',
            'Structure' => 'Yes',
            'Point Type' => 'Residential',
            'x' => '-12429269.825',
            'y' => '4879785.434',
        ], $overrides);
    }

    public static function write(string $path, array $rows, bool $bom = false): void
    {
        $handle = fopen($path, 'wb');

        try {
            if ($bom) {
                fwrite($handle, "\xEF\xBB\xBF");
            }

            fputcsv($handle, array_keys($rows[0]), ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row), ',', '"', '');
            }
        } finally {
            fclose($handle);
        }
    }
}
