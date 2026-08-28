<?php

namespace Cdburgess\SpanishForkAddresses\Support;

use Generator;
use InvalidArgumentException;

class DbfReader
{
    public function __construct(protected string $path)
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("DBF file is not readable: {$path}");
        }
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function records(): Generator
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open DBF file: {$this->path}");
        }

        try {
            $header = fread($handle, 32);
            $recordCount = unpack('V', substr($header, 4, 4))[1];
            $headerLength = unpack('v', substr($header, 8, 2))[1];
            $recordLength = unpack('v', substr($header, 10, 2))[1];

            $fields = [];
            while (ord(fgetc($handle)) !== 0x0D) {
                fseek($handle, -1, SEEK_CUR);
                $field = fread($handle, 32);
                $fields[] = [
                    'name' => rtrim(substr($field, 0, 11), "\0"),
                    'length' => ord($field[16]),
                ];
            }

            fseek($handle, $headerLength);

            for ($i = 0; $i < $recordCount; $i++) {
                $row = fread($handle, $recordLength);

                if ($row === false || $row === '' || $row[0] === '*') {
                    continue;
                }

                $offset = 1;
                $record = [];

                foreach ($fields as $field) {
                    $record[$field['name']] = trim(substr($row, $offset, $field['length']));
                    $offset += $field['length'];
                }

                yield $record;
            }
        } finally {
            fclose($handle);
        }
    }
}