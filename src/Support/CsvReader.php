<?php

namespace Cdburgess\SpanishForkAddresses\Support;

use Generator;
use InvalidArgumentException;
use RuntimeException;

class CsvReader
{
    public function __construct(
        protected string $path,
        protected array $requiredHeaders = [],
    ) {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("CSV file is not readable: {$path}");
        }
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function records(): Generator
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open CSV file: {$this->path}");
        }

        try {
            // Remove the BOM before parsing, including when the first header is quoted.
            if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = fgetcsv($handle, 0, ',', '"', '');

            if ($headers === false || $headers === [null]) {
                throw new InvalidArgumentException("CSV header is missing: {$this->path}");
            }

            $headers = array_map(fn ($value) => trim((string) $value), $headers);

            if (in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) {
                throw new InvalidArgumentException("CSV headers must be nonempty and unique: {$this->path}");
            }

            $missing = array_diff($this->requiredHeaders, $headers);

            if ($missing !== []) {
                throw new InvalidArgumentException('CSV is missing required headers: '.implode(', ', $missing));
            }

            $record = 1;

            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $record++;

                if ($values === [null]) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    throw new InvalidArgumentException("CSV record {$record} has an unexpected number of fields: {$this->path}");
                }

                yield array_combine($headers, array_map(fn ($value) => trim((string) $value), $values));
            }

            if (! feof($handle)) {
                throw new RuntimeException("Unable to read CSV file: {$this->path}");
            }
        } finally {
            fclose($handle);
        }
    }
}
