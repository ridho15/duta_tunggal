<?php

namespace App\Observers;

use App\Models\InventoryStock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockMovementObserver
{
    private static $originalStates = [];

    /**
     * Handle the StockMovement "created" event.
     */
    public function created(StockMovement $stockMovement): void
    {
        if ($this->shouldSkipStockUpdate($stockMovement->meta)) {
            return;
        }

        $delta = $this->stockEffectDelta($stockMovement->type, $stockMovement->quantity);
        if ($delta !== 0.0) {
            $this->adjustAvailableStockByKey(
                $stockMovement->product_id,
                $stockMovement->warehouse_id,
                $stockMovement->rak_id,
                $delta
            );
        }
    }

    /**
     * Handle the StockMovement "updating" event.
     */
    public function updating(StockMovement $stockMovement): void
    {
        self::$originalStates[$stockMovement->id] = [
            'product_id' => $stockMovement->getOriginal('product_id'),
            'warehouse_id' => $stockMovement->getOriginal('warehouse_id'),
            'rak_id' => $stockMovement->getOriginal('rak_id'),
            'quantity' => $stockMovement->getOriginal('quantity'),
            'type' => $stockMovement->getOriginal('type'),
            'meta' => $stockMovement->getOriginal('meta'),
        ];
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
        if (!isset(self::$originalStates[$stockMovement->id])) {
            return;
        }

        $originalState = self::$originalStates[$stockMovement->id];
        unset(self::$originalStates[$stockMovement->id]);

        $currentState = [
            'product_id' => $stockMovement->product_id,
            'warehouse_id' => $stockMovement->warehouse_id,
            'rak_id' => $stockMovement->rak_id,
            'quantity' => $stockMovement->quantity,
            'type' => $stockMovement->type,
            'meta' => $stockMovement->meta,
        ];

        if (
            $originalState['product_id'] == $currentState['product_id']
            && $originalState['warehouse_id'] == $currentState['warehouse_id']
            && $originalState['rak_id'] == $currentState['rak_id']
            && $this->stockEffectDelta($originalState['type'], $originalState['quantity']) === $this->stockEffectDelta($currentState['type'], $currentState['quantity'])
            && $this->shouldSkipStockUpdate($originalState['meta']) === $this->shouldSkipStockUpdate($currentState['meta'])
        ) {
            return;
        }

        if (! $this->shouldSkipStockUpdate($originalState['meta'])) {
            $originalDelta = $this->stockEffectDelta($originalState['type'], $originalState['quantity']);
            if ($originalDelta !== 0.0) {
                $this->adjustAvailableStockByKey(
                    $originalState['product_id'],
                    $originalState['warehouse_id'],
                    $originalState['rak_id'],
                    -1 * $originalDelta
                );
            }
        }

        if (! $this->shouldSkipStockUpdate($currentState['meta'])) {
            $currentDelta = $this->stockEffectDelta($currentState['type'], $currentState['quantity']);
            if ($currentDelta !== 0.0) {
                $this->adjustAvailableStockByKey(
                    $currentState['product_id'],
                    $currentState['warehouse_id'],
                    $currentState['rak_id'],
                    $currentDelta
                );
            }
        }
    }

    /**
     * Handle the StockMovement "deleted" event.
     */
    public function deleted(StockMovement $stockMovement): void
    {
        if ($this->shouldSkipStockUpdate($stockMovement->meta)) {
            return;
        }

        $delta = $this->stockEffectDelta($stockMovement->type, $stockMovement->quantity);
        if ($delta !== 0.0) {
            $this->adjustAvailableStockByKey(
                $stockMovement->product_id,
                $stockMovement->warehouse_id,
                $stockMovement->rak_id,
                -1 * $delta
            );
        }
    }

    /**
     * Handle the StockMovement "restored" event.
     */
    public function restored(StockMovement $stockMovement): void
    {
        if ($this->shouldSkipStockUpdate($stockMovement->meta)) {
            return;
        }

        $delta = $this->stockEffectDelta($stockMovement->type, $stockMovement->quantity);
        if ($delta !== 0.0) {
            $this->adjustAvailableStockByKey(
                $stockMovement->product_id,
                $stockMovement->warehouse_id,
                $stockMovement->rak_id,
                $delta
            );
        }
    }

    private function adjustAvailableStock(StockMovement $stockMovement, float $delta): void
    {
        $this->adjustAvailableStockByKey(
            $stockMovement->product_id,
            $stockMovement->warehouse_id,
            $stockMovement->rak_id,
            $delta
        );
    }

    private function adjustAvailableStockByKey(?int $productId, ?int $warehouseId, ?int $rakId, float $delta): void
    {
        if (! $productId || ! $warehouseId || $delta === 0.0) {
            return;
        }

        DB::transaction(function () use ($productId, $warehouseId, $rakId, $delta) {
            $inventoryStock = InventoryStock::where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('rak_id', $rakId)
                ->lockForUpdate()
                ->first();

            if (!$inventoryStock) {
                $inventoryStock = InventoryStock::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'rak_id' => $rakId,
                    'qty_available' => 0,
                    'qty_reserved' => 0,
                ]);
            }

            $inventoryStock->qty_available = (float) $inventoryStock->qty_available + $delta;
            $inventoryStock->save();
        });
    }

    private function stockEffectDelta(?string $type, mixed $quantity): float
    {
        $normalizedQuantity = abs((float) $quantity);

        return match ($type) {
            'purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in' => $normalizedQuantity,
            'sales', 'transfer_out', 'manufacture_out', 'adjustment_out' => -1 * $normalizedQuantity,
            default => 0.0,
        };
    }

    private function shouldSkipStockUpdate(mixed $meta): bool
    {
        return (bool) data_get($meta, 'skip_stock_update', false);
    }

    /**
     * Handle the StockMovement "force deleted" event.
     */
    public function forceDeleted(StockMovement $stockMovement): void
    {
        //
    }
}
