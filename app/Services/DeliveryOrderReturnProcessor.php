<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Retur by DO yang disetujui (UAT 13 Okt 2026, gelombang 3, B14–B17).
 *
 *  - B15: stok yang kembali tercatat sebagai Stock Adjustment (approved) bernomor sama dengan retur (DO…R);
 *  - B16: barang kondisi damage/repair (reject) masuk ke gudang KARANTINA cabang (bukan stok jual), kondisi good kembali ke gudang sumber;
 *  - B17: gerakan stoknya berjenis sendiri `do_return` ("Retur DO"), bukan `customer_return`;
 *  - B14: DO asal ditandai "sudah diretur" dan dibuat DO pengganti (DO…C) untuk kuantitas yang diretur.
 *
 * Jurnal akuntansi retur tetap dari ReturnProductService::createReversingJournalEntries; Stock Adjustment di sini hanya mencatat stok
 * (tanpa jurnal sendiri) agar tidak terjadi pembukuan ganda.
 */
class DeliveryOrderReturnProcessor
{
    /** Kondisi barang retur yang TIDAK boleh kembali ke stok jual. */
    public const QUARANTINE_CONDITIONS = ['damage', 'repair'];

    /** @var array{adjustments: array<int, string>, quarantine_qty: float, replacement: ?string, replacement_allocated: bool} */
    public array $summary = ['adjustments' => [], 'quarantine_qty' => 0.0, 'replacement' => null, 'replacement_allocated' => false];

    public function __construct(private readonly ReturnProductService $returns) {}

    // ------------------------------------------------------------------ B15/B16/B17 stok

