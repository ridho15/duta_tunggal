<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$now = now();

$fixture = [
    'product_a_sku' => 'FG-PW-COGM-A',
    'product_a_name' => 'Fixture COGM Product A',
    'product_b_sku' => 'FG-PW-COGM-B',
    'product_b_name' => 'Fixture COGM Product B',
    'plan_a' => 'PP-PW-COGM-A',
    'plan_b' => 'PP-PW-COGM-B',
    'mo_a' => 'MO-PW-COGM-A',
    'mo_b' => 'MO-PW-COGM-B',
];

DB::transaction(function () use ($fixture, $now) {
    $filterColumns = static function (string $table, array $payload): array {
        static $columnsByTable = [];

        $columns = $columnsByTable[$table] ??= Schema::getColumnListing($table);

        return array_intersect_key($payload, array_flip($columns));
    };

    $user = DB::table('users')->where('email', 'ralamzah@gmail.com')->first()
        ?? DB::table('users')->orderBy('id')->first();

    if (! $user) {
        throw new RuntimeException('No user found for COGM Playwright fixture.');
    }

    $cabangId = $user->cabang_id ?: DB::table('cabangs')->value('id');
    if (! $cabangId) {
        throw new RuntimeException('No cabang found for COGM Playwright fixture.');
    }

    $uomId = DB::table('unit_of_measures')->value('id');
    if (! $uomId) {
        $uomId = DB::table('unit_of_measures')->insertGetId([
            'name' => 'Piece',
            'abbreviation' => 'pcs',
        ]);
    }

    $warehouseId = DB::table('warehouses')->where('kode', 'GDG-PW-COGM-001')->value('id');
    if (! $warehouseId) {
        $warehouseId = DB::table('warehouses')->insertGetId([
            'kode' => 'GDG-PW-COGM-001',
            'name' => 'Gudang Fixture COGM',
            'location' => 'Fixture COGM',
            'cabang_id' => $cabangId,
            'tipe' => 'Besar',
            'telepon' => '081111111111',
            'status' => 1,
            'warna_background' => '#dcfce7',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $categoryId = DB::table('product_categories')->where('kode', 'CAT-PW-COGM')->value('id');
    if (! $categoryId) {
        $categoryId = DB::table('product_categories')->insertGetId([
            'kode' => 'CAT-PW-COGM',
            'name' => 'Kategori Fixture COGM',
            'kenaikan_harga' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $wipCoaId = DB::table('chart_of_accounts')->where('code', '1150.001')->value('id')
        ?? DB::table('chart_of_accounts')->insertGetId([
            'code' => '1150.001',
            'name' => 'Persediaan Barang Dalam Proses',
            'type' => 'Asset',
            'is_active' => 1,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    $laborCoaId = DB::table('chart_of_accounts')->where('code', '5230.001')->value('id')
        ?? DB::table('chart_of_accounts')->insertGetId([
            'code' => '5230.001',
            'name' => 'Biaya Tenaga Kerja Proses Produksi',
            'type' => 'Expense',
            'is_active' => 1,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    $overheadCoaId = DB::table('chart_of_accounts')->where('code', '6100.001')->value('id')
        ?? DB::table('chart_of_accounts')->insertGetId([
            'code' => '6100.001',
            'name' => 'Biaya Overhead Produksi',
            'type' => 'Expense',
            'is_active' => 1,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    if (Schema::hasTable('hpp_prefixes')) {
        foreach ([
            'raw_material_inventory' => ['1140.001'],
            'raw_material_purchase' => ['5110'],
            'direct_labor' => ['5120'],
            'wip_inventory' => ['1150.001'],
        ] as $category => $prefixes) {
            DB::table('hpp_prefixes')->where('category', $category)->delete();
            foreach ($prefixes as $index => $prefix) {
                DB::table('hpp_prefixes')->insert([
                    'category' => $category,
                    'prefix' => $prefix,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    if (Schema::hasTable('hpp_overhead_items') && Schema::hasTable('hpp_overhead_item_prefixes')) {
        $overheadItemId = DB::table('hpp_overhead_items')->where('key', 'factory_overhead')->value('id');
        if (! $overheadItemId) {
            $overheadItemId = DB::table('hpp_overhead_items')->insertGetId([
                'key' => 'factory_overhead',
                'label' => 'Factory Overhead',
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('hpp_overhead_item_prefixes')->where('hpp_overhead_item_id', $overheadItemId)->delete();
        DB::table('hpp_overhead_item_prefixes')->insert([
            'hpp_overhead_item_id' => $overheadItemId,
            'prefix' => '6100',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $productAId = DB::table('products')->where('sku', $fixture['product_a_sku'])->value('id');
    if ($productAId) {
        DB::table('products')->where('id', $productAId)->update([
            'name' => $fixture['product_a_name'],
            'product_category_id' => $categoryId,
            'cabang_id' => $cabangId,
            'uom_id' => $uomId,
            'is_active' => 1,
            'updated_at' => $now,
        ]);
    } else {
        $productAId = DB::table('products')->insertGetId([
            'sku' => $fixture['product_a_sku'],
            'name' => $fixture['product_a_name'],
            'product_category_id' => $categoryId,
            'cabang_id' => $cabangId,
            'cost_price' => 100,
            'sell_price' => 150,
            'uom_id' => $uomId,
            'tipe_pajak' => 'Non Pajak',
            'pajak' => 0,
            'jumlah_kelipatan_gudang_besar' => 1,
            'jumlah_jual_kategori_banyak' => 1,
            'kode_merk' => 'PW-COGM-A',
            'is_manufacture' => 1,
            'is_raw_material' => 0,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $productBId = DB::table('products')->where('sku', $fixture['product_b_sku'])->value('id');
    if ($productBId) {
        DB::table('products')->where('id', $productBId)->update([
            'name' => $fixture['product_b_name'],
            'product_category_id' => $categoryId,
            'cabang_id' => $cabangId,
            'uom_id' => $uomId,
            'is_active' => 1,
            'updated_at' => $now,
        ]);
    } else {
        $productBId = DB::table('products')->insertGetId([
            'sku' => $fixture['product_b_sku'],
            'name' => $fixture['product_b_name'],
            'product_category_id' => $categoryId,
            'cabang_id' => $cabangId,
            'cost_price' => 120,
            'sell_price' => 170,
            'uom_id' => $uomId,
            'tipe_pajak' => 'Non Pajak',
            'pajak' => 0,
            'jumlah_kelipatan_gudang_besar' => 1,
            'jumlah_jual_kategori_banyak' => 1,
            'kode_merk' => 'PW-COGM-B',
            'is_manufacture' => 1,
            'is_raw_material' => 0,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $bomAId = DB::table('bill_of_materials')->where('code', 'BOM-PW-COGM-A')->value('id')
        ?? DB::table('bill_of_materials')->insertGetId([
            'code' => 'BOM-PW-COGM-A',
            'cabang_id' => $cabangId,
            'product_id' => $productAId,
            'quantity' => 1,
            'nama_bom' => 'Fixture BOM COGM A',
            'is_active' => 1,
            'uom_id' => $uomId,
            'labor_cost' => 20,
            'overhead_cost' => 10,
            'total_cost' => 30,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    $bomBId = DB::table('bill_of_materials')->where('code', 'BOM-PW-COGM-B')->value('id')
        ?? DB::table('bill_of_materials')->insertGetId([
            'code' => 'BOM-PW-COGM-B',
            'cabang_id' => $cabangId,
            'product_id' => $productBId,
            'quantity' => 1,
            'nama_bom' => 'Fixture BOM COGM B',
            'is_active' => 1,
            'uom_id' => $uomId,
            'labor_cost' => 25,
            'overhead_cost' => 15,
            'total_cost' => 40,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    foreach ([$fixture['plan_a'], $fixture['plan_b']] as $planNumber) {
        $planId = DB::table('production_plans')->where('plan_number', $planNumber)->value('id');
        if (! $planId) {
            continue;
        }

        $moIds = DB::table('manufacturing_orders')->where('production_plan_id', $planId)->pluck('id');
        $productionIds = DB::table('productions')->whereIn('manufacturing_order_id', $moIds)->pluck('id');
        $qcIds = DB::table('quality_controls')
            ->where('from_model_type', 'App\\Models\\Production')
            ->whereIn('from_model_id', $productionIds)
            ->pluck('id');

        DB::table('journal_entries')->where('source_type', 'App\\Models\\QualityControl')->whereIn('source_id', $qcIds)->delete();
        DB::table('journal_entries')->where('source_type', 'App\\Models\\Production')->whereIn('source_id', $productionIds)->delete();
        DB::table('quality_controls')->whereIn('id', $qcIds)->delete();
        DB::table('productions')->whereIn('id', $productionIds)->delete();
        DB::table('material_issues')->where('production_plan_id', $planId)->delete();
        DB::table('manufacturing_orders')->whereIn('id', $moIds)->delete();
        DB::table('production_plans')->where('id', $planId)->delete();
    }

    $planAId = DB::table('production_plans')->insertGetId($filterColumns('production_plans', [
        'plan_number' => $fixture['plan_a'],
        'name' => 'Fixture COGM Plan A',
        'source_type' => 'manual',
        'bill_of_material_id' => $bomAId,
        'product_id' => $productAId,
        'quantity' => 10,
        'uom_id' => $uomId,
        'warehouse_id' => $warehouseId,
        'cabang_id' => $cabangId,
        'start_date' => '2025-01-05 08:00:00',
        'end_date' => '2025-01-31 08:00:00',
        'status' => 'in_progress',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]));

    $planBId = DB::table('production_plans')->insertGetId($filterColumns('production_plans', [
        'plan_number' => $fixture['plan_b'],
        'name' => 'Fixture COGM Plan B',
        'source_type' => 'manual',
        'bill_of_material_id' => $bomBId,
        'product_id' => $productBId,
        'quantity' => 8,
        'uom_id' => $uomId,
        'warehouse_id' => $warehouseId,
        'cabang_id' => $cabangId,
        'start_date' => '2025-01-06 08:00:00',
        'end_date' => '2025-01-31 08:00:00',
        'status' => 'in_progress',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]));

    $moAId = DB::table('manufacturing_orders')->insertGetId($filterColumns('manufacturing_orders', [
        'mo_number' => $fixture['mo_a'],
        'production_plan_id' => $planAId,
        'quantity' => 10,
        'status' => 'completed',
        'start_date' => '2025-01-05 08:00:00',
        'end_date' => '2025-01-20 08:00:00',
        'cabang_id' => $cabangId,
        'created_at' => '2025-01-05 08:00:00',
        'updated_at' => '2025-01-20 08:00:00',
    ]));

    $moBId = DB::table('manufacturing_orders')->insertGetId($filterColumns('manufacturing_orders', [
        'mo_number' => $fixture['mo_b'],
        'production_plan_id' => $planBId,
        'quantity' => 8,
        'status' => 'completed',
        'start_date' => '2025-01-06 08:00:00',
        'end_date' => '2025-01-21 08:00:00',
        'cabang_id' => $cabangId,
        'created_at' => '2025-01-06 08:00:00',
        'updated_at' => '2025-01-21 08:00:00',
    ]));

    $productionAId = DB::table('productions')->insertGetId($filterColumns('productions', [
        'production_number' => 'PROD-PW-COGM-A',
        'manufacturing_order_id' => $moAId,
        'quantity_produced' => 10,
        'production_date' => '2025-01-10',
        'status' => 'finished',
        'created_at' => '2025-01-10 08:00:00',
        'updated_at' => '2025-01-10 08:00:00',
    ]));

    $productionBId = DB::table('productions')->insertGetId($filterColumns('productions', [
        'production_number' => 'PROD-PW-COGM-B',
        'manufacturing_order_id' => $moBId,
        'quantity_produced' => 8,
        'production_date' => '2025-01-12',
        'status' => 'finished',
        'created_at' => '2025-01-12 08:00:00',
        'updated_at' => '2025-01-12 08:00:00',
    ]));

    $qcAId = DB::table('quality_controls')->insertGetId($filterColumns('quality_controls', [
        'qc_number' => 'QC-PW-COGM-A',
        'inspected_by' => $user->id,
        'passed_quantity' => 4,
        'rejected_quantity' => 0,
        'quantity_received' => 4,
        'status' => 1,
        'warehouse_id' => $warehouseId,
        'product_id' => $productAId,
        'date_send_stock' => '2025-01-20',
        'from_model_id' => $productionAId,
        'from_model_type' => 'App\\Models\\Production',
        'cabang_id' => $cabangId,
        'created_at' => '2025-01-20 10:00:00',
        'updated_at' => '2025-01-20 10:00:00',
    ]));

    $qcBId = DB::table('quality_controls')->insertGetId($filterColumns('quality_controls', [
        'qc_number' => 'QC-PW-COGM-B',
        'inspected_by' => $user->id,
        'passed_quantity' => 2,
        'rejected_quantity' => 0,
        'quantity_received' => 2,
        'status' => 1,
        'warehouse_id' => $warehouseId,
        'product_id' => $productBId,
        'date_send_stock' => '2025-01-21',
        'from_model_id' => $productionBId,
        'from_model_type' => 'App\\Models\\Production',
        'cabang_id' => $cabangId,
        'created_at' => '2025-01-21 10:00:00',
        'updated_at' => '2025-01-21 10:00:00',
    ]));

    DB::table('material_issues')->insert([
        $filterColumns('material_issues', [
            'issue_number' => 'MI-PW-COGM-A-ISSUE',
            'production_plan_id' => $planAId,
            'manufacturing_order_id' => $moAId,
            'warehouse_id' => $warehouseId,
            'issue_date' => '2025-01-09',
            'type' => 'issue',
            'status' => 'completed',
            'total_cost' => 500,
            'created_by' => $user->id,
            'created_at' => '2025-01-09 08:00:00',
            'updated_at' => '2025-01-09 08:00:00',
        ]),
        $filterColumns('material_issues', [
            'issue_number' => 'MI-PW-COGM-A-RETURN',
            'production_plan_id' => $planAId,
            'manufacturing_order_id' => $moAId,
            'warehouse_id' => $warehouseId,
            'issue_date' => '2025-01-11',
            'type' => 'return',
            'status' => 'completed',
            'total_cost' => 50,
            'created_by' => $user->id,
            'created_at' => '2025-01-11 08:00:00',
            'updated_at' => '2025-01-11 08:00:00',
        ]),
        $filterColumns('material_issues', [
            'issue_number' => 'MI-PW-COGM-B-ISSUE',
            'production_plan_id' => $planBId,
            'manufacturing_order_id' => $moBId,
            'warehouse_id' => $warehouseId,
            'issue_date' => '2025-01-09',
            'type' => 'issue',
            'status' => 'completed',
            'total_cost' => 700,
            'created_by' => $user->id,
            'created_at' => '2025-01-09 09:00:00',
            'updated_at' => '2025-01-09 09:00:00',
        ]),
    ]);

    DB::table('journal_entries')->insert([
        [
            'coa_id' => $wipCoaId,
            'date' => '2025-01-10',
            'reference' => 'PROD-PW-COGM-A',
            'description' => 'Produksi in progress - MO A',
            'debit' => 750,
            'credit' => 0,
            'journal_type' => 'manufacturing_wip',
            'cabang_id' => $cabangId,
            'source_type' => 'App\\Models\\Production',
            'source_id' => $productionAId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'coa_id' => $laborCoaId,
            'date' => '2025-01-10',
            'reference' => 'PROD-PW-COGM-A',
            'description' => 'Produksi in progress - MO A (tenaga kerja langsung)',
            'debit' => 0,
            'credit' => 200,
            'journal_type' => 'manufacturing_wip',
            'cabang_id' => $cabangId,
            'source_type' => 'App\\Models\\Production',
            'source_id' => $productionAId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'coa_id' => $overheadCoaId,
            'date' => '2025-01-10',
            'reference' => 'PROD-PW-COGM-A',
            'description' => 'Produksi in progress - MO A (overhead produksi)',
            'debit' => 0,
            'credit' => 100,
            'journal_type' => 'manufacturing_wip',
            'cabang_id' => $cabangId,
            'source_type' => 'App\\Models\\Production',
            'source_id' => $productionAId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'coa_id' => $wipCoaId,
            'date' => '2025-01-20',
            'reference' => 'QC-PW-COGM-A',
            'description' => 'Penyelesaian produksi - MO A (Fixture COGM Product A)',
            'debit' => 0,
            'credit' => 300,
            'journal_type' => 'manufacturing_completion',
            'cabang_id' => $cabangId,
            'source_type' => 'App\\Models\\QualityControl',
            'source_id' => $qcAId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'coa_id' => $wipCoaId,
            'date' => '2025-01-12',
            'reference' => 'PROD-PW-COGM-B',
            'description' => 'Produksi in progress - MO B',
            'debit' => 920,
            'credit' => 0,
            'journal_type' => 'manufacturing_wip',
            'cabang_id' => $cabangId,
            'source_type' => 'App\\Models\\Production',
            'source_id' => $productionBId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'coa_id' => $laborCoaId,
            'date' => '2025-01-12',
            'reference' => 'PROD-PW-COGM-B',
            'description' => 'Produksi in progress - MO B (tenaga kerja langsung)',
            'debit' => 0,
            'credit' => 200,
            'journal_type' => 'manufacturing_wip',
            'cabang_id' => $cabangId,
            'source_type' => 'App\\Models\\Production',
            'source_id' => $productionBId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'coa_id' => $overheadCoaId,
            'date' => '2025-01-12',
            'reference' => 'PROD-PW-COGM-B',
            'description' => 'Produksi in progress - MO B (overhead produksi)',
            'debit' => 0,
            'credit' => 120,
            'journal_type' => 'manufacturing_wip',
            'cabang_id' => $cabangId,
            'source_type' => 'App\\Models\\Production',
            'source_id' => $productionBId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'coa_id' => $wipCoaId,
            'date' => '2025-01-21',
            'reference' => 'QC-PW-COGM-B',
            'description' => 'Penyelesaian produksi - MO B (Fixture COGM Product B)',
            'debit' => 0,
            'credit' => 220,
            'journal_type' => 'manufacturing_completion',
            'cabang_id' => $cabangId,
            'source_type' => 'App\\Models\\QualityControl',
            'source_id' => $qcBId,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    echo "✅ COGM fixture ready\n";
    echo "   Product A: {$fixture['product_a_sku']}\n";
    echo "   Product B: {$fixture['product_b_sku']}\n";
});