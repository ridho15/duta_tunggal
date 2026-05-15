<?php

namespace App\Support;

use App\Models\Currency;

class CurrencyConversionResolver
{
    public static function resolveCurrency(?int $currencyId): ?Currency
    {
        if (! $currencyId) {
            return null;
        }

        return Currency::find($currencyId);
    }

    public static function resolveRate(?int $currencyId): float
    {
        $currency = static::resolveCurrency($currencyId);

        if (! $currency) {
            return 1.0;
        }

        $rate = (float) ($currency->to_rupiah ?? 1);

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * Convert amount to IDR with high-precision intermediate calculation.
     * Uses bcmath to maintain precision through multiplication.
     *
     * @param float $amount Amount in source currency
     * @param int|null $currencyId Currency ID to convert from
     * @return float Amount in IDR, rounded to 2 decimals
     */
    public static function convertToIdr(float $amount, ?int $currencyId, bool $round = true)
    {
        $rate = static::resolveRate($currencyId);
        $product = bcmul((string) $amount, (string) $rate, 10);
        if (! $round) {
            return rtrim(rtrim($product, '0'), '.');
        }

        return (float) round((float) $product, 2);
    }

    /**
     * Convert amount between two currencies with high-precision intermediate calculation.
     * Uses bcmath with 10 decimal places during intermediate math to minimize precision loss.
     *
     * Fix for bug: IDR→USD→IDR conversion (68,000,000 → 4,533.33 → 67,999,950)
     * - Previous formula: round(($amount * $fromRate) / $toRate, 2)
     * - New formula: bcmul and bcdiv with 10 decimals, then round to 2
     * 
     * Note: 2-decimal precision of the intermediate rounded value still causes some loss
     * for certain exchange rates (e.g., 1:15000), but the bcmath calculation minimizes it.
     *
     * @param float $amount Amount to convert
     * @param int|null $fromCurrencyId Source currency ID
     * @param int|null $toCurrencyId Target currency ID
     * @return float Converted amount, rounded to 2 decimals
     */
    public static function convertBetweenCurrencies(float $amount, ?int $fromCurrencyId, ?int $toCurrencyId, bool $round = true)
    {
        $fromRate = static::resolveRate($fromCurrencyId);
        $toRate = static::resolveRate($toCurrencyId);

        if ($toRate <= 0) {
            return $amount;
        }

        // Use bcmath for 10-decimal precision during intermediate calculation
        $product = bcmul((string) $amount, (string) $fromRate, 10);
        $quotient = bcdiv($product, (string) $toRate, 10);

        if (! $round) {
            return rtrim(rtrim($quotient, '0'), '.');
        }

        return (float) round((float) $quotient, 2);
    }

    /**
     * Convert amount from IDR to target currency with high-precision intermediate calculation.
     * Uses bcmath to maintain precision during division.
     *
     * @param float $amount Amount in IDR
     * @param int|null $currencyId Target currency ID
     * @return float Amount in target currency, rounded to 2 decimals
     */
    public static function convertFromIdr(float $amount, ?int $currencyId, bool $round = true)
    {
        $rate = static::resolveRate($currencyId);
        $quotient = bcdiv((string) $amount, (string) $rate, 10);
        if (! $round) {
            return rtrim(rtrim($quotient, '0'), '.');
        }

        return (float) round((float) $quotient, 2);
    }

    public static function formatAmount(?int $currencyId, float $amount, int $decimals = 0): string
    {
        return static::resolveSymbol($currencyId) . ' ' . number_format($amount, $decimals, ',', '.');
    }

    public static function formatAmountFromIdr(float $amount, ?int $currencyId, int $decimals = 0): string
    {
        return static::formatAmount($currencyId, static::convertFromIdr($amount, $currencyId), $decimals);
    }

    public static function resolveSymbol(?int $currencyId): string
    {
        return static::resolveCurrency($currencyId)?->symbol ?: 'Rp';
    }

    public static function resolveCurrencyIdByCode(string $code): ?int
    {
        return Currency::whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->value('id');
    }
}