<?php

namespace App\Console\Commands;

use App\Models\SaleOrder;
use App\Services\SalesOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Melengkapi data header Sales Order lama yang kosong (mata uang, kurs, tempo,
 * alamat kirim, catatan).
 *
 * Aturan keselamatan:
 *  - DEFAULT dry-run: hanya melaporkan. Perubahan baru terjadi dengan --apply.
 *  - HANYA mengisi kolom yang kosong (NULL / string kosong); nilai yang sudah ada tidak pernah ditimpa.
 *  - Tidak menyentuh item, total, status, maupun invoice (jatuh tempo invoice yang sudah
 *    terbit TIDAK diubah; hanya dilaporkan jumlahnya).
 *  - --apply menulis CSV sebelum/sesudah agar bisa dipulihkan.
 */
class BackfillSaleOrderFromQuotation extends Command
{
    protected $signature = 'sales:backfill-so-from-quotation
        {--apply : Terapkan perubahan ke database (default: dry-run, hanya melaporkan)}';

    protected $description = 'Lengkapi mata uang, kurs, tempo, alamat kirim, dan catatan pada Sales Order lama yang masih kosong (dry-run secara default)';

    public function handle(SalesOrderService $service): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('Backfill header Sales Order' . ($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $rows = [];          // baris laporan CSV
        $updates = [];       // id => [kolom => nilai baru]
        $scanned = 0;

        SaleOrder::withoutGlobalScopes()
            ->with(['customer', 'quotation.customer'])
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('currency_id')
                    ->orWhereNull('exchange_rate')
                    ->orWhereNull('tempo_pembayaran')
                    ->orWhereNull('shipped_to')
                    ->orWhere('shipped_to', '')
                    ->orWhereNull('notes');
            })
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($service, &$rows, &$updates, &$scanned) {
                foreach ($orders as $so) {
                    $scanned++;

                    $quotation = $so->quotation?->exists ? $so->quotation : null;
                    $customer = $so->customer?->exists ? $so->customer : null;

                    // Sumber nilai: quotation (pemetaan tunggal) bila ada, selain itu master customer / default.
                    if ($quotation) {
                        $src = $service->headerFromQuotation($quotation);
                    } else {
                        $currencyId = $service->defaultCurrencyId();
                        $src = [
                            'currency_id' => $currencyId,
                            'exchange_rate' => \App\Support\CurrencyConversionResolver::resolveRate($currencyId),
                            'tempo_pembayaran' => $service->resolveTempoPembayaran(null, $customer),
                            'shipped_to' => filled($customer?->address) ? trim($customer->address) : null,
                            'notes' => null,
                        ];
                    }

                    $new = [];
                    if ($so->currency_id === null && $src['currency_id'] !== null) {
                        $new['currency_id'] = $src['currency_id'];
                    }
                    if (($so->exchange_rate === null || (float) $so->exchange_rate <= 0) && ! empty($src['exchange_rate'])) {
                        $new['exchange_rate'] = $src['exchange_rate'];
                    }
                    if ($so->tempo_pembayaran === null) {
                        $new['tempo_pembayaran'] = $src['tempo_pembayaran'];
                    }
                    if (! filled($so->shipped_to) && filled($src['shipped_to'])) {
                        $new['shipped_to'] = $src['shipped_to'];
                    }
                    if (! filled($so->notes) && filled($src['notes'])) {
                        $new['notes'] = $src['notes'];
                    }

                    if ($new === []) {
                        continue;
                    }

                    $updates[$so->id] = $new;
                    foreach ($new as $column => $value) {
                        $rows[] = [
                            'sale_order_id' => $so->id,
                            'so_number' => $so->so_number,
                            'quotation_id' => $so->quotation_id,
                            'column' => $column,
                            'old' => $so->getRawOriginal($column),
                            'new' => $value,
                        ];
                    }
                }
            });

        if ($rows === []) {
            $this->info("Diperiksa {$scanned} SO. Tidak ada yang perlu dilengkapi.");

            return self::SUCCESS;
        }

        $this->table(['SO', 'Kolom', 'Lama', 'Baru'], array_map(
            fn ($r) => [$r['so_number'], $r['column'], $r['old'] ?? 'NULL', $r['new']],
            $rows
        ));

        $invoiceCount = DB::table('invoices')
            ->where('from_model_type', SaleOrder::class)
            ->whereIn('from_model_id', array_keys($updates))
            ->whereNull('deleted_at')
            ->count();

        $this->info(sprintf(
            'Diperiksa %d SO; %d SO akan dilengkapi (%d kolom). Invoice terkait: %d (TIDAK diubah).',
            $scanned,
            count($updates),
            count($rows),
            $invoiceCount
        ));

        if (! $apply) {
            $this->warn('Dry-run selesai. Jalankan ulang dengan --apply setelah laporan di atas ditinjau.');

            return self::SUCCESS;
        }

        $path = storage_path('app/backfill/so-from-quotation-' . now()->format('Ymd_His') . '.csv');
        File::ensureDirectoryExists(dirname($path));
        $fh = fopen($path, 'w');
        fputcsv($fh, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $columns) {
                // Query builder: sengaja tanpa observer/event agar tidak memicu jurnal/log massal.
                DB::table('sale_orders')->where('id', $id)->update($columns + ['updated_at' => now()]);
            }
        });

        $this->info('Selesai. ' . count($updates) . " SO diperbarui. CSV sebelum/sesudah: {$path}");

        return self::SUCCESS;
    }
}
