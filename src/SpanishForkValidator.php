<?php

namespace Cdburgess\SpanishForkAddresses;

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\Contracts\AddressValidator;
use Cdburgess\SpanishForkAddresses\Support\StreetKey;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

class SpanishForkValidator implements AddressValidator
{
    public function __construct(
        protected ConnectionInterface $connection,
    ) {}

    public function validate(Address $address): ValidationResult
    {
        if (! filled($address->primaryNumber) && ! filled($address->streetName)) {
            throw new InvalidArgumentException(
                'SpanishForkValidator requires a standardized Address with a primary number or street name.'
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
        $second = $candidates[0] ?? null;

        $sameStreet = $second
            && ($second['row']['street_name_key'] ?? '') === ($best['row']['street_name_key'] ?? '')
            && ($second['row']['house_number'] ?? '') === ($best['row']['house_number'] ?? '');

        $ambiguous = $second
            && ! $sameStreet
            && ($best['score'] - $second['score']) < 10;

        $matched = ! $ambiguous && $best['score'] >= 70;

        return new ValidationResult(
            matched: $matched,
            address: $matched ? $this->applyMatch($address, $best['row']) : $address,
            confidence: $best['score'] / 100,
            record: $best['row'],
            alternatives: array_map(
                fn ($item) => $item['row'],
                $matched ? $candidates : array_merge([$best], $candidates)
            ),
            message: $ambiguous
                ? 'Multiple Spanish Fork GIS records are close matches.'
                : ($matched ? 'Matched Spanish Fork GIS record.' : 'Possible GIS match found.'),
        );
    }

    /**
     * @return array<int, array{row: array<string, mixed>, score: float}>
     */
    protected function candidates(Address $address): array
    {
        $inputNameKey = StreetKey::compact(
            $address->streetName,
            $address->postDirectional,
            $address->suffix
        );

        $inputKey = StreetKey::compact(
            $address->preDirectional,
            $address->streetName,
            $address->postDirectional,
            $address->suffix
        );

        $inputLoose = StreetKey::loose(
            trim(($address->streetName ?? '').' '.($address->postDirectional ?? '')),
            $address->suffix
        );

        $query = $this->connection->table('gis_addresses');

        if (filled($address->primaryNumber)) {
            $query->where('house_number', $address->primaryNumber);
        }

        $rows = $query->get();

        $scored = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $rowNameKey = $row['street_name_key'] ?: StreetKey::compact($row['street_name'] ?? '');
            $rowKey = $row['street_key'] ?: StreetKey::compact(
                $row['pre_directional'] ?? '',
                $row['street_name'] ?? ''
            );

            $score = 0.0;

            if ($address->primaryNumber && $row['house_number'] === $address->primaryNumber) {
                $score += 40;
            }

            if ($inputNameKey !== '' && $rowNameKey === $inputNameKey) {
                $score += 50;
            } elseif ($inputKey !== '' && $rowKey === $inputKey) {
                $score += 50;
            } else {
                similar_text($inputNameKey, $rowNameKey, $percent);
                $score += min(50, $percent * 0.5);
            }

            if ($inputLoose !== '' && ($row['street_key_loose'] ?? '') === $inputLoose) {
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
        $streetName = $row['street_name'] ?: $address->streetName;
        $postDirectional = $address->postDirectional;
        $preDirectional = $row['pre_directional'] ?: $address->preDirectional;

        if ($streetName && preg_match('/^(.*)\s+(N|S|E|W|NE|NW|SE|SW)$/', $streetName, $match)) {
            $streetName = $match[1];
            $postDirectional = $match[2];
        }

        return $address->with([
            'primaryNumber' => $row['house_number'] ?: $address->primaryNumber,
            'preDirectional' => $preDirectional,
            'streetName' => $streetName,
            'suffix' => $row['suffix'] ?: $address->suffix,
            'postDirectional' => $postDirectional,
            'secondaryNumber' => $row['secondary_number'] ?: $address->secondaryNumber,
            'city' => $address->city ?: 'SPANISH FORK',
            'state' => $address->state ?: 'UT',
        ]);
    }
}
