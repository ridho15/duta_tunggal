<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class LegacyApplyCategoryApproval extends Command
{
    protected $signature = 'legacy:apply-category-approval
        {file : Path CSV hasil legacy:export-category-approval}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--execute : Jalankan update category ke database staging}';

    protected $description = 'Apply keputusan approval kategori duplicate products staging dari file CSV';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->argument('file'));
        $stagingCabang = (int) $this->option('staging-cabang');
        $prefix = (string) $this->option('prefix');
        $execute = (bool) $this->option('execute');

        if (! is_file($path)) {
            $this->error("File approval tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        $rows = $this->readCsv($path);
        $approvedRows = collect($rows)
            ->filter(function (array $row) {
                $status = strtolower(trim((string) ($row['decision_status'] ?? '')));
                $approvedCategoryId = trim((string) ($row['approved_category_id'] ?? ''));

                return in_array($status, ['approved', 'approve', 'apply'], true) && $approvedCategoryId !== '';
            })
            ->values();

        $this->info('Category approval apply summary');
        $this->table(
            ['Metric', 'Value'],
            [
                ['file_rows', count($rows)],
                ['approved_rows', $approvedRows->count()],
                ['mode', $execute ? 'execute' : 'dry-run'],
            ]
        );

        if ($approvedRows->isEmpty()) {
            $this->warn('Tidak ada row dengan decision_status=approved/apply dan approved_category_id terisi.');
            return self::SUCCESS;
        }

        $pattern = '/^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$/';
        $updates = $approvedRows->map(function (array $row) use ($pattern) {
            return [
                'target_sku' => preg_replace($pattern, '', (string) ($row['target_sku'] ?? '')),
                'approved_category_id' => (int) $row['approved_category_id'],
            ];
        });

        $this->table(
            ['Target SKU', 'Approved Category ID'],
            $updates->take(15)->map(fn (array $row) => [$row['target_sku'], $row['approved_category_id']])->all()
        );

        if (! $execute) {
            $this->warn('Dry-run only. Tambahkan --execute untuk menulis category approval ke database staging.');
            return self::SUCCESS;
        }

        try {
            DB::beginTransaction();
            $now = now();

            foreach ($updates as $update) {
                DB::table('products')
                    ->where('cabang_id', $stagingCabang)
                    ->whereNull('deleted_at')
                    ->whereRaw("REGEXP_REPLACE(sku, '^" . preg_quote($prefix, '/') . "|(-DUP[0-9]+-R[0-9]+)$', '') = ?", [$update['target_sku']])
                    ->update([
                        'product_category_id' => $update['approved_category_id'],
                        'updated_at' => $now,
                    ]);
            }

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            $this->error('Apply category approval gagal: ' . $throwable->getMessage());
            return self::FAILURE;
        }

        $this->info('Category approval berhasil diterapkan ke staging products.');
        return self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        return str_starts_with($file, DIRECTORY_SEPARATOR)
            ? $file
            : base_path($file);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headers);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if (! array_filter($values, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = $values[$index] ?? null;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}