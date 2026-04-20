<?php

namespace App\Support;

use App\Models\Product;
use App\Models\TaxSetting;

class TaxDefaultResolver
{
    public static function resolveForProductId(?int $productId, ?string $taxType = null): float
    {
        if (static::isNonTaxType($taxType)) {
            return 0.0;
        }

        if ($productId) {
            return static::resolveForProduct(Product::find($productId));
        }

        return static::resolveFallbackRate();
    }

    public static function resolveForProduct(?Product $product): float
    {
        if ($product && $product->pajak !== null && $product->pajak !== '') {
            return (float) $product->pajak;
        }

        return static::resolveFallbackRate();
    }

    public static function resolveFallbackRate(): float
    {
        $activeRate = (float) TaxSetting::activeRate('PPN');

        return $activeRate > 0 ? $activeRate : 0.0;
    }

    protected static function isNonTaxType(?string $taxType): bool
    {
        $value = strtolower(trim((string) $taxType));

        return in_array($value, ['none', 'non pajak', 'non-pajak', 'nonpajak'], true);
    }
}