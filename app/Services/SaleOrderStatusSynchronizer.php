<?php

namespace App\Services;

use App\Models\SaleOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menurunkan status SO dari KUANTITAS yang benar-benar terkirim (bukan dari event DO):
 *
 *   terkirim = 0                 -> approved   (hanya bila sebelumnya partially_delivered/completed)
 *   0 < terkirim < dipesan       -> partially_delivered  ("Dikirim Sebagian")
 *   terkirim >= dipesan (semua)  -> completed
 *
 * Aturan keselamatan:
 *  - hanya menyentuh SO berstatus SaleOrder::DELIVERY_MANAGED_STATUSES;
 *  - hanya SO yang terhubung ke DO (pivot delivery_sales_orders) yang naik ke partially_delivered/completed;
 *    SO tanpa DO (mis. "Ambil Sendiri" diselesaikan manual) TIDAK diubah, kecuali partially_delivered
 *    yang DO-nya sudah tidak ada (dikembalikan ke approved);
 *  - perubahan lewat $saleOrder->update() agar observer (invoice, stok self-pickup) tetap berjalan.
 */
class SaleOrderStatusSynchronizer
{
    public function __construct(private SaleOrderDeliveryProgress $progress)
    {
    }

    /**
     * @return string|null status baru bila berubah, null bila tidak ada perubahan
     */
    public function sync(SaleOrder|int $saleOrder): ?string
    {
        $model = $saleOrder instanceof SaleOrder
            ? $saleOrder
            : SaleOrder::withoutGlobalScopes()->find($saleOrder);

        if (! $model || ! in_array($model->status, SaleOrder::DELIVERY_MANAGED_STATUSES, true)) {
            return null;
        }

        $summary = $this->progress->forSaleOrder($model);
        $target = $this->targetStatus($model, $summary);

        if ($target === null) {
            return null;
        }

        Log::info('SaleOrderStatusSynchronizer: status SO disesuaikan dengan progres pengiriman', [
            'sale_order_id' => $model->id,
            'so_number' => $model->so_number,
            'from' => $model->status,
            'to' => $target,
            'ordered' => $summary['totals']['ordered'],
            'delivered' => $summary['totals']['delivered'],
        ]);

        $model->update([
            'status' => $target,
            'completed_at' => $target === 'completed' ? ($model->completed_at ?? now()) : null,
        ]);

        return $target;
    }

    /**
     * Status target untuk sebuah SO berdasarkan progres kuantitas (null = tidak berubah).
     * Dipakai juga oleh command `sales:resync-so-status` agar dry-run identik dengan sinkronisasi nyata.
     *
     * @param  array{items: array<int, array>, totals: array<string, float>}  $summary  hasil SaleOrderDeliveryProgress::forSaleOrder()
     */
    public function targetStatus(SaleOrder $model, array $summary): ?string
    {
        if (! in_array($model->status, SaleOrder::DELIVERY_MANAGED_STATUSES, true) || $summary['items'] === []) {
            return null;
        }

        // SO dianggap "dikirim lewat DO" hanya bila terhubung ke DO (pivot delivery_sales_orders) — sama seperti
        // perilaku lama observer DO dan pembuatan invoice per-DO. DO yang di-soft-delete tidak dihitung.
        $linkedToDeliveryOrder = DB::table('delivery_sales_orders as pivot')
            ->join('delivery_orders as d', 'd.id', '=', 'pivot.delivery_order_id')
            ->where('pivot.sales_order_id', $model->id)
            ->whereNull('d.deleted_at')
            ->exists();

        $allDelivered = collect($summary['items'])->every(fn ($row) => $row['delivered'] + 0.0001 >= $row['ordered']);
        $anyDelivered = $summary['totals']['delivered'] > 0;

        if ($linkedToDeliveryOrder && $allDelivered && $anyDelivered) {
            $target = 'completed';
        } elseif ($linkedToDeliveryOrder && $anyDelivered) {
            $target = 'partially_delivered';
        } elseif (! $anyDelivered && in_array($model->status, ['partially_delivered', 'completed'], true) && ($linkedToDeliveryOrder || $model->status === 'partially_delivered')) {
            // Belum ada yang terkirim (DO batal/gagal/dihapus): mundur ke approved bila sebelumnya sudah bergerak.
            // "completed" tanpa DO sama sekali (mis. Ambil Sendiri diselesaikan manual) TIDAK dimundurkan.
            $target = 'approved';
        } else {
            return null;
        }

        return $target === $model->status ? null : $target;
    }
}
