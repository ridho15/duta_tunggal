<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LegacyPlanConsolidation extends Command
{
    protected $signature = 'legacy:plan-consolidation
        {--main-cabang=2 : Cabang utama hasil import inventory}
        {--staging-cabang=3 : Cabang staging hasil import inventory_cab}
        {--main-warehouse=2 : Warehouse utama hasil import inventory}
        {--staging-warehouse=3 : Warehouse staging hasil import inventory_cab}
        {--prefix=CAB- : Prefix key staging}
        {--sample=10 : Jumlah sample konflik}';

    protected $description = 'Rencana dry-run untuk konsolidasi data staging inventory_cab ke source utama inventory';

    public function handle(): int
    {
        $mainCabang = (int) $this->option('main-cabang');
        $stagingCabang = (int) $this->option('staging-cabang');
        $mainWarehouse = (int) $this->option('main-warehouse');
        $stagingWarehouse = (int) $this->option('staging-warehouse');
        $prefix = (string) $this->option('prefix');
        $sample = max(1, (int) $this->option('sample'));

        $this->info('Consolidation plan (dry-run only)');
        $this->line('No data was changed. This command only reports what a future consolidation would need to do.');

        $customers = $this->planEntity('customers', 'code', 'name', $mainCabang, $stagingCabang, $prefix, $sample);
        $suppliers = $this->planEntity('suppliers', 'code', 'perusahaan', $mainCabang, $stagingCabang, $prefix, $sample);
        $products = $this->planEntity('products', 'sku', 'name', $mainCabang, $stagingCabang, $prefix, $sample);
        $stocks = $this->planStocks($mainCabang, $stagingCabang, $mainWarehouse, $stagingWarehouse, $prefix);

        foreach ([
            'CUSTOMERS' => $customers,
            'SUPPLIERS' => $suppliers,
            'PRODUCTS' => $products,
        ] as $title => $plan) {
            $this->newLine();
            $this->info($title);
            $this->table(
                ['Action', 'Count'],
                [
                    ['main_matches_existing', $plan['matches']],
                    ['staging_only_promote_to_main', $plan['staging_only']],
                    ['different_name_review', $plan['different_name']],
                ]
            );

            if ($plan['samples']) {
                $this->line('Sample rows requiring review:');
                $this->table(['Main Code', 'Staging Code', 'Main Name', 'Staging Name'], $plan['samples']);
            }
        }

        $this->newLine();
        $this->info('STOCKS');
        $this->table(
            ['Action', 'Value'],
            [
                ['merge_to_main_product_rows', $stocks['mergeable_rows']],
                ['staging_only_stock_rows', $stocks['staging_only_rows']],
                ['mergeable_qty_available', $stocks['mergeable_qty_available']],
                ['staging_only_qty_available', $stocks['staging_only_qty_available']],
            ]
        );

        $this->newLine();
        $this->info('Suggested consolidation order');
        $this->line('1. Review customer and supplier name differences, then approve auto-merge by base code.');
        $this->line('2. Review product overlaps and decide whether base SKU should merge metadata or keep staged variants separate.');
        $this->line('3. After product decisions are frozen, move or merge staging stock from warehouse staging into warehouse main.');

        return self::SUCCESS;
    }

    private function planEntity(string $table, string $codeColumn, string $nameColumn, int $mainCabang, int $stagingCabang, string $prefix, int $sample): array
    {
        $matches = (int) DB::scalar(
            "SELECT COUNT(*) FROM {$table} s JOIN {$table} m ON m.cabang_id = ? AND s.cabang_id = ? AND m.{$codeColumn} = REGEXP_REPLACE(s.{$codeColumn}, ?, '')",
            [$mainCabang, $stagingCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$']
        );

        $stagingTotal = (int) DB::table($table)->where('cabang_id', $stagingCabang)->count();

        $differentName = (int) DB::scalar(
            "SELECT COUNT(*) FROM {$table} s JOIN {$table} m ON m.cabang_id = ? AND s.cabang_id = ? AND m.{$codeColumn} = REGEXP_REPLACE(s.{$codeColumn}, ?, '') WHERE COALESCE(m.{$nameColumn}, '') <> COALESCE(s.{$nameColumn}, '')",
            [$mainCabang, $stagingCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$']
        );

        $samples = array_map(
            fn ($row) => [$row->main_code, $row->staging_code, $row->main_name, $row->staging_name],
            DB::select(
                "SELECT m.{$codeColumn} AS main_code, s.{$codeColumn} AS staging_code, m.{$nameColumn} AS main_name, s.{$nameColumn} AS staging_name FROM {$table} s JOIN {$table} m ON m.cabang_id = ? AND s.cabang_id = ? AND m.{$codeColumn} = REGEXP_REPLACE(s.{$codeColumn}, ?, '') WHERE COALESCE(m.{$nameColumn}, '') <> COALESCE(s.{$nameColumn}, '') ORDER BY s.id LIMIT ?",
                [$mainCabang, $stagingCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$', $sample]
            )
        );

        return [
            'matches' => $matches,
            'staging_only' => $stagingTotal - $matches,
            'different_name' => $differentName,
            'samples' => $samples,
        ];
    }

    private function planStocks(int $mainCabang, int $stagingCabang, int $mainWarehouse, int $stagingWarehouse, string $prefix): array
    {
        $mergeableRows = (int) DB::scalar(
            "SELECT COUNT(*) FROM inventory_stocks s JOIN products sp ON sp.id = s.product_id AND sp.cabang_id = ? JOIN products mp ON mp.cabang_id = ? AND mp.sku = REGEXP_REPLACE(sp.sku, ?, '') WHERE s.warehouse_id = ?",
            [$stagingCabang, $mainCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$', $stagingWarehouse]
        );

        $stagingTotal = (int) DB::table('inventory_stocks')->where('warehouse_id', $stagingWarehouse)->count();

        $mergeableQty = (float) DB::scalar(
            "SELECT COALESCE(SUM(s.qty_available), 0) FROM inventory_stocks s JOIN products sp ON sp.id = s.product_id AND sp.cabang_id = ? JOIN products mp ON mp.cabang_id = ? AND mp.sku = REGEXP_REPLACE(sp.sku, ?, '') WHERE s.warehouse_id = ?",
            [$stagingCabang, $mainCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$', $stagingWarehouse]
        );

        $stagingOnlyQty = (float) DB::scalar(
            "SELECT COALESCE(SUM(s.qty_available), 0) FROM inventory_stocks s JOIN products sp ON sp.id = s.product_id AND sp.cabang_id = ? LEFT JOIN products mp ON mp.cabang_id = ? AND mp.sku = REGEXP_REPLACE(sp.sku, ?, '') WHERE s.warehouse_id = ? AND mp.id IS NULL",
            [$stagingCabang, $mainCabang, '^' . preg_quote($prefix, '/') . '|(-DUP[0-9]+-R[0-9]+)$', $stagingWarehouse]
        );

        return [
            'mergeable_rows' => $mergeableRows,
            'staging_only_rows' => $stagingTotal - $mergeableRows,
            'mergeable_qty_available' => round($mergeableQty, 2),
            'staging_only_qty_available' => round($stagingOnlyQty, 2),
        ];
    }
}