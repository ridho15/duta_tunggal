<?php

namespace App\Services;

use App\Models\StockReservation;
use App\Models\InventoryStock;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockReservationService
{
    /**
     * Reserve stock for material issue items
     */
    public function reserveStockForMaterialIssue(MaterialIssue $materialIssue): void
    {
        if (!$materialIssue->isApproved() && !$materialIssue->isPendingApproval()) {
            throw new \Exception('Material issue must be approved or pending approval to reserve stock');
        }

        Log::info('Starting reserveStockForMaterialIssue', [
            'material_issue_id' => $materialIssue->id,
            'status' => $materialIssue->status,
            'item_count' => $materialIssue->items->count(),
        ]);

        DB::transaction(function () use ($materialIssue) {
            // Delete existing reservations for this material issue
            StockReservation::where('material_issue_id', $materialIssue->id)->delete();

            // Create new reservations for each item
            foreach ($materialIssue->items as $index => $item) {
                Log::info('Processing item in reserve loop', [
                    'index' => $index,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ]);
                $warehouseId = $item->warehouse_id ?? $materialIssue->warehouse_id;

                StockReservation::create([
                    'material_issue_id' => $materialIssue->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'warehouse_id' => $warehouseId,
                    'rak_id' => $item->rak_id,
                ]);

                // Update inventory stock qty_reserved
                $inventoryStock = InventoryStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if ($inventoryStock) {
                    Log::info('Before increment check', [
                        'material_issue_id' => $materialIssue->id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                        'current_qty_reserved' => $inventoryStock->qty_reserved,
                    ]);

                    // NOTE: qty_reserved is automatically incremented by StockReservationObserver when StockReservation is created
                    // No manual increment needed here
                    
                    Log::info('Stock reservation created (observer will increment)', [
                        'material_issue_id' => $materialIssue->id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                        'item_quantity' => $item->quantity,
                    ]);
                }

                Log::info('Stock reserved for material issue item', [
                    'material_issue_id' => $materialIssue->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'warehouse_id' => $warehouseId,
                ]);
            }
        });
    }

    /**
     * Release stock reservations for material issue
     */
    public function releaseStockReservationsForMaterialIssue(MaterialIssue $materialIssue): void
    {
        DB::transaction(function () use ($materialIssue) {
            $reservations = StockReservation::where('material_issue_id', $materialIssue->id)->get();

            foreach ($reservations as $reservation) {
                // NOTE: qty_reserved is automatically decremented by StockReservationObserver when StockReservation is deleted
                // No manual decrement needed here

                Log::info('Stock reservation to be released for material issue', [
                    'material_issue_id' => $materialIssue->id,
                    'product_id' => $reservation->product_id,
                    'quantity' => $reservation->quantity,
                    'warehouse_id' => $reservation->warehouse_id,
                ]);
            }

            // Delete reservations (this will trigger StockReservationObserver deleted method)
            StockReservation::where('material_issue_id', $materialIssue->id)->delete();
        });
    }

    /**
     * Consume reserved stock when material issue is completed
     */
    public function consumeReservedStockForMaterialIssue(MaterialIssue $materialIssue): void
    {
        if (!$materialIssue->isCompleted()) {
            throw new \Exception('Material issue must be completed to consume reserved stock');
        }

        Log::info('Starting consumeReservedStockForMaterialIssue', [
            'material_issue_id' => $materialIssue->id,
            'status' => $materialIssue->status,
        ]);

        DB::transaction(function () use ($materialIssue) {
            $reservations = StockReservation::where('material_issue_id', $materialIssue->id)->get();

            foreach ($reservations as $reservation) {
                // Manually update inventory stock since observer may not fire inside transaction
                $inventoryStock = InventoryStock::where('product_id', $reservation->product_id)
                    ->where('warehouse_id', $reservation->warehouse_id)
                    ->first();

                if ($inventoryStock) {
                    // Increment available stock and decrement reserved stock
                    $inventoryStock->increment('qty_available', $reservation->quantity);
                    $inventoryStock->decrement('qty_reserved', $reservation->quantity);

                    Log::info('Manually updated stock for consumed reservation', [
                        'material_issue_id' => $materialIssue->id,
                        'product_id' => $reservation->product_id,
                        'warehouse_id' => $reservation->warehouse_id,
                        'quantity' => $reservation->quantity,
                        'new_available' => $inventoryStock->qty_available,
                        'new_reserved' => $inventoryStock->qty_reserved,
                    ]);
                }

                Log::info('Reserved stock consumed for completed material issue', [
                    'material_issue_id' => $materialIssue->id,
                    'product_id' => $reservation->product_id,
                    'quantity' => $reservation->quantity,
                    'warehouse_id' => $reservation->warehouse_id,
                ]);
            }

            // Delete reservations after updating stock
            Log::info('About to delete reservations', [
                'material_issue_id' => $materialIssue->id,
                'reservation_count' => $reservations->count(),
            ]);
            $deletedCount = StockReservation::where('material_issue_id', $materialIssue->id)->delete();
            Log::info('Deleted reservations result', [
                'material_issue_id' => $materialIssue->id,
                'deleted_count' => $deletedCount,
            ]);
        });
    }

    /**
     * Get available stock considering reservations
     */
    public function getAvailableStock(int $productId, int $warehouseId): float
    {
        $inventoryStock = InventoryStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$inventoryStock) {
            return 0;
        }

        // Since qty_available is now decremented when reserved, available stock = qty_available
        return $inventoryStock->qty_available;
    }

    /**
     * Get stock reservations for a material issue
     */
    public function getReservationsForMaterialIssue(int $materialIssueId): \Illuminate\Database\Eloquent\Collection
    {
        return StockReservation::where('material_issue_id', $materialIssueId)
            ->with(['product', 'warehouse', 'rak'])
            ->get();
    }

    /**
     * Get all active stock reservations
     */
    public function getAllActiveReservations(): \Illuminate\Database\Eloquent\Collection
    {
        return StockReservation::with(['saleOrder', 'materialIssue', 'product', 'warehouse', 'rak'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get total reserved quantity for a product in a warehouse
     */
    public function getTotalReservedQuantity(int $productId, int $warehouseId): float
    {
        return StockReservation::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * Check if stock is available for material issue items (considering reservations)
     */
    public function checkStockAvailabilityForMaterialIssue(MaterialIssue $materialIssue): array
    {
        $insufficientStock = [];
        $outOfStock = [];

        foreach ($materialIssue->items as $item) {
            $warehouseId = $item->warehouse_id ?? $materialIssue->warehouse_id;
            $availableQty = $this->getAvailableStock($item->product_id, $warehouseId);
            $requiredQty = $item->quantity;

            $warehouseName = $item->warehouse ? $item->warehouse->name :
                           ($materialIssue->warehouse ? $materialIssue->warehouse->name : 'N/A');

            if ($availableQty <= 0) {
                $outOfStock[] = "{$item->product->name} di {$warehouseName} (Stock: 0)";
            } elseif ($availableQty < $requiredQty) {
                $insufficientStock[] = "{$item->product->name} di {$warehouseName} (Dibutuhkan: " . number_format($requiredQty, 2) . ", Tersedia: " . number_format($availableQty, 2) . ")";
            }
        }

        if (!empty($outOfStock)) {
            return [
                'valid' => false,
                'message' => 'Stock habis untuk produk berikut: ' . implode(', ', $outOfStock) . '. Silakan lakukan stock adjustment atau transfer stock terlebih dahulu.'
            ];
        }

        if (!empty($insufficientStock)) {
            return [
                'valid' => false,
                'message' => 'Stock tidak mencukupi untuk produk berikut: ' . implode(', ', $insufficientStock) . '. Silakan sesuaikan quantity atau lakukan stock adjustment terlebih dahulu.'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Stock tersedia untuk semua item'
        ];
    }
}