<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\CustomerDuplicateFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Audit customer ganda — READ-ONLY (satu-satunya keluaran tulis: CSV di storage/app/audits).
 *
 * Menghasilkan KANDIDAT grup duplikat (nama sama/mirip, NPWP/NIK sama, telepon sama) lengkap dengan jumlah transaksi,
 * piutang, deposit, dan saran survivor — untuk dibawa ke bisnis (keputusan D14). Penggabungan sendiri baru di T4.
 */
class AuditCustomerDuplicates extends Command
{
    protected $signature = 'customers:audit-duplicates
        {--min-score=0.88 : Ambang kemiripan nama (0-1)}
        {--no-csv : Jangan tulis CSV (hanya ringkasan di layar)}
        {--out-dir= : Folder CSV (default storage/app/audits)}';

    protected $description = 'Audit customer ganda (read-only): kandidat grup, transaksi, piutang, deposit, saran survivor → CSV';

    /** Tabel yang mereferensikan customer_id → label kolom CSV */
    private const TRANSACTION_TABLES = [
        'quotations' => 'quotation',
        'sale_orders' => 'sale_order',
        'account_receivables' => 'piutang_baris',
        'customer_receipts' => 'penerimaan',
        'customer_returns' => 'retur',
        'other_sales' => 'penjualan_lain',
    ];

    public function handle(): int
    {
        $customers = Customer::query()
            ->get(['id', 'code', 'name', 'perusahaan', 'nik_npwp', 'phone', 'telephone', 'cabang_id', 'created_at'])
            ->map(fn ($c) => $c->only(['id', 'code', 'name', 'perusahaan', 'nik_npwp', 'phone', 'telephone', 'cabang_id']) + ['created_at' => (string) $c->created_at])
            ->keyBy('id');

        $groups = (new CustomerDuplicateFinder)->groups($customers->values()->all(), (float) $this->option('min-score'));

        $counts = $this->transactionCounts();
        $usage = $this->outstandingReceivables();
        $deposits = $this->depositBalances();
        $cabangs = DB::table('cabangs')->pluck('kode', 'id');

        $nikLikeCodes = $customers->filter(fn ($c) => preg_match('/^\d{12,}$/', (string) $c['code']) === 1)->count();
        $noTaxId = $customers->filter(fn ($c) => strlen(CustomerDuplicateFinder::digits($c['nik_npwp'])) < 15)->count();

        $csvRows = [];
        $screenRows = [];

        foreach ($groups as $index => $group) {
            $groupId = $index + 1;
            $members = collect($group['ids'])->map(function (int $id) use ($customers, $counts) {
                $total = collect(array_values(self::TRANSACTION_TABLES))->sum(fn ($label) => $counts[$label][$id] ?? 0);

                return ['id' => $id, 'total' => $total, 'created' => $customers[$id]['created_at']];
            });
            // Saran survivor: transaksi terbanyak, lalu yang paling lama dibuat, lalu id terkecil
            $survivor = $members->sort(fn ($a, $b) => [$b['total'], $a['created'], $a['id']] <=> [$a['total'], $b['created'], $b['id']])->first()['id'];

            foreach ($group['ids'] as $id) {
                $c = $customers[$id];
                $row = [
                    'grup' => $groupId,
                    'alasan' => implode('; ', $group['reasons']),
                    'saran_survivor' => $id === $survivor ? 'YA' : '',
                    'id' => $id,
                    'kode' => $c['code'],
                    'kode_menyerupai_nik' => preg_match('/^\d{12,}$/', (string) $c['code']) === 1 ? 'YA' : '',
                    'nama' => $c['name'],
                    'perusahaan' => $c['perusahaan'],
                    'nik_npwp' => $c['nik_npwp'],
                    'telepon' => $c['phone'] ?: $c['telephone'],
                    'cabang' => $cabangs[$c['cabang_id']] ?? '',
                ];
                foreach (self::TRANSACTION_TABLES as $label) {
                    $row[$label] = $counts[$label][$id] ?? 0;
                }
                $row['total_transaksi'] = collect(array_values(self::TRANSACTION_TABLES))->sum(fn ($label) => $counts[$label][$id] ?? 0);
                $row['piutang_berjalan'] = round((float) ($usage[$id] ?? 0), 2);
                $row['saldo_deposit'] = round((float) ($deposits[$id] ?? 0), 2);
                $row['dibuat'] = $c['created_at'];

                $csvRows[] = $row;
            }

            $screenRows[] = [
                $groupId,
                implode('; ', $group['reasons']),
                collect($group['ids'])->map(fn ($id) => "#{$id} {$customers[$id]['name']} ({$customers[$id]['code']})".($id === $survivor ? ' ★' : ''))->implode(' | '),
            ];
        }

        $this->info('AUDIT CUSTOMER GANDA — read-only, tidak ada data yang diubah');
        $this->line(sprintf(
            'Customer: %d · Grup kandidat duplikat: %d (melibatkan %d customer) · Kode menyerupai NIK: %d · Tanpa NPWP/NIK valid: %d',
            $customers->count(),
            count($groups),
            count($csvRows),
            $nikLikeCodes,
            $noTaxId
        ));

        if ($screenRows !== []) {
            $this->table(['Grup', 'Alasan', 'Anggota (★ = saran survivor)'], array_slice($screenRows, 0, 30));
            if (count($screenRows) > 30) {
                $this->line('… '.(count($screenRows) - 30).' grup lain — lihat CSV.');
            }
        }

        if (! $this->option('no-csv') && $csvRows !== []) {
            $path = $this->writeCsv($csvRows);
            $this->info("CSV: {$path}");
        }

        $this->warn('Hasil adalah KANDIDAT (heuristik). Bisnis memutuskan survivor (D14); penggabungan dilakukan di tahap T4 dengan cadangan.');

        return self::SUCCESS;
    }

    /** @return array<string, array<int, int>> label → [customer_id => jumlah] */
    private function transactionCounts(): array
    {
        $counts = [];
        foreach (self::TRANSACTION_TABLES as $table => $label) {
            $counts[$label] = DB::table($table)->whereNull('deleted_at')->whereNotNull('customer_id')
                ->selectRaw('customer_id, COUNT(*) as n')->groupBy('customer_id')->pluck('n', 'customer_id')->map(fn ($n) => (int) $n)->all();
        }

        return $counts;
    }

    /** Piutang berjalan per customer — predikat SAMA dengan CreditValidationService::getCurrentCreditUsage. */
    private function outstandingReceivables(): array
    {
        return DB::table('account_receivables')->whereNull('deleted_at')
            ->where('status', \App\Enums\PaymentStatus::UNPAID->value)
            ->selectRaw('customer_id, SUM(remaining) as total')->groupBy('customer_id')->pluck('total', 'customer_id')->all();
    }

    private function depositBalances(): array
    {
        return DB::table('deposits')->whereNull('deleted_at')->where('from_model_type', Customer::class)
            ->selectRaw('from_model_id, SUM(remaining_amount) as total')->groupBy('from_model_id')->pluck('total', 'from_model_id')->all();
    }

    private function writeCsv(array $rows): string
    {
        $dir = (string) ($this->option('out-dir') ?: storage_path('app/audits'));
        File::ensureDirectoryExists($dir);
        $path = rtrim($dir, '/').'/customers-duplicates-'.now()->format('Ymd-His').'.csv';

        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");   // BOM agar Excel membaca UTF-8
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}
