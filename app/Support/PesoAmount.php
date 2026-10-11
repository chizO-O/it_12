<?php

namespace App\Support;

final class PesoAmount
{
    /** Compare and add PHP peso values exactly to the cent without floats/BCMath. */
    public static function cents(int|float|string $value): int
    {
        $string = trim((string) $value);
        if (! preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,2}))?$/', $string, $parts)) {
            throw new \InvalidArgumentException('Invalid peso amount.');
        }

        return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
