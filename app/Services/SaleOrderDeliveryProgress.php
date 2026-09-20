<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\SaleOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA sumber kebenaran progres pengiriman Sales Order.
 *
 * Untuk setiap item SO dihitung tiga angka dari item Delivery Order:
 *  - delivered  : DO berstatus DeliveryOrder::DELIVERED_STATUSES (sent/received/completed)
 *  - in_process : DO terbuka yang belum dikirim (draft s/d approved, partial, reject, dst.)
 *  - available  : ordered - delivered - in_process  (yang BELUM dijadwalkan; batas atas DO baru)
 * DO berstatus RELEASED_STATUSES (closed) tidak dihitung: kuantitasnya kembali ke SO.
 * Item yang belum pernah punya baris DO (mis. SO hasil impor legacy) memakai cache delivered_quantity.
 *
 * Kolom sale_order_items.delivered_quantity hanyalah CACHE dari angka `delivered` dan
 * HANYA boleh ditulis oleh service ini (syncDeliveredCache) — jangan menulisnya dari
 * observer/resource lain.
 */
class SaleOrderDeliveryProgress
{
    /**
     * Ekspresi SQL "kuantitas terikat DO" (terkirim + sedang diproses) untuk satu item SO,
     * dipakai sebagai subquery di scope/whereRaw.
     */
    public static function committedQuantitySql(string $itemTable = 'sale_order_items'): string
    {
        $released = implode(',', array_map(fn ($s) => "'" . $s . "'", DeliveryOrder::RELEASED_STATUSES));

        // Item yang PERNAH punya baris DO (walau sudah dihapus/ditutup) dihitung dari DO.
        // Item yang belum pernah punya DO (mis. SO hasil impor legacy) memakai cache delivered_quantity.
        return "CASE WHEN EXISTS (SELECT 1 FROM delivery_order_items hist WHERE hist.sale_order_item_id = {$itemTable}.id) "
            . "THEN COALESCE((SELECT SUM(doi.quantity) FROM delivery_order_items doi "
            . "INNER JOIN delivery_orders d ON d.id = doi.delivery_order_id "
            . "WHERE doi.sale_order_item_id = {$itemTable}.id "
            . "AND doi.deleted_at IS NULL AND d.deleted_at IS NULL "
            . "AND d.status NOT IN ({$released})), 0) "
            . "ELSE COALESCE({$itemTable}.delivered_quantity, 0) END";
    }

    /** Ekspresi SQL sisa yang belum dijadwalkan: qty item - kuantitas terikat DO. */
    public static function availableQuantitySql(string $itemTable = 'sale_order_items'): string
    {
        return "({$itemTable}.quantity - " . self::committedQuantitySql($itemTable) . ')';
    }

