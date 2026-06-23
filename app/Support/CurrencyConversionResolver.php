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
     * @param  float        $amount     Amount in source currency
     * @param  int|null     $currencyId Currency ID to convert from
     * @param  bool         $round      If true, round to 2 decimals; otherwise return full precision
     * @return float Amount in IDR
     */
    public static function convertToIdr(float $amount, ?int $currencyId, bool $round = true): float
    {
        $rate    = static::resolveRate($currencyId);
        $product = bcmul((string) $amount, (string) $rate, 10);

        return $round
            ? (float) round((float) $product, 2)
            : (float) $product;
    }

    /**
     * Convert amount to IDR and return a high-precision bcmath string.
     * Does NOT cast to float — avoids floating-point precision loss.
     * Use this as the authoritative IDR anchor value.
     *
     * @param  string|float $amount     Amount in source currency (bcmath string or float)
     * @param  int|null     $currencyId Currency ID to convert from
     * @param  int          $scale      bcmath decimal precision (default 10)
     * @return string IDR amount as a high-precision decimal string
     */
    public static function convertToIdrHighPrecision(string|float $amount, ?int $currencyId, int $scale = 10): string
    {
        $rate    = static::resolveRate($currencyId);
        $product = bcmul((string) $amount, (string) $rate, $scale);

        // Round mathematically to 2 decimal places instead of truncating (bcadd truncates)
        // This ensures e.g. 999999.999999... is correctly rounded to 1000000.00
        return number_format((float) $product, 2, '.', '');
    }

    /**
     * Convert amount from IDR to target currency and return a high-precision bcmath string.
     * Does NOT cast to float — avoids floating-point precision loss in chain calculations.
     *
     * @param  string|float $amountIdr  Amount in IDR (bcmath string or float)
     * @param  int|null     $currencyId Target currency ID
     * @param  int          $scale      bcmath decimal precision (default 10)
     * @return string Converted amount as a high-precision decimal string
     */
    public static function convertFromIdrHighPrecision(string|float $amountIdr, ?int $currencyId, int $scale = 10): string
    {
        $rate = static::resolveRate($currencyId);

        if ($rate <= 0) {
            return (string) $amountIdr;
        }

        return bcdiv((string) $amountIdr, (string) $rate, $scale);
    }

    /**
     * Convert amount between two currencies with high-precision intermediate calculation.
     * Uses bcmath with 10 decimal places during intermediate math to minimize precision loss.
     *
     * IMPORTANT: Even with bcmath, if $amount is already a rounded intermediate value
     * (e.g. $66.67 from a previous IDR→USD conversion), converting back to IDR will not
     * recover the original IDR value. Always use convertFromIdrHighPrecision() with the
     * stored IDR anchor value for lossless round-trips.
     *
     * @param  float|string $amount         Amount to convert
     * @param  int|null     $fromCurrencyId Source currency ID
     * @param  int|null     $toCurrencyId   Target currency ID
     * @param  bool         $round          If true, round result to 2 decimals
     * @return float|string Converted amount
     */
    public static function convertBetweenCurrencies(
        float|string $amount,
        ?int $fromCurrencyId,
        ?int $toCurrencyId,
        bool $round = true
    ): float|string {
        $fromRate = static::resolveRate($fromCurrencyId);
        $toRate   = static::resolveRate($toCurrencyId);

        if ($toRate <= 0) {
            return $round ? (float) $amount : (string) $amount;
        }

        // Step 1: convert to IDR using bcmath
        $idrAmount = bcmul((string) $amount, (string) $fromRate, 10);
        // Step 2: convert from IDR to target currency
        $result    = bcdiv($idrAmount, (string) $toRate, 10);

        if (! $round) {
            return $result;
        }

        return (float) round((float) $result, 2);
    }

    /**
     * Convert amount from IDR to target currency with high-precision intermediate calculation.
     * Uses bcmath to maintain precision during division.
     *
     * @param  float $amount     Amount in IDR
     * @param  int|null $currencyId Target currency ID
     * @param  bool  $round      If true, round to 2 decimals
     * @return float Amount in target currency
     */
    public static function convertFromIdr(float $amount, ?int $currencyId, bool $round = true): float
    {
        $rate     = static::resolveRate($currencyId);
        $quotient = bcdiv((string) $amount, (string) $rate, 10);

        return $round
            ? (float) round((float) $quotient, 2)
            : (float) $quotient;
    }

    public static function formatAmount(?int $currencyId, float $amount, ?int $decimals = null): string
    {
        $currency = static::resolveCurrency($currencyId);

        if ($decimals === null) {
            if ($currency) {
                $code     = strtoupper(trim($currency->code ?? ''));
                $decimals = ($code === 'IDR' || (int) ($currency->to_rupiah ?? 0) === 1) ? 0 : 2;
            } else {
                $decimals = 2;
            }
        }

        $formatted = number_format($amount, $decimals, ',', '.');

        if ($decimals > 0 && $decimals === 2) {
            $formatted = preg_replace('/,0{2}$/', '', $formatted) ?? $formatted;
        }

        return static::resolveSymbol($currencyId) . ' ' . $formatted;
    }

    public static function formatAmountFromIdr(float $amount, ?int $currencyId, ?int $decimals = null): string
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
