<?php

namespace App\Observers;

use App\Models\InventoryStock;
use App\Models\StockReservation;
use Illuminate\Support\Facades\Log;

class StockReservationObserver
{
    /**
     * Handle the StockReservation "created" event.
     */
    public function created(StockReservation $stockReservation): void
    {
        $this->updateReservedStock($stockReservation, 'increment');
    }

    /**
     * Handle the StockReservation "updated" event.
     */
    public function updated(StockReservation $stockReservation): void
    {
        // Only handle quantity increases, not decreases (decreases are handled manually in partial releases)
        $originalQuantity = $stockReservation->getOriginal('quantity');
        $newQuantity = $stockReservation->quantity;

        if ($originalQuantity !== $newQuantity && $newQuantity > $originalQuantity) {
            $difference = $newQuantity - $originalQuantity;
            $this->updateReservedStock($stockReservation, 'increment', $difference);
        }
    }

    /**
     * Handle the StockReservation "deleted" event.
     */
    public function deleted(StockReservation $stockReservation): void
    {
        $this->updateReservedStock($stockReservation, 'decrement');
    }

    /**
     * Handle the StockReservation "restored" event.
     */
    public function restored(StockReservation $stockReservation): void
    {
        $this->updateReservedStock($stockReservation, 'increment');
    }

    /**
     * Handle the StockReservation "force deleted" event.
     */
    public function forceDeleted(StockReservation $stockReservation): void
    {
        $this->updateReservedStock($stockReservation, 'decrement');
    }

    /**
     * Update the reserved stock quantity in inventory.
     */
    private function updateReservedStock(StockReservation $stockReservation, string $operation, ?float $quantity = null): void
    {
        $inventoryStock = InventoryStock::where('product_id', $stockReservation->product_id)
            ->where('warehouse_id', $stockReservation->warehouse_id)
            ->first();

        if (!$inventoryStock) {
            // Create inventory stock if it doesn't exist
            $inventoryStock = InventoryStock::create([
                'product_id' => $stockReservation->product_id,
                'warehouse_id' => $stockReservation->warehouse_id,
                'rak_id' => $stockReservation->rak_id,
                'qty_available' => 0,
                'qty_reserved' => 0,
                'qty_min' => 0,
            ]);
        }

        $qtyToUpdate = $quantity ?? $stockReservation->quantity;

        if ($operation === 'increment') {
            $inventoryStock->increment('qty_reserved', $qtyToUpdate);
        } elseif ($operation === 'decrement') {
            $inventoryStock->decrement('qty_reserved', $qtyToUpdate);
        }
    }
}