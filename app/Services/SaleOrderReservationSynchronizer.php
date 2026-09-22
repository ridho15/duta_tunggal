<?php

namespace App\Services;

use App\Models\SaleOrder;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * Reservasi stok level-SO (T2.4, flag `sales.stock.reserve_on_so_approve`) dengan model KEADAAN-YANG-DIINGINKAN:
 * dipanggil ulang pada setiap peristiwa dan selalu menghasilkan keadaan yang sama (idempoten).
 *
 * Per item SO:
 *   kebutuhan = qty − terkirim (Σ DO sent/received/completed)               ← SaleOrderDeliveryProgress::remaining
 *   ditahan DO = Σ reservasi baris DO terbuka (DO Siap Kirim) untuk item ini
 *   target SO  = kebutuhan − ditahan DO                                     ← total tertahan per item ≤ kebutuhan
 * Reservasi SO ditempatkan: alokasi gudang item → gudang item → (D22) gudang cabang dengan stok bebas terbesar, dan DIBATASI stok bebas
 * yang ada (D21: parsial bila stok kurang; kekurangan = backorder, diisi ulang FIFO saat stok masuk — `sales:top-up-reservations`).
 *
 * SO non-aktif (draft, ditolak, dibatalkan, ditutup, selesai): reservasi level-SO dilepas.
 */
class SaleOrderReservationSynchronizer
{
    /** Status SO yang menahan stok. */
    public const ACTIVE_STATUSES = ['approved', 'confirmed', 'partial_confirmed', 'partially_delivered', 'request_close'];

    private const EPS = 0.00001;

    public function __construct(
        private readonly StockReservationLedger $ledger,
        private readonly StockAvailability $availability,
        private readonly SaleOrderDeliveryProgress $progress,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('sales.stock.reserve_on_so_approve', false);
    }

    /**
     * @return array<int, array{needed: float, held_by_do: float, target: float, held: float, shortage: float}> per id item SO
     */
    public function sync(SaleOrder|int $saleOrder, string $reason = 'Sinkron reservasi SO'): array
    {
        $id = $saleOrder instanceof SaleOrder ? $saleOrder->getKey() : $saleOrder;

        return DB::transaction(function () use ($id, $reason) {
            $so = SaleOrder::withoutGlobalScopes()->lockForUpdate()->find($id);
            if (! $so) {
                return [];
            }

            if (! in_array($so->status, self::ACTIVE_STATUSES, true)) {
                $this->releaseOwn($so, $reason.' — SO tidak lagi aktif');

                return [];
            }

            $so->load('saleOrderItem.warehouseAllocations');
            $itemIds = $so->saleOrderItem->pluck('id')->all();
            $progress = $this->progress->forItems($itemIds);

            $summary = [];
            foreach ($so->saleOrderItem as $item) {
                $needed = (float) ($progress[$item->id]['remaining'] ?? 0.0);
                $heldByDo = (float) StockReservation::query()
                    ->where('sale_order_item_id', $item->id)->whereNotNull('delivery_order_id')->sum('quantity');
                $target = max(0.0, $needed - $heldByDo);

                $existing = StockReservation::query()
                    ->where('sale_order_id', $so->id)->where('sale_order_item_id', $item->id)->whereNull('delivery_order_id')
                    ->whereNull('material_issue_id')->orderBy('id')->get();

                $placements = $this->placements($so, $item, $target, $existing);
                $this->reconcile($so, $item, $existing, $placements, $reason);

                $held = array_sum(array_column($placements, 'quantity'));
                $summary[$item->id] = [
                    'needed' => $needed,
                    'held_by_do' => $heldByDo,
                    'target' => $target,
                    'held' => (float) $held,
                    'shortage' => max(0.0, $target - $held),
                ];
            }

            // Reservasi level-SO milik item yang sudah tidak ada pada SO (item dihapus) dilepas
            StockReservation::query()
                ->where('sale_order_id', $so->id)->whereNull('delivery_order_id')->whereNull('material_issue_id')
                ->where(fn ($q) => $q->whereNull('sale_order_item_id')->orWhereNotIn('sale_order_item_id', $itemIds ?: [0]))
                ->get()->each(fn ($row) => $this->ledger->release($row, $reason.' — item SO tidak ada lagi'));

            return $summary;
        });
    }

    /**
     * Sinkronkan semua SO yang disentuh sebuah DO (lewat item DO dan pivot) — dipanggil setiap DO berpindah status/kuantitas.
     * Tidak melakukan apa pun bila flag mati.
     */
    public function syncForDeliveryOrder(\App\Models\DeliveryOrder|int $deliveryOrder, string $reason = 'Perubahan Delivery Order'): void
    {
        if (! self::enabled()) {
            return;
        }

        $id = $deliveryOrder instanceof \App\Models\DeliveryOrder ? $deliveryOrder->getKey() : $deliveryOrder;

        $saleOrderIds = DB::table('delivery_order_items as doi')
            ->join('sale_order_items as soi', 'soi.id', '=', 'doi.sale_order_item_id')
            ->where('doi.delivery_order_id', $id)
            ->pluck('soi.sale_order_id')
            ->merge(DB::table('delivery_sales_orders')->where('delivery_order_id', $id)->pluck('sales_order_id'))
            ->unique()->sort()->values();

        foreach ($saleOrderIds as $saleOrderId) {
            $this->sync((int) $saleOrderId, $reason);
        }
    }

