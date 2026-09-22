<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\CreditValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Audit limit kredit customer — READ-ONLY (satu-satunya keluaran tulis: CSV di storage/app/audits).
 *
 * Mendaftar customer bertipe Kredit yang limitnya perlu ditinjau bisnis: limit 0 (kini dianggap TAK TERBATAS oleh
 * CreditValidationService), limit sangat tinggi (mis. Rp999.999.999.999), tempo 0, atau piutang berjalan sudah melebihi limit.
 * Mengoreksi angka adalah KEPUTUSAN BISNIS — perintah ini tidak mengubah data apa pun.
 */
class AuditCustomerCreditLimit extends Command
{
    protected $signature = 'customers:audit-credit-limit
        {--threshold=1000000000 : Limit >= nilai ini dianggap mencurigakan (kemungkinan "tak terbatas")}
        {--no-csv : Jangan tulis CSV}
        {--out-dir= : Folder CSV (default storage/app/audits)}';

    protected $description = 'Audit limit kredit customer (read-only): limit 0, terlalu tinggi, tempo 0, sudah melebihi limit → CSV';

    public function handle(CreditValidationService $credit): int
    {
        $threshold = (float) $this->option('threshold');

        $creditCustomers = Customer::query()->where('tipe_pembayaran', 'Kredit')->orderBy('name')->get();
        $otherWithLimit = Customer::query()->where('tipe_pembayaran', '!=', 'Kredit')->where('kredit_limit', '>', 0)->count();

        $rows = [];
        foreach ($creditCustomers as $customer) {
            $flags = [];
            $limit = (float) $customer->kredit_limit;

            if ($limit <= 0) {
                $flags[] = 'LIMIT 0 (dianggap tak terbatas oleh sistem)';
            }
            if ($limit >= $threshold) {
                $flags[] = 'LIMIT SANGAT TINGGI (≥ Rp'.number_format($threshold, 0, ',', '.').')';
            }
            if ((int) $customer->tempo_kredit === 0) {
                $flags[] = 'TEMPO 0 HARI';
            }

            $summary = $credit->getCreditSummary($customer);
            $usage = (float) $summary['current_usage'];
            if ($limit > 0 && $usage > $limit) {
                $flags[] = 'PIUTANG MELEBIHI LIMIT';
            }
            if ((int) $summary['overdue_count'] > 0) {
                $flags[] = 'ADA TAGIHAN JATUH TEMPO';
            }

            if ($flags === []) {
                continue;
            }

            $rows[] = [
                'id' => $customer->id,
                'kode' => $customer->code,
                'nama' => $customer->name,
                'kredit_limit' => round($limit, 2),
                'tempo_kredit_hari' => (int) $customer->tempo_kredit,
                'piutang_berjalan' => round($usage, 2),
                'persen_terpakai' => $limit > 0 ? round($usage / $limit * 100, 2) : null,
                'invoice_jatuh_tempo' => (int) $summary['overdue_count'],
                'total_jatuh_tempo' => round((float) $summary['overdue_total'], 2),
                'catatan' => implode('; ', $flags),
            ];
        }

        $this->info('AUDIT LIMIT KREDIT — read-only, tidak ada data yang diubah');
        $this->line(sprintf(
            'Customer bertipe Kredit: %d · perlu ditinjau: %d · customer non-Kredit dengan limit > 0 (tidak berlaku): %d',
            $creditCustomers->count(),
            count($rows),
            $otherWithLimit
        ));

        if ($rows !== []) {
            $this->table(
                ['ID', 'Kode', 'Nama', 'Limit', 'Tempo', 'Piutang', '%', 'Catatan'],
                array_map(fn ($r) => [$r['id'], $r['kode'], $r['nama'], number_format($r['kredit_limit'], 0, ',', '.'), $r['tempo_kredit_hari'], number_format($r['piutang_berjalan'], 0, ',', '.'), $r['persen_terpakai'] ?? '–', $r['catatan']], array_slice($rows, 0, 40))
            );
            if (count($rows) > 40) {
                $this->line('… '.(count($rows) - 40).' baris lain — lihat CSV.');
            }

            if (! $this->option('no-csv')) {
                $this->info('CSV: '.$this->writeCsv($rows));
            }
        }

        $this->warn('Koreksi angka limit adalah keputusan bisnis — perintah ini tidak mengubah data.');

        return self::SUCCESS;
    }

    private function writeCsv(array $rows): string
    {
        $dir = (string) ($this->option('out-dir') ?: storage_path('app/audits'));
        File::ensureDirectoryExists($dir);
        $path = rtrim($dir, '/').'/customers-credit-limit-'.now()->format('Ymd-His').'.csv';

        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}
