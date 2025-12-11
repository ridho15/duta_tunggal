<?php

namespace App\Observers;

use App\Models\InventoryStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;

class StockMovementObserver
{
    private static $originalQuantities = [];

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

        // Skip stock update if flag is set (e.g., for material issue completed movements)
        if (isset($stockMovement->meta['skip_stock_update']) && $stockMovement->meta['skip_stock_update']) {
            return;
        }

        if (in_array($stockMovement->type, $inTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available + $stockMovement->quantity;
            $inventoryStock->save();
        } elseif (in_array($stockMovement->type, $outTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available - $stockMovement->quantity;
            $inventoryStock->save();
        }
    }

    /**
     * Handle the StockMovement "updating" event.
     */
    public function updating(StockMovement $stockMovement): void
    {
        // Store original quantity before update
        self::$originalQuantities[$stockMovement->id] = $stockMovement->getOriginal('quantity');
    }

    /**
     * Handle the StockMovement "updated" event.
     */
    public function updated(StockMovement $stockMovement): void
    {
        // Skip if this is a new record (should be handled by created event)
        // Note: wasRecentlyCreated check removed because it can be true for updates in some cases
        // if ($stockMovement->wasRecentlyCreated) {
        //     return;
        // }

        // Only handle quantity changes that affect inventory
        if (!isset(self::$originalQuantities[$stockMovement->id])) {
            return;
        }

        $originalQuantity = self::$originalQuantities[$stockMovement->id];
        $currentQuantity = $stockMovement->quantity;

        if ($originalQuantity == $currentQuantity) {
            unset(self::$originalQuantities[$stockMovement->id]);
            return;
        }

        $inventoryStock = InventoryStock::where('product_id', $stockMovement->product_id)
            ->where('warehouse_id', $stockMovement->warehouse_id)
            ->first();

        if (!$inventoryStock) {
            unset(self::$originalQuantities[$stockMovement->id]);
            return;
        }

        $inTypes = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'];
        $outTypes = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];

        // Skip stock update if flag is set (e.g., for material issue completed movements)
        if (isset($stockMovement->meta['skip_stock_update']) && $stockMovement->meta['skip_stock_update']) {
            unset(self::$originalQuantities[$stockMovement->id]);
            return;
        }

        $quantityDiff = $currentQuantity - $originalQuantity;

        if (in_array($stockMovement->type, $inTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available + $quantityDiff;
            $inventoryStock->save();
        } elseif (in_array($stockMovement->type, $outTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available - $quantityDiff;
            $inventoryStock->save();
        }

        unset(self::$originalQuantities[$stockMovement->id]);
    }

    /**
     * Handle the StockMovement "deleted" event.
     */
    public function deleted(StockMovement $stockMovement): void
    {
        $inventoryStock = InventoryStock::where('product_id', $stockMovement->product_id)
            ->where('warehouse_id', $stockMovement->warehouse_id)
            ->first();

        if (!$inventoryStock) {
            return;
        }

        $inTypes = ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'];
        $outTypes = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];

        // Skip stock update if flag is set (e.g., for material issue completed movements)
        if (isset($stockMovement->meta['skip_stock_update']) && $stockMovement->meta['skip_stock_update']) {
            return;
        }

        // Reverse the stock movement effect when deleted
        if (in_array($stockMovement->type, $inTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available - $stockMovement->quantity;
            $inventoryStock->save();
        } elseif (in_array($stockMovement->type, $outTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available + $stockMovement->quantity;
            $inventoryStock->save();
        }
    }

    /**
     * Handle the StockMovement "restored" event.
     */
    public function restored(StockMovement $stockMovement): void
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

        // Skip stock update if flag is set (e.g., for material issue completed movements)
        if (isset($stockMovement->meta['skip_stock_update']) && $stockMovement->meta['skip_stock_update']) {
            return;
        }

        // Re-apply the stock movement effect when restored
        if (in_array($stockMovement->type, $inTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available + $stockMovement->quantity;
            $inventoryStock->save();
        } elseif (in_array($stockMovement->type, $outTypes, true)) {
            $inventoryStock->qty_available = $inventoryStock->qty_available - $stockMovement->quantity;
            $inventoryStock->save();
        }
    }

    /**
     * Handle the StockMovement "force deleted" event.
     */
    public function forceDeleted(StockMovement $stockMovement): void
    {
        //
    }
}
