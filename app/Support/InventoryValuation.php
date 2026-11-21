<?php

namespace App\Support;

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockMovement;

/**
 * Nilai persediaan berbasis HPP RATA-RATA BERGERAK (averaging).
 *
 * Keputusan Ko Handy:
 *  - 10 Nov 2026: Laporan Stok DAN Buku Besar sama-sama menilai persediaan pada HPP (`products.cost_price`).
 *  - 21 Nov 2026: HPP menganut prinsip AVERAGING (seperti software lama); "harga beli terakhir" adalah REFERENSI terpisah,
 *    bukan HPP (`products.last_purchase_price`).
 *
 * Saat barang pembelian masuk: Persediaan didebit sebesar harga beli aktual (sama dengan akun barang-diterima-belum-ditagih),
 * lalu HPP produk dihitung ulang = (qty lama × HPP lama + nilai pembelian) ÷ (qty lama + qty masuk). Dengan begitu saldo Buku
 * Besar = Σ qty × HPP rata-rata = nilai Laporan Stok. Selisih harga faktur vs PO tetap ke akun Selisih Pembelian (5160).
 */
class InventoryValuation
{
    /** Selisih di bawah ini dianggap nol (pembulatan sen). */
    public const EPSILON = 0.005;

    /**
     * HPP per unit (IDR) untuk menilai barang KELUAR / penyesuaian. Produk tanpa HPP (cost_price ≤ 0) memakai harga cadangan.
     */
    public static function unitCost(?Product $product, float $fallbackUnitPriceIdr): float
    {
        $hpp = (float) ($product?->cost_price ?? 0);

        return $hpp > 0 ? $hpp : $fallbackUnitPriceIdr;
    }

    public static function varianceCoa(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', config('coa.purchase_price_variance', '5160'))->first()
            ?? ChartOfAccount::where('name', 'like', '%Selisih Pembelian%')->first();
    }

    /**
     * Rincian penilaian satu penerimaan pembelian (rata-rata bergerak): persediaan = harga beli aktual, tanpa selisih HPP.
     *
     * @return array{inventory_amount: float, counter_amount: float, variance: float, unit_cost: float, variance_coa: ?ChartOfAccount}
     */
    public static function forPurchase(?Product $product, float $quantity, float $poUnitPriceIdr): array
    {
        $amount = round($quantity * $poUnitPriceIdr, 2);

        return ['inventory_amount' => $amount, 'counter_amount' => $amount, 'variance' => 0.0, 'unit_cost' => $poUnitPriceIdr, 'variance_coa' => null];
    }

    /**
     * Perbarui HPP rata-rata & harga beli terakhir produk dari satu gerakan `purchase_in`. Dipanggil StockMovementObserver
     * SEBELUM stok gudang ditambah, sehingga qty yang dibaca adalah stok sebelum penerimaan ini.
     */
    public static function applyPurchaseToAverage(StockMovement $movement): void
    {
        if ($movement->type !== 'purchase_in' || ! $movement->product_id) {
            return;
        }

        $qtyIn = (float) $movement->quantity;
        if ($qtyIn <= 0) {
            return;
        }

        $unitPrice = (float) (data_get($movement->meta, 'po_unit_price_idr') ?: ((float) $movement->value / $qtyIn));
        if ($unitPrice <= 0) {
            return;
        }

        $product = Product::withoutGlobalScopes()->find($movement->product_id);
        if (! $product) {
            return;
        }

        $qtyBefore = max(0.0, (float) InventoryStock::query()->where('product_id', $product->id)->sum('qty_available'));
        $costBefore = (float) ($product->cost_price ?? 0);

        $newCost = ($qtyBefore > 0 && $costBefore > 0)
            ? (($qtyBefore * $costBefore) + ($qtyIn * $unitPrice)) / ($qtyBefore + $qtyIn)
            : $unitPrice;

        $product->forceFill([
            'cost_price' => round($newCost, 2),
            'last_purchase_price' => round($unitPrice, 2),
            'last_purchase_at' => self::dateString($movement->date),
        ])->saveQuietly();
    }

    private static function dateString(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return $date ? substr((string) $date, 0, 10) : now()->toDateString();
    }
}
