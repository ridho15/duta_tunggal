<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function approveStockAdjustment(StockAdjustment $stockAdjustment, ?int $approvedBy = null): StockAdjustment
    {
        return DB::transaction(function () use ($stockAdjustment, $approvedBy) {
            /** @var StockAdjustment $lockedAdjustment */
            $lockedAdjustment = StockAdjustment::query()
                ->whereKey($stockAdjustment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAdjustment->load([
                'items.product',
                'items.rak',
                'warehouse',
            ]);

            if ($lockedAdjustment->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya stock adjustment berstatus draft yang dapat disetujui.',
                ]);
            }

            if ($lockedAdjustment->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tambahkan minimal satu item sebelum menyetujui stock adjustment.',
                ]);
            }

            foreach ($lockedAdjustment->items as $item) {
                $this->validateAdjustmentItemShape($lockedAdjustment, $item);

                if ($lockedAdjustment->adjustment_type === 'decrease') {
                    $inventoryStock = InventoryStock::query()
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $lockedAdjustment->warehouse_id)
                        ->where('rak_id', $item->rak_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventoryStock) {
                        throw ValidationException::withMessages([
                            'stock' => sprintf(
                                'Stok untuk produk %s di rak %s tidak ditemukan.',
                                $item->product?->name ?? $item->product_id,
                                $item->rak?->name ?? '-'
                            ),
                        ]);
                    }

                    $requiredQuantity = abs((float) $item->difference_qty);

                    if ((float) $inventoryStock->qty_available < $requiredQuantity) {
                        throw ValidationException::withMessages([
                            'stock' => sprintf(
                                'Stok tidak cukup untuk produk %s di rak %s. Tersedia %s, dibutuhkan %s.',
                                $item->product?->name ?? $item->product_id,
                                $item->rak?->name ?? '-',
                                rtrim(rtrim((string) $inventoryStock->qty_available, '0'), '.'),
                                rtrim(rtrim((string) $requiredQuantity, '0'), '.')
                            ),
                        ]);
                    }
                }

                $this->upsertItemMovement($lockedAdjustment, $item);
            }

            $lockedAdjustment->forceFill([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ])->saveQuietly();

            return $lockedAdjustment->fresh();
        });
    }

    private function validateAdjustmentItemShape(StockAdjustment $stockAdjustment, StockAdjustmentItem $item): void
    {
        if (! $item->product_id || ! $item->rak_id) {
            throw ValidationException::withMessages([
                'items' => 'Setiap item stock adjustment harus memiliki produk dan rak.',
            ]);
        }

        if (! $item->rak || (int) $item->rak->warehouse_id !== (int) $stockAdjustment->warehouse_id) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'Rak untuk produk %s harus berasal dari gudang yang sama dengan stock adjustment.',
                    $item->product?->name ?? $item->product_id
                ),
            ]);
        }

        if ((float) $item->adjusted_qty < 0) {
            throw ValidationException::withMessages([
                'items' => 'Qty setelah adjustment tidak boleh bernilai negatif.',
            ]);
        }

        if ((float) $item->difference_qty === 0.0) {
            throw ValidationException::withMessages([
                'items' => 'Setiap item stock adjustment harus memiliki selisih qty yang tidak nol.',
            ]);
        }

        if ($stockAdjustment->adjustment_type === 'increase' && (float) $item->difference_qty <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Stock adjustment tipe penambahan hanya boleh berisi item dengan selisih qty positif.',
            ]);
        }

        if ($stockAdjustment->adjustment_type === 'decrease' && (float) $item->difference_qty >= 0) {
            throw ValidationException::withMessages([
                'items' => 'Stock adjustment tipe pengurangan hanya boleh berisi item dengan selisih qty negatif.',
            ]);
        }
    }

    private function upsertItemMovement(StockAdjustment $stockAdjustment, StockAdjustmentItem $item): void
    {
        $type = (float) $item->difference_qty > 0 ? 'adjustment_in' : 'adjustment_out';

        $existingMovement = StockMovement::query()
            ->where('from_model_type', StockAdjustment::class)
            ->where('from_model_id', $stockAdjustment->id)
            ->where('meta->stock_adjustment_item_id', $item->id)
            ->where('type', $type)
            ->first();

        $payload = [
            'product_id' => $item->product_id,
            'warehouse_id' => $stockAdjustment->warehouse_id,
            'rak_id' => $item->rak_id,
            'quantity' => abs((float) $item->difference_qty),
            'value' => abs((float) $item->difference_value),
            'type' => $type,
            'reference_id' => $stockAdjustment->adjustment_number,
            'date' => $stockAdjustment->adjustment_date,
            'notes' => $stockAdjustment->reason,
            'from_model_type' => StockAdjustment::class,
            'from_model_id' => $stockAdjustment->id,
            'meta' => [
                'stock_adjustment_item_id' => $item->id,
            ],
        ];

        if ($existingMovement) {
            $existingMovement->update($payload);

            return;
        }

        StockMovement::create($payload);
    }
}