<?php

namespace App\Console\Commands;

use App\Models\InvoiceItem;
use App\Models\SaleOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi rincian baris invoice PENJUALAN lama (invoice_items.gross_amount & discount_amount).
 *
 *  - DEFAULT dry-run; perubahan hanya dengan --apply; --apply menulis CSV sebelum/sesudah;
 *  - HANYA mengisi dua kolom baru yang masih NULL. Nilai yang sudah diposting (price, discount, subtotal,
 *    tax_amount, total) TIDAK PERNAH diubah — jurnal dan piutang tidak tersentuh;
 *  - `price` invoice lama bisa GROSS (jalur SO) atau NET (jalur Delivery Order / form lama). Basis ditebak dengan
 *    mencocokkan nilai bersih baris (sama seperti InvoiceItem::breakdown()); yang NET dan yang tidak cocok
 *    DILAPORKAN terpisah untuk ditinjau, dan tidak diisi bila tidak cocok.
 */
class BackfillInvoiceLineBreakdown extends Command
{
    protected $signature = 'invoices:backfill-line-breakdown
        {--apply : Terapkan perubahan (default: dry-run, hanya melaporkan)}
        {--limit=0 : Batasi jumlah baris yang diperiksa (0 = semua)}';

    protected $description = 'Lengkapi gross_amount/discount_amount invoice penjualan lama (dry-run secara default)';

    public function handle(): int
    {
        if (! Schema::hasColumn('invoice_items', 'gross_amount') || ! Schema::hasColumn('invoice_items', 'discount_amount')) {
            $this->error('Kolom invoice_items.gross_amount/discount_amount belum ada. Jalankan dulu: php artisan migrate');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info('Backfill rincian baris invoice penjualan' . ($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $stats = ['diperiksa' => 0, 'gross' => 0, 'net' => 0, 'tidak-cocok' => 0];
        $updates = [];
        $review = [];

        InvoiceItem::query()
            ->whereNull('gross_amount')
            ->whereHas('invoice', fn ($q) => $q->where('from_model_type', SaleOrder::class))
            ->with('invoice')
            ->orderBy('id')
            ->when((int) $this->option('limit') > 0, fn ($q) => $q->limit((int) $this->option('limit')))
            ->each(function (InvoiceItem $item) use (&$stats, &$updates, &$review) {
                $stats['diperiksa']++;
                $b = $item->breakdown();
                $stats[$b['basis']] = ($stats[$b['basis']] ?? 0) + 1;

                if ($b['basis'] === 'tidak-cocok') {
                    $review[] = [$item->id, $item->invoice->invoice_number, 'nilai baris tidak cocok dengan harga × qty — tidak diisi'];

                    return;
                }

                if ($b['basis'] === 'net') {
                    $review[] = [$item->id, $item->invoice->invoice_number, 'price bersifat NET (setelah diskon); gross dihitung mundur, price TIDAK diubah'];
                }

                $updates[] = [
                    'id' => $item->id,
                    'invoice' => $item->invoice->invoice_number,
                    'basis' => $b['basis'],
                    'gross_amount' => $b['gross'],
                    'discount_amount' => $b['discount_amount'],
                ];
            });

        $this->table(['Basis price', 'Baris'], [
            ['gross (tidak perlu tinjauan)', $stats['gross']],
            ['net (dilaporkan)', $stats['net']],
            ['tidak cocok (tidak diisi)', $stats['tidak-cocok']],
            ['diperiksa', $stats['diperiksa']],
        ]);

        if ($review !== []) {
            $this->warn('Perlu ditinjau (' . count($review) . '):');
            $this->table(['Item', 'Invoice', 'Catatan'], array_slice($review, 0, 40));
            if (count($review) > 40) {
                $this->line('… dan ' . (count($review) - 40) . ' baris lain (lengkap di CSV saat --apply).');
            }
        }

        if ($updates === []) {
            $this->info('Tidak ada baris yang perlu dilengkapi.');

            return self::SUCCESS;
        }

        $this->info(count($updates) . ' baris dapat dilengkapi.');

        if (! $apply) {
            $this->warn('Dry-run selesai. Jalankan ulang dengan --apply setelah laporan di atas ditinjau.');

            return self::SUCCESS;
        }

        $path = storage_path('app/backfill/invoice-line-breakdown-' . now()->format('Ymd_His') . '.csv');
        File::ensureDirectoryExists(dirname($path));
        $fh = fopen($path, 'w');
        fputcsv($fh, ['invoice_item_id', 'invoice', 'basis', 'gross_amount_lama', 'gross_amount_baru', 'discount_amount_lama', 'discount_amount_baru']);

        DB::transaction(function () use ($updates, $fh) {
            foreach ($updates as $row) {
                fputcsv($fh, [$row['id'], $row['invoice'], $row['basis'], '', $row['gross_amount'], '', $row['discount_amount']]);
                // Query builder: tanpa observer/event dan hanya dua kolom baru.
                DB::table('invoice_items')->where('id', $row['id'])->whereNull('gross_amount')->update([
                    'gross_amount' => $row['gross_amount'],
                    'discount_amount' => $row['discount_amount'],
                ]);
            }
        });
        fclose($fh);

        $this->info(count($updates) . " baris dilengkapi. CSV: {$path}");

        return self::SUCCESS;
    }
}
