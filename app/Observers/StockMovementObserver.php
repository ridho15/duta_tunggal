<?php

namespace App\Observers;

use App\Models\InventoryStock;
use App\Models\StockMovement;

class StockMovementObserver
{
    /**
     * Handle the StockMovement "created" event.
     */
    public function created(StockMovement $stockMovement): void
    {
        $inventoryStock = InventoryStock::where('product_id', $stockMovement->product_id)
            ->where('warehouse_id', $stockMovement->warehouse_id)
            ->first();
        if (!$inventoryStock) {
            $inventoryStock = InventoryStock::create([
                'product_id' => $stockMovement->product_id,
                'warehouse_id' => $stockMovement->warehouse_id,
                'rak_id' => $stockMovement->rak_id,
                'qty_available' => 0,
                'qty_reserved' => 0,
            ]);
        }

        $inTypes = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'];
        $outTypes = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];

        if (in_array($stockMovement->type, $inTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available + $stockMovement->quantity;
            $inventoryStock->save();
        } elseif (in_array($stockMovement->type, $outTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available - $stockMovement->quantity;
            $inventoryStock->save();
        }
    }

    /**
     * Handle the StockMovement "updated" event.
     */
    public function updated(StockMovement $stockMovement): void
    {
        //
    }

    /**
     * Handle the StockMovement "deleted" event.
     */
    public function deleted(StockMovement $stockMovement): void
    {
        //
    }

    /**
     * Handle the StockMovement "restored" event.
     */
    public function restored(StockMovement $stockMovement): void
    {
        //
    }

    /**
     * Handle the StockMovement "force deleted" event.
     */
    public function forceDeleted(StockMovement $stockMovement): void
    {
        //
    }
}
