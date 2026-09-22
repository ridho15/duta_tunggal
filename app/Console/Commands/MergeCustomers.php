<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\CustomerMerger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * T4.3 — gabung customer ganda dari CSV YANG DISETUJUI BISNIS (kolom: survivor_id, merged_id). Dry-run secara default:
 * menjalankan penggabungan sungguhan di dalam transaksi lalu MEMBATALKANNYA, jadi hasil & verifikasi identik dengan --apply.
 * --apply menulis CSV cadangan (tabel, id dokumen, customer lama) lebih dulu. Jalankan di luar jam kerja (D14/D33).
 */
class MergeCustomers extends Command
{
    protected $signature = 'customers:merge
        {csv : Berkas CSV dengan kolom survivor_id,merged_id}
        {--apply : Lakukan penggabungan. Default: dry-run}
        {--out-dir= : Folder CSV cadangan (default storage/app/audits)}';

    protected $description = 'Gabungkan customer ganda dari CSV yang disetujui bisnis (dry-run secara default, dengan cadangan CSV)';

    public function handle(CustomerMerger $merger): int
    {
        $pairs = $this->readPairs((string) $this->argument('csv'));
        if ($pairs === null) {
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info('GABUNG CUSTOMER'.($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]').' — '.count($pairs).' pasangan');

        $backup = [];
        $rows = [];
        $failed = 0;

        DB::beginTransaction();
        try {
            foreach ($pairs as [$survivorId, $mergedId]) {
                $survivor = Customer::find($survivorId);
                $merged = Customer::find($mergedId);
                if (! $survivor || ! $merged) {
                    $rows[] = [$survivorId, $mergedId, 'DILEWATI', 'customer tidak ditemukan'];
                    $failed++;

                    continue;
                }

                DB::beginTransaction();   // savepoint per pasangan: satu pasangan gagal tidak membatalkan yang lain
                try {
                    $result = $merger->merge($survivor, $merged);
                    DB::commit();
                } catch (ValidationException $e) {
                    DB::rollBack();
                    $rows[] = [$survivorId, $mergedId, 'DITOLAK', collect($e->errors())->flatten()->implode(' ')];
                    $failed++;

                    continue;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $rows[] = [$survivorId, $mergedId, 'GAGAL', $e->getMessage()];
                    $failed++;

                    continue;
                }

                $movedTotal = array_sum(array_map('count', $result['moved']));
                $rows[] = [$survivorId, $mergedId, 'OK', sprintf('%d dokumen dipindah · piutang %s · deposit %s', $movedTotal, number_format($result['after']['receivables'], 0, ',', '.'), number_format($result['after']['deposit'], 0, ',', '.'))];
                foreach ($result['moved'] as $table => $ids) {
                    foreach ($ids as $id) {
                        $backup[] = ['tabel' => $table, 'id' => $id, 'customer_lama' => $mergedId, 'customer_baru' => $survivorId];
                    }
                }
            }

            if ($apply) {
                $this->writeBackup($backup);
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['Survivor', 'Digabung', 'Hasil', 'Keterangan'], $rows);
        $this->line($apply ? 'Selesai.' : 'Dry-run selesai. Jalankan dengan --apply di luar jam kerja setelah meninjau hasilnya.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<int, array{0: int, 1: int}>|null */
    private function readPairs(string $path): ?array
    {
        if (! is_file($path)) {
            $this->error("Berkas CSV tidak ditemukan: {$path}");

            return null;
        }

        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), (array) fgetcsv($handle));
        $survivorIdx = array_search('survivor_id', $header, true);
        $mergedIdx = array_search('merged_id', $header, true);
        if ($survivorIdx === false || $mergedIdx === false) {
            fclose($handle);
            $this->error('CSV harus memiliki kolom survivor_id dan merged_id.');

            return null;
        }

        $pairs = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (isset($row[$survivorIdx], $row[$mergedIdx]) && is_numeric($row[$survivorIdx]) && is_numeric($row[$mergedIdx])) {
                $pairs[] = [(int) $row[$survivorIdx], (int) $row[$mergedIdx]];
            }
        }
        fclose($handle);

        return $pairs;
    }

    private function writeBackup(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $dir = (string) ($this->option('out-dir') ?: storage_path('app/audits'));
        File::ensureDirectoryExists($dir);
        $path = rtrim($dir, '/').'/gabung-customer-cadangan-'.now()->format('Ymd-His').'.csv';
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        $this->line("CSV cadangan: {$path}");
    }
}
