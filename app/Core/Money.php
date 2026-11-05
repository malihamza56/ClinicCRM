<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Currency arithmetic WITHOUT floating point.
 *
 * Amounts are handled as whole "minor units" (paisa/cents): Rs. 1,250.50 is the integer 125050.
 * Integers add and subtract exactly, so totals can never drift by a fraction of a cent.
 * The database stores DECIMAL(12,2) strings such as "1250.50"; this class converts both ways.
 */
final class Money
{
    /** Parses "1250", "1250.5" or "1250.50" into minor units. Returns null for anything else. */
    public static function parse(string $value): ?int
    {
        $value = trim($value);
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $value, $m) !== 1) {
            return null;
        }
        $fraction = str_pad($m[2] ?? '', 2, '0');

        return ((int) $m[1]) * 100 + (int) $fraction;
    }

    /** "1250.50" (database format) from minor units. */
    public static function toDecimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    /** Reads a DECIMAL string from the database into minor units. */
    public static function fromDb(string|int|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $string = (string) $value;
        $negative = str_starts_with($string, '-');
        $parsed = self::parse(ltrim($string, '-'));
        if ($parsed === null) {
            throw new \InvalidArgumentException("Not a money value: $string");
        }

        return $negative ? -$parsed : $parsed;
    }

    /** Human format, e.g. "Rs. 1,250.50". */
    public static function format(int $minor, ?string $symbol = null): string
    {
        $symbol ??= 'Rs.';
        $sign = $minor < 0 ? '-' : '';
        $abs = abs($minor);

        return $sign . $symbol . ' ' . number_format(intdiv($abs, 100)) . '.' . sprintf('%02d', $abs % 100);
    }

    /**
     * Percentage of an amount, rounded half-up to the nearest minor unit.
     * $percent is a decimal string such as "5" or "7.5" (max two decimals).
     */
    public static function percentOf(int $minor, string $percent): int
    {
        $basisPoints = self::parse($percent); // "7.5" -> 750 hundredths of a percent
        if ($basisPoints === null) {
            throw new \InvalidArgumentException("Invalid percentage: $percent");
        }

        return intdiv($minor * $basisPoints + 5000, 10000);
    }
}
