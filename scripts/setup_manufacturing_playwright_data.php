<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$now = now();

$fixture = [
    'warehouse_code' => 'GDG-PW-MFG-001',
    'warehouse_name' => 'Gudang Fixture Manufacturing',
    'fg_sku' => 'FG-PW-MFG-001',
    'fg_name' => 'Fixture Finished Good Manufacturing',
    'rm_sku' => 'RM-PW-MFG-001',
    'rm_name' => 'Fixture Raw Material Manufacturing',
    'bom_code' => 'BOM-PW-MFG-001',
    'bom_name' => 'Fixture BOM Manufacturing',
    'plan_number' => 'PP-PW-MFG-001',
    'plan_name' => 'Fixture Production Plan Manufacturing',
];

DB::transaction(function () use ($fixture, $now) {
    $user = DB::table('users')->where('email', 'ralamzah@gmail.com')->first()
        ?? DB::table('users')->orderBy('id')->first();

    if (!$user) {
        throw new RuntimeException('No user found for manufacturing Playwright fixture.');
    }

    $cabangId = $user->cabang_id ?: DB::table('cabangs')->value('id');
    if (!$cabangId) {
        throw new RuntimeException('No cabang found for manufacturing Playwright fixture.');
    }

    $uomId = DB::table('unit_of_measures')->value('id');
    if (!$uomId) {
        $uomId = DB::table('unit_of_measures')->insertGetId([
            'name' => 'Piece',
            'abbreviation' => 'pcs',
        ]);
    }

    $categoryId = DB::table('product_categories')->value('id');
    if (!$categoryId) {
        $categoryId = DB::table('product_categories')->insertGetId([
            'kode' => 'CAT-PW-MFG',
            'name' => 'Kategori Fixture Manufacturing',
            'kenaikan_harga' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $supplierId = DB::table('suppliers')->value('id');

    $warehouseId = DB::table('warehouses')->where('kode', $fixture['warehouse_code'])->value('id');
    if ($warehouseId) {
        DB::table('warehouses')->where('id', $warehouseId)->update([
            'name' => $fixture['warehouse_name'],
            'location' => 'Fixture Location Manufacturing',
            'cabang_id' => $cabangId,
            'tipe' => 'Besar',
            'telepon' => '081111111111',
            'status' => 1,
            'updated_at' => $now,
        ]);
    } else {
        $warehouseId = DB::table('warehouses')->insertGetId([
            'kode' => $fixture['warehouse_code'],
            'name' => $fixture['warehouse_name'],
            'location' => 'Fixture Location Manufacturing',
            'cabang_id' => $cabangId,
            'tipe' => 'Besar',
            'telepon' => '081111111111',
            'status' => 1,
            'warna_background' => '#dbeafe',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $coaInventoryRaw = DB::table('chart_of_accounts')->where('code', '1140.01')->value('id');
    $coaInventoryFg = DB::table('chart_of_accounts')->where('code', '1140.03')->value('id');

    $rawMaterialId = DB::table('products')->where('sku', $fixture['rm_sku'])->value('id');
    $rawMaterialPayload = [
        'name' => $fixture['rm_name'],
        'product_category_id' => $categoryId,
        'cabang_id' => $cabangId,
        'cost_price' => 50000,
        'sell_price' => 75000,
        'description' => 'Fixture raw material for Playwright manufacturing flow',
        'uom_id' => $uomId,
        'supplier_id' => $supplierId,
        'harga_batas' => 0,
        'item_value' => 50000,
        'tipe_pajak' => 'Non Pajak',
        'pajak' => 0,
        'jumlah_kelipatan_gudang_besar' => 1,
        'jumlah_jual_kategori_banyak' => 1,
        'kode_merk' => 'MRK-PW-MFG-RM',
        'biaya' => 0,
        'is_manufacture' => 0,
        'is_raw_material' => 1,
        'inventory_coa_id' => $coaInventoryRaw,
        'goods_delivery_coa_id' => null,
        'sales_coa_id' => null,
        'sales_return_coa_id' => null,
        'sales_discount_coa_id' => null,
        'cogs_coa_id' => null,
        'purchase_return_coa_id' => null,
        'unbilled_purchase_coa_id' => null,
        'temporary_procurement_coa_id' => null,
        'is_active' => 1,
        'updated_at' => $now,
    ];

    if ($rawMaterialId) {
        DB::table('products')->where('id', $rawMaterialId)->update($rawMaterialPayload);
    } else {
        $rawMaterialId = DB::table('products')->insertGetId(array_merge($rawMaterialPayload, [
            'sku' => $fixture['rm_sku'],
            'created_at' => $now,
        ]));
    }

    $finishedGoodId = DB::table('products')->where('sku', $fixture['fg_sku'])->value('id');
    $finishedGoodPayload = [
        'name' => $fixture['fg_name'],
        'product_category_id' => $categoryId,
        'cabang_id' => $cabangId,
        'cost_price' => 100000,
        'sell_price' => 150000,
        'description' => 'Fixture finished good for Playwright manufacturing flow',
        'uom_id' => $uomId,
        'supplier_id' => $supplierId,
        'harga_batas' => 0,
        'item_value' => 100000,
        'tipe_pajak' => 'Non Pajak',
        'pajak' => 0,
        'jumlah_kelipatan_gudang_besar' => 1,
        'jumlah_jual_kategori_banyak' => 1,
        'kode_merk' => 'MRK-PW-MFG-FG',
        'biaya' => 0,
        'is_manufacture' => 1,
        'is_raw_material' => 0,
        'inventory_coa_id' => $coaInventoryFg,
        'goods_delivery_coa_id' => null,
        'sales_coa_id' => null,
        'sales_return_coa_id' => null,
        'sales_discount_coa_id' => null,
        'cogs_coa_id' => null,
        'purchase_return_coa_id' => null,
        'unbilled_purchase_coa_id' => null,
        'temporary_procurement_coa_id' => null,
        'is_active' => 1,
        'updated_at' => $now,
    ];

    if ($finishedGoodId) {
        DB::table('products')->where('id', $finishedGoodId)->update($finishedGoodPayload);
    } else {
        $finishedGoodId = DB::table('products')->insertGetId(array_merge($finishedGoodPayload, [
            'sku' => $fixture['fg_sku'],
            'created_at' => $now,
        ]));
    }

    $bomId = DB::table('bill_of_materials')->where('code', $fixture['bom_code'])->value('id');
    $bomPayload = [
        'cabang_id' => $cabangId,
        'product_id' => $finishedGoodId,
        'quantity' => 1,
        'nama_bom' => $fixture['bom_name'],
        'note' => 'Fixture BOM for Playwright manufacturing flow',
        'is_active' => 1,
        'uom_id' => $uomId,
        'labor_cost' => 0,
        'overhead_cost' => 0,
        'total_cost' => 100000,
        'work_in_progress_coa_id' => DB::table('chart_of_accounts')->where('code', '1140.02')->value('id'),
        'finished_goods_coa_id' => DB::table('chart_of_accounts')->where('code', '1140.03')->value('id'),
        'updated_at' => $now,
    ];

    if ($bomId) {
        DB::table('bill_of_materials')->where('id', $bomId)->update($bomPayload);
    } else {
        $bomId = DB::table('bill_of_materials')->insertGetId(array_merge($bomPayload, [
            'code' => $fixture['bom_code'],
            'created_at' => $now,
        ]));
    }

    DB::table('bill_of_material_items')->where('bill_of_material_id', $bomId)->delete();
    DB::table('bill_of_material_items')->insert([
        'bill_of_material_id' => $bomId,
        'product_id' => $rawMaterialId,
        'quantity' => 2,
        'uom_id' => $uomId,
        'unit_price' => 50000,
        'subtotal' => 100000,
        'note' => 'Fixture item for Playwright manufacturing flow',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $planId = DB::table('production_plans')->where('plan_number', $fixture['plan_number'])->value('id');
    if ($planId) {
        $moIds = DB::table('manufacturing_orders')->where('production_plan_id', $planId)->pluck('id');
        $productionIds = DB::table('productions')->whereIn('manufacturing_order_id', $moIds)->pluck('id');
        $qcIds = DB::table('quality_controls')
            ->where('from_model_type', 'App\\Models\\Production')
            ->whereIn('from_model_id', $productionIds)
            ->pluck('id');

        if ($qcIds->isNotEmpty()) {
            DB::table('stock_movements')->where('from_model_type', 'App\\Models\\QualityControl')->whereIn('from_model_id', $qcIds)->delete();
            DB::table('journal_entries')->where('source_type', 'App\\Models\\QualityControl')->whereIn('source_id', $qcIds)->delete();
            DB::table('quality_controls')->whereIn('id', $qcIds)->delete();
        }

        if ($productionIds->isNotEmpty()) {
            DB::table('journal_entries')->where('source_type', 'App\\Models\\Production')->whereIn('source_id', $productionIds)->delete();
            DB::table('productions')->whereIn('id', $productionIds)->delete();
        }

        if ($moIds->isNotEmpty()) {
            DB::table('journal_entries')->where('source_type', 'App\\Models\\ManufacturingOrder')->whereIn('source_id', $moIds)->delete();
            DB::table('warehouse_confirmations')
                ->where('confirmable_type', 'App\\Models\\ManufacturingOrder')
                ->whereIn('confirmable_id', $moIds)
                ->delete();
            DB::table('manufacturing_orders')->whereIn('id', $moIds)->delete();
        }
    }

    $planPayload = [
        'name' => $fixture['plan_name'],
        'source_type' => 'manual',
        'sale_order_id' => null,
        'bill_of_material_id' => $bomId,
        'product_id' => $finishedGoodId,
        'quantity' => 5,
        'uom_id' => $uomId,
        'warehouse_id' => $warehouseId,
        'cabang_id' => $cabangId,
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDay()->toDateTimeString(),
        'status' => 'scheduled',
        'notes' => 'Fixture plan for Playwright manufacturing flow',
        'created_by' => $user->id,
        'updated_at' => $now,
    ];

    if ($planId) {
        DB::table('production_plans')->where('id', $planId)->update($planPayload);
    } else {
        $planId = DB::table('production_plans')->insertGetId(array_merge($planPayload, [
            'plan_number' => $fixture['plan_number'],
            'created_at' => $now,
        ]));
    }

    $inventoryStockId = DB::table('inventory_stocks')
        ->where('product_id', $rawMaterialId)
        ->where('warehouse_id', $warehouseId)
        ->whereNull('rak_id')
        ->value('id');

    $inventoryPayload = [
        'qty_available' => 100,
        'qty_reserved' => 0,
        'qty_min' => 0,
        'updated_at' => $now,
    ];

    if ($inventoryStockId) {
        DB::table('inventory_stocks')->where('id', $inventoryStockId)->update($inventoryPayload);
    } else {
        DB::table('inventory_stocks')->insert(array_merge($inventoryPayload, [
            'product_id' => $rawMaterialId,
            'warehouse_id' => $warehouseId,
            'rak_id' => null,
            'created_at' => $now,
        ]));
    }

    echo "✅ Manufacturing fixture ready\n";
    echo "   Plan      : {$fixture['plan_number']}\n";
    echo "   BOM       : {$fixture['bom_code']}\n";
    echo "   Product   : {$fixture['fg_sku']}\n";
    echo "   Material  : {$fixture['rm_sku']}\n";
    echo "   Warehouse : {$fixture['warehouse_code']}\n";
});