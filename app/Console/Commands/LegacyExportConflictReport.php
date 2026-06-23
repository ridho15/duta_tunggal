<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LegacyExportConflictReport extends Command
{
    protected $signature = 'legacy:export-conflict-report
        {--main-cabang=2 : Cabang utama hasil import inventory}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--directory=legacy-review : Direktori output pada storage/app}';

    protected $description = 'Ekspor conflict report ke markdown dan CSV untuk approval manual sebelum konsolidasi';

    public function handle(): int
    {
        $mainCabang = (int) $this->option('main-cabang');
        $stagingCabang = (int) $this->option('staging-cabang');
        $prefix = (string) $this->option('prefix');
        $directory = trim((string) $this->option('directory'), '/');
        $timestamp = now()->format('Ymd_His');
        $pattern = '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$';

        $datasets = [
            'customers' => ['table' => 'customers', 'code' => 'code', 'name' => 'name'],
            'suppliers' => ['table' => 'suppliers', 'code' => 'code', 'name' => 'perusahaan'],
            'products' => ['table' => 'products', 'code' => 'sku', 'name' => 'name'],
        ];

        $summaryRows = [];
        $files = [];

        foreach ($datasets as $label => $config) {
            $conflicts = DB::select(
                "SELECT m.{$config['code']} AS main_code, s.{$config['code']} AS staging_code, m.{$config['name']} AS main_name, s.{$config['name']} AS staging_name FROM {$config['table']} s JOIN {$config['table']} m ON m.cabang_id = ? AND s.cabang_id = ? AND m.{$config['code']} = REGEXP_REPLACE(s.{$config['code']}, ?, '') WHERE COALESCE(m.{$config['name']}, '') <> COALESCE(s.{$config['name']}, '') ORDER BY s.id",
                [$mainCabang, $stagingCabang, $pattern]
            );

            $stagingOnly = DB::select(
                "SELECT s.id, s.{$config['code']} AS staging_code, REGEXP_REPLACE(s.{$config['code']}, ?, '') AS target_code, s.{$config['name']} AS staging_name FROM {$config['table']} s LEFT JOIN {$config['table']} m ON m.cabang_id = ? AND m.{$config['code']} = REGEXP_REPLACE(s.{$config['code']}, ?, '') WHERE s.cabang_id = ? AND m.id IS NULL ORDER BY s.id",
                [$pattern, $mainCabang, $pattern, $stagingCabang]
            );

            $conflictCsv = $this->csvFromRows(['main_code', 'staging_code', 'main_name', 'staging_name'], array_map(fn ($row) => [(string) $row->main_code, (string) $row->staging_code, (string) $row->main_name, (string) $row->staging_name], $conflicts));
            $stagingOnlyCsv = $this->csvFromRows(['id', 'staging_code', 'target_code', 'staging_name'], array_map(fn ($row) => [(string) $row->id, (string) $row->staging_code, (string) $row->target_code, (string) $row->staging_name], $stagingOnly));

            $conflictPath = "{$directory}/{$timestamp}_{$label}_name_conflicts.csv";
            $stagingOnlyPath = "{$directory}/{$timestamp}_{$label}_staging_only.csv";

            Storage::disk('local')->put($conflictPath, $conflictCsv);
            Storage::disk('local')->put($stagingOnlyPath, $stagingOnlyCsv);

            $files[] = $conflictPath;
            $files[] = $stagingOnlyPath;
            $summaryRows[] = [
                'entity' => $label,
                'name_conflicts' => count($conflicts),
                'staging_only' => count($stagingOnly),
            ];
        }

        $blockedDuplicateSummary = DB::select(
            "SELECT reason, COUNT(*) AS groups_count, SUM(total_rows) AS rows_count FROM (SELECT REGEXP_REPLACE(p.sku, '^" . preg_quote($prefix, '/') . "|(-DUP[0-9]+-R[0-9]+)$', '') AS target_sku, COUNT(*) AS total_rows, CONCAT_WS(',', IF(COUNT(DISTINCT UPPER(REGEXP_REPLACE(TRIM(p.name), '[^A-Za-z0-9]+', ''))) > 1, 'name', NULL), IF(COUNT(DISTINCT p.product_category_id) > 1, 'category', NULL), IF(COUNT(DISTINCT p.uom_id) > 1, 'uom', NULL), IF(COUNT(DISTINCT p.cost_price) > 1, 'cost', NULL), IF(COUNT(DISTINCT p.sell_price) > 1, 'sell', NULL), IF(COUNT(DISTINCT p.item_value) > 1, 'item_value', NULL), IF(COUNT(DISTINCT p.tipe_pajak) > 1, 'tax_type', NULL), IF(COUNT(DISTINCT p.pajak) > 1, 'tax', NULL), IF(COUNT(DISTINCT p.jumlah_kelipatan_gudang_besar) > 1, 'bulk_capacity', NULL), IF(COUNT(DISTINCT p.jumlah_jual_kategori_banyak) > 1, 'bulk_sell_qty', NULL), IF(COUNT(DISTINCT p.kode_merk) > 1, 'brand', NULL), IF(COUNT(DISTINCT p.biaya) > 1, 'biaya', NULL), IF(COUNT(DISTINCT COALESCE(s.qty_min, 0)) > 1, 'qty_min', NULL)) AS reason FROM products p LEFT JOIN products m ON m.cabang_id = ? AND m.sku = REGEXP_REPLACE(p.sku, '^" . preg_quote($prefix, '/') . "|(-DUP[0-9]+-R[0-9]+)$', '') LEFT JOIN inventory_stocks s ON s.product_id = p.id AND s.warehouse_id = ? AND s.deleted_at IS NULL WHERE p.cabang_id = ? AND p.deleted_at IS NULL AND m.id IS NULL GROUP BY target_sku HAVING COUNT(*) > 1) t GROUP BY reason ORDER BY groups_count DESC, reason",
            [$mainCabang, $stagingCabang, $stagingCabang]
        );

        $blockedDuplicateDetails = DB::select(
            "SELECT agg.target_sku, p.sku AS current_sku, p.name AS product_name, agg.difference_reason, p.cost_price, p.sell_price, p.biaya, COALESCE(s.qty_min, 0) AS qty_min, p.product_category_id, p.uom_id FROM (SELECT REGEXP_REPLACE(p.sku, '^" . preg_quote($prefix, '/') . "|(-DUP[0-9]+-R[0-9]+)$', '') AS target_sku, CONCAT_WS(',', IF(COUNT(DISTINCT UPPER(REGEXP_REPLACE(TRIM(p.name), '[^A-Za-z0-9]+', ''))) > 1, 'name', NULL), IF(COUNT(DISTINCT p.product_category_id) > 1, 'category', NULL), IF(COUNT(DISTINCT p.uom_id) > 1, 'uom', NULL), IF(COUNT(DISTINCT p.cost_price) > 1, 'cost', NULL), IF(COUNT(DISTINCT p.sell_price) > 1, 'sell', NULL), IF(COUNT(DISTINCT p.item_value) > 1, 'item_value', NULL), IF(COUNT(DISTINCT p.tipe_pajak) > 1, 'tax_type', NULL), IF(COUNT(DISTINCT p.pajak) > 1, 'tax', NULL), IF(COUNT(DISTINCT p.jumlah_kelipatan_gudang_besar) > 1, 'bulk_capacity', NULL), IF(COUNT(DISTINCT p.jumlah_jual_kategori_banyak) > 1, 'bulk_sell_qty', NULL), IF(COUNT(DISTINCT p.kode_merk) > 1, 'brand', NULL), IF(COUNT(DISTINCT p.biaya) > 1, 'biaya', NULL), IF(COUNT(DISTINCT COALESCE(s.qty_min, 0)) > 1, 'qty_min', NULL)) AS difference_reason FROM products p LEFT JOIN products m ON m.cabang_id = ? AND m.sku = REGEXP_REPLACE(p.sku, '^" . preg_quote($prefix, '/') . "|(-DUP[0-9]+-R[0-9]+)$', '') LEFT JOIN inventory_stocks s ON s.product_id = p.id AND s.warehouse_id = ? AND s.deleted_at IS NULL WHERE p.cabang_id = ? AND p.deleted_at IS NULL AND m.id IS NULL GROUP BY REGEXP_REPLACE(p.sku, '^" . preg_quote($prefix, '/') . "|(-DUP[0-9]+-R[0-9]+)$', '') HAVING COUNT(*) > 1) agg JOIN products p ON p.cabang_id = ? AND p.deleted_at IS NULL AND REGEXP_REPLACE(p.sku, '^" . preg_quote($prefix, '/') . "|(-DUP[0-9]+-R[0-9]+)$', '') = agg.target_sku LEFT JOIN inventory_stocks s ON s.product_id = p.id AND s.warehouse_id = ? AND s.deleted_at IS NULL ORDER BY agg.difference_reason, agg.target_sku, p.sku",
            [$mainCabang, $stagingCabang, $stagingCabang, $stagingCabang, $stagingCabang]
        );

        $blockedSummaryPath = "{$directory}/{$timestamp}_products_blocked_duplicate_summary.csv";
        $blockedDetailsPath = "{$directory}/{$timestamp}_products_blocked_duplicate_details.csv";

        Storage::disk('local')->put(
            $blockedSummaryPath,
            $this->csvFromRows(
                ['reason', 'groups_count', 'rows_count'],
                array_map(fn ($row) => [(string) $row->reason, (string) $row->groups_count, (string) $row->rows_count], $blockedDuplicateSummary)
            )
        );

        Storage::disk('local')->put(
            $blockedDetailsPath,
            $this->csvFromRows(
                ['target_sku', 'current_sku', 'product_name', 'difference_reason', 'cost_price', 'sell_price', 'biaya', 'qty_min', 'product_category_id', 'uom_id'],
                array_map(fn ($row) => [(string) $row->target_sku, (string) $row->current_sku, (string) $row->product_name, (string) $row->difference_reason, (string) $row->cost_price, (string) $row->sell_price, (string) $row->biaya, (string) $row->qty_min, (string) $row->product_category_id, (string) $row->uom_id], $blockedDuplicateDetails)
            )
        );

        $files[] = $blockedSummaryPath;
        $files[] = $blockedDetailsPath;

        $markdown = $this->markdownSummary($mainCabang, $stagingCabang, $prefix, $summaryRows, $files, $blockedDuplicateSummary);
        $markdownPath = "{$directory}/{$timestamp}_summary.md";
        Storage::disk('local')->put($markdownPath, $markdown);
        $files[] = $markdownPath;

        $this->info('Conflict report exported.');
        $this->table(['Entity', 'Name Conflicts', 'Staging Only'], array_map(fn ($row) => [$row['entity'], $row['name_conflicts'], $row['staging_only']], $summaryRows));
        $this->newLine();
        foreach ($files as $file) {
            $this->line('storage/app/' . $file);
        }

        return self::SUCCESS;
    }

    private function csvFromRows(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    private function markdownSummary(int $mainCabang, int $stagingCabang, string $prefix, array $summaryRows, array $files, array $blockedDuplicateSummary): string
    {
        $lines = [
            '# Legacy Conflict Report',
            '',
            '- Main cabang: ' . $mainCabang,
            '- Staging cabang: ' . $stagingCabang,
            '- Staging prefix: ' . $prefix,
            '',
            '| Entity | Name Conflicts | Staging Only |',
            '| --- | ---: | ---: |',
        ];

        foreach ($summaryRows as $row) {
            $lines[] = sprintf('| %s | %d | %d |', $row['entity'], $row['name_conflicts'], $row['staging_only']);
        }

        if ($blockedDuplicateSummary) {
            $lines[] = '';
            $lines[] = '## Remaining Product Duplicate Buckets';
            $lines[] = '';
            $lines[] = '| Reason | Groups | Rows |';
            $lines[] = '| --- | ---: | ---: |';
            foreach ($blockedDuplicateSummary as $row) {
                $lines[] = sprintf('| %s | %d | %d |', $row->reason, $row->groups_count, $row->rows_count);
            }
        }

        $lines[] = '';
        $lines[] = '## Files';
        $lines[] = '';
        foreach ($files as $file) {
            $lines[] = '- storage/app/' . $file;
        }

        return implode("\n", $lines) . "\n";
    }
}