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

        $settingRate = static::resolveSettingRate();
        if ($settingRate !== null) {
            return $settingRate;
        }

        if ($productId) {
            $productRate = static::resolveProductRate(Product::find($productId));
            if ($productRate > 0) {
                return $productRate;
            }
        }

        return 0.0;
    }

    public static function resolveForProduct(?Product $product): float
    {
        $settingRate = static::resolveSettingRate();
        if ($settingRate !== null) {
            return $settingRate;
        }

        $productRate = static::resolveProductRate($product);
        if ($productRate > 0) {
            return $productRate;
        }

        return 0.0;
    }

    public static function resolveFallbackRate(): float
    {
        return static::resolveSettingRate() ?? 0.0;
    }

    protected static function resolveSettingRate(): ?float
    {
        $activeRate = (float) TaxSetting::activeRate('PPN');

        return $activeRate > 0 ? $activeRate : null;
    }

    protected static function resolveProductRate(?Product $product): float
    {
        if ($product && $product->pajak !== null && $product->pajak !== '') {
            return (float) $product->pajak;
        }

        return 0.0;
    }

    protected static function isNonTaxType(?string $taxType): bool
    {
        $value = strtolower(trim((string) $taxType));

        return in_array($value, ['none', 'non pajak', 'non-pajak', 'nonpajak'], true);
    }
}