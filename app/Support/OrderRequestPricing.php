<?php

namespace App\Support;

use App\Models\Currency;

/** Perhitungan harga Permintaan Pembelian (OR) yang dipakai di luar layar input. */
class OrderRequestPricing
{
    public static function isIdrCurrency(?int $currencyId): bool
    {
        if (! $currencyId) {
            return true;
        }

        return strtoupper((string) Currency::find($currencyId)?->code) === 'IDR';
    }

    public static function convertIdrAnchorToCurrency(string|float $anchorIdr, ?int $currencyId): string
    {
        if (self::isIdrCurrency($currencyId)) {
            return bcadd((string) $anchorIdr, '0', 2);
        }

        return CurrencyConversionResolver::convertFromIdrHighPrecision($anchorIdr, $currencyId);
    }

    public static function calculateApprovalItemPreview(
        float $quantity,
        float $unitPrice,
        float $discountPct,
        float $taxPct,
        string $taxType
    ): array {
        $base = $quantity * $unitPrice;
        $afterDisc = $base - $base * ($discountPct / 100);

        // Nominal pajak selalu dihitung dari nilai setelah diskon, tidak berubah saat jenis pajak diganti.
        $taxNominal = round($afterDisc * ($taxPct / 100), 2);

        // Hanya subtotal yang berbeda antar jenis pajak: inklusif sudah termasuk pajak, selain itu pajak ditambahkan.
        $subtotal = TaxTypeHelper::normalize($taxType) === 'inklusif'
            ? $afterDisc
            : $afterDisc + $taxNominal;

        return [
            'total_cost' => (float) $base,
            'subtotal' => (float) $subtotal,
            'tax_nominal' => (float) $taxNominal,
        ];
    }
}
