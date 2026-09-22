<?php

namespace App\Console\Commands;

use App\Models\StockReservation;
use App\Models\StockReservationEvent;
use App\Services\StockReservationLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Rekonsiliasi reservasi stok penjualan (T2.1, temuan X1).
 *
 *  A. YATIM (dapat diperbaiki dengan --apply): baris `stock_reservations` penjualan yang induknya sudah berakhir —
 *     DO sudah dikirim/selesai/ditutup/ditolak/terhapus, atau SO dibatalkan/ditutup/selesai/terhapus. Dulu reservasi DO tidak pernah
 *     dilepas setelah barang berangkat sehingga stok bebas terus menyusut. Dihapus lewat buku besar (event `reconciled`).
 *  B. TAK TERJELASKAN (HANYA DILAPORKAN, keputusan D17): selisih `qty_reserved` terhadap Σ reservasi aktif per produk×gudang.
 *     Sumbernya di luar reservasi penjualan (impor legacy, Retur Pembelian yang menaikkan qty_reserved) — perlu tinjauan gudang/akuntansi.
 *  C. STOK NEGATIF (HANYA DILAPORKAN): baris `qty_available` < 0.
 *
 * Default DRY-RUN. `--apply` hanya mengerjakan A dan menulis CSV cadangan (baris + stok sebelum/sesudah) lebih dulu.
 * Reservasi Material Issue tidak pernah disentuh.
 */
class ReconcileStockReservations extends Command
{
    protected $signature = 'stock:reconcile-reservations
        {--apply : Hapus reservasi yatim (bagian A). Default: dry-run}
        {--sale-order= : Batasi bagian A ke satu SO (id)}
        {--no-csv : Jangan tulis CSV}
        {--out-dir= : Folder CSV (default storage/app/audits)}';

    protected $description = 'Rekonsiliasi reservasi stok penjualan: hapus yatim (--apply), laporkan qty_reserved tak terjelaskan dan stok negatif';

    private const DO_ENDED = ['sent', 'received', 'completed', 'closed', 'reject'];

    private const SO_ENDED = ['canceled', 'closed', 'completed', 'reject'];

