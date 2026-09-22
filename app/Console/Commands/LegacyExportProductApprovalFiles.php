<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class LegacyExportProductApprovalFiles extends Command
{
    protected $signature = 'legacy:export-product-approval-files
        {--main-cabang=2 : Cabang utama hasil import inventory}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--staging-warehouse=3 : Warehouse staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--output-dir=docs : Direktori output approval files}
        {--reason= : Filter exact difference reason tertentu}
        {--date= : Override date stamp file output (Ymd)}';

    protected $description = 'Export approval CSV files untuk remaining staging-only product duplicate buckets, dipecah per reason';

    public function handle(): int
    {
        $mainCabang = (int) $this->option('main-cabang');
        $stagingCabang = (int) $this->option('staging-cabang');
        $stagingWarehouse = (int) $this->option('staging-warehouse');
        $prefix = (string) $this->option('prefix');
        $outputDir = $this->resolveOutputDir((string) $this->option('output-dir'));
        $reasonFilter = $this->filledString($this->option('reason'));
        $date = $this->filledString($this->option('date')) ?: now()->format('Ymd');

        File::ensureDirectoryExists($outputDir);

        $groups = $this->loadBlockedDuplicateGroups($mainCabang, $stagingCabang, $stagingWarehouse, $prefix);

        if ($reasonFilter) {
            $groups = $groups->filter(fn (array $group) => $group['difference_reason'] === $reasonFilter)->values();
        }

        if ($groups->isEmpty()) {
            $this->warn('Tidak ada blocked duplicate bucket yang cocok untuk diekspor.');
            return self::SUCCESS;
        }

        $byReason = $groups->groupBy('difference_reason')->sortKeys();
        $files = [];
        $summary = [];

        foreach ($byReason as $reason => $reasonGroups) {
            $slug = $this->slugReason((string) $reason);
            $approvalFile = $outputDir . DIRECTORY_SEPARATOR . "legacy-product-approval-{$slug}-{$date}.csv";
            $detailFile = $outputDir . DIRECTORY_SEPARATOR . "legacy-product-approval-{$slug}-details-{$date}.csv";

            File::put($approvalFile, $this->buildApprovalCsv($reasonGroups));
            File::put($detailFile, $this->buildDetailCsv($reasonGroups));

            $files[] = $approvalFile;
            $files[] = $detailFile;
            $summary[] = [
                'reason' => $reason,
                'groups' => $reasonGroups->count(),
                'rows' => $reasonGroups->sum('row_count'),
                'approval_file' => $approvalFile,
                'detail_file' => $detailFile,
            ];
        }

        $summaryMarkdown = $this->buildSummaryMarkdown($mainCabang, $stagingCabang, $stagingWarehouse, $prefix, $summary);
        $summaryPath = $outputDir . DIRECTORY_SEPARATOR . "legacy-product-approval-summary-{$date}.md";
        File::put($summaryPath, $summaryMarkdown);

        $this->info('Product approval files exported.');
        $this->table(
            ['Reason', 'Groups', 'Rows', 'Approval File'],
            array_map(fn (array $row) => [$row['reason'], $row['groups'], $row['rows'], $row['approval_file']], $summary)
        );
        $this->line($summaryPath);

        return self::SUCCESS;
    }

    private function resolveOutputDir(string $outputDir): string
    {
        if ($outputDir === '') {
            return base_path('docs');
        }

        return str_starts_with($outputDir, DIRECTORY_SEPARATOR)
            ? $outputDir
            : base_path($outputDir);
    }

    private function loadBlockedDuplicateGroups(int $mainCabang, int $stagingCabang, int $stagingWarehouse, string $prefix): Collection
    {
        $pattern = '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$';

        $rows = collect(DB::select(
            "SELECT
                p.id,
                p.sku AS current_sku,
                REGEXP_REPLACE(p.sku, ?, '') AS target_sku,
                p.name AS product_name,
                p.product_category_id,
                p.uom_id,
                p.cost_price,
                p.sell_price,
                p.item_value,
                p.tipe_pajak,
                p.pajak,
                p.jumlah_kelipatan_gudang_besar,
                p.jumlah_jual_kategori_banyak,
                p.kode_merk,
                p.biaya,
                COALESCE(s.qty_available, 0) AS qty_available,
                COALESCE(s.qty_reserved, 0) AS qty_reserved,
                COALESCE(s.qty_min, 0) AS qty_min
            FROM products p
            LEFT JOIN products m
                ON m.cabang_id = ?
                AND m.sku = REGEXP_REPLACE(p.sku, ?, '')
            LEFT JOIN inventory_stocks s
                ON s.product_id = p.id
                AND s.warehouse_id = ?
                AND s.deleted_at IS NULL
                AND s.rak_id IS NULL
            WHERE p.cabang_id = ?
                AND p.deleted_at IS NULL
                AND m.id IS NULL
            ORDER BY target_sku, p.id",
            [$pattern, $mainCabang, $pattern, $stagingWarehouse, $stagingCabang]
        ));

        return $rows
            ->groupBy(fn ($row) => (string) $row->target_sku)
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(function (Collection $group) use ($prefix) {
                $differenceFields = $this->productDuplicateDifferenceFields($group);
                $canonical = $this->selectCanonicalProductRow($group, (string) $group->first()->target_sku, $prefix);

                return [
                    'target_sku' => (string) $group->first()->target_sku,
                    'difference_reason' => implode(',', $differenceFields->all()),
                    'difference_fields' => $differenceFields->all(),
                    'row_count' => $group->count(),
                    'total_qty_available' => round((float) $group->sum('qty_available'), 2),
                    'total_qty_reserved' => round((float) $group->sum('qty_reserved'), 2),
                    'recommended_canonical_sku' => (string) $canonical->current_sku,
                    'recommended_category_id' => (string) $canonical->product_category_id,
                    'recommended_biaya' => $this->decimalString($canonical->biaya),
                    'rows' => $group->map(fn ($row) => [
                        'current_sku' => (string) $row->current_sku,
                        'product_name' => (string) $row->product_name,
                        'product_category_id' => (string) $row->product_category_id,
                        'uom_id' => (string) $row->uom_id,
                        'cost_price' => $this->decimalString($row->cost_price),
                        'sell_price' => $this->decimalString($row->sell_price),
                        'item_value' => $this->decimalString($row->item_value),
                        'tipe_pajak' => (string) $row->tipe_pajak,
                        'pajak' => $this->decimalString($row->pajak),
                        'jumlah_kelipatan_gudang_besar' => (string) $row->jumlah_kelipatan_gudang_besar,
                        'jumlah_jual_kategori_banyak' => (string) $row->jumlah_jual_kategori_banyak,
                        'kode_merk' => (string) $row->kode_merk,
                        'biaya' => $this->decimalString($row->biaya),
                        'qty_available' => $this->decimalString($row->qty_available),
                        'qty_reserved' => $this->decimalString($row->qty_reserved),
                        'qty_min' => $this->decimalString($row->qty_min),
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('row_count')
            ->values();
    }

    private function buildApprovalCsv(Collection $reasonGroups): string
    {
        $headers = [
            'target_sku',
            'difference_reason',
            'row_count',
            'candidate_skus',
            'candidate_names',
            'candidate_category_ids',
            'candidate_biaya_values',
            'total_qty_available',
            'total_qty_reserved',
            'recommended_canonical_sku',
            'recommended_category_id',
            'recommended_biaya',
            'approval_status',
            'approved_canonical_sku',
            'approved_category_id',
            'approved_biaya',
            'notes',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($reasonGroups as $group) {
            fputcsv($handle, [
                $group['target_sku'],
                $group['difference_reason'],
                $group['row_count'],
                implode(' || ', array_map(fn (array $row) => $row['current_sku'], $group['rows'])),
                implode(' || ', array_map(fn (array $row) => $row['product_name'], $group['rows'])),
                implode(' || ', array_map(fn (array $row) => $row['product_category_id'], $group['rows'])),
                implode(' || ', array_map(fn (array $row) => $row['biaya'], $group['rows'])),
                $this->decimalString($group['total_qty_available']),
                $this->decimalString($group['total_qty_reserved']),
                $group['recommended_canonical_sku'],
                $group['recommended_category_id'],
                $group['recommended_biaya'],
                '',
                '',
                '',
                '',
                '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    private function buildDetailCsv(Collection $reasonGroups): string
    {
        $headers = [
            'target_sku',
            'difference_reason',
            'current_sku',
            'product_name',
            'product_category_id',
            'uom_id',
            'cost_price',
            'sell_price',
            'item_value',
            'tipe_pajak',
            'pajak',
            'jumlah_kelipatan_gudang_besar',
            'jumlah_jual_kategori_banyak',
            'kode_merk',
            'biaya',
            'qty_available',
            'qty_reserved',
            'qty_min',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($reasonGroups as $group) {
            foreach ($group['rows'] as $row) {
                fputcsv($handle, [
                    $group['target_sku'],
                    $group['difference_reason'],
                    $row['current_sku'],
                    $row['product_name'],
                    $row['product_category_id'],
                    $row['uom_id'],
                    $row['cost_price'],
                    $row['sell_price'],
                    $row['item_value'],
                    $row['tipe_pajak'],
                    $row['pajak'],
                    $row['jumlah_kelipatan_gudang_besar'],
                    $row['jumlah_jual_kategori_banyak'],
                    $row['kode_merk'],
                    $row['biaya'],
                    $row['qty_available'],
                    $row['qty_reserved'],
                    $row['qty_min'],
                ]);
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    private function buildSummaryMarkdown(int $mainCabang, int $stagingCabang, int $stagingWarehouse, string $prefix, array $summary): string
    {
        $lines = [
            '# Legacy Product Approval Summary',
            '',
            '- Main cabang: ' . $mainCabang,
            '- Staging cabang: ' . $stagingCabang,
            '- Staging warehouse: ' . $stagingWarehouse,
            '- Prefix: ' . $prefix,
            '',
            '| Reason | Groups | Rows | Approval File |',
            '| --- | ---: | ---: | --- |',
        ];

        foreach ($summary as $row) {
            $lines[] = sprintf('| %s | %d | %d | %s |', $row['reason'], $row['groups'], $row['rows'], basename($row['approval_file']));
        }

        $lines[] = '';
        $lines[] = '## Approval Workflow';
        $lines[] = '';
        $lines[] = '1. Review file approval per reason.';
        $lines[] = '2. Isi `approval_status` dengan `APPROVE` untuk grup yang disetujui.';
        $lines[] = '3. Jika menerima rekomendasi, cukup set `approval_status=APPROVE` dan biarkan kolom approved kosong.';
        $lines[] = '4. Jika override diperlukan, isi `approved_canonical_sku`, `approved_category_id`, dan/atau `approved_biaya`.';
        $lines[] = '5. Jalankan command konsolidasi approval khusus reason yang sesuai.';

        return implode("\n", $lines) . "\n";
    }

    private function productDuplicateDifferenceFields(Collection $rows): Collection
    {
        $comparisons = [
            'name' => $rows->map(fn ($row) => $this->normalizeProductName($row->product_name))->unique()->count(),
            'category' => $rows->pluck('product_category_id')->unique()->count(),
            'uom' => $rows->pluck('uom_id')->unique()->count(),
            'cost' => $rows->map(fn ($row) => $this->decimalFingerprint($row->cost_price))->unique()->count(),
            'sell' => $rows->map(fn ($row) => $this->decimalFingerprint($row->sell_price))->unique()->count(),
            'item_value' => $rows->map(fn ($row) => $this->decimalFingerprint($row->item_value))->unique()->count(),
            'tax_type' => $rows->map(fn ($row) => strtoupper(trim((string) $row->tipe_pajak)))->unique()->count(),
            'tax' => $rows->map(fn ($row) => $this->decimalFingerprint($row->pajak))->unique()->count(),
            'bulk_capacity' => $rows->pluck('jumlah_kelipatan_gudang_besar')->unique()->count(),
            'bulk_sell_qty' => $rows->pluck('jumlah_jual_kategori_banyak')->unique()->count(),
            'brand' => $rows->map(fn ($row) => strtoupper(trim((string) $row->kode_merk)))->unique()->count(),
            'biaya' => $rows->map(fn ($row) => $this->decimalFingerprint($row->biaya))->unique()->count(),
            'qty_min' => $rows->map(fn ($row) => $this->decimalFingerprint($row->qty_min))->unique()->count(),
        ];

        return collect($comparisons)
            ->filter(fn ($count) => $count > 1)
            ->keys()
            ->values();
    }

    private function selectCanonicalProductRow(Collection $rows, string $targetCode, string $prefix): object
    {
        $preferredCode = $prefix . $targetCode;

        return $rows
            ->sortBy(fn ($row) => [
                (string) $row->current_sku === $preferredCode ? 0 : 1,
                (int) $row->id,
            ])
            ->first();
    }

    private function normalizeProductName(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        $normalized = preg_replace('/[^A-Z0-9]+/', '', $value);

        return $normalized ?: '';
    }

    private function decimalFingerprint(mixed $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function slugReason(string $reason): string
    {
        $reason = strtolower(trim($reason));
        $reason = preg_replace('/[^a-z0-9]+/', '-', $reason);

        return trim((string) $reason, '-') ?: 'unknown';
    }

    private function filledString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}