    public function restock(ReturnProduct $returnProduct): void
    {
        $alreadyDone = StockMovement::query()
            ->where('type', 'do_return')
            ->where('meta->return_product_id', $returnProduct->id)
            ->exists();
        if ($alreadyDone) {
            return;
        }

        $returnProduct->loadMissing('returnProductItem.product', 'returnProductItem.fromItemModel', 'fromModel');
        $deliveryOrder = $returnProduct->fromModel instanceof DeliveryOrder ? $returnProduct->fromModel : null;

        /** @var array<int, array<int, array{item: ReturnProductItem, rak_id: int|null, quantity: float, quarantine: bool, origin_warehouse_id: int, origin_rak_id: int|null}>> $byWarehouse */
        $byWarehouse = [];
        $sourceLeft = [];   // sisa kuantitas tiap sumber gudang DO, dibagi antar item retur agar tidak dialokasikan dua kali

        foreach ($returnProduct->returnProductItem as $item) {
            if ((float) $item->quantity <= 0) {
                continue;
            }

            $toQuarantine = in_array(strtolower((string) $item->condition), self::QUARANTINE_CONDITIONS, true);
            $quarantine = $toQuarantine ? $this->quarantineWarehouse($deliveryOrder?->cabang_id ?? $returnProduct->warehouse?->cabang_id) : null;

            foreach ($this->originPortions($item, $returnProduct, $sourceLeft) as $portion) {
                if (! $portion['warehouse_id']) {
                    continue;
                }
                $destinationId = $quarantine ? (int) $quarantine->id : (int) $portion['warehouse_id'];
                $byWarehouse[$destinationId][] = [
                    'item' => $item,
                    'rak_id' => $quarantine ? null : $portion['rak_id'],
                    'quantity' => (float) $portion['quantity'],
                    'quarantine' => (bool) $quarantine,
                    'origin_warehouse_id' => (int) $portion['warehouse_id'],
                    'origin_rak_id' => $portion['rak_id'],
                ];
                if ($quarantine) {
                    $this->summary['quarantine_qty'] += (float) $portion['quantity'];
                }
            }
        }

        if ($byWarehouse === []) {
            return;
        }

        // Sumber gudang DO dikurangi sebesar yang diretur (sinkron dengan kuantitas item yang sudah dikurangi); baris 0 dihapus.
        // Tanpa event model: stok sudah ditangani di sini, bukan oleh reconcile.
        foreach ($sourceLeft as $sourceId => $left) {
            $source = \App\Models\DeliveryOrderItemWarehouseSource::query()->find($sourceId);
            if (! $source) {
                continue;
            }
            if ($left <= 0.00001) {
                \App\Models\DeliveryOrderItemWarehouseSource::withoutEvents(fn () => $source->delete());
            } elseif (abs($left - (float) $source->quantity) > 0.00001) {
                $source->forceFill(['quantity' => $left])->saveQuietly();
            }
        }

        $actorId = Auth::id() ?? $deliveryOrder?->created_by ?? \App\Models\User::query()->orderBy('id')->value('id');
        $index = 0;
        $single = count($byWarehouse) === 1;
        foreach ($byWarehouse as $warehouseId => $lines) {
            $index++;
            $number = $this->uniqueAdjustmentNumber($single ? $returnProduct->return_number : $returnProduct->return_number.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT));
            $isQuarantine = collect($lines)->contains(fn ($l) => $l['quarantine']);

            $adjustment = StockAdjustment::create([
                'adjustment_number' => $number,
                'adjustment_date' => now()->toDateString(),
                'warehouse_id' => $warehouseId,
                'adjustment_type' => 'increase',
                'reason' => 'Retur DO '.($deliveryOrder?->do_number ?? '-').' ('.$returnProduct->return_number.')'.($isQuarantine ? ' - masuk karantina, bukan stok jual' : ''),
                'notes' => $returnProduct->reason,
                'status' => 'approved',
                'created_by' => $actorId,
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);
            $this->summary['adjustments'][] = $number;

            foreach ($lines as $line) {
                $product = $line['item']->product;
                $cost = (float) ($product?->cost_price ?? 0);
                $current = (float) InventoryStock::query()
                    ->where('product_id', $line['item']->product_id)->where('warehouse_id', $warehouseId)
                    ->when($line['rak_id'], fn ($q) => $q->where('rak_id', $line['rak_id']), fn ($q) => $q->whereNull('rak_id'))
                    ->sum('qty_available');

                $adjustmentItem = StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $line['item']->product_id,
                    'rak_id' => $line['rak_id'],
                    'current_qty' => $current,
                    'adjusted_qty' => $current + $line['quantity'],
                    'difference_qty' => $line['quantity'],
                    'unit_cost' => $cost,
                    'difference_value' => round($line['quantity'] * $cost, 2),
                    'notes' => ($line['quarantine'] ? 'Karantina: ' : 'Kembali ke gudang sumber: ').($line['item']->note ?: $line['item']->condition),
                ]);

                StockMovement::create([
                    'product_id' => $line['item']->product_id,
                    'warehouse_id' => $warehouseId,
                    'rak_id' => $line['rak_id'],
                    'quantity' => $line['quantity'],
                    'value' => round($line['quantity'] * $cost, 2),
                    'type' => 'do_return',
                    'reference_id' => $returnProduct->id,
                    'date' => now()->toDateString(),
                    'notes' => 'Retur DO '.($deliveryOrder?->do_number ?? '-').': '.$returnProduct->return_number.($line['quarantine'] ? ' (karantina)' : ''),
                    'from_model_type' => StockAdjustment::class,
                    'from_model_id' => $adjustment->id,
                    'meta' => [
                        'return_product_id' => $returnProduct->id,
                        'return_product_item_id' => $line['item']->id,
                        'stock_adjustment_id' => $adjustment->id,
                        'stock_adjustment_item_id' => $adjustmentItem->id,
                        'from_model_type' => $returnProduct->from_model_type,
                        'from_model_id' => $returnProduct->from_model_id,
                        'quarantine' => $line['quarantine'],
                        // asal barang: dipakai DeliveryShipments::netShipped agar DO yang ditutup/diedit tidak mengembalikan stok dua kali
                        'delivery_order_item_id' => $line['item']->from_item_model_type === DeliveryOrderItem::class ? (int) $line['item']->from_item_model_id : null,
                        'origin_warehouse_id' => $line['origin_warehouse_id'],
                        'origin_rak_id' => (int) ($line['origin_rak_id'] ?? 0),
                    ],
                ]);
            }
        }
    }

    /**
     * Pembagian kuantitas retur menurut sumber gudang DO (urut sumber; sisa tiap sumber dibagi antar item retur lewat $left).
     *
     * @param  array<int, float>  $left  sisa kuantitas per id sumber gudang (diperbarui)
     * @return array<int, array{warehouse_id: int|null, rak_id: int|null, quantity: float, source_id: int|null}>
     */
    private function originPortions(ReturnProductItem $item, ReturnProduct $returnProduct, array &$left): array
    {
        $remaining = (float) $item->quantity;
        $fromItem = $item->fromItemModel;
        $portions = [];

        if ($fromItem instanceof DeliveryOrderItem) {
            foreach ($fromItem->warehouseSources()->orderBy('id')->get() as $source) {
                if ($remaining <= 0.00001 || ! $source->warehouse_id) {
                    continue;
                }
                $available = $left[$source->id] ?? (float) $source->quantity;
                $take = min($remaining, $available);
                if ($take > 0) {
                    $portions[] = ['warehouse_id' => (int) $source->warehouse_id, 'rak_id' => $source->rak_id ? (int) $source->rak_id : null, 'quantity' => $take, 'source_id' => (int) $source->id];
                    $left[$source->id] = $available - $take;
                    $remaining -= $take;
                }
            }
        }

        if ($remaining > 0.00001) {
            $portions[] = [
                'warehouse_id' => $fromItem?->warehouse_id ?? $returnProduct->warehouse_id,
                'rak_id' => $returnProduct->returnProductItem->firstWhere('id', $item->id)?->rak_id ?? $fromItem?->rak_id,
                'quantity' => $remaining,
                'source_id' => null,
            ];
        }

        return $portions;
    }

    /** Gudang karantina cabang (dibuat otomatis bila belum ada). */
    public function quarantineWarehouse(?int $cabangId): Warehouse
    {
        $existing = Warehouse::query()->where('is_quarantine', true)->where('cabang_id', $cabangId)->first();
        if ($existing) {
            return $existing;
        }

        $cabang = $cabangId ? Cabang::query()->find($cabangId) : null;
        $code = 'QRT-'.strtoupper((string) ($cabang?->kode ?: ($cabangId ?: 'ALL')));

        return Warehouse::create([
            'kode' => $code,
            'name' => 'Karantina / Barang Rusak'.($cabang?->nama ? ' - '.$cabang->nama : ''),
            'cabang_id' => $cabangId,
            'location' => 'Area karantina retur DO',
            'tipe' => 'Kecil',
            'status' => 1,
            'is_quarantine' => true,
        ]);
    }

    private function uniqueAdjustmentNumber(string $base): string
    {
        $number = $base;
        $n = 1;
        while (StockAdjustment::withTrashed()->where('adjustment_number', $number)->exists()) {
            $number = $base.'-A'.(++$n);
        }

        return $number;
    }

    // ------------------------------------------------------------------ B14 tanda diretur + DO pengganti

    public function afterApproval(ReturnProduct $returnProduct): ?DeliveryOrder
    {
        $deliveryOrder = $returnProduct->fromModel;
        if (! $deliveryOrder instanceof DeliveryOrder) {
            return null;
        }

        $deliveryOrder->forceFill(['returned_at' => $deliveryOrder->returned_at ?? now()])->saveQuietly();

        if (($returnProduct->return_action ?? 'reduce_quantity_only') !== 'reduce_quantity_only') {
            return null;   // DO/SO ditutup paksa: tidak ada pengiriman pengganti
        }

        $existing = DeliveryOrder::withoutGlobalScopes()
            ->where('replacement_for_delivery_order_id', $deliveryOrder->id)
            ->where('notes', 'like', '%'.$returnProduct->return_number.'%')
            ->first();
        if ($existing) {
            return $existing;
        }

        return $this->createReplacement($deliveryOrder, $returnProduct);
    }

    private function createReplacement(DeliveryOrder $original, ReturnProduct $returnProduct): ?DeliveryOrder
    {
        $returnProduct->loadMissing('returnProductItem');
        $lines = $returnProduct->returnProductItem
            ->filter(fn (ReturnProductItem $i) => $i->from_item_model_type === DeliveryOrderItem::class && (float) $i->quantity > 0)
            ->groupBy('from_item_model_id')
            ->map(fn ($group, $itemId) => ['do_item' => DeliveryOrderItem::withoutGlobalScopes()->find($itemId), 'quantity' => (float) $group->sum('quantity')])
            ->filter(fn ($line) => $line['do_item'] !== null);

        if ($lines->isEmpty()) {
            return null;
        }

        $number = $this->uniqueReplacementNumber($original->do_number);
        $saleOrderIds = $original->salesOrders()->pluck('sale_orders.id')->all();
        $header = [
            'do_number' => $number,
            'delivery_date' => now(),
            'driver_id' => $original->driver_id,
            'vehicle_id' => $original->vehicle_id,
            'cabang_id' => $original->cabang_id,
            'status' => 'draft',
            'notes' => 'DO pengganti '.$original->do_number.' akibat retur '.$returnProduct->return_number,
            'created_by' => Auth::id() ?? $original->created_by,
            'replacement_for_delivery_order_id' => $original->id,
        ];

        // 1) Coba alokasikan stok & ajukan konfirmasi gudang seperti DO biasa; bila stok belum cukup → DO draf tanpa sumber gudang.
        try {
            $replacement = DB::transaction(function () use ($header, $saleOrderIds, $lines, $original) {
                return $this->buildReplacement($header, $saleOrderIds, $lines, withSources: true, original: $original);
            });
            $this->summary['replacement'] = $replacement->do_number;
            $this->summary['replacement_allocated'] = true;

            return $replacement;
        } catch (ValidationException|\RuntimeException $e) {
            Log::info('DO pengganti: alokasi stok gagal, dibuat sebagai draf tanpa sumber gudang', ['original' => $original->do_number, 'error' => $e->getMessage()]);
        }

        $replacement = DB::transaction(fn () => $this->buildReplacement($header, $saleOrderIds, $lines, withSources: false, original: $original));
        $this->summary['replacement'] = $replacement->do_number;
        $this->summary['replacement_allocated'] = false;

        return $replacement;
    }

    private function buildReplacement(array $header, array $saleOrderIds, $lines, bool $withSources, DeliveryOrder $original): DeliveryOrder
    {
        $previousDefer = \App\Observers\DeliveryOrderObserver::$deferStockDeduction;
        \App\Observers\DeliveryOrderObserver::$deferStockDeduction = true;

        try {
            $replacement = DeliveryOrder::create($header);
            $replacement->salesOrders()->sync($saleOrderIds);
            $availability = app(StockAvailability::class);

            foreach ($lines as $line) {
                /** @var DeliveryOrderItem $doItem */
                $doItem = $line['do_item'];
                $newItem = DeliveryOrderItem::create([
                    'delivery_order_id' => $replacement->id,
                    'sale_order_item_id' => $doItem->sale_order_item_id,
                    'product_id' => $doItem->product_id,
                    'quantity' => $line['quantity'],
                    'reason' => 'Pengganti barang retur DO '.$original->do_number,
                ]);

                if (! $withSources) {
                    continue;
                }

                $rows = collect($availability->sourceRowsForItem(
                    (int) $doItem->product_id,
                    $doItem->sale_order_item_id ? (int) $doItem->sale_order_item_id : null,
                    (float) $line['quantity'],
                    $original->cabang_id ? (int) $original->cabang_id : null,
                    $doItem->warehouseSources()->pluck('warehouse_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
                ))->filter(fn ($row) => $row['quantity'] > 0.00001);

                $free = $rows->sum('free');
                if ($rows->isEmpty() || $free + 0.00001 < $line['quantity'] || abs($rows->sum('quantity') - $line['quantity']) > 0.0001) {
                    throw new \RuntimeException('Stok bebas belum cukup untuk DO pengganti.');
                }

                foreach ($rows as $row) {
                    \App\Models\DeliveryOrderItemWarehouseSource::create([
                        'delivery_order_item_id' => $newItem->id,
                        'warehouse_id' => $row['warehouse_id'],
                        'rak_id' => $row['rak_id'],
                        'quantity' => $row['quantity'],
                    ]);
                }
            }

            if ($withSources) {
                $replacement->forceFill(['warehouse_id' => $replacement->deliveryOrderItem()->with('warehouseSources')->get()->flatMap->warehouseSources->first()?->warehouse_id])->saveQuietly();

                app(\App\Observers\DeliveryOrderObserver::class)->createStockMovementsForDeliveryOrder($replacement->fresh(), force: true);
                $confirmations = app(DeliveryOrderService::class)->createWarehouseConfirmationsForDeliveryOrder($replacement->fresh());
                if (! empty($confirmations)) {
                    app(DeliveryOrderService::class)->updateStatus(
                        deliveryOrder: $replacement->fresh(),
                        status: 'request_stock',
                        comments: 'DO pengganti otomatis: konfirmasi gudang diminta.',
                    );
                }
            }

            return $replacement->fresh();
        } finally {
            \App\Observers\DeliveryOrderObserver::$deferStockDeduction = $previousDefer;
        }
    }

    private function uniqueReplacementNumber(string $doNumber): string
    {
        $number = $doNumber.'C';
        $n = 1;
        while (DeliveryOrder::withoutGlobalScopes()->withTrashed()->where('do_number', $number)->exists()) {
            $number = $doNumber.'C'.(++$n);
        }

        return $number;
    }
}
