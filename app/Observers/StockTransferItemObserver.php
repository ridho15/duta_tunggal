<?php

namespace App\Observers;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Log;

class StockTransferItemObserver
{
    /**
     * Handle the StockTransferItem "created" event.
     */
    public function created(StockTransferItem $stockTransferItem): void
    {
        Log::info("StockTransferItemObserver: created method called for item ID {$stockTransferItem->id}");

        if (! $this->shouldSyncStock($stockTransferItem)) {
            return;
        }

        $this->stockTransferService()->syncApprovedItemMovements($stockTransferItem->stockTransfer, $stockTransferItem);
    }

    /**
     * Handle the StockTransferItem "updated" event.
     */
    public function updated(StockTransferItem $stockTransferItem): void
    {
        if (! $this->shouldSyncStock($stockTransferItem)) {
            return;
        }

        if ($stockTransferItem->isDirty(['quantity', 'product_id', 'from_warehouse_id', 'from_rak_id', 'to_warehouse_id', 'to_rak_id'])) {
            $this->stockTransferService()->syncApprovedItemMovements($stockTransferItem->stockTransfer, $stockTransferItem);
        }
    }

    /**
     * Handle the StockTransferItem "deleted" event.
     */
    public function deleted(StockTransferItem $stockTransferItem): void
    {
        if (! $this->shouldSyncStock($stockTransferItem)) {
            return;
        }

        $this->stockTransferService()->deleteApprovedItemMovements($stockTransferItem->stockTransfer, $stockTransferItem);
    }

    /**
     * Handle the StockTransferItem "restored" event.
     */
    public function restored(StockTransferItem $stockTransferItem): void
    {
        if (! $this->shouldSyncStock($stockTransferItem)) {
            return;
        }

        $this->stockTransferService()->syncApprovedItemMovements($stockTransferItem->stockTransfer, $stockTransferItem);
    }

    /**
     * Handle the StockTransferItem "force deleted" event.
     */
    public function forceDeleted(StockTransferItem $stockTransferItem): void
    {
        if (! $this->shouldSyncStock($stockTransferItem)) {
            return;
        }

        $this->stockTransferService()->deleteApprovedItemMovements($stockTransferItem->stockTransfer, $stockTransferItem, true);
    }

    private function shouldSyncStock(StockTransferItem $stockTransferItem): bool
    {
        $stockTransfer = $stockTransferItem->relationLoaded('stockTransfer')
            ? $stockTransferItem->stockTransfer
            : $stockTransferItem->stockTransfer()->withTrashed()->first();

        return $stockTransfer?->status === 'Approved';
    }

    private function stockTransferService(): StockTransferService
    {
        return app(StockTransferService::class);
    }
}