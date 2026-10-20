<?php

namespace App\Services\Hp;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use Illuminate\Support\Collection;

/**
 * Picking list per gudang (Tahap 3, mockup F3): gabungan barang dari DO yang siap dicek & dikirim,
 * dikelompokkan per rak lalu produk, dengan rincian qty per DO.
 */
class PickingList
{
    /**
     * @param  array<int, int>  $deliveryOrderIds  kosong = semua DO siap di gudang ini
     * @return array{orders: Collection, rows: Collection, total_qty: float}
     */
    public function build(int $warehouseId, array $deliveryOrderIds = []): array
    {
        $orders = DeliveryOrder::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', CheckerQueue::SHIPPABLE)
            ->when($deliveryOrderIds !== [], fn ($q) => $q->whereIn('id', $deliveryOrderIds))
            ->with(['salesOrders.customer', 'deliveryOrderItem.product', 'deliveryOrderItem.warehouseSources.rak'])
            ->orderBy('do_number')
            ->get();

        $rows = [];
        foreach ($orders as $order) {
            foreach ($order->deliveryOrderItem as $item) {
                /** @var DeliveryOrderItem $item */
                if ((float) $item->quantity <= 0) {
                    continue;
                }

                $sources = $item->warehouseSources->filter(fn ($s) => (int) $s->warehouse_id === $warehouseId && (float) $s->quantity > 0);
                $parts = $sources->isNotEmpty()
                    ? $sources->map(fn ($s) => ['rak' => $s->rak?->code ?: $s->rak?->name, 'qty' => (float) $s->quantity])->all()
                    : [['rak' => null, 'qty' => (float) $item->quantity]];

                foreach ($parts as $part) {
                    $key = ($part['rak'] ?? '').'|'.$item->product_id;
                    $rows[$key] ??= [
                        'rak' => $part['rak'],
                        'product' => $item->product?->name,
                        'sku' => $item->product?->sku,
                        'uom' => $item->product?->uom?->name,
                        'total' => 0.0,
                        'orders' => [],
                    ];
                    $rows[$key]['total'] += $part['qty'];
                    $rows[$key]['orders'][$order->do_number] = ($rows[$key]['orders'][$order->do_number] ?? 0) + $part['qty'];
                }
            }
        }

        $rows = collect($rows)
            ->sortBy(fn ($row) => sprintf('%s|%s', $row['rak'] === null ? 'zzzz' : $row['rak'], $row['product']))
            ->values();

        return [
            'orders' => $orders,
            'rows' => $rows,
            'total_qty' => (float) $rows->sum('total'),
        ];
    }
}
