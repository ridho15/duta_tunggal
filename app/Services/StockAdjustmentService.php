<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
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
                    $stockQuery = InventoryStock::query()
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $lockedAdjustment->warehouse_id);

                    if ($item->rak_id) {
                        $stockQuery->where('rak_id', $item->rak_id);
                    } else {
                        $stockQuery->where(function ($q) {
                            $q->whereNull('rak_id')->orWhere('rak_id', 0);
                        });
                    }

                    $inventoryStock = $stockQuery->lockForUpdate()->first();

                    if (! $inventoryStock && ! $item->rak_id) {
                        $inventoryStock = InventoryStock::query()
                            ->where('product_id', $item->product_id)
                            ->where('warehouse_id', $lockedAdjustment->warehouse_id)
                            ->lockForUpdate()
                            ->first();
                    }

                    if (! $inventoryStock) {
                        throw ValidationException::withMessages([
                            'stock' => sprintf(
                                'Stok untuk produk %s di lokasi %s tidak ditemukan.',
                                $item->product?->name ?? $item->product_id,
                                $item->rak?->name ?? 'gudang ' . ($lockedAdjustment->warehouse?->name ?? '-')
                            ),
                        ]);
                    }

                    $requiredQuantity = abs((float) $item->difference_qty);

                    $freeQuantity = (float) $inventoryStock->free_qty;

                    if ($freeQuantity < $requiredQuantity) {
                        throw ValidationException::withMessages([
                            'stock' => sprintf(
                                'Stok tidak cukup untuk produk %s di rak %s. Tersedia %s, dibutuhkan %s.',
                                $item->product?->name ?? $item->product_id,
                                $item->rak?->name ?? 'gudang ' . ($lockedAdjustment->warehouse?->name ?? '-'),
                                rtrim(rtrim((string) $freeQuantity, '0'), '.'),
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

            $this->syncJournalEntries($lockedAdjustment);

            return $lockedAdjustment->fresh();
        });
    }

    private function validateAdjustmentItemShape(StockAdjustment $stockAdjustment, StockAdjustmentItem $item): void
    {
        if (! $item->product_id) {
            throw ValidationException::withMessages([
                'items' => 'Setiap item stock adjustment harus memiliki produk.',
            ]);
        }

        if ($item->rak_id) {
            if (! $item->rak || (int) $item->rak->warehouse_id !== (int) $stockAdjustment->warehouse_id) {
                throw ValidationException::withMessages([
                    'items' => sprintf(
                        'Rak untuk produk %s harus berasal dari gudang yang sama dengan stock adjustment.',
                        $item->product?->name ?? $item->product_id
                    ),
                ]);
            }
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
            'rak_id' => $item->rak_id ?: null,
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

    /**
     * Create or sync journal entries for stock adjustment
     */
    public function syncJournalEntries(StockAdjustment $stockAdjustment): void
    {
        $stockAdjustment->loadMissing(['items.product', 'warehouse']);

        // Delete existing journal entries for idempotency
        JournalEntry::where('source_type', StockAdjustment::class)
            ->where('source_id', $stockAdjustment->id)
            ->delete();

        if ($stockAdjustment->status !== 'approved') {
            return;
        }

        $reference = $stockAdjustment->adjustment_number;
        $date = $stockAdjustment->adjustment_date ?? now();
        $cabangId = $stockAdjustment->warehouse?->cabang_id;
        $description = sprintf(
            'Penyesuaian stok %s (%s)%s',
            $stockAdjustment->adjustment_number,
            $stockAdjustment->adjustment_type === 'increase' ? 'Penambahan' : 'Pengurangan',
            $stockAdjustment->reason ? ' - ' . $stockAdjustment->reason : ''
        );

        $entriesByInventoryCoa = [];
        $totalValue = 0.0;

        foreach ($stockAdjustment->items as $item) {
            $qtyDiff = abs((float) $item->difference_qty);
            if ($qtyDiff <= 0) {
                continue;
            }

            $itemVal = abs((float) $item->difference_value);
            if ($itemVal <= 0) {
                $unitCost = (float) ($item->unit_cost ?: ($item->product?->cost_price ?? 0));
                $itemVal = $qtyDiff * $unitCost;
            }

            if ($itemVal <= 0) {
                continue;
            }

            $inventoryCoa = $item->product?->resolveInventoryCoaOrDefault()
                ?? ChartOfAccount::whereIn('code', ['1140.10', '1140.01', '1140', '1100'])->first();

            if (! $inventoryCoa) {
                continue;
            }

            $coaId = $inventoryCoa->id;
            $entriesByInventoryCoa[$coaId] = ($entriesByInventoryCoa[$coaId] ?? 0.0) + $itemVal;
            $totalValue += $itemVal;
        }

        if ($totalValue <= 0 || empty($entriesByInventoryCoa)) {
            return;
        }

        if ($stockAdjustment->adjustment_type === 'increase') {
            // Inventory Increase:
            // Debit: Inventory COA
            // Credit: Adjustment Income COA (Selisih Persediaan - Pendapatan Luar Usaha)
            $incomeCoa = $this->findFirstExistingCoa([
                config('coa.inventory_variance_gain', '7000.04'),
                '7000.04',
                '7000.01',
                '7000',
            ]);

            if (! $incomeCoa) {
                throw ValidationException::withMessages([
                    'accounting' => 'Akun pendapatan penyesuaian persediaan tidak ditemukan.',
                ]);
            }

            foreach ($entriesByInventoryCoa as $invCoaId => $amount) {
                JournalEntry::create([
                    'coa_id' => $invCoaId,
                    'date' => $date,
                    'reference' => $reference,
                    'description' => $description,
                    'debit' => $amount,
                    'credit' => 0,
                    'journal_type' => 'stock_adjustment',
                    'cabang_id' => $cabangId,
                    'source_type' => StockAdjustment::class,
                    'source_id' => $stockAdjustment->id,
                ]);
            }

            JournalEntry::create([
                'coa_id' => $incomeCoa->id,
                'date' => $date,
                'reference' => $reference,
                'description' => $description,
                'debit' => 0,
                'credit' => $totalValue,
                'journal_type' => 'stock_adjustment',
                'cabang_id' => $cabangId,
                'source_type' => StockAdjustment::class,
                'source_id' => $stockAdjustment->id,
            ]);
        } else {
            // Inventory Decrease:
            // Debit: Adjustment Expense COA (Selisih Persediaan - Beban Luar Usaha/Umum)
            // Credit: Inventory COA
            $expenseCoa = $this->findFirstExistingCoa([
                config('coa.inventory_variance_loss', '8000.05'),
                '8000.05',
                '6280.05',
                '8000',
                '6280',
                '6100',
            ]);

            if (! $expenseCoa) {
                throw ValidationException::withMessages([
                    'accounting' => 'Akun beban penyesuaian persediaan tidak ditemukan.',
                ]);
            }

            JournalEntry::create([
                'coa_id' => $expenseCoa->id,
                'date' => $date,
                'reference' => $reference,
                'description' => $description,
                'debit' => $totalValue,
                'credit' => 0,
                'journal_type' => 'stock_adjustment',
                'cabang_id' => $cabangId,
                'source_type' => StockAdjustment::class,
                'source_id' => $stockAdjustment->id,
            ]);

            foreach ($entriesByInventoryCoa as $invCoaId => $amount) {
                JournalEntry::create([
                    'coa_id' => $invCoaId,
                    'date' => $date,
                    'reference' => $reference,
                    'description' => $description,
                    'debit' => 0,
                    'credit' => $amount,
                    'journal_type' => 'stock_adjustment',
                    'cabang_id' => $cabangId,
                    'source_type' => StockAdjustment::class,
                    'source_id' => $stockAdjustment->id,
                ]);
            }
        }
    }

    protected function findFirstExistingCoa(array $codes): ?ChartOfAccount
    {
        foreach ($codes as $code) {
            if (! $code) {
                continue;
            }

            $coa = ChartOfAccount::where('code', $code)->first();
            if ($coa?->id) {
                return $coa;
            }
        }

        return null;
    }
}