    public function handle(StockReservationLedger $ledger): int
    {
        $apply = (bool) $this->option('apply');
        $orphans = $this->orphans($this->option('sale-order') ? (int) $this->option('sale-order') : null);

        $this->info('REKONSILIASI RESERVASI STOK'.($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        // ── A. Yatim ────────────────────────────────────────────────
        $orphanQty = collect($orphans)->sum('quantity');
        $this->line(sprintf('A. Reservasi yatim: %d baris · total %s unit', count($orphans), $this->num($orphanQty)));
        if ($orphans !== []) {
            $this->table(['ID', 'Produk', 'Gudang', 'Qty', 'DO', 'SO', 'Sebab'], array_map(
                fn ($o) => [$o['id'], $o['product_id'], $o['warehouse_id'], $this->num($o['quantity']), $o['do_number'] ?: '–', $o['so_number'] ?: '–', $o['sebab']],
                array_slice($orphans, 0, 25)
            ));
            if (count($orphans) > 25) {
                $this->line('… '.(count($orphans) - 25).' baris lain — lihat CSV.');
            }
        }

        $files = [];
        if (! $this->option('no-csv') && $orphans !== []) {
            $files[] = $this->writeCsv('stock-reservation-orphans', $orphans);
        }

        // Dihitung SEBELUM apply agar laporan B/C konsisten (proyeksi setelah yatim dihapus)
        $unexplained = $this->unexplained($orphans);
        $negative = $this->negativeStock();

        if ($apply && $orphans !== []) {
            $files[] = $this->writeCsv('stock-reservation-backup', $this->backupRows($orphans));

            $removed = 0;
            foreach ($orphans as $orphan) {
                $reservation = StockReservation::find($orphan['id']);
                if ($reservation) {
                    $ledger->release($reservation, 'rekonsiliasi: '.$orphan['sebab'], StockReservationEvent::RECONCILED);
                    $removed++;
                }
            }
            $this->info("Dihapus: {$removed} reservasi yatim (qty_reserved diturunkan; event 'reconciled' tercatat).");
        } elseif ($orphans !== []) {
            $this->warn('Dry-run: jalankan dengan --apply untuk menghapus reservasi yatim (CSV cadangan ditulis lebih dulu).');
        }

        // ── B. Tak terjelaskan ──────────────────────────────────────
        $this->line('');
        $this->line(sprintf('B. qty_reserved TAK TERJELASKAN (hanya dilaporkan, D17): %d produk×gudang', count($unexplained)));
        if ($unexplained !== []) {
            $this->table(['Produk', 'Gudang', 'qty_reserved', 'Σ reservasi aktif', 'Selisih', 'Petunjuk'], array_map(
                fn ($u) => [$u['product_id'], $u['warehouse_id'], $this->num($u['qty_reserved']), $this->num($u['reservasi_aktif']), $this->num($u['selisih']), $u['petunjuk']],
                array_slice($unexplained, 0, 25)
            ));
            if (! $this->option('no-csv')) {
                $files[] = $this->writeCsv('stock-reservation-unexplained', $unexplained);
            }
            $this->warn('Tinjau bersama gudang/akuntansi; angka ini TIDAK diubah otomatis (bukan berasal dari reservasi penjualan).');
        }

        // ── C. Stok negatif ─────────────────────────────────────────
        $this->line('');
        $this->line(sprintf('C. Baris stok NEGATIF (hanya dilaporkan): %d', count($negative)));
        if ($negative !== [] && ! $this->option('no-csv')) {
            $files[] = $this->writeCsv('stock-negative', $negative);
        }

        foreach ($files as $file) {
            $this->info("CSV: {$file}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orphans(?int $saleOrderId): array
    {
        $rows = DB::table('stock_reservations as r')
            ->leftJoin('delivery_orders as d', 'd.id', '=', 'r.delivery_order_id')
            ->leftJoin('sale_orders as s', 's.id', '=', 'r.sale_order_id')
            ->whereNull('r.material_issue_id')
            ->when($saleOrderId, fn ($q) => $q->where('r.sale_order_id', $saleOrderId))
            ->select('r.id', 'r.product_id', 'r.warehouse_id', 'r.rak_id', 'r.quantity', 'r.sale_order_id', 'r.delivery_order_id',
                'd.status as do_status', 'd.deleted_at as do_deleted', 'd.do_number', 's.status as so_status', 's.deleted_at as so_deleted', 's.so_number')
            ->orderBy('r.id')
            ->get();

        $orphans = [];
        foreach ($rows as $row) {
            $reasons = [];

            if ($row->delivery_order_id) {
                if ($row->do_status === null) {
                    $reasons[] = 'DO tidak ada';
                } elseif ($row->do_deleted) {
                    $reasons[] = 'DO terhapus';
                } elseif (in_array($row->do_status, self::DO_ENDED, true)) {
                    $reasons[] = "DO {$row->do_status} (reservasi tak pernah dilepas)";
                }
            }

            if ($row->sale_order_id) {
                if ($row->so_status === null) {
                    $reasons[] = 'SO tidak ada';
                } elseif ($row->so_deleted) {
                    $reasons[] = 'SO terhapus';
                } elseif (in_array($row->so_status, self::SO_ENDED, true)) {
                    $reasons[] = "SO {$row->so_status}";
                }
            }

            if (! $row->delivery_order_id && ! $row->sale_order_id) {
                $reasons[] = 'tanpa DO/SO/Material Issue';
            }

            if ($reasons !== []) {
                $orphans[] = [
                    'id' => (int) $row->id, 'product_id' => (int) $row->product_id, 'warehouse_id' => (int) $row->warehouse_id, 'rak_id' => $row->rak_id,
                    'quantity' => (float) $row->quantity, 'sale_order_id' => $row->sale_order_id, 'so_number' => $row->so_number,
                    'delivery_order_id' => $row->delivery_order_id, 'do_number' => $row->do_number, 'sebab' => implode('; ', $reasons),
                ];
            }
        }

        return $orphans;
    }

    /** @return array<int, array<string, mixed>> */
    private function unexplained(array $orphans): array
    {
        $orphanIds = array_column($orphans, 'id');

        $active = DB::table('stock_reservations')
            ->when($orphanIds !== [], fn ($q) => $q->whereNotIn('id', $orphanIds))
            ->selectRaw('product_id, warehouse_id, SUM(quantity) as total')
            ->groupBy('product_id', 'warehouse_id')->get()
            ->mapWithKeys(fn ($r) => [$r->product_id.'|'.$r->warehouse_id => (float) $r->total]);

        $orphanQty = collect($orphans)->groupBy(fn ($o) => $o['product_id'].'|'.$o['warehouse_id'])->map(fn ($g) => (float) $g->sum('quantity'));

        $returned = \Illuminate\Support\Facades\Schema::hasTable('purchase_return_items')
            ? DB::table('purchase_return_items')->whereNull('deleted_at')->selectRaw('product_id, SUM(qty_returned) as total')->groupBy('product_id')->pluck('total', 'product_id')
            : collect();

        $rows = [];
        $stocks = DB::table('inventory_stocks')->whereNull('deleted_at')->selectRaw('product_id, warehouse_id, SUM(qty_reserved) as reserved')->groupBy('product_id', 'warehouse_id')->get();

        foreach ($stocks as $stock) {
            $key = $stock->product_id.'|'.$stock->warehouse_id;
            // Proyeksi setelah yatim dihapus (bila sudah di-apply, orphans kosong dan hitungan tetap benar)
            $projected = max(0.0, (float) $stock->reserved - ($orphanQty[$key] ?? 0.0));
            $explained = $active[$key] ?? 0.0;
            $diff = round($projected - $explained, 2);

            if (abs($diff) < 0.005) {
                continue;
            }

            $hint = $diff > 0
                ? (($returned[$stock->product_id] ?? 0) > 0
                    ? 'ada Retur Pembelian produk ini (Σ '.$this->num($returned[$stock->product_id]).') — kemungkinan qty_reserved dari retur; selain itu impor legacy'
                    : 'kemungkinan impor legacy / penyesuaian manual')
                : 'reservasi aktif LEBIH BESAR dari qty_reserved — periksa manual';

            $rows[] = [
                'product_id' => (int) $stock->product_id, 'warehouse_id' => (int) $stock->warehouse_id, 'qty_reserved' => round($projected, 2),
                'reservasi_aktif' => round($explained, 2), 'selisih' => $diff, 'petunjuk' => $hint,
            ];
        }

        usort($rows, fn ($a, $b) => abs($b['selisih']) <=> abs($a['selisih']));

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function negativeStock(): array
    {
        return DB::table('inventory_stocks')->whereNull('deleted_at')->where('qty_available', '<', 0)->orderBy('qty_available')
            ->get(['id', 'product_id', 'warehouse_id', 'rak_id', 'qty_available', 'qty_reserved'])
            ->map(fn ($r) => (array) $r)->all();
    }

    /** Baris yang akan dihapus + stok sebelum. */
    private function backupRows(array $orphans): array
    {
        return array_map(function (array $orphan) {
            $stock = DB::table('inventory_stocks')->where('product_id', $orphan['product_id'])->where('warehouse_id', $orphan['warehouse_id'])
                ->selectRaw('SUM(qty_available) as available, SUM(qty_reserved) as reserved')->first();

            return $orphan + ['stok_fisik_sebelum' => (float) ($stock->available ?? 0), 'qty_reserved_sebelum' => (float) ($stock->reserved ?? 0)];
        }, $orphans);
    }

    private function writeCsv(string $name, array $rows): string
    {
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

        return $path;
    }

    private function num(float|int|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
