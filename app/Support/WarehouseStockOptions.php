<?php

namespace App\Support;

use App\Models\InventoryStock;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class WarehouseStockOptions
{
    public static function forProduct(?int $productId, ?int $selectedWarehouseId = null, bool $includeStockLabel = true): array
    {
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

        if (! $user || ! is_array($manageType) || ! in_array('all', $manageType)) {
            if ($user?->cabang_id) {
                $query->where('cabang_id', $user->cabang_id);
            }
        }

        $warehouses = $query->orderBy('name')->get();

        // If branch filtering resulted in 0 warehouses, fallback to all active warehouses
        if ($warehouses->isEmpty()) {
            $warehouses = Warehouse::query()->where('status', 1)->orderBy('name')->get();
        }

        // Include selected warehouse if not already in collection
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