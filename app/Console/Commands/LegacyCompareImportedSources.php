<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyCompareImportedSources extends Command
{
    protected $signature = 'legacy:compare-imported-sources
        {--main-cabang=2 : Cabang utama hasil import inventory}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--main-warehouse=2 : Warehouse utama hasil import inventory}
        {--staging-warehouse=3 : Warehouse staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--sample=10 : Jumlah sample konflik}';

    protected $description = 'Bandingkan hasil import source inventory vs inventory_cab yang sudah masuk ke ERP';

    public function handle(): int
    {
        $mainCabang = (int) $this->option('main-cabang');
        $stagingCabang = (int) $this->option('staging-cabang');
        $mainWarehouse = (int) $this->option('main-warehouse');
        $stagingWarehouse = (int) $this->option('staging-warehouse');
        $prefix = (string) $this->option('prefix');
        $sample = max(1, (int) $this->option('sample'));

        $this->info('Imported source comparison');
        $this->table(
            ['Scope', 'Main', 'Staging'],
            [
                ['Cabang', $mainCabang, $stagingCabang],
                ['Warehouse', $mainWarehouse, $stagingWarehouse],
                ['Key prefix', '-', $prefix],
            ]
        );

        $entities = [
            'customers' => ['table' => 'customers', 'code' => 'code', 'name' => 'name'],
            'suppliers' => ['table' => 'suppliers', 'code' => 'code', 'name' => 'perusahaan'],
            'products' => ['table' => 'products', 'code' => 'sku', 'name' => 'name'],
        ];

        foreach ($entities as $label => $config) {
            $summary = $this->entitySummary($config['table'], $config['code'], $config['name'], $mainCabang, $stagingCabang, $prefix);

            $this->newLine();
            $this->info(strtoupper($label));
            $this->table(
                ['Metric', 'Value'],
                [
                    ['main_rows', $summary['main_rows']],
                    ['staging_rows', $summary['staging_rows']],
                    ['overlap_base_code', $summary['overlap_base_code']],
                    ['staging_only', $summary['staging_only']],
                    ['name_differences', $summary['name_differences']],
                ]
            );

            if ($summary['samples']) {
                $this->line('Sample differences:');
                $this->table(
                    ['Main Code', 'Staging Code', 'Main Name', 'Staging Name'],
                    array_slice($summary['samples'], 0, $sample)
                );
            }
        }

        $stockSummary = $this->stockSummary($mainCabang, $stagingCabang, $mainWarehouse, $stagingWarehouse, $prefix);

        $this->newLine();
        $this->info('STOCKS');
        $this->table(
            ['Metric', 'Value'],
            [
                ['main_stock_rows', $stockSummary['main_rows']],
                ['staging_stock_rows', $stockSummary['staging_rows']],
                ['mergeable_to_main_sku', $stockSummary['mergeable_rows']],
                ['staging_only_sku', $stockSummary['staging_only_rows']],
                ['mergeable_qty_available', $stockSummary['mergeable_qty_available']],
                ['staging_only_qty_available', $stockSummary['staging_only_qty_available']],
            ]
        );

        return self::SUCCESS;
    }

    private function entitySummary(string $table, string $codeColumn, string $nameColumn, int $mainCabang, int $stagingCabang, string $prefix): array
    {
        $mainRows = (int) DB::table($table)->where('cabang_id', $mainCabang)->count();
        $stagingRows = (int) DB::table($table)->where('cabang_id', $stagingCabang)->count();

        $overlap = (int) DB::scalar(
            "SELECT COUNT(*) FROM {$table} s JOIN {$table} m ON m.cabang_id = ? AND s.cabang_id = ? AND m.{$codeColumn} = REGEXP_REPLACE(s.{$codeColumn}, ?, '')",
            [$mainCabang, $stagingCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$']
        );

        $nameDifferences = (int) DB::scalar(
            "SELECT COUNT(*) FROM {$table} s JOIN {$table} m ON m.cabang_id = ? AND s.cabang_id = ? AND m.{$codeColumn} = REGEXP_REPLACE(s.{$codeColumn}, ?, '') WHERE COALESCE(m.{$nameColumn}, '') <> COALESCE(s.{$nameColumn}, '')",
            [$mainCabang, $stagingCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$']
        );

        $samples = array_map(
            fn ($row) => [$row->main_code, $row->staging_code, $row->main_name, $row->staging_name],
            DB::select(
                "SELECT m.{$codeColumn} AS main_code, s.{$codeColumn} AS staging_code, m.{$nameColumn} AS main_name, s.{$nameColumn} AS staging_name FROM {$table} s JOIN {$table} m ON m.cabang_id = ? AND s.cabang_id = ? AND m.{$codeColumn} = REGEXP_REPLACE(s.{$codeColumn}, ?, '') WHERE COALESCE(m.{$nameColumn}, '') <> COALESCE(s.{$nameColumn}, '') ORDER BY s.id LIMIT 10",
                [$mainCabang, $stagingCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$']
            )
        );

        return [
            'main_rows' => $mainRows,
            'staging_rows' => $stagingRows,
            'overlap_base_code' => $overlap,
            'staging_only' => $stagingRows - $overlap,
            'name_differences' => $nameDifferences,
            'samples' => $samples,
        ];
    }

    private function stockSummary(int $mainCabang, int $stagingCabang, int $mainWarehouse, int $stagingWarehouse, string $prefix): array
    {
        $mainRows = (int) DB::table('inventory_stocks')->where('warehouse_id', $mainWarehouse)->count();
        $stagingRows = (int) DB::table('inventory_stocks')->where('warehouse_id', $stagingWarehouse)->count();

        $mergeableRows = (int) DB::scalar(
            "SELECT COUNT(*) FROM inventory_stocks s JOIN products sp ON sp.id = s.product_id AND sp.cabang_id = ? JOIN products mp ON mp.cabang_id = ? AND mp.sku = REGEXP_REPLACE(sp.sku, ?, '') WHERE s.warehouse_id = ?",
            [$stagingCabang, $mainCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$', $stagingWarehouse]
        );

        $mergeableQty = (float) DB::scalar(
            "SELECT COALESCE(SUM(s.qty_available), 0) FROM inventory_stocks s JOIN products sp ON sp.id = s.product_id AND sp.cabang_id = ? JOIN products mp ON mp.cabang_id = ? AND mp.sku = REGEXP_REPLACE(sp.sku, ?, '') WHERE s.warehouse_id = ?",
            [$stagingCabang, $mainCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$', $stagingWarehouse]
        );

        $stagingOnlyRows = $stagingRows - $mergeableRows;
        $stagingOnlyQty = (float) DB::scalar(
            "SELECT COALESCE(SUM(s.qty_available), 0) FROM inventory_stocks s JOIN products sp ON sp.id = s.product_id AND sp.cabang_id = ? LEFT JOIN products mp ON mp.cabang_id = ? AND mp.sku = REGEXP_REPLACE(sp.sku, ?, '') WHERE s.warehouse_id = ? AND mp.id IS NULL",
            [$stagingCabang, $mainCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$', $stagingWarehouse]
        );

        return [
            'main_rows' => $mainRows,
            'staging_rows' => $stagingRows,
            'mergeable_rows' => $mergeableRows,
            'staging_only_rows' => $stagingOnlyRows,
            'mergeable_qty_available' => round($mergeableQty, 2),
            'staging_only_qty_available' => round($stagingOnlyQty, 2),
        ];
    }
}