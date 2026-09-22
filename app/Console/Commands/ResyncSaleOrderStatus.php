<?php

namespace App\Console\Commands;

use App\Models\SaleOrder;
use App\Services\SaleOrderDeliveryProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Menghitung ulang progres pengiriman SO lama dari Delivery Order:
 *  - cache sale_order_items.delivered_quantity
 *  - status SO (approved / partially_delivered / completed) sesuai kuantitas terkirim
 *
 * Contoh yang dibetulkan: SO "completed" padahal baru 12 dari 20 pcs terkirim
 * (akibat aturan lama "DO pertama selesai -> SO selesai").
 *
 * Keselamatan:
 *  - DEFAULT dry-run; perubahan hanya dengan --apply;
 *  - memakai aturan yang sama dengan sinkronisasi otomatis (SaleOrderStatusSynchronizer),
 *    jadi SO tanpa DO (mis. Ambil Sendiri) tidak disentuh;
 *  - tidak menyentuh invoice, stok, maupun jurnal; --apply menulis CSV sebelum/sesudah.
 */
class ResyncSaleOrderStatus extends Command
{
    protected $signature = 'sales:resync-so-status
        {--apply : Terapkan perubahan ke database (default: dry-run, hanya melaporkan)}
        {--so= : Batasi ke satu nomor SO}';

    protected $description = 'Hitung ulang delivered_quantity dan status SO (Disetujui/Dikirim Sebagian/Selesai) dari Delivery Order (dry-run secara default)';

    public function handle(SaleOrderDeliveryProgress $progress): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('Resync progres pengiriman SO' . ($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $orders = SaleOrder::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('status', SaleOrder::DELIVERY_MANAGED_STATUSES)
            ->when($this->option('so'), fn ($q, $so) => $q->where('so_number', $so))
            ->whereExists(function ($q) {
                // hanya SO yang punya item yang dirujuk item DO
                $q->select(DB::raw(1))
                    ->from('sale_order_items as soi')
                    ->join('delivery_order_items as doi', 'doi.sale_order_item_id', '=', 'soi.id')
                    ->whereColumn('soi.sale_order_id', 'sale_orders.id')
                    ->whereNull('doi.deleted_at');
            })
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($orders as $so) {
            $summary = $progress->forSaleOrder($so);
            $cacheChanges = [];

            foreach ($summary['items'] as $itemId => $row) {
                $current = (float) DB::table('sale_order_items')->where('id', $itemId)->value('delivered_quantity');
                if (abs($current - $row['delivered']) > 0.0001) {
                    $cacheChanges[$itemId] = [$current, $row['delivered']];
                }
            }

            $target = app(\App\Services\SaleOrderStatusSynchronizer::class)->targetStatus($so, $summary);

            if ($cacheChanges === [] && $target === null) {
                continue;
            }

            $rows[] = [
                'so_number' => $so->so_number,
                'status_lama' => $so->status,
                'status_baru' => $target ?? $so->status,
                'dipesan' => $summary['totals']['ordered'],
                'terkirim' => $summary['totals']['delivered'],
                'dalam_proses_do' => $summary['totals']['in_process'],
                'item_cache_berubah' => count($cacheChanges),
                'sale_order_id' => $so->id,
            ];
        }

        if ($rows === []) {
            $this->info('Diperiksa ' . $orders->count() . ' SO. Semua sudah konsisten.');

            return self::SUCCESS;
        }

        $this->table(
            ['SO', 'Status lama', 'Status baru', 'Dipesan', 'Terkirim', 'Proses DO', 'Item cache berubah'],
            array_map(fn ($r) => [$r['so_number'], $r['status_lama'], $r['status_baru'], $r['dipesan'], $r['terkirim'], $r['dalam_proses_do'], $r['item_cache_berubah']], $rows)
        );
        $this->info(sprintf('Diperiksa %d SO; %d SO akan disesuaikan. Invoice/stok/jurnal TIDAK diubah.', $orders->count(), count($rows)));

        if (! $apply) {
            $this->warn('Dry-run selesai. Jalankan ulang dengan --apply setelah laporan di atas ditinjau.');

            return self::SUCCESS;
        }

        $path = storage_path('app/backfill/resync-so-status-' . now()->format('Ymd_His') . '.csv');
        File::ensureDirectoryExists(dirname($path));
        $fh = fopen($path, 'w');
        fputcsv($fh, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        $synchronizer = app(\App\Services\SaleOrderStatusSynchronizer::class);
        foreach ($rows as $row) {
            $itemIds = array_keys($progress->forSaleOrder((int) $row['sale_order_id'])['items']);
            $progress->syncDeliveredCache($itemIds);
            $synchronizer->sync((int) $row['sale_order_id']);
        }

        $this->info(count($rows) . " SO disesuaikan. CSV sebelum/sesudah: {$path}");

        return self::SUCCESS;
    }
}
