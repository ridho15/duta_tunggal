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

        $warehouseIds = collect($stockByWarehouse->keys())
            ->when($selectedWarehouseId, fn (Collection $ids) => $ids->push($selectedWarehouseId))
            ->filter()
            ->unique()
            ->values();

        if ($warehouseIds->isEmpty()) {
            return [];
        }

        $query = Warehouse::query()
            ->where('status', 1)
            ->whereIn('id', $warehouseIds->all());

        if (! $user || ! is_array($manageType) || ! in_array('all', $manageType)) {
            $query->where('cabang_id', $user?->cabang_id);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Warehouse $warehouse) use ($stockByWarehouse, $includeStockLabel) {
                $stockLabel = '';

                if ($includeStockLabel && isset($stockByWarehouse[$warehouse->id])) {
                    $stockLabel = ' - Stok: ' . number_format((float) $stockByWarehouse[$warehouse->id], 0, ',', '.');
                }

                return [$warehouse->id => "({$warehouse->kode}) {$warehouse->name}{$stockLabel}"];
            })
            ->toArray();
    }
}