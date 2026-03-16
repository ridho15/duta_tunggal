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
     *   rupiah(1000000)      → "Rp 1.000.000"
     *   rupiah("20.000.000") → "Rp 20.000.000"  (formatted string also handled)
     *   rupiah(0)            → "Rp 0"
     *   rupiah(null)         → "Rp 0"
     *   rupiah(-5000)        → "-Rp 5.000"
     */
    public static function rupiah(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Rp 0';
        }

        $numeric = self::parse($value);

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

        $numeric = self::parse($value);

        if ($numeric < 0) {
            return '-Rp ' . number_format(abs($numeric), $decimals, ',', '.');
        }

        return 'Rp ' . number_format($numeric, $decimals, ',', '.');
    }

    /**
     * Parse an Indonesian-formatted money string back to a float.
     * Accepts: "1.000.000", "1.000.000,50", "1000000", "Rp 1.000.000",
     *          raw DB decimals like "20000000.00", "5000.50"
     *
     * Rules for dot-only values:
     *  - Ends with .X or .XX  (1-2 decimal digits) → treat dot as decimal separator
     *  - Ends with .XXX        (3 digits)           → treat dot as thousands separator
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
        $cleaned = preg_replace('/[Rp\s]/u', '', $str);
        $cleaned = trim($cleaned);

        if ($cleaned === '' || $cleaned === '-') {
            return 0.0;
        }

        // No formatting chars — plain integer or float string from DB
        if (!preg_match('/[.,]/', $cleaned)) {
            return (float) $cleaned;
        }

        $hasComma = strpos($cleaned, ',') !== false;
        $hasDot   = strpos($cleaned, '.') !== false;

        $integer = '';
        $decimal = '0';

        if ($hasComma && $hasDot) {
            // Both separators present — determine decimal by position
            $lastCommaPos = strrpos($cleaned, ',');
            $lastDotPos   = strrpos($cleaned, '.');

            if ($lastDotPos > $lastCommaPos) {
                // Western format: 1,000,000.50  (dot = decimal)
                $parts   = explode('.', $cleaned);
                $decimal = array_pop($parts);
                $integer = str_replace(',', '', implode('', $parts));
            } else {
                // Indonesian format: 1.000.000,50  (comma = decimal)
                $parts   = explode(',', $cleaned);
                $decimal = $parts[1] ?? '0';
                $integer = str_replace('.', '', $parts[0]);
            }
        } elseif ($hasComma) {
            // Only commas
            $parts = explode(',', $cleaned);
            $last  = end($parts);
            if (count($parts) === 2 && preg_match('/^\d{1,2}$/', $last)) {
                // Last segment is 1-2 digits → decimal separator
                $decimal = $last;
                $integer = str_replace(',', '', $parts[0]);
            } else {
                // All commas are thousands separators (e.g. Western "1,000,000")
                $integer = str_replace(',', '', $cleaned);
                $decimal = '0';
            }
        } else {
            // Only dots — ambiguous between decimal and thousands
            if (preg_match('/\.(\d{1,2})$/', $cleaned, $matches)) {
                // Ends with .X or .XX  → decimal separator
                // Handles raw DB values like "20000000.00" or "1500.5"
                $decimal = $matches[1];
                $integer = preg_replace('/\.\d{1,2}$/', '', $cleaned);
                $integer = str_replace('.', '', $integer); // clear any remaining thousand dots
            } else {
                // Ends with .XXX or more → Indonesian thousands separator
                // e.g. "1.000", "20.000.000", "1.500.000"
                $integer = str_replace('.', '', $cleaned);
                $decimal = '0';
            }
        }

        return (float) ($integer . '.' . $decimal);
    }
}
