<?php

namespace App\Console\Commands;

use App\Models\DeliveryOrder;
use App\Observers\DeliveryOrderObserver;
use App\Services\DeliveryOrderReservations;
use App\Services\DeliveryShipments;
use App\Services\SaleOrderReservationSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Migrasi data satu kali: DO yang BELUM berangkat tetapi stok fisiknya sudah terpotong oleh aturan lama ("Tahap 3": stok dipotong saat
 * DO dibuat). Aturan sekarang: stok bebas DIKUNCI saat DO dibuat dan stok fisik baru berkurang saat DO dikirim.
 *
 * Per DO terbuka (bukan sent/received/completed/closed/reject):
 *   1. kuantitas yang masih "di luar gudang" (netShipped) dikembalikan ke stok fisik lewat gerakan `adjustment_in` berjejak;
 *   2. stoknya dikunci ulang lewat reservasi level-DO (isi DO terbaru);
 *   3. reservasi level-SO disusun ulang bila flag `sales.stock.reserve_on_so_approve` hidup.
 *
 * Stok bebas (fisik − terkunci) tidak berubah; yang berubah adalah stok fisik kembali benar dan kolom Terpesan terisi.
 * Default DRY-RUN; `--apply` mengerjakannya per DO dalam transaksi dan menulis CSV. Idempoten: DO yang sudah dikonversi dilewati.
 */
class ConvertOpenDoDeductions extends Command
{
    protected $signature = 'stock:convert-open-do-deductions
        {--apply : Kerjakan konversi. Default: dry-run (tidak mengubah apa pun)}
        {--do= : Batasi ke satu DO (id atau nomor DO)}
        {--no-csv : Jangan tulis CSV}
        {--out-dir= : Folder CSV (default storage/app/audits)}';

    protected $description = 'Kembalikan pemotongan stok DO yang belum dikirim (aturan lama) dan ganti dengan kunci stok (reservasi DO)';

    private const SKIP_STATUSES = ['sent', 'received', 'completed', 'closed', 'reject'];

    public function handle(DeliveryShipments $shipments, DeliveryOrderReservations $reservations): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('KONVERSI POTONGAN STOK DO TERBUKA → KUNCI STOK'.($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $rows = [];
        $doCount = 0;
        foreach ($this->candidates() as $deliveryOrder) {
            $lines = $this->outstanding($shipments, $deliveryOrder);
            if ($lines === []) {
                continue;
            }
            $doCount++;

            if ($apply) {
                DB::transaction(function () use ($deliveryOrder, $shipments, $reservations) {
                    $shipments->reverse($deliveryOrder, 'konversi aturan stok: DO belum dikirim, pemotongan saat DO dibuat dikembalikan dan stok kini dikunci');
                    $reservations->sync($deliveryOrder->fresh(), "Stok dikunci untuk DO {$deliveryOrder->do_number} (konversi aturan stok)", strict: false);
                    app(SaleOrderReservationSynchronizer::class)->syncForDeliveryOrder($deliveryOrder, "Konversi aturan stok DO {$deliveryOrder->do_number}");
                });
            }

            foreach ($lines as $line) {
                $rows[] = [
                    'do_id' => $deliveryOrder->id,
                    'do_number' => $deliveryOrder->do_number,
                    'status' => $deliveryOrder->status,
                    'product' => $line['product'],
                    'warehouse' => $line['warehouse'],
                    'qty' => $line['qty'],
                ];
            }
        }

        $total = array_sum(array_column($rows, 'qty'));
        $this->line(sprintf('DO terbuka dengan stok terpotong: %d DO · %d baris · total %s unit', $doCount, count($rows), $this->num($total)));

        if ($rows !== []) {
            $this->table(['DO', 'Status', 'Produk', 'Gudang', 'Qty dikembalikan & dikunci'], array_map(
                fn ($r) => [$r['do_number'], $r['status'], $r['product'], $r['warehouse'], $this->num($r['qty'])],
                array_slice($rows, 0, 50)
            ));
            if (count($rows) > 50) {
                $this->line('… '.(count($rows) - 50).' baris lain — lihat CSV.');
            }

            if (! $this->option('no-csv')) {
                $this->line('CSV: '.$this->writeCsv($rows, $apply));
            }
        }

        $this->line($apply
            ? 'Selesai. Stok fisik DO terbuka dikembalikan dan stoknya kini dikunci (kolom Terpesan).'
            : 'Dry-run: jalankan dengan --apply untuk mengerjakan konversi.');

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\LazyCollection<int, DeliveryOrder> */
    private function candidates()
    {
        $filter = $this->option('do');

        return DeliveryOrder::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNotIn('status', self::SKIP_STATUSES)
            ->when($filter, fn ($q) => is_numeric($filter) ? $q->whereKey((int) $filter) : $q->where('do_number', $filter))
            ->orderBy('id')
            ->lazy();
    }

    /** @return array<int, array{product: string, warehouse: string, qty: float}> */
    private function outstanding(DeliveryShipments $shipments, DeliveryOrder $deliveryOrder): array
    {
        if (DeliveryOrderObserver::isDispatched($deliveryOrder)) {
            return [];
        }

        $lines = [];
        foreach ($shipments->sources($deliveryOrder) as $source) {
            $net = DeliveryShipments::netShipped($source['item'], $source['warehouse_id'], $source['rak_id']);
            if ($net > 0.00001) {
                $lines[] = [
                    'product' => (string) ($source['item']->product?->name ?? 'Produk #'.$source['item']->product_id),
                    'warehouse' => (string) (DB::table('warehouses')->where('id', $source['warehouse_id'])->value('name') ?? 'Gudang #'.$source['warehouse_id']),
                    'qty' => $net,
                ];
            }
        }

        return $lines;
    }

    private function writeCsv(array $rows, bool $apply): string
    {
        $dir = $this->option('out-dir') ?: storage_path('app/audits');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/konversi-potongan-do-'.($apply ? 'apply' : 'dryrun').'-'.now()->format('Ymd-His').'.csv';

        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['do_id', 'do_number', 'status', 'produk', 'gudang', 'qty']);
        foreach ($rows as $row) {
            fputcsv($handle, [$row['do_id'], $row['do_number'], $row['status'], $row['product'], $row['warehouse'], $row['qty']]);
        }
        fclose($handle);

        return $path;
    }

    private function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') ?: '0';
    }
}
