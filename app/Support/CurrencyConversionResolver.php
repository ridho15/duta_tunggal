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

    public static function convertToIdr(float $amount, ?int $currencyId): float
    {
        return round($amount * static::resolveRate($currencyId), 2);
    }

    public static function convertBetweenCurrencies(float $amount, ?int $fromCurrencyId, ?int $toCurrencyId): float
    {
        $fromRate = static::resolveRate($fromCurrencyId);
        $toRate = static::resolveRate($toCurrencyId);

        if ($toRate <= 0) {
            return $amount;
        }

        return round(($amount * $fromRate) / $toRate, 2);
    }

    public static function convertFromIdr(float $amount, ?int $currencyId): float
    {
        return round($amount / static::resolveRate($currencyId), 2);
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