    /**
     * @param  iterable<int>  $saleOrderItemIds
     * @param  int|null  $excludeDeliveryOrderId  abaikan DO ini (mis. DO yang sedang diedit)
     * @param  int|null  $excludeDeliveryOrderItemId  abaikan satu baris item DO (guard saat item disimpan)
     * @return array<int, array{ordered: float, delivered: float, in_process: float, remaining: float, available: float}>
     */
    public function forItems(iterable $saleOrderItemIds, ?int $excludeDeliveryOrderId = null, ?int $excludeDeliveryOrderItemId = null): array
    {
        $ids = collect($saleOrderItemIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $items = DB::table('sale_order_items')
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->get(['id', 'quantity', 'delivered_quantity'])
            ->keyBy('id');
        $ordered = $items->map(fn ($row) => $row->quantity);

        // Item yang PERNAH punya baris DO (termasuk yang sudah dihapus/ditutup) dihitung dari DO.
        // Item tanpa riwayat DO (mis. SO hasil impor legacy) memakai cache delivered_quantity sebagai sumber.
        $hasDeliveryHistory = DB::table('delivery_order_items')
            ->whereIn('sale_order_item_id', $ids)
            ->distinct()
            ->pluck('sale_order_item_id')
            ->flip();

        $delivered = DeliveryOrder::DELIVERED_STATUSES;
        $placeholders = implode(',', array_fill(0, count($delivered), '?'));

        $rows = DB::table('delivery_order_items as doi')
            ->join('delivery_orders as d', 'd.id', '=', 'doi.delivery_order_id')
            ->whereIn('doi.sale_order_item_id', $ids)
            ->whereNull('doi.deleted_at')
            ->whereNull('d.deleted_at')
            ->whereNotIn('d.status', DeliveryOrder::RELEASED_STATUSES)
            ->when($excludeDeliveryOrderId, fn ($q) => $q->where('d.id', '!=', $excludeDeliveryOrderId))
            ->when($excludeDeliveryOrderItemId, fn ($q) => $q->where('doi.id', '!=', $excludeDeliveryOrderItemId))
            ->selectRaw(
                "doi.sale_order_item_id as item_id, "
                . "SUM(CASE WHEN d.status IN ({$placeholders}) THEN doi.quantity ELSE 0 END) as delivered, "
                . "SUM(CASE WHEN d.status IN ({$placeholders}) THEN 0 ELSE doi.quantity END) as in_process",
                array_merge($delivered, $delivered)
            )
            ->groupBy('doi.sale_order_item_id')
            ->get()
            ->keyBy('item_id');

        $result = [];
        foreach ($ids as $id) {
            if (! $ordered->has($id)) {
                continue;
            }

            $qty = (float) $ordered[$id];
            $done = $hasDeliveryHistory->has($id)
                ? (float) ($rows[$id]->delivered ?? 0)
                : (float) ($items[$id]->delivered_quantity ?? 0);
            $open = (float) ($rows[$id]->in_process ?? 0);

            $result[$id] = [
                'ordered' => $qty,
                'delivered' => $done,
                'in_process' => $open,
                'remaining' => max(0.0, $qty - $done),
                'available' => max(0.0, $qty - $done - $open),
            ];
        }

        return $result;
    }

    /**
     * Ringkasan satu SO: per item + total.
     *
     * @return array{items: array<int, array>, totals: array{ordered: float, delivered: float, in_process: float, remaining: float, available: float}}
     */
    public function forSaleOrder(SaleOrder|int $saleOrder, ?int $excludeDeliveryOrderId = null): array
    {
        $id = $saleOrder instanceof SaleOrder ? $saleOrder->getKey() : (int) $saleOrder;

        $itemIds = DB::table('sale_order_items')
            ->where('sale_order_id', $id)
            ->whereNull('deleted_at')
            ->pluck('id');

        $items = $this->forItems($itemIds, $excludeDeliveryOrderId);

        $totals = ['ordered' => 0.0, 'delivered' => 0.0, 'in_process' => 0.0, 'remaining' => 0.0, 'available' => 0.0];
        foreach ($items as $row) {
            foreach ($totals as $key => $_) {
                $totals[$key] += $row[$key];
            }
        }

        return ['items' => $items, 'totals' => $totals];
    }

    /**
     * Tulis ulang cache delivered_quantity untuk item SO. SATU-SATUNYA penulis kolom itu.
     * Baris dikunci (FOR UPDATE) agar penyelesaian DO yang bersamaan tidak saling menimpa.
     *
     * @param  iterable<int>  $saleOrderItemIds
     */
    public function syncDeliveredCache(iterable $saleOrderItemIds): void
    {
        $ids = collect($saleOrderItemIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($ids) {
            DB::table('sale_order_items')->whereIn('id', $ids)->lockForUpdate()->pluck('id');

            $current = DB::table('sale_order_items')->whereIn('id', $ids)->pluck('delivered_quantity', 'id');
            $progress = $this->forItems($ids);

            foreach ($progress as $itemId => $row) {
                if (abs((float) ($current[$itemId] ?? 0) - $row['delivered']) > 0.0001) {
                    // Query builder: sengaja tanpa event model (bukan perubahan yang perlu di-log per item).
                    DB::table('sale_order_items')->where('id', $itemId)->update(['delivered_quantity' => $row['delivered']]);
                }
            }
        });
    }

    /**
     * ID item SO yang disentuh sebuah DO — termasuk baris yang sudah di-soft-delete
     * (dipakai saat DO dihapus / item dilepas).
     *
     * @return Collection<int, int>
     */
    public function saleOrderItemIdsOfDeliveryOrder(int $deliveryOrderId): Collection
    {
        return DB::table('delivery_order_items')
            ->where('delivery_order_id', $deliveryOrderId)
            ->whereNotNull('sale_order_item_id')
            ->pluck('sale_order_item_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Sinkronkan cache + status SO untuk semua SO yang disentuh sebuah DO.
     * Aman dipanggil berulang (idempoten).
     */
    public function syncForDeliveryOrder(DeliveryOrder|int $deliveryOrder): void
    {
        $deliveryOrderId = $deliveryOrder instanceof DeliveryOrder ? $deliveryOrder->getKey() : (int) $deliveryOrder;

        $itemIds = $this->saleOrderItemIdsOfDeliveryOrder($deliveryOrderId);
        $this->syncForSaleOrderItems($itemIds);
    }

    /**
     * @param  iterable<int>  $saleOrderItemIds
     */
    public function syncForSaleOrderItems(iterable $saleOrderItemIds): void
    {
        $itemIds = collect($saleOrderItemIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($itemIds->isEmpty()) {
            return;
        }

        $this->syncDeliveredCache($itemIds);

        $saleOrderIds = DB::table('sale_order_items')->whereIn('id', $itemIds)->pluck('sale_order_id')->unique();
        $synchronizer = app(SaleOrderStatusSynchronizer::class);
        foreach ($saleOrderIds as $saleOrderId) {
            $synchronizer->sync((int) $saleOrderId);
        }
    }
}
