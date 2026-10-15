<?php

namespace App\Support;

use App\Models\Product;

class ProductPriceLimitation
{
    /** Nilai harga_batas di atas ini dianggap nominal rupiah, bukan persen. */
    public const MAX_PERCENT = 100.0;

    /**
     * Hitung harga batas minimum yang diizinkan untuk sebuah produk.
     *
     * Aturan:
     * - sell_price: Harga jual acuan / standar
     * - cost_price: Harga modal / HPP acuan
     * - harga_batas: umumnya persentase toleransi diskon maksimal (0 - 100%). Data hasil migrasi legacy
     *   menyimpan HARGA BATAS NOMINAL (Rp) di kolom yang sama; nilai > 100 mustahil berupa persen,
     *   sehingga diperlakukan sebagai harga minimum nominal.
     *
     * Formula:
     * Jika harga_batas > 100 (nominal):
     *   floor_price = harga_batas
     * Jika 0 < harga_batas <= 100 (persen):
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

        if ($hargaBatas > self::MAX_PERCENT) {
            $floorPrice = $hargaBatas;
        } elseif ($hargaBatas > 0 && $sellPrice > 0) {
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
    public static function validatePrice(Product|int $product, float $unitPrice, float $discountPercent = 0.0): ?string
    {
        if (is_int($product)) {
            $product = Product::withoutGlobalScopes()->find($product);
        }

        if (!$product) {
            return null;
        }

        // Harga efektif = harga satuan setelah diskon baris; diskon tidak boleh menembus batas harga.
        $discountPercent = max(0.0, min(100.0, $discountPercent));
        $effectivePrice = $unitPrice * (1.0 - $discountPercent / 100.0);

        $minPrice = self::getMinimumAllowedPrice($product);
        if ($minPrice > 0 && $effectivePrice < ($minPrice - 0.009)) {
            $productName = $product->name ?: "Produk #{$product->id}";
            $formattedInput = number_format($effectivePrice, 2, ',', '.');
            $formattedMin = number_format($minPrice, 2, ',', '.');
            $afterDiscount = $discountPercent > 0 ? ' setelah diskon ' . rtrim(rtrim(number_format($discountPercent, 2, ',', '.'), '0'), ',') . '%' : '';
            return "Harga satuan untuk '{$productName}' (Rp {$formattedInput}{$afterDiscount}) di bawah batas harga minimum yang diizinkan (Rp {$formattedMin}).";
        }

        return null;
    }
}
