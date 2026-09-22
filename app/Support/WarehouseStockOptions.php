<?php

namespace App\Support;

use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class WarehouseStockOptions
{
    /**
     * Daftar gudang aktif untuk dropdown, difilter per cabang dan disertai label stok.
     *
     * @param  int|null  $productId            ID produk untuk menghitung stok tersedia.
     * @param  int|null  $selectedWarehouseId  Gudang yang sudah terpilih — selalu disertakan
     *                                         meski di luar filter, agar nilai lama tidak hilang.
     * @param  bool      $includeStockLabel    Sertakan "- Stok: X" di label opsi.
     * @param  int|null  $cabangId             Filter eksplisit berdasarkan cabang (mis. cabang SO).
     *                                         Bila diberikan, menggantikan filter cabang user login,
     *                                         sehingga Super Admin pun dibatasi ke cabang ini.
     *                                         Bila null, perilaku lama berlaku (filter per cabang user
     *                                         atau semua gudang untuk manage_type = all).
     */
    public static function forProduct(
        ?int $productId,
        ?int $selectedWarehouseId = null,
        bool $includeStockLabel = true,
        ?int $cabangId = null,
    ): array {
        $user = Auth::user();
        $manageType = $user?->manage_type ?? [];

        $stockByWarehouse = collect();

        if ($productId) {
            $stockByWarehouse = InventoryStock::query()
                ->selectRaw('warehouse_id, SUM(qty_available) as total_qty')
                ->where('product_id', $productId)
                ->where('qty_available', '>', 0)
                ->groupBy('warehouse_id')
                ->pluck('total_qty', 'warehouse_id');
        }

        $query = Warehouse::query()->where('status', 1);

        if ($cabangId) {
            // Filter eksplisit dari parameter (prioritas utama — misalnya cabang SO).
            // Berlaku untuk semua role termasuk Super Admin/Owner.
            $query->where('cabang_id', $cabangId);
        } elseif (! $user || ! is_array($manageType) || ! in_array('all', $manageType)) {
            // Fallback: filter dari cabang user login (untuk user non-all).
            if ($user?->cabang_id) {
                $query->where('cabang_id', $user->cabang_id);
            }
        }
        // Super Admin tanpa $cabangId eksplisit → melihat semua gudang (behaviour lama).

        $warehouses = $query->orderBy('name')->get();

        // Jika filter menghasilkan 0 gudang (mis. cabang SO belum punya gudang aktif),
        // fallback ke semua gudang aktif agar form tidak kosong.
        if ($warehouses->isEmpty()) {
            $warehouses = Warehouse::query()->where('status', 1)->orderBy('name')->get();
        }

        // Selalu sertakan gudang yang sudah terpilih meski di luar filter cabang,
        // agar nilai yang tersimpan sebelumnya tidak menjadi opsi tidak valid.
        if ($selectedWarehouseId && ! $warehouses->contains('id', $selectedWarehouseId)) {
            $selected = Warehouse::find($selectedWarehouseId);
            if ($selected) {
                $warehouses->push($selected);
            }
        }

        return $warehouses
            ->mapWithKeys(function (Warehouse $warehouse) use ($stockByWarehouse, $includeStockLabel) {
                $stockLabel = '';

                if ($includeStockLabel) {
                    $qty = (float) ($stockByWarehouse[$warehouse->id] ?? 0);
                    $stockLabel = ' - Stok: ' . number_format($qty, 0, ',', '.');
                }

                return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}{$stockLabel}"];
            })
            ->toArray();
    }
}