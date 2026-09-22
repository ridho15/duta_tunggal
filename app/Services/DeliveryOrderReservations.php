<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\StockReservation;

/**
 * Menyelaraskan reservasi level-DO dengan isi DO (item × gudang sumber) — idempoten (T2.1).
 *
 * Dipanggil saat DO menjadi "Siap Kirim" (approved) dan saat kuantitas item DO berubah selagi masih approved.
 * Kuantitas total per item tidak pernah melebihi `deliveryOrderItem.quantity` (sumber terakhir dipotong lebih dulu),
 * sehingga hasil edit checker (kuantitas turun) otomatis menurunkan reservasi.
 */
class DeliveryOrderReservations
{
    public function __construct(private readonly StockReservationLedger $ledger) {}

    /**
     * @throws \Exception bila konfigurasi gudang sumber tidak valid (perilaku sama dengan sebelumnya)
     */
    public function sync(DeliveryOrder $deliveryOrder, string $reason = 'DO disetujui (Siap Kirim)'): void
    {
        $deliveryOrder->load('deliveryOrderItem.warehouseSources', 'deliveryOrderItem.saleOrderItem');   // selalu segar: relasi ter-cache bisa basi

        $desired = [];
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $remaining = max(0.0, (float) ($item->quantity ?? 0));
            if ($remaining <= 0) {
                continue;
            }

            $saleOrderItem = $item->saleOrderItem?->exists ? $item->saleOrderItem : null;

            foreach ($item->warehouseSources as $source) {
                $sourceQty = max(0.0, (float) ($source->quantity ?? 0));
                if ($sourceQty <= 0 || ! $source->warehouse_id) {
                    throw new \Exception('Warehouse source configuration is required for stock reservation');
                }

                $take = min($sourceQty, $remaining);
                if ($take > 0) {
                    $desired[] = $this->row($item, $saleOrderItem, (int) $source->warehouse_id, $source->rak_id, $take, $deliveryOrder);
                    $remaining -= $take;
                }
            }

            if ($item->warehouseSources->isEmpty()) {
                $warehouseId = $deliveryOrder->warehouse_id ?? $item->warehouse_id;
                if (! $warehouseId) {
                    throw new \Exception('Warehouse ID is required for stock reservation');
                }
                $desired[] = $this->row($item, $saleOrderItem, (int) $warehouseId, $item->rak_id, $remaining, $deliveryOrder);
            }
        }

        $this->reconcile($deliveryOrder, $desired, $reason);
    }

    private function row($item, $saleOrderItem, int $warehouseId, ?int $rakId, float $qty, DeliveryOrder $deliveryOrder): array
    {
        return [
            'product_id' => $item->product_id,
            'warehouse_id' => $warehouseId,
            'rak_id' => $rakId ?: null,
            'quantity' => $qty,
            'sale_order_id' => $saleOrderItem?->sale_order_id,
            'sale_order_item_id' => $saleOrderItem?->id,
            'delivery_order_id' => $deliveryOrder->id,
        ];
    }

    /** @param  array<int, array<string, mixed>>  $desired */
    private function reconcile(DeliveryOrder $deliveryOrder, array $desired, string $reason): void
    {
        $existing = StockReservation::where('delivery_order_id', $deliveryOrder->id)->orderBy('id')->get();
        $key = fn ($r) => implode('|', [$r['sale_order_item_id'] ?? 'p'.$r['product_id'], $r['warehouse_id'], $r['rak_id'] ?? 0]);

        $existingByKey = [];
        foreach ($existing as $reservation) {
            $existingByKey[$key([
                'sale_order_item_id' => $reservation->sale_order_item_id, 'product_id' => $reservation->product_id,
                'warehouse_id' => $reservation->warehouse_id, 'rak_id' => $reservation->rak_id,
            ])][] = $reservation;
        }

        foreach ($desired as $row) {
            $k = $key($row);
            $current = isset($existingByKey[$k]) ? array_shift($existingByKey[$k]) : null;

            if ($current) {
                if (! $current->sale_order_item_id && $row['sale_order_item_id']) {
                    $current->forceFill(['sale_order_item_id' => $row['sale_order_item_id']])->saveQuietly();
                }
                $this->ledger->adjust($current, (float) $row['quantity'], $reason);
            } else {
                $this->ledger->reserve($row, $reason);
            }
        }

        // Sisa baris yang tidak lagi dibutuhkan → dilepas
        foreach ($existingByKey as $leftovers) {
            foreach ($leftovers as $reservation) {
                $this->ledger->release($reservation, $reason.' (baris tidak lagi dibutuhkan)');
            }
        }
    }
}
