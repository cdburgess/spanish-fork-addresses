<?php

namespace Cdburgess\SpanishForkAddresses\Support;

class StreetKey
{
    public static function compact(?string ...$parts): string
    {
        $value = strtoupper(implode('', $parts));
        $value = preg_replace('/[^A-Z0-9]/', '', $value) ?? '';

        return $value;
    }

    public static function loose(?string $streetName, ?string $suffix = null): string
    {
        $value = strtoupper(trim(($streetName ?? '').' '.($suffix ?? '')));
        $value = preg_replace('/\b(N|S|E|W|NE|NW|SE|SW|NORTH|SOUTH|EAST|WEST)\b/', '', $value) ?? '';

        return self::compact($value);
    }
}
