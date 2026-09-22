<?php

namespace App\Console\Commands;

use App\Models\Cabang;
use App\Services\DocumentNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * T4.1 — benih urutan `document_sequences` dari nomor BERFORMAT BARU yang sudah ada di tabel dokumen (mis. flag sempat hidup lalu mati).
 * Nomor format lama tidak disentuh dan tidak dihitung. Dry-run secara default; --apply menulis CSV rencana lebih dulu.
 * Aman dijalankan berulang (hanya menaikkan last_number, tidak pernah menurunkan).
 */
class SeedDocumentSequences extends Command
{
    protected $signature = 'documents:seed-sequences
        {--apply : Tulis urutan. Default: dry-run}
        {--no-csv : Jangan tulis CSV}
        {--out-dir= : Folder CSV (default storage/app/audits)}';

    protected $description = 'Seed urutan nomor dokumen dari nomor berformat baru yang sudah ada (dry-run secara default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('SEED URUTAN NOMOR DOKUMEN'.($apply ? ' [APPLY]' : ' [DRY-RUN — tidak ada yang diubah]'));

        $cabangIds = Cabang::withoutGlobalScopes()->pluck('id', 'kode')->mapWithKeys(fn ($id, $kode) => [strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $kode)) => (int) $id])->all();
        $plan = [];   // "type|cabang|period" => [type,cabang,period,max,current]

        foreach (DocumentNumberService::TYPES as $type => $definition) {
            $numbers = DB::table($definition['table'])->whereNotNull($definition['column'])->pluck($definition['column']);
            foreach ($numbers as $number) {
                if (! preg_match('/^(?<prefix>[A-Z]+(?:-[A-Z]+)*)-(?<cabang>[A-Z0-9]+)-(?<period>\d{4})-(?<seq>\d{4})$/', (string) $number, $m)) {
                    continue;   // format lama → tidak dihitung
                }
                if ($m['prefix'] !== $this->prefixFor($type, $cabangIds[$m['cabang']] ?? 0, $definition['prefix'])) {
                    continue;   // prefiks milik jenis lain pada tabel yang sama (mis. invoice pajak vs non-pajak)
                }

                $cabangId = $m['cabang'] === 'PST' ? 0 : ($cabangIds[$m['cabang']] ?? null);
                if ($cabangId === null) {
                    continue;   // kode cabang tak dikenal
                }

                $key = "{$type}|{$cabangId}|{$m['period']}";
                $plan[$key] ??= ['type' => $type, 'cabang_id' => $cabangId, 'period' => $m['period'], 'max' => 0, 'current' => 0];
                $plan[$key]['max'] = max($plan[$key]['max'], (int) $m['seq']);
            }
        }

        foreach ($plan as $key => &$row) {
            $row['current'] = (int) DB::table('document_sequences')->where(['type' => $row['type'], 'cabang_id' => $row['cabang_id'], 'period' => $row['period']])->value('last_number');
        }
        unset($row);

        $changes = array_values(array_filter($plan, fn ($row) => $row['max'] > $row['current']));
        $this->line(sprintf('Kelompok berformat baru: %d · perlu dinaikkan: %d', count($plan), count($changes)));
        if ($changes !== []) {
            $this->table(['Jenis', 'Cabang', 'Periode', 'Terbesar di dokumen', 'Urutan sekarang'], array_map(fn ($r) => [$r['type'], $r['cabang_id'] ?: 'PST', $r['period'], $r['max'], $r['current']], $changes));
        }

        if (! $this->option('no-csv') && $changes !== []) {
            $this->writeCsv($changes);
        }

        if ($apply) {
            foreach ($changes as $row) {
                DB::table('document_sequences')->updateOrInsert(
                    ['type' => $row['type'], 'cabang_id' => $row['cabang_id'], 'period' => $row['period']],
                    ['last_number' => $row['max'], 'updated_at' => now(), 'created_at' => now()]
                );
            }
            $this->info('Selesai — urutan dinaikkan.');
        } elseif ($changes !== []) {
            $this->warn('Dry-run: jalankan dengan --apply untuk menulis.');
        }

        return self::SUCCESS;
    }

    private function prefixFor(string $type, int $cabangId, string $default): string
    {
        $column = match ($type) {
            'invoice_tax' => 'kode_invoice_pajak',
            'invoice_non_tax' => 'kode_invoice_non_pajak',
            default => null,
        };
        if ($column && $cabangId) {
            $configured = (string) Cabang::withoutGlobalScopes()->whereKey($cabangId)->value($column);
            $stripped = trim((string) preg_replace('/[-\s]*\d+$/', '', $configured));
            if ($stripped !== '') {
                return strtoupper($stripped);
            }
        }

        return $default;
    }

    private function writeCsv(array $rows): void
    {
        $dir = (string) ($this->option('out-dir') ?: storage_path('app/audits'));
        File::ensureDirectoryExists($dir);
        $path = rtrim($dir, '/').'/seed-urutan-dokumen-'.now()->format('Ymd-His').'.csv';
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
