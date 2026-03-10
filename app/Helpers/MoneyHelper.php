<?php

namespace App\Helpers;

/**
 * MoneyHelper
 *
 * Centralized Rupiah (IDR) money formatter for Duta Tunggal ERP.
 *
 * Standard format: Rp 1.000.000
 * Rules:
 *  - Prefix    : "Rp " (with trailing space)
 *  - Thousands : "." (dot)
 *  - Decimal   : "," (comma) — not shown by default
 *  - Negative  : "-Rp 1.000"
 *  - Null/empty: "Rp 0"
 */
class MoneyHelper
{
    /**
     * Format a numeric value as Indonesian Rupiah (no decimals).
     *
     * Examples:
     *   rupiah(1000000)  → "Rp 1.000.000"
     *   rupiah(0)        → "Rp 0"
     *   rupiah(null)     → "Rp 0"
     *   rupiah(-5000)    → "-Rp 5.000"
     */
    public static function rupiah(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Rp 0';
        }

        $numeric = (float) $value;

        if ($numeric < 0) {
            return '-Rp ' . number_format(abs($numeric), 0, ',', '.');
        }

        return 'Rp ' . number_format($numeric, 0, ',', '.');
    }

    /**
     * Format a numeric value as Indonesian Rupiah with decimal places.
     *
     * Example: rupiahDecimal(1500.75, 2) → "Rp 1.500,75"
     */
    public static function rupiahDecimal(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return 'Rp 0';
        }

        $numeric = (float) $value;

        if ($numeric < 0) {
            return '-Rp ' . number_format(abs($numeric), $decimals, ',', '.');
        }

        return 'Rp ' . number_format($numeric, $decimals, ',', '.');
    }

    /**
     * Parse an Indonesian-formatted money string back to a float.
     * Accepts: "1.000.000", "1.000.000,50", "1000000", "Rp 1.000.000"
     *
     * Returns float suitable for database storage.
     */
    public static function parse(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $str = (string) $value;

        // Strip currency prefix and whitespace
        $str = preg_replace('/[Rp\s]/u', '', $str);

        // Remove all dots (thousands separator)
        $str = str_replace('.', '', $str);

        // Replace comma with dot (decimal separator)
        $str = str_replace(',', '.', $str);

        return (float) $str;
    }
}
