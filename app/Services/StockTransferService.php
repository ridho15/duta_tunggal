<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function generateTransferNumber(): string
    {
        return StockTransfer::generateTransferNumber();
    }

    public function requestTransfer(StockTransfer $stockTransfer): StockTransfer
    {
        $stockTransfer->loadMissing('stockTransferItem');

        if ($stockTransfer->status !== 'Draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya transfer stok berstatus draft yang dapat diajukan.',
            ]);
        }

        $items = $stockTransfer->stockTransferItem;

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Tambahkan minimal satu item sebelum request transfer stok.',
            ]);
        }

        foreach ($items as $item) {
            $this->validateTransferItemShape($item);
        }

        $stockTransfer->update([
            'status' => 'Request',
        ]);

        return $stockTransfer->fresh();
    }

    public function approveStockTransfer(StockTransfer $stockTransfer): StockTransfer
    {
        return DB::transaction(function () use ($stockTransfer) {
            /** @var StockTransfer $lockedTransfer */
            $lockedTransfer = StockTransfer::query()
                ->whereKey($stockTransfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTransfer->load([
                'stockTransferItem.product',
                'stockTransferItem.fromWarehouse',
                'stockTransferItem.fromRak',
                'stockTransferItem.toWarehouse',
                'stockTransferItem.toRak',
            ]);

            if ($lockedTransfer->status !== 'Request') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya transfer stok berstatus request yang dapat disetujui.',
                ]);
            }

            if ($lockedTransfer->stockTransferItem->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Transfer stok tidak memiliki item untuk diproses.',
                ]);
            }

            foreach ($lockedTransfer->stockTransferItem as $item) {
                $this->validateTransferItemShape($item);

                $sourceStock = InventoryStock::query()
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->from_warehouse_id)
                    ->where('rak_id', $item->from_rak_id)
                    ->lockForUpdate()
                    ->first();

                if (! $sourceStock) {
                    throw ValidationException::withMessages([
                        'stock' => sprintf(
                            'Stok sumber untuk produk %s di gudang %s / rak %s tidak ditemukan.',
                            $item->product?->name ?? $item->product_id,
                            $item->fromWarehouse?->name ?? '-',
                            $item->fromRak?->name ?? '-'
                        ),
                    ]);
                }

                if ((float) $sourceStock->qty_available < (float) $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => sprintf(
                            'Stok tidak cukup untuk produk %s di gudang %s / rak %s. Tersedia %s, diminta %s.',
                            $item->product?->name ?? $item->product_id,
                            $item->fromWarehouse?->name ?? '-',
                            $item->fromRak?->name ?? '-',
                            rtrim(rtrim((string) $sourceStock->qty_available, '0'), '.'),
                            rtrim(rtrim((string) $item->quantity, '0'), '.')
                        ),
                    ]);
                }

                $this->syncApprovedItemMovements($lockedTransfer, $item);
            }

            $lockedTransfer->update([
                'status' => 'Approved',
            ]);

            return $lockedTransfer->fresh();
        });
    }

    public function syncApprovedItemMovements(StockTransfer $stockTransfer, StockTransferItem $stockTransferItem): void
    {
        $this->upsertItemMovement($stockTransfer, $stockTransferItem, 'transfer_out', $stockTransferItem->from_warehouse_id, $stockTransferItem->from_rak_id, [
            'notes' => 'Transfer ke gudang ' . ($stockTransferItem->toWarehouse?->name ?? '-'),
        ]);

        $this->upsertItemMovement($stockTransfer, $stockTransferItem, 'transfer_in', $stockTransferItem->to_warehouse_id, $stockTransferItem->to_rak_id, [
            'notes' => 'Transfer dari gudang ' . ($stockTransferItem->fromWarehouse?->name ?? '-'),
        ]);
    }

    public function deleteApprovedItemMovements(StockTransfer $stockTransfer, StockTransferItem $stockTransferItem, bool $forceDelete = false): void
    {
        $movements = StockMovement::query()
            ->where('from_model_type', StockTransfer::class)
            ->where('from_model_id', $stockTransfer->id)
            ->where('meta->stock_transfer_item_id', $stockTransferItem->id)
            ->get();

        foreach ($movements as $movement) {
            if ($forceDelete) {
                $movement->forceDelete();

                continue;
            }

            $movement->delete();
        }
    }

    private function validateTransferItemShape(StockTransferItem $item): void
    {
        if ((float) $item->quantity <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Qty transfer harus lebih besar dari 0.',
            ]);
        }

        if (! $item->product_id || ! $item->from_warehouse_id || ! $item->to_warehouse_id || ! $item->from_rak_id || ! $item->to_rak_id) {
            throw ValidationException::withMessages([
                'items' => 'Setiap item transfer harus memiliki produk, gudang asal/tujuan, dan rak asal/tujuan yang lengkap.',
            ]);
        }

        if (
            (int) $item->from_warehouse_id === (int) $item->to_warehouse_id
            && (int) $item->from_rak_id === (int) $item->to_rak_id
        ) {
            throw ValidationException::withMessages([
                'items' => 'Gudang dan rak tujuan harus berbeda dari gudang dan rak asal.',
            ]);
        }
    }

    private function upsertItemMovement(
        StockTransfer $stockTransfer,
        StockTransferItem $stockTransferItem,
        string $type,
        int $warehouseId,
        ?int $rakId,
        array $attributes = []
    ): void {
        $existingMovement = StockMovement::query()
            ->where('from_model_type', StockTransfer::class)
            ->where('from_model_id', $stockTransfer->id)
            ->where('type', $type)
            ->where('meta->stock_transfer_item_id', $stockTransferItem->id)
            ->first();

        $payload = array_merge([
            'product_id' => $stockTransferItem->product_id,
            'warehouse_id' => $warehouseId,
            'rak_id' => $rakId,
            'quantity' => $stockTransferItem->quantity,
            'type' => $type,
            'reference_id' => $stockTransfer->transfer_number,
            'date' => $stockTransfer->transfer_date,
            'from_model_type' => StockTransfer::class,
            'from_model_id' => $stockTransfer->id,
            'meta' => [
                'stock_transfer_item_id' => $stockTransferItem->id,
            ],
        ], $attributes);

        if ($existingMovement) {
            $existingMovement->update($payload);

            return;
        }

        StockMovement::create($payload);
    }
}
