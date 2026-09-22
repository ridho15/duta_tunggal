<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Mengisi Quotation.created_by yang kosong dari activity log (peristiwa "Quotation dibuat.").
 *
 *  - DEFAULT dry-run; perubahan hanya dengan --apply;
 *  - hanya mengisi created_by yang NULL dan hanya bila activity log punya causer;
 *  - yang tidak dapat dilacak dibiarkan NULL (UI menampilkan "Legacy / tidak tercatat");
 *  - --apply menulis CSV sebelum/sesudah.
 */
class BackfillQuotationCreator extends Command
{
    protected $signature = 'quotations:backfill-creator
        {--apply : Terapkan perubahan ke database (default: dry-run, hanya melaporkan)}';

    protected $description = 'Isi created_by quotation yang kosong dari activity log (dry-run secara default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('Backfill pembuat Quotation' . ($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $rows = [];
        $missing = 0;

        Quotation::withoutGlobalScopes()->whereNull('created_by')->orderBy('id')->each(function (Quotation $quotation) use (&$rows, &$missing) {
            $causerId = DB::table('activity_log')
                ->where('subject_type', Quotation::class)
                ->where('subject_id', $quotation->id)
                ->where('description', 'Quotation dibuat.')
                ->whereNotNull('causer_id')
                ->orderBy('id')
                ->value('causer_id');

            if ($causerId && DB::table('users')->where('id', $causerId)->exists()) {
                $rows[] = ['quotation_id' => $quotation->id, 'quotation_number' => $quotation->quotation_number, 'created_by_lama' => null, 'created_by_baru' => $causerId];
            } else {
                $missing++;
            }
        });

        if ($rows === []) {
            $this->info("Tidak ada created_by yang dapat dilengkapi. Tetap kosong (tak terlacak): {$missing}.");

            return self::SUCCESS;
        }

        $this->table(['ID', 'Nomor', 'Lama', 'Baru'], array_map(fn ($r) => [$r['quotation_id'], $r['quotation_number'], 'NULL', $r['created_by_baru']], $rows));
        $this->info(count($rows) . " quotation dapat dilengkapi; {$missing} tetap kosong (tak terlacak).");

        if (! $apply) {
            $this->warn('Dry-run selesai. Jalankan ulang dengan --apply setelah laporan di atas ditinjau.');

            return self::SUCCESS;
        }

        $path = storage_path('app/backfill/quotation-creator-' . now()->format('Ymd_His') . '.csv');
        File::ensureDirectoryExists(dirname($path));
        $fh = fopen($path, 'w');
        fputcsv($fh, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                // Query builder: tanpa event/observer.
                DB::table('quotations')->where('id', $row['quotation_id'])->whereNull('created_by')->update(['created_by' => $row['created_by_baru']]);
            }
        });

        $this->info(count($rows) . " quotation dilengkapi. CSV sebelum/sesudah: {$path}");

        return self::SUCCESS;
    }
}
