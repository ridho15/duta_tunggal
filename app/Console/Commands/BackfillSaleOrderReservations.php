<?php

namespace App\Console\Commands;

use App\Models\SaleOrder;
use App\Services\SaleOrderReservationSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * T2.4 — reservasi stok untuk SO yang sudah berjalan (Approved dst.) sebelum `stock.reserve_on_so_approve` dihidupkan.
 *
 * Berurutan menurut WAKTU APPROVE (FIFO: SO lebih lama mendapat stok lebih dulu). Memakai SaleOrderReservationSynchronizer yang sama
 * dengan alur nyata, jadi hasil dry-run identik dengan --apply: dry-run menjalankan semuanya di dalam transaksi lalu MEMBATALKANNYA.
 *
 * Urutan operasional: `stock:reconcile-reservations --apply` (hapus yatim) → perintah ini (dry-run, tinjau) → `--apply` → hidupkan flag.
 * `--apply` menulis CSV lebih dulu (hasil per item + stok sebelum/sesudah) sebagai cadangan/penjelasan; perubahan tercatat di
 * `stock_reservation_events`. Rollback = matikan flag dan `stock:reconcile-reservations` melaporkan sisa.
 */
class BackfillSaleOrderReservations extends Command
{
    protected $signature = 'sales:backfill-so-reservations
        {--apply : Tulis reservasi. Default: dry-run (tidak ada yang diubah)}
        {--sale-order= : Batasi ke satu SO (id)}
        {--no-csv : Jangan tulis CSV}
        {--out-dir= : Folder CSV (default storage/app/audits)}';

    protected $description = 'Buat reservasi stok level-SO untuk SO berjalan (FIFO menurut waktu approve); dry-run secara default';

    public function handle(SaleOrderReservationSynchronizer $synchronizer): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('BACKFILL RESERVASI SO'.($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $orders = SaleOrder::withoutGlobalScopes()
            ->whereIn('status', SaleOrderReservationSynchronizer::ACTIVE_STATUSES)
            ->when($this->option('sale-order'), fn ($q) => $q->whereKey((int) $this->option('sale-order')))
            ->orderByRaw('COALESCE(approve_at, created_at) asc')->orderBy('id')
            ->get(['id', 'so_number', 'status', 'approve_at', 'created_at']);

        $before = $this->stockSnapshot();
        $rows = [];

        DB::beginTransaction();
        try {
            foreach ($orders as $order) {
                $summary = $synchronizer->sync($order->id, "Backfill reservasi SO {$order->so_number}");

                foreach ($summary as $itemId => $line) {
                    $item = DB::table('sale_order_items')->where('id', $itemId)->first(['product_id']);
                    $rows[] = [
                        'so_number' => $order->so_number,
                        'status_so' => $order->status,
                        'approve_at' => $order->approve_at,
                        'item_id' => $itemId,
                        'product_id' => $item?->product_id,
                        'kebutuhan' => $line['needed'],
                        'ditahan_do' => $line['held_by_do'],
                        'target_so' => $line['target'],
                        'tertahan_so' => $line['held'],
                        'kurang' => $line['shortage'],
                        'hasil' => $this->classify($line),
                    ];
                }
            }

            $after = $this->stockSnapshot();

            if ($apply) {
                $this->writeCsvIfWanted('backfill-reservasi-so', $rows);
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $count = fn (string $class) => count(array_filter($rows, fn ($r) => $r['hasil'] === $class));
        $this->line(sprintf('SO diproses: %d · item: %d', $orders->count(), count($rows)));
        $this->table(['Hasil', 'Item'], [
            ['Tertahan penuh', $count('penuh')],
            ['Tertahan sebagian (backorder)', $count('sebagian')],
            ['Tidak dapat ditahan (stok habis)', $count('tidak')],
            ['Tidak ada kebutuhan tersisa', $count('tidak_perlu')],
        ]);

        $short = array_values(array_filter($rows, fn ($r) => $r['kurang'] > 0.00001));
        if ($short !== []) {
            $this->warn('Item dengan kekurangan (backorder):');
            $this->table(['SO', 'Produk', 'Target', 'Tertahan', 'Kurang'], array_map(
                fn ($r) => [$r['so_number'], $r['product_id'], $this->num($r['target_so']), $this->num($r['tertahan_so']), $this->num($r['kurang'])],
                array_slice($short, 0, 25)
            ));
        }

        $delta = 0.0;
        foreach ($after as $key => $reserved) {
            $delta += $reserved - ($before[$key] ?? 0.0);
        }
        $this->line(sprintf('Perubahan total qty_reserved: %s%s unit', $delta >= 0 ? '+' : '', $this->num($delta)));
        $this->line($apply ? 'Selesai — reservasi ditulis.' : 'Dry-run selesai. Jalankan dengan --apply setelah meninjau hasilnya.');

        if (! $apply) {
            $this->writeCsvIfWanted('backfill-reservasi-so-dryrun', $rows);
        }

        return self::SUCCESS;
    }

    /** @param  array{needed: float, held_by_do: float, target: float, held: float, shortage: float}  $line */
    private function classify(array $line): string
    {
        if ($line['target'] <= 0.00001) {
            return 'tidak_perlu';
        }

        return match (true) {
            $line['shortage'] <= 0.00001 => 'penuh',
            $line['held'] > 0.00001 => 'sebagian',
            default => 'tidak',
        };
    }

    /** @return array<string, float> "produk|gudang" => qty_reserved */
    private function stockSnapshot(): array
    {
        return DB::table('inventory_stocks')->whereNull('deleted_at')
            ->selectRaw('product_id, warehouse_id, SUM(qty_reserved) as reserved')->groupBy('product_id', 'warehouse_id')->get()
            ->mapWithKeys(fn ($r) => [$r->product_id.'|'.$r->warehouse_id => (float) $r->reserved])->all();
    }

    private function writeCsvIfWanted(string $name, array $rows): void
    {
        if ($this->option('no-csv') || $rows === []) {
            return;
        }

        $dir = (string) ($this->option('out-dir') ?: storage_path('app/audits'));
        File::ensureDirectoryExists($dir);
        $path = rtrim($dir, '/')."/{$name}-".now()->format('Ymd-His').'.csv';

        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $this->line("CSV: {$path}");
    }

    private function num(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
