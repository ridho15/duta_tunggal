<?php

namespace App\Support;

use App\Models\ChartOfAccount;
use App\Models\Product;

/**
 * Nilai persediaan berbasis HPP (keputusan Ko Handy 10 Nov 2026): Laporan Stok DAN Buku Besar sama-sama menilai persediaan
 * pada HPP master produk (`products.cost_price`), bukan harga PO.
 *
 * Saat barang masuk dari pembelian: Persediaan didebit pada HPP, akun lawan (barang diterima belum ditagih / pengadaan sementara)
 * tetap pada harga PO agar faktur pembelian & pembersihannya tidak berubah, dan selisih (harga PO − HPP) dibukukan ke akun
 * Selisih Pembelian (`config('coa.purchase_price_variance')`, default 5160) — akun yang sama dengan selisih harga faktur vs PO.
 */
class InventoryValuation
{
    /** Selisih di bawah ini dianggap nol (pembulatan sen). */
    public const EPSILON = 0.005;

    /**
     * HPP per unit (IDR) untuk menilai persediaan. Produk tanpa HPP (cost_price ≤ 0) memakai harga PO agar barang tidak bernilai nol.
     */
    public static function unitCost(?Product $product, float $poUnitPriceIdr): float
    {
        $hpp = (float) ($product?->cost_price ?? 0);

        return $hpp > 0 ? $hpp : $poUnitPriceIdr;
    }

    public static function varianceCoa(): ?ChartOfAccount
    {
        return ChartOfAccount::where('code', config('coa.purchase_price_variance', '5160'))->first()
            ?? ChartOfAccount::where('name', 'like', '%Selisih Pembelian%')->first();
    }

    /**
     * Rincian penilaian satu penerimaan.
     *
     * @return array{inventory_amount: float, counter_amount: float, variance: float, unit_cost: float, variance_coa: ?ChartOfAccount}
     *         variance > 0 = harga PO di atas HPP (debit Selisih Pembelian); < 0 = di bawah HPP (kredit).
     *         Bila ada selisih tetapi akun Selisih Pembelian tidak ada, persediaan dinilai pada harga PO (perilaku lama) agar jurnal tetap seimbang.
     */
    public static function forPurchase(?Product $product, float $quantity, float $poUnitPriceIdr): array
    {
        $counterAmount = round($quantity * $poUnitPriceIdr, 2);
        $unitCost = self::unitCost($product, $poUnitPriceIdr);
        $inventoryAmount = round($quantity * $unitCost, 2);
        $variance = round($counterAmount - $inventoryAmount, 2);
        $varianceCoa = abs($variance) > self::EPSILON ? self::varianceCoa() : null;

        if (abs($variance) > self::EPSILON && ! $varianceCoa) {
            \Illuminate\Support\Facades\Log::warning('InventoryValuation: akun Selisih Pembelian tidak ditemukan; persediaan dinilai pada harga PO', [
                'product_id' => $product?->id, 'coa_code' => config('coa.purchase_price_variance', '5160'),
            ]);

            return ['inventory_amount' => $counterAmount, 'counter_amount' => $counterAmount, 'variance' => 0.0, 'unit_cost' => $poUnitPriceIdr, 'variance_coa' => null];
        }

        if (abs($variance) <= self::EPSILON) {
            // Selisih pembulatan: samakan agar jurnal seimbang persis.
            return ['inventory_amount' => $counterAmount, 'counter_amount' => $counterAmount, 'variance' => 0.0, 'unit_cost' => $unitCost, 'variance_coa' => null];
        }

        return [
            'inventory_amount' => $inventoryAmount,
            'counter_amount' => $counterAmount,
            'variance' => $variance,
            'unit_cost' => $unitCost,
            'variance_coa' => $varianceCoa,
        ];
    }
}
