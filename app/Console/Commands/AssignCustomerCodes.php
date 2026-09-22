<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\DocumentNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * T4.2 (D32) — beri customer lama kode CUST-00001 berurutan menurut id; kode lama disimpan di `legacy_code`.
 * Dry-run secara default (transaksi dibatalkan → nomor identik dengan --apply); --apply menulis CSV pemetaan lama→baru.
 * Aman diulang: customer yang sudah berkode CUST-##### dilewati; customer yang sudah digabung tidak disentuh.
 */
class AssignCustomerCodes extends Command
{
    protected $signature = 'customers:assign-codes
        {--apply : Tulis kode baru. Default: dry-run}
        {--no-csv : Jangan tulis CSV}
        {--out-dir= : Folder CSV (default storage/app/audits)}';

    protected $description = 'Beri customer lama kode CUST-##### berurutan (kode lama → legacy_code); dry-run secara default';

    public function handle(DocumentNumberService $numbers): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('KODE CUSTOMER CUST-#####'.($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $customers = Customer::query()->whereNull('merged_into')->orderBy('id')->get()
            ->reject(fn (Customer $c) => preg_match('/^CUST-\d{5}$/', (string) $c->code));

        $rows = [];
        DB::beginTransaction();
        try {
            foreach ($customers as $customer) {
                $old = (string) $customer->code;
                $new = $numbers->next('customer');
                $customer->forceFill(['legacy_code' => $customer->legacy_code ?: $old, 'code' => $new])->save();
                $rows[] = ['id' => $customer->id, 'nama' => $customer->name, 'kode_lama' => $old, 'kode_baru' => $new];
            }

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->line(sprintf('Customer yang diberi kode: %d dari %d (sisanya sudah berkode CUST-#####)', count($rows), Customer::whereNull('merged_into')->count()));
        if ($rows !== []) {
            $this->table(['ID', 'Nama', 'Kode lama', 'Kode baru'], array_map(fn ($r) => array_values($r), array_slice($rows, 0, 20)));
            if (count($rows) > 20) {
                $this->line('… '.(count($rows) - 20).' baris lain — lihat CSV.');
            }
            if (! $this->option('no-csv')) {
                $this->writeCsv($rows);
            }
        }
        $this->line($apply ? 'Selesai — kode ditulis.' : 'Dry-run selesai. Jalankan dengan --apply di luar jam kerja setelah meninjau CSV.');

        return self::SUCCESS;
    }

    private function writeCsv(array $rows): void
    {
        $dir = (string) ($this->option('out-dir') ?: storage_path('app/audits'));
        File::ensureDirectoryExists($dir);
        $path = rtrim($dir, '/').'/kode-customer-'.($this->option('apply') ? 'apply' : 'dryrun').'-'.now()->format('Ymd-His').'.csv';
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        $this->line("CSV: {$path}");
    }
}
