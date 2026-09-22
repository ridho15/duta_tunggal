<?php

namespace App\Helpers;

/**
 * MoneyHelper
 *
 * Centralized Rupiah (IDR) money formatter for Duta Tunggal ERP.
 *
 * Standard format: Rp 1.000.000,00
 * Rules:
 *  - Prefix    : "Rp " (with trailing space)
 *  - Thousands : "." (dot)
 *  - Decimal   : "," (comma)
 *  - Negative  : "-Rp 1.000,00"
 *  - Null/empty: "Rp 0,00"
 */
class MoneyHelper
{
    /**
     * Format a numeric value as Indonesian Rupiah with two decimals.
     *
     * Examples:
     *   rupiah(1000000)      → "Rp 1.000.000,00"
     *   rupiah("20.000.000") → "Rp 20.000.000,00"  (formatted string also handled)
     *   rupiah(0)            → "Rp 0,00"
     *   rupiah(null)         → "Rp 0,00"
     *   rupiah(-5000)        → "-Rp 5.000,00"
     */
    public static function rupiah(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Rp 0,00';
        }

        $numeric = self::parse($value);

        if ($numeric < 0) {
            return '-Rp ' . number_format(abs($numeric), 2, ',', '.');
        }

        return 'Rp ' . number_format($numeric, 2, ',', '.');
    }

    /**
     * Format a numeric value as Indonesian Rupiah with decimal places.
     *
     * Example: rupiahDecimal(1500.75, 2) → "Rp 1.500,75"
     */
    public static function rupiahDecimal(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return 'Rp 0,00';
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
            // Only dots — ambiguous between decimal and thousands.
            // Treat a single dot with exactly 3 trailing digits as thousands
            // (e.g. "27.500" => 27500), but preserve true decimals with 1-2
            // trailing digits or longer raw float strings like "4.2510666666".
            $dotCount = substr_count($cleaned, '.');
            if ($dotCount === 1) {
                $parts = explode('.', $cleaned);
                $fraction = $parts[1] ?? '';
                if (preg_match('/^\d{3}$/', $fraction)) {
                    $integer = str_replace('.', '', $cleaned);
                    $decimal = '0';
                } elseif (strlen($fraction) > 3) {
                    $integer = $parts[0];
                    $decimal = $fraction;
                } elseif (preg_match('/\.(\d{1,2})$/', $cleaned, $matches)) {
                    // Ends with .X or .XX  → decimal separator
                    $decimal = $matches[1];
                    $integer = preg_replace('/\.\d{1,2}$/', '', $cleaned);
                    $integer = str_replace('.', '', $integer);
                } else {
                    // Single dot but fractional length <=2 — assume thousands
                    $integer = str_replace('.', '', $cleaned);
                    $decimal = '0';
                }
            } elseif (preg_match('/\.(\d{1,2})$/', $cleaned, $matches)) {
                // Ends with .X or .XX  → decimal separator
                $decimal = $matches[1];
                $integer = preg_replace('/\.\d{1,2}$/', '', $cleaned);
                $integer = str_replace('.', '', $integer); // clear any remaining thousand dots
            } else {
                // Multiple dots or other patterns → Indonesian thousands separator
                $integer = str_replace('.', '', $cleaned);
                $decimal = '0';
            }
        }

        return (float) ($integer . '.' . $decimal);
    }

    /**
     * Parse a money string into a high-precision numeric string suitable for bcmath.
     * Preserves all fractional digits and does NOT cast to float.
     * Examples:
     *  - "1.000.000,50" -> "1000000.50"
     *  - "4.2510666666"  -> "4.2510666666"
     *  - "-Rp 1.000"     -> "-1000"
     */
    public static function parseHighPrecision(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $str = (string) $value;

        // Strip currency prefix and whitespace
        $cleaned = preg_replace('/[Rp\s]/u', '', $str);
        $cleaned = trim($cleaned);

        if ($cleaned === '' || $cleaned === '-') {
            return '0';
        }

        // No formatting chars — plain integer or float string from DB
        if (!preg_match('/[.,]/', $cleaned)) {
            return $cleaned;
        }

        $hasComma = strpos($cleaned, ',') !== false;
        $hasDot   = strpos($cleaned, '.') !== false;

        $integer = '';
        $decimal = '';

        if ($hasComma && $hasDot) {
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
            $parts = explode(',', $cleaned);
            $last  = end($parts);
            if (count($parts) === 2 && preg_match('/^\d{1,2}$/', $last)) {
                $decimal = $last;
                $integer = str_replace('.', '', $parts[0]);
            } else {
                $integer = str_replace(',', '', $cleaned);
                $decimal = '';
            }
        } else {
            $dotCount = substr_count($cleaned, '.');
            if ($dotCount === 1) {
                $parts = explode('.', $cleaned);
                $fraction = $parts[1] ?? '';
                if (preg_match('/^\d{3}$/', $fraction)) {
                    $integer = str_replace('.', '', $cleaned);
                    $decimal = '';
                } else {
                    $integer = $parts[0];
                    $decimal = $fraction;
                }
            } else {
                $integer = str_replace('.', '', $cleaned);
                $decimal = '';
            }
        }

        $result = $integer;
        if ($decimal !== '') {
            $result .= '.' . $decimal;
        }

        return $result === '' ? '0' : $result;
    }

    /**
     * Safely parse a currency state coming from UI.
     * Native ints/floats and numeric strings without separators are returned as
     * numeric values, while formatted money strings are parsed with Indonesian
     * separator rules. This prevents "92.551" from being saved as 92.55.
     */
    public static function safeParse(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || $trimmed === '-') {
                return 0.0;
            }

            if (! preg_match('/[.,]/', $trimmed) && is_numeric($trimmed)) {
                return (float) $trimmed;
            }

            return self::parse($trimmed);
        }

        return self::parse($value);
    }
}
