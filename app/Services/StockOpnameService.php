<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\PurchaseReceiptItem;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
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

            $warehouseHasRaks = Rak::where('warehouse_id', $lockedOpname->warehouse_id)->exists();

            foreach ($lockedOpname->items as $item) {
                if (! $item->product_id) {
                    throw ValidationException::withMessages([
                        'items' => 'Setiap item stock opname harus memiliki produk.',
                    ]);
                }

                if ($warehouseHasRaks && ! $item->rak_id) {
                    throw ValidationException::withMessages([
                        'items' => 'Setiap item stock opname pada gudang dengan rak harus memiliki rak.',
                    ]);
                }

                if ($item->rak_id && (! $item->rak || (int) $item->rak->warehouse_id !== (int) $lockedOpname->warehouse_id)) {
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'Rak untuk produk %s harus berasal dari gudang stock opname yang sama.',
                            $item->product?->name ?? $item->product_id
                        ),
                    ]);
                }
            }

            // Generate stock movements for physical variance so inventory balances update
            foreach ($lockedOpname->items as $item) {
                $diffQty = (float) $item->difference_qty;
                if ($diffQty === 0.0) {
                    continue;
                }

                $type = $diffQty > 0 ? 'adjustment_in' : 'adjustment_out';
                $existingMovement = StockMovement::query()
                    ->where('from_model_type', StockOpname::class)
                    ->where('from_model_id', $lockedOpname->id)
                    ->where('meta->stock_opname_item_id', $item->id)
                    ->where('type', $type)
                    ->first();

                $payload = [
                    'product_id' => $item->product_id,
                    'warehouse_id' => $lockedOpname->warehouse_id,
                    'rak_id' => $item->rak_id ?: null,
                    'quantity' => abs($diffQty),
                    'value' => abs((float) $item->difference_value),
                    'type' => $type,
                    'reference_id' => $lockedOpname->opname_number,
                    'date' => $lockedOpname->opname_date,
                    'notes' => 'Penyesuaian hasil opname ' . $lockedOpname->opname_number,
                    'from_model_type' => StockOpname::class,
                    'from_model_id' => $lockedOpname->id,
                    'meta' => [
                        'stock_opname_item_id' => $item->id,
                    ],
                ];

                if ($existingMovement) {
                    $existingMovement->update($payload);
                } else {
                    StockMovement::create($payload);
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

    /**
     * Start physical counting: populates products for warehouse into opname items and sets status to in_progress
     */
    public function startPhysicalCount(StockOpname $stockOpname): int
    {
        return DB::transaction(function () use ($stockOpname) {
            /** @var StockOpname $lockedOpname */
            $lockedOpname = StockOpname::query()
                ->whereKey($stockOpname->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOpname->status === 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Stock opname yang sudah disetujui tidak dapat diubah.',
                ]);
            }

            $stocks = InventoryStock::query()
                ->where('warehouse_id', $lockedOpname->warehouse_id)
                ->with(['product', 'rak'])
                ->get();

            $count = 0;
            $opnameDate = $lockedOpname->opname_date ?? now();

            if ($stocks->isNotEmpty()) {
                foreach ($stocks as $stock) {
                    if (! $stock->product_id) {
                        continue;
                    }

                    $systemQty = (float) $stock->qty_available;
                    $avgCost = $this->calculateProductCost($stock->product_id, $opnameDate);

                    $existingItem = StockOpnameItem::query()
                        ->where('stock_opname_id', $lockedOpname->id)
                        ->where('product_id', $stock->product_id)
                        ->when($stock->rak_id, fn ($q) => $q->where('rak_id', $stock->rak_id), fn ($q) => $q->whereNull('rak_id'))
                        ->first();

                    if (! $existingItem) {
                        StockOpnameItem::create([
                            'stock_opname_id' => $lockedOpname->id,
                            'product_id' => $stock->product_id,
                            'rak_id' => $stock->rak_id,
                            'system_qty' => $systemQty,
                            'physical_qty' => $systemQty,
                            'difference_qty' => 0.0,
                            'unit_cost' => $avgCost,
                            'average_cost' => $avgCost,
                            'difference_value' => 0.0,
                            'total_value' => $systemQty * $avgCost,
                        ]);
                        $count++;
                    } else {
                        // Sync system qty with current stock
                        $physicalQty = (float) $existingItem->physical_qty;
                        $diffQty = $physicalQty - $systemQty;
                        $unitCost = (float) ($existingItem->unit_cost ?: $avgCost);

                        $existingItem->update([
                            'system_qty' => $systemQty,
                            'difference_qty' => $diffQty,
                            'unit_cost' => $unitCost,
                            'average_cost' => $avgCost,
                            'difference_value' => $diffQty * $unitCost,
                            'total_value' => $physicalQty * $unitCost,
                        ]);
                        $count++;
                    }
                }
            } else {
                // If warehouse has no inventory stock rows yet, load active products
                $products = Product::where('status', 1)->get();
                foreach ($products as $product) {
                    $avgCost = (float) ($product->cost_price ?? $product->price_buy ?? 0);
                    StockOpnameItem::firstOrCreate([
                        'stock_opname_id' => $lockedOpname->id,
                        'product_id' => $product->id,
                        'rak_id' => null,
                    ], [
                        'system_qty' => 0,
                        'physical_qty' => 0,
                        'difference_qty' => 0,
                        'unit_cost' => $avgCost,
                        'average_cost' => $avgCost,
                        'difference_value' => 0,
                        'total_value' => 0,
                    ]);
                    $count++;
                }
            }

            if ($lockedOpname->status === 'draft') {
                $lockedOpname->update(['status' => 'in_progress']);
            }

            return $count;
        });
    }

    /**
     * Complete physical counting: sets status from in_progress to completed
     */
    public function completePhysicalCount(StockOpname $stockOpname): StockOpname
    {
        return DB::transaction(function () use ($stockOpname) {
            /** @var StockOpname $lockedOpname */
            $lockedOpname = StockOpname::query()
                ->whereKey($stockOpname->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOpname->status !== 'in_progress') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya stock opname berstatus sedang berlangsung yang dapat ditandai selesai.',
                ]);
            }

            if ($lockedOpname->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'items' => 'Stock opname belum memiliki item hasil hitung.',
                ]);
            }

            $lockedOpname->update(['status' => 'completed']);

            return $lockedOpname->fresh();
        });
    }

    protected function calculateProductCost(int $productId, mixed $opnameDate): float
    {
        $purchaseItems = PurchaseReceiptItem::where('product_id', $productId)
            ->whereHas('purchaseReceipt', function ($query) use ($opnameDate) {
                $query->where('receipt_date', '<=', $opnameDate);
            })
            ->orderBy('purchase_receipt_items.created_at', 'asc')
            ->get();

        if ($purchaseItems->isEmpty()) {
            $product = Product::find($productId);
            return (float) ($product ? ($product->cost_price ?? $product->price_buy ?? 0) : 0);
        }

        $totalQty = 0;
        $totalVal = 0;
        foreach ($purchaseItems as $item) {
            $qty = (float) ($item->quantity_received ?? $item->qty_received ?? $item->quantity ?? 0);
            $price = (float) ($item->unit_price ?? 0);
            $totalQty += $qty;
            $totalVal += ($qty * $price);
        }

        return $totalQty > 0 ? $totalVal / $totalQty : 0.0;
    }
}