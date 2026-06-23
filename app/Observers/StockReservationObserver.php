<?php

namespace App\Observers;

use App\Models\InventoryStock;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockReservationObserver
{
    public function created(StockReservation $stockReservation): void
    {
        Log::info('StockReservationObserver: created', [
            'reservation_id' => $stockReservation->id,
            'material_issue_id' => $stockReservation->material_issue_id,
            'quantity' => $stockReservation->quantity,
        ]);
        $this->updateReservedStock($stockReservation, 'increment');
    }

    public function updated(StockReservation $stockReservation): void
    {
        $originalQuantity = $stockReservation->getOriginal('quantity');
        $newQuantity = $stockReservation->quantity;

        if ($originalQuantity !== $newQuantity && $newQuantity > $originalQuantity) {
            $difference = $newQuantity - $originalQuantity;
            $this->updateReservedStock($stockReservation, 'increment', $difference);
        }
    }

    public function deleted(StockReservation $stockReservation): void
    {
        Log::info('StockReservationObserver: deleted', [
            'reservation_id' => $stockReservation->id,
            'product_id' => $stockReservation->product_id,
            'quantity' => $stockReservation->quantity,
        ]);
        $this->updateReservedStock($stockReservation, 'decrement');
    }

    public function restored(StockReservation $stockReservation): void
    {
        $this->updateReservedStock($stockReservation, 'increment');
    }

    public function forceDeleted(StockReservation $stockReservation): void
    {
        $this->updateReservedStock($stockReservation, 'decrement');
    }

    private function updateReservedStock(StockReservation $stockReservation, string $operation, ?float $quantity = null): void
    {
        Log::info('StockReservationObserver: updateReservedStock', [
            'operation' => $operation,
            'reservation_id' => $stockReservation->id,
            'product_id' => $stockReservation->product_id,
            'warehouse_id' => $stockReservation->warehouse_id,
            'quantity' => $quantity ?? $stockReservation->quantity,
        ]);

        DB::transaction(function () use ($stockReservation, $operation, $quantity) {
            $inventoryStock = InventoryStock::where('product_id', $stockReservation->product_id)
                ->where('warehouse_id', $stockReservation->warehouse_id)
                ->lockForUpdate()
                ->first();

            if (!$inventoryStock) {
                $inventoryStock = InventoryStock::create([
                    'product_id' => $stockReservation->product_id,
                    'warehouse_id' => $stockReservation->warehouse_id,
                    'rak_id' => $stockReservation->rak_id,
                    'qty_available' => 0,
                    'qty_reserved' => 0,
                    'qty_min' => 0,
                ]);
            }

            $qtyToUpdate = (float) ($quantity ?? $stockReservation->quantity);
            $isMaterialIssueReservation = $stockReservation->material_issue_id !== null;

            if ($isMaterialIssueReservation) {
                if ($operation === 'increment') {
                    $inventoryStock->increment('qty_reserved', $qtyToUpdate);
                } elseif ($operation === 'decrement') {
                    $inventoryStock->decrement('qty_reserved', $qtyToUpdate);
                }
                return;
            }

            // NON-MATERIAL ISSUE (Delivery Order)
            if ($operation === 'increment') {
                $inventoryStock->increment('qty_reserved', $qtyToUpdate);
                // qty_available TIDAK dikurangi di sini
                // qty_available berkurang SAAT delivery selesai (handleCompletedStatus)
            } elseif ($operation === 'decrement') {
                $inventoryStock->decrement('qty_reserved', $qtyToUpdate);
            }
        });
    }
}
