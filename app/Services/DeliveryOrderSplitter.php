<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use Illuminate\Support\Facades\DB;

/**
 * Memecah DO yang mengambil barang dari BEBERAPA gudang menjadi satu DO per gudang (UAT 13 Okt 2026, B9; contoh Excel Ko Handy:
 * "DO 1-01", "DO 1-02"). Nomor DO asal menjadi `<nomor>-01`, DO tambahan `<nomor>-02`, `-03`, dst.
 *
 * Dipanggil SETELAH item & sumber gudang tersimpan tetapi SEBELUM stok dipotong (halaman Create menunda pemotongan), sehingga
 * pemecahan hanya menata ulang data — tidak ada gerakan stok yang perlu dipindahkan. Ongkos kirim (additional_cost) tetap di DO pertama.
 */
class DeliveryOrderSplitter
{
    /**
     * @return array<int, DeliveryOrder> DO hasil (satu elemen bila tidak perlu dipecah); selalu berurutan menurut nomor
     */
    public function splitByWarehouse(DeliveryOrder $deliveryOrder): array
    {
        $deliveryOrder->unsetRelation('deliveryOrderItem');
        $items = $deliveryOrder->deliveryOrderItem()->with('warehouseSources')->get();

        $warehouseIds = $items->flatMap(fn (DeliveryOrderItem $item) => $item->warehouseSources->where('quantity', '>', 0)->pluck('warehouse_id'))
            ->filter()->unique()->sort()->values();

        if ($warehouseIds->count() <= 1) {
            if ($warehouseIds->count() === 1 && (int) $deliveryOrder->warehouse_id !== (int) $warehouseIds->first()) {
                $deliveryOrder->forceFill(['warehouse_id' => $warehouseIds->first()])->saveQuietly();
            }

            return [$deliveryOrder->fresh()];
        }

        // Pemecahan hanya menata ulang data: tunda pemotongan stok selama berjalan (item/sumber baru memicu hook pemotongan)
        $previousDefer = \App\Observers\DeliveryOrderObserver::$deferStockDeduction;
        \App\Observers\DeliveryOrderObserver::$deferStockDeduction = true;

        try {
            return $this->performSplit($deliveryOrder, $items, $warehouseIds);
        } finally {
            \App\Observers\DeliveryOrderObserver::$deferStockDeduction = $previousDefer;
        }
    }

    private function performSplit(DeliveryOrder $deliveryOrder, $items, $warehouseIds): array
    {
        return DB::transaction(function () use ($deliveryOrder, $items, $warehouseIds) {
            $baseNumber = $deliveryOrder->do_number;
            $saleOrderIds = $deliveryOrder->salesOrders()->pluck('sale_orders.id')->all();
            $result = [];

            foreach ($warehouseIds as $index => $warehouseId) {
                $suffix = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

                if ($index === 0) {
                    $target = $deliveryOrder;
                    $target->forceFill(['do_number' => "{$baseNumber}-{$suffix}", 'warehouse_id' => $warehouseId])->saveQuietly();
                } else {
                    $target = $deliveryOrder->replicate(['id', 'created_at', 'updated_at', 'deleted_at', 'additional_cost', 'additional_cost_description']);
                    $target->do_number = "{$baseNumber}-{$suffix}";
                    $target->warehouse_id = $warehouseId;
                    $target->save();
                    $target->salesOrders()->sync($saleOrderIds);
                }

                $result[] = $target;
            }

            // Tata ulang item: untuk tiap item, kuantitas asal dikurangi lebih dulu (guard sisa SO di model item), lalu item per gudang dibuat.
            foreach ($items as $item) {
                $byWarehouse = $item->warehouseSources->where('quantity', '>', 0)->groupBy('warehouse_id');
                if ($byWarehouse->isEmpty()) {
                    continue;
                }

                $firstWarehouseOfDo = $result[0]->warehouse_id;
                // Item tetap di DO asal bila punya sumber di gudang pertama; bila tidak, item asal dipindah ke DO gudang pertama item itu.
                $keepWarehouse = $byWarehouse->has($firstWarehouseOfDo) ? $firstWarehouseOfDo : $byWarehouse->keys()->sort()->first();
                $keepTarget = collect($result)->first(fn (DeliveryOrder $do) => (int) $do->warehouse_id === (int) $keepWarehouse);

                $keepQty = (float) $byWarehouse[$keepWarehouse]->sum('quantity');

                DeliveryOrderItem::withoutEvents(function () use ($item, $keepTarget, $keepQty) {
                    $item->forceFill(['delivery_order_id' => $keepTarget->id, 'quantity' => $keepQty])->save();
                });

                // Sumber gudang lain di item asal dipindah ke item baru DO gudang masing-masing
                foreach ($byWarehouse as $warehouseId => $sources) {
                    if ((int) $warehouseId === (int) $keepWarehouse) {
                        // sumber yang tertinggal: hapus baris bernilai 0 (jika ada) agar tidak jadi sampah
                        continue;
                    }

                    $target = collect($result)->first(fn (DeliveryOrder $do) => (int) $do->warehouse_id === (int) $warehouseId);
                    $newItem = $item->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                    $newItem->delivery_order_id = $target->id;
                    $newItem->quantity = (float) $sources->sum('quantity');
                    $newItem->save();

                    DeliveryOrderItemWarehouseSource::withoutEvents(function () use ($sources, $newItem) {
                        foreach ($sources as $source) {
                            $source->forceFill(['delivery_order_item_id' => $newItem->id])->save();
                        }
                    });
                }
            }

            return array_map(fn (DeliveryOrder $do) => $do->fresh(), $result);
        });
    }
}
