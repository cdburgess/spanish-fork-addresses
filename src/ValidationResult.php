<?php

namespace Cdburgess\SpanishForkAddresses;

use Cdburgess\AddressingStandards\Address;

readonly class ValidationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $alternatives
     * @param  array<string, mixed>  $record
     */
    public function __construct(
        public bool $matched,
        public Address $address,
        public float $confidence = 0.0,
        public string $source = 'spanish_fork_gis',
        public array $record = [],
        public array $alternatives = [],
        public ?string $message = null,
    ) {}

    public function matched(): bool
    {
        return $this->matched;
    }

    public function address(): Address
    {
        return $this->address;
    }

    public function confidence(): float
    {
        return $this->confidence;
    }

    public function source(): string
    {
        return $this->source;
    }

    public function record(): array
    {
        return $this->record;
    }

    public function alternatives(): array
    {
        return $this->alternatives;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return [
            'matched' => $this->matched,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'message' => $this->message,
            'address' => $this->address->toArray(),
            'record' => $this->record,
            'alternatives' => $this->alternatives,
        ];
    }
}