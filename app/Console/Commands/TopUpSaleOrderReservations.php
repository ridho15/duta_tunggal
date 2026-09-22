<?php

namespace App\Console\Commands;

use App\Models\SaleOrder;
use App\Services\SaleOrderReservationSynchronizer;
use Illuminate\Console\Command;

/**
 * T2.5 (D21) — isi ulang reservasi SO yang masih kurang (backorder) ketika stok masuk.
 *
 * FIFO menurut waktu approve: SO yang lebih lama mendapat stok bebas lebih dulu. Memakai SaleOrderReservationSynchronizer yang sama
 * dengan alur nyata (idempoten; hanya menambah bila ada stok bebas). Aman dijalankan berulang; dijadwalkan tiap 30 menit
 * (routes/console.php). Tidak melakukan apa-apa bila flag `sales.stock.reserve_on_so_approve` mati.
 */
class TopUpSaleOrderReservations extends Command
{
    protected $signature = 'sales:top-up-reservations
        {--sale-order= : Batasi ke satu SO (id)}
        {--dry-run : Hanya laporkan SO yang masih kurang, tanpa menahan stok}';

    protected $description = 'Isi ulang reservasi SO yang masih kurang (backorder) secara FIFO menurut waktu approve';

    public function handle(SaleOrderReservationSynchronizer $synchronizer): int
    {
        if (! SaleOrderReservationSynchronizer::enabled()) {
            $this->line('Flag sales.stock.reserve_on_so_approve mati — tidak ada yang dikerjakan.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $orders = SaleOrder::withoutGlobalScopes()
            ->whereIn('status', SaleOrderReservationSynchronizer::ACTIVE_STATUSES)
            ->when($this->option('sale-order'), fn ($q) => $q->whereKey((int) $this->option('sale-order')))
            ->orderByRaw('COALESCE(approve_at, created_at) asc')->orderBy('id')
            ->get(['id', 'so_number']);

        $toppedUp = 0;
        $stillShort = 0;

        // Dry-run: jalankan yang sama persis lalu batalkan (hasil identik dengan nyata).
        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            foreach ($orders as $order) {
                $before = $this->reservedFor($order->id);
                $summary = $synchronizer->sync($order->id, "Isi ulang reservasi SO {$order->so_number}");
                $short = collect($summary)->sum('shortage');

                $gain = $this->reservedFor($order->id) - $before;
                if ($gain > 0.00001) {
                    $toppedUp++;
                    $this->line(sprintf('  %s: +%s tertahan%s', $order->so_number, $this->num($gain), $short > 0.00001 ? ' (masih kurang '.$this->num($short).')' : ' (penuh)'));
                }
                if ($short > 0.00001) {
                    $stillShort++;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            throw $e;
        }

        $dry ? \Illuminate\Support\Facades\DB::rollBack() : \Illuminate\Support\Facades\DB::commit();

        $this->info(($dry ? '[DRY-RUN] ' : '').sprintf('%d SO diperiksa · %d bertambah reservasinya · %d masih kurang.', $orders->count(), $toppedUp, $stillShort));

        return self::SUCCESS;
    }

    private function reservedFor(int $saleOrderId): float
    {
        return (float) \App\Models\StockReservation::where('sale_order_id', $saleOrderId)->sum('quantity');
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