    /** Sinkronkan satu SO hanya bila statusnya aktif (dipakai hook item SO agar pengeditan draf tidak membebani). */
    public function syncIfActive(int $saleOrderId, string $reason): void
    {
        if (! self::enabled()) {
            return;
        }

        $status = DB::table('sale_orders')->where('id', $saleOrderId)->value('status');
        if (in_array($status, self::ACTIVE_STATUSES, true)) {
            $this->sync($saleOrderId, $reason);
        }
    }

    /** Lepas semua reservasi level-SO (baris DO dibiarkan; itu urusan DO). */
    private function releaseOwn(SaleOrder $so, string $reason): void
    {
        StockReservation::query()
            ->where('sale_order_id', $so->id)->whereNull('delivery_order_id')->whereNull('material_issue_id')
            ->get()->each(fn ($row) => $this->ledger->release($row, $reason));
    }

    /**
     * Penempatan reservasi untuk satu item sebesar $target (dibatasi stok bebas).
     * Stok bebas yang dipakai = bebas saat ini + reservasi level-SO item ini yang sudah ada di gudang itu (akan disusun ulang).
     *
     * @return array<int, array{warehouse_id: int, rak_id: int|null, quantity: float}>
     */
    private function placements(SaleOrder $so, $item, float $target, $existing): array
    {
        if ($target <= self::EPS) {
            return [];
        }

        $own = fn (int $warehouseId, ?int $rakId): float => (float) $existing
            ->filter(fn ($r) => (int) $r->warehouse_id === $warehouseId && ($r->rak_id ? (int) $r->rak_id : null) === $rakId)
            ->sum('quantity');
        $free = fn (int $warehouseId, ?int $rakId): float => max(0.0, $this->availability->freeInWarehouse((int) $item->product_id, $warehouseId, $rakId) + $own($warehouseId, $rakId));

        $placements = [];
        $left = $target;
        $place = function (int $warehouseId, ?int $rakId, float $cap) use (&$placements, &$left, $free) {
            $take = min($left, $cap, $free($warehouseId, $rakId));
            if ($take > self::EPS) {
                $placements[] = ['warehouse_id' => $warehouseId, 'rak_id' => $rakId, 'quantity' => $take];
                $left -= $take;
            }
        };

        if ($item->warehouseAllocations->isNotEmpty()) {
            foreach ($item->warehouseAllocations->sortBy('id') as $allocation) {
                if ($allocation->warehouse_id && $left > self::EPS) {
                    $place((int) $allocation->warehouse_id, null, (float) $allocation->quantity);
                }
            }

            return $placements;
        }

        if ($item->warehouse_id) {
            $place((int) $item->warehouse_id, $item->rak_id ? (int) $item->rak_id : null, $left);

            return $placements;
        }

        // D22: item tanpa gudang → gudang cabang SO dengan stok bebas terbesar lebih dulu (data item tidak diubah).
        $candidates = $this->availability->stockByWarehouse((int) $item->product_id, $this->availability->accessibleWarehouseIds($so->cabang_id ? (int) $so->cabang_id : null));
        uasort($candidates, fn ($a, $b) => $b['free'] <=> $a['free']);
        foreach (array_keys($candidates) as $warehouseId) {
            if ($left > self::EPS) {
                $place((int) $warehouseId, null, $left);
            }
        }

        return $placements;
    }

    /** @param  \Illuminate\Support\Collection<int, StockReservation>  $existing */
    private function reconcile(SaleOrder $so, $item, $existing, array $placements, string $reason): void
    {
        $key = fn ($warehouseId, $rakId) => $warehouseId.'|'.($rakId ?: 0);
        $byKey = [];
        foreach ($existing as $row) {
            $byKey[$key($row->warehouse_id, $row->rak_id)][] = $row;
        }

        foreach ($placements as $placement) {
            $k = $key($placement['warehouse_id'], $placement['rak_id']);
            $current = isset($byKey[$k]) ? array_shift($byKey[$k]) : null;

            if ($current) {
                $this->ledger->adjust($current, $placement['quantity'], $reason);
            } else {
                $this->ledger->reserve([
                    'sale_order_id' => $so->id,
                    'sale_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $placement['warehouse_id'],
                    'rak_id' => $placement['rak_id'],
                    'quantity' => $placement['quantity'],
                ], $reason);
            }
        }

        foreach ($byKey as $leftovers) {
            foreach ($leftovers as $row) {
                $this->ledger->release($row, $reason);
            }
        }
    }
}
