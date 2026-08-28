<?php

namespace Cdburgess\SpanishForkAddresses;

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\Contracts\AddressValidator;
use Cdburgess\SpanishForkAddresses\Support\StreetKey;
use InvalidArgumentException;
use PDO;

class SpanishForkValidator implements AddressValidator
{
    public function __construct(
        protected string $databasePath,
    ) {}

    public function validate(Address $address): ValidationResult
    {
        if (! filled($address->primaryNumber) && ! filled($address->streetName)) {
            throw new InvalidArgumentException(
                'SpanishForkValidator requires a standardized Address with a primary number or street name.'
            );
        }

        if (! file_exists($this->databasePath)) {
            return new ValidationResult(
                matched: false,
                address: $address,
                confidence: 0.0,
                message: "Gazetteer database not found at {$this->databasePath}.",
            );
        }

        $candidates = $this->candidates($address);

        if ($candidates === []) {
            return new ValidationResult(
                matched: false,
                address: $address,
                confidence: 0.0,
                message: 'No Spanish Fork GIS match found.',
            );
        }

        $best = array_shift($candidates);

        return new ValidationResult(
            matched: $best['score'] >= 70,
            address: $this->applyMatch($address, $best['row']),
            confidence: $best['score'] / 100,
            record: $best['row'],
            alternatives: array_map(fn ($item) => $item['row'], $candidates),
            message: $best['score'] >= 70 ? 'Matched Spanish Fork GIS record.' : 'Possible GIS match found.',
        );
    }

    /**
     * @return array<int, array{row: array<string, mixed>, score: float}>
     */
    protected function candidates(Address $address): array
    {
        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $inputKey = StreetKey::compact(
            $address->preDirectional,
            $address->streetName,
            $address->suffix
        );
        $inputLoose = StreetKey::loose($address->streetName, $address->suffix);

        $sql = 'SELECT * FROM addresses WHERE 1=1';
        $bindings = [];

        if (filled($address->primaryNumber)) {
            $sql .= ' AND house_number = :house_number';
            $bindings['house_number'] = $address->primaryNumber;
        }

        $statement = $pdo->prepare($sql);
        $statement->execute($bindings);
        $rows = $statement->fetchAll();

        if ($rows === [] && $inputLoose !== '') {
            $statement = $pdo->prepare('SELECT * FROM addresses WHERE street_key_loose = :loose LIMIT 25');
            $statement->execute(['loose' => $inputLoose]);
            $rows = $statement->fetchAll();
        }

        $scored = [];

        foreach ($rows as $row) {
            $score = 0;

            if ($address->primaryNumber && $row['house_number'] === $address->primaryNumber) {
                $score += 40;
            }

            if ($inputKey !== '' && $row['street_key'] === $inputKey) {
                $score += 50;
            } else {
                similar_text($inputKey, (string) $row['street_key'], $percent);
                $score += min(50, $percent * 0.5);
            }

            if ($inputLoose !== '' && $row['street_key_loose'] === $inputLoose) {
                $score += 10;
            }

            $scored[] = [
                'row' => $row,
                'score' => round($score, 1),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, 5);
    }

    protected function applyMatch(Address $address, array $row): Address
    {
        return $address->with([
            'primaryNumber' => $row['house_number'] ?: $address->primaryNumber,
            'preDirectional' => $row['pre_directional'] ?: $address->preDirectional,
            'streetName' => $row['street_name'] ?: $address->streetName,
            'suffix' => $row['suffix'] ?: $address->suffix,
            'secondaryNumber' => $row['secondary_number'] ?: $address->secondaryNumber,
            'city' => $address->city ?: 'SPANISH FORK',
            'state' => $address->state ?: 'UT',
        ]);
    }
}