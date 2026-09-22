<?php

namespace App\Services;

use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameService
{
    public function approveStockOpname(StockOpname $stockOpname, ?int $approvedBy = null): StockOpname
    {
        return DB::transaction(function () use ($stockOpname, $approvedBy) {
            /** @var StockOpname $lockedOpname */
            $lockedOpname = StockOpname::query()
                ->whereKey($stockOpname->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOpname->load([
                'items.product',
                'items.rak',
                'warehouse',
            ]);

            if ($lockedOpname->status !== 'completed') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya stock opname berstatus selesai yang dapat disetujui.',
                ]);
            }

            if ($lockedOpname->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tambahkan minimal satu item sebelum menyetujui stock opname.',
                ]);
            }

            foreach ($lockedOpname->items as $item) {
                if (! $item->product_id || ! $item->rak_id) {
                    throw ValidationException::withMessages([
                        'items' => 'Setiap item stock opname harus memiliki produk dan rak.',
                    ]);
                }

                if (! $item->rak || (int) $item->rak->warehouse_id !== (int) $lockedOpname->warehouse_id) {
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'Rak untuk produk %s harus berasal dari gudang stock opname yang sama.',
                            $item->product?->name ?? $item->product_id
                        ),
                    ]);
                }
            }

            $lockedOpname->forceFill([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ])->saveQuietly();

            $lockedOpname->createAdjustmentJournalEntries();

            return $lockedOpname->fresh();
        });
    }
}