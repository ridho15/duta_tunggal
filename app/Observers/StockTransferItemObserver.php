<?php

namespace App\Observers;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockMovement;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\Log;

class StockTransferItemObserver
{
    /**
     * Handle the StockTransferItem "created" event.
     */
    public function created(StockTransferItem $stockTransferItem): void
    {
        Log::info("StockTransferItemObserver: created method called for item ID {$stockTransferItem->id}");
        // Create StockMovement records for the transfer
        $this->createStockMovements($stockTransferItem);
    }

    /**
     * Handle the StockTransferItem "updated" event.
     */
    public function updated(StockTransferItem $stockTransferItem): void
    {
        if ($stockTransferItem->isDirty('quantity')) {
            $oldQuantity = $stockTransferItem->getOriginal('quantity');
            $this->reverseInventoryStocks($stockTransferItem, $oldQuantity);
            $this->updateStockMovements($stockTransferItem);
            $this->updateInventoryStocks($stockTransferItem);
        }
    }

    /**
     * Handle the StockTransferItem "deleted" event.
     */
    public function deleted(StockTransferItem $stockTransferItem): void
    {
        // Delete associated StockMovement records
        $this->deleteStockMovements($stockTransferItem);
    }

    /**
     * Handle the StockTransferItem "restored" event.
     */
    public function restored(StockTransferItem $stockTransferItem): void
    {
        // Restore StockMovement records
        $this->createStockMovements($stockTransferItem);
    }

    /**
     * Handle the StockTransferItem "force deleted" event.
     */
    public function forceDeleted(StockTransferItem $stockTransferItem): void
    {
        // Force delete associated StockMovement records
        $this->deleteStockMovements($stockTransferItem, true);
    }

    /**
     * Create StockMovement records for stock transfer
     */
    private function createStockMovements(StockTransferItem $stockTransferItem): void
    {
        $stockTransfer = $stockTransferItem->stockTransfer;

        // Create outgoing movement from source warehouse
        StockMovement::create([
            'product_id' => $stockTransferItem->product_id,
            'warehouse_id' => $stockTransferItem->from_warehouse_id,
            'rak_id' => $stockTransferItem->from_rak_id,
            'quantity' => -$stockTransferItem->quantity, // Negative for outgoing
            'type' => 'transfer_out',
            'from_model_type' => StockTransfer::class,
            'from_model_id' => $stockTransfer->id,
            'reference_id' => $stockTransfer->transfer_number,
            'date' => $stockTransfer->transfer_date,
            'notes' => "Transfer to warehouse {$stockTransfer->toWarehouse->name}",
            'meta' => ['skip_stock_update' => true],
        ]);

        // Create incoming movement to destination warehouse
        StockMovement::create([
            'product_id' => $stockTransferItem->product_id,
            'warehouse_id' => $stockTransferItem->to_warehouse_id,
            'rak_id' => $stockTransferItem->to_rak_id,
            'quantity' => $stockTransferItem->quantity, // Positive for incoming
            'type' => 'transfer_in',
            'from_model_type' => StockTransfer::class,
            'from_model_id' => $stockTransfer->id,
            'reference_id' => $stockTransfer->transfer_number,
            'date' => $stockTransfer->transfer_date,
            'notes' => "Transfer from warehouse {$stockTransfer->fromWarehouse->name}",
            'meta' => ['skip_stock_update' => true],
        ]);

        // Update inventory stocks
        $this->updateInventoryStocks($stockTransferItem);
    }

    /**
     * Update StockMovement records for stock transfer
     */
    private function updateStockMovements(StockTransferItem $stockTransferItem): void
    {
        $stockTransfer = $stockTransferItem->stockTransfer;

        // Delete existing movements and recreate them
        $this->deleteStockMovements($stockTransferItem);
        $this->createStockMovements($stockTransferItem);
    }

    /**
     * Delete StockMovement records for stock transfer
     */
    private function deleteStockMovements(StockTransferItem $stockTransferItem, bool $forceDelete = false): void
    {
        $stockTransfer = $stockTransferItem->stockTransfer;

        $query = StockMovement::where('from_model_type', StockTransfer::class)
            ->where('from_model_id', $stockTransfer->id)
            ->where('product_id', $stockTransferItem->product_id);

        if ($forceDelete) {
            $query->forceDelete();
        } else {
            $query->delete();
        }

        // Reverse inventory stock adjustments
        $this->reverseInventoryStocks($stockTransferItem);
    }

    /**
     * Update inventory stocks based on stock movements
     */
    private function updateInventoryStocks(StockTransferItem $stockTransferItem): void
    {
        Log::info("StockTransferItemObserver: Updating inventory stocks for item ID {$stockTransferItem->id}");

        // Decrease stock in source warehouse/rak
        $sourceStock = InventoryStock::where('product_id', $stockTransferItem->product_id)
            ->where('warehouse_id', $stockTransferItem->from_warehouse_id)
            ->where('rak_id', $stockTransferItem->from_rak_id)
            ->first();

        if ($sourceStock) {
            $oldQty = $sourceStock->qty_available;
            $newQty = $sourceStock->qty_available - $stockTransferItem->quantity;
            $sourceStock->update(['qty_available' => $newQty]);
            Log::info("StockTransferItemObserver: Decreased source stock from {$oldQty} to {$newQty}");
        }

        // Increase stock in destination warehouse/rak
        $destinationStock = InventoryStock::where('product_id', $stockTransferItem->product_id)
            ->where('warehouse_id', $stockTransferItem->to_warehouse_id)
            ->where('rak_id', $stockTransferItem->to_rak_id)
            ->first();

        if ($destinationStock) {
            $oldQty = $destinationStock->qty_available;
            $newQty = $destinationStock->qty_available + $stockTransferItem->quantity;
            $destinationStock->update(['qty_available' => $newQty]);
            Log::info("StockTransferItemObserver: Increased destination stock from {$oldQty} to {$newQty}");
        } else {
            // Create new inventory stock record if it doesn't exist
            InventoryStock::create([
                'product_id' => $stockTransferItem->product_id,
                'warehouse_id' => $stockTransferItem->to_warehouse_id,
                'rak_id' => $stockTransferItem->to_rak_id,
                'qty_available' => $stockTransferItem->quantity,
                'qty_reserved' => 0,
                'qty_min' => 0,
            ]);
            Log::info("StockTransferItemObserver: Created new destination stock with quantity {$stockTransferItem->quantity}");
        }
    }

    /**
     * Reverse inventory stock adjustments
     */
    private function reverseInventoryStocks(StockTransferItem $stockTransferItem, ?float $quantity = null): void
    {
        $quantity = $quantity ?? $stockTransferItem->quantity;

        // Increase stock back in source warehouse/rak
        $sourceStock = InventoryStock::where('product_id', $stockTransferItem->product_id)
            ->where('warehouse_id', $stockTransferItem->from_warehouse_id)
            ->where('rak_id', $stockTransferItem->from_rak_id)
            ->first();

        if ($sourceStock) {
            $sourceStock->update(['qty_available' => $sourceStock->qty_available + $quantity]);
        }

        // Decrease stock in destination warehouse/rak
        $destinationStock = InventoryStock::where('product_id', $stockTransferItem->product_id)
            ->where('warehouse_id', $stockTransferItem->to_warehouse_id)
            ->where('rak_id', $stockTransferItem->to_rak_id)
            ->first();

        if ($destinationStock) {
            $destinationStock->update(['qty_available' => $destinationStock->qty_available - $quantity]);
        }
    }
}