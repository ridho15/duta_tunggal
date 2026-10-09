<?php

namespace App\Support;

use App\Models\Product;

class ProductPriceLimitation
{
    /**
     * Hitung harga batas minimum yang diizinkan untuk sebuah produk.
     *
     * Aturan:
     * - sell_price: Harga jual acuan / standar
     * - cost_price: Harga modal / HPP acuan
     * - harga_batas: Persentase toleransi diskon maksimal (0 - 100%)
     *
     * Formula:
     * Jika harga_batas > 0:
     *   floor_price = sell_price * (1 - (harga_batas / 100))
     * Jika harga_batas <= 0:
     *   floor_price = sell_price (atau cost_price jika sell_price kosong)
     *
     * Batas mutlak: harga jual tidak boleh di bawah cost_price (modal).
     */
    public static function getMinimumAllowedPrice(Product|int $product): float
    {
        if (is_int($product)) {
            $product = Product::withoutGlobalScopes()->find($product);
        }

        if (!$product) {
            return 0.0;
        }

        $sellPrice = (float) ($product->sell_price ?? 0);
        $costPrice = (float) ($product->cost_price ?? 0);
        $hargaBatas = (float) ($product->harga_batas ?? 0);

        if ($hargaBatas > 0 && $sellPrice > 0) {
            $floorPrice = $sellPrice * (1.0 - ($hargaBatas / 100.0));
        } else {
            $floorPrice = $sellPrice > 0 ? $sellPrice : $costPrice;
        }

        // Harga minimum tidak boleh di bawah modal jika modal terdefinisi
        if ($costPrice > 0) {
            $floorPrice = max($floorPrice, $costPrice);
        }

        return round($floorPrice, 2);
    }

    /**
     * Validasi apakah harga satuan yang dimasukkan memenuhi aturan batasan harga.
     *
     * @return string|null Pesan error jika melanggar, atau null jika valid.
     */
    public static function validatePrice(Product|int $product, float $unitPrice): ?string
    {
        if (is_int($product)) {
            $product = Product::withoutGlobalScopes()->find($product);
        }

        if (!$product) {
            return null;
        }

        $minPrice = self::getMinimumAllowedPrice($product);
        if ($minPrice > 0 && $unitPrice < ($minPrice - 0.009)) {
            $productName = $product->name ?: "Produk #{$product->id}";
            $formattedInput = number_format($unitPrice, 2, ',', '.');
            $formattedMin = number_format($minPrice, 2, ',', '.');
            return "Harga satuan untuk '{$productName}' (Rp {$formattedInput}) di bawah batas harga minimum yang diizinkan (Rp {$formattedMin}).";
        }

        return null;
    }
}
