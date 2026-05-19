<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

DB::transaction(function () {
    $cabang = Cabang::query()->first() ?? Cabang::factory()->create([
        'name' => 'Cabang Playwright Stock Adjustment',
    ]);

    $category = ProductCategory::query()->firstOrCreate(
        ['kode' => 'PW-SA-CAT'],
        ['name' => 'Playwright Stock Adjustment Category']
    );

    $product = Product::query()->where('sku', 'PW-SA-001')->first();
    $user = User::query()->first() ?? User::factory()->create();

    if (! $product) {
        $product = Product::factory()->forCabang($cabang)->create([
            'sku' => 'PW-SA-001',
            'name' => 'Playwright Stock Adjustment Product',
            'product_category_id' => $category->id,
            'cost_price' => 10000,
            'sell_price' => 15000,
        ]);
    }

    $successWarehouseData = [
        'name' => 'Gudang Playwright Adjustment Sukses',
        'location' => 'Playwright Adjustment Success',
        'status' => 1,
    ];
    if (Schema::hasColumn('warehouses', 'cabang_id')) {
        $successWarehouseData['cabang_id'] = $cabang->id;
    }
    $successWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(['kode' => 'GDG-PW-SA-001'], $successWarehouseData);

    $failedWarehouseData = [
        'name' => 'Gudang Playwright Adjustment Gagal',
        'location' => 'Playwright Adjustment Fail',
        'status' => 1,
    ];
    if (Schema::hasColumn('warehouses', 'cabang_id')) {
        $failedWarehouseData['cabang_id'] = $cabang->id;
    }
    $failedWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(['kode' => 'GDG-PW-SA-002'], $failedWarehouseData);

    $successRakData = [
        'name' => 'Rak PW Adjustment Sukses',
    ];
    if (Schema::hasColumn('raks', 'warehouse_id')) {
        $successRakData['warehouse_id'] = $successWarehouse->id;
    }
    $successRak = Rak::query()->firstOrCreate(['code' => 'RAK-PW-SA-001'], $successRakData);

    $failedRakData = [
        'name' => 'Rak PW Adjustment Gagal',
    ];
    if (Schema::hasColumn('raks', 'warehouse_id')) {
        $failedRakData['warehouse_id'] = $failedWarehouse->id;
    }
    $failedRak = Rak::query()->firstOrCreate(['code' => 'RAK-PW-SA-002'], $failedRakData);

    $stockKey1 = [
        'product_id' => $product->id,
        'rak_id' => $successRak->id,
    ];
    if (Schema::hasColumn('inventory_stocks', 'warehouse_id')) {
        $stockKey1['warehouse_id'] = $successWarehouse->id;
    }
    InventoryStock::updateOrCreate($stockKey1, [
        'qty_available' => 10,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $stockKey2 = [
        'product_id' => $product->id,
        'rak_id' => $failedRak->id,
    ];
    if (Schema::hasColumn('inventory_stocks', 'warehouse_id')) {
        $stockKey2['warehouse_id'] = $failedWarehouse->id;
    }
    InventoryStock::updateOrCreate($stockKey2, [
        'qty_available' => 3,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $successAdjustment = StockAdjustment::withTrashed()->firstOrCreate(
        ['adjustment_number' => 'ADJ-PW-APPROVE-001'],
        [
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'increase',
            'reason' => 'Playwright approval success fixture',
            'status' => 'draft',
            'created_by' => $user->id,
        ]
    );
    if ($successAdjustment->trashed()) {
        $successAdjustment->restore();
    }
    $updateData1 = [
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'increase',
        'reason' => 'Playwright approval success fixture',
        'notes' => 'Playwright stock adjustment approval success case',
        'status' => 'draft',
        'created_by' => $user->id,
        'approved_by' => null,
        'approved_at' => null,
    ];
    if (Schema::hasColumn('stock_adjustments', 'warehouse_id')) {
        $updateData1['warehouse_id'] = $successWarehouse->id;
    }
    $successAdjustment->update($updateData1);

    StockMovement::query()
        ->where('from_model_type', StockAdjustment::class)
        ->where('from_model_id', $successAdjustment->id)
        ->forceDelete();
    StockAdjustmentItem::query()->where('stock_adjustment_id', $successAdjustment->id)->delete();
    StockAdjustmentItem::query()->where('stock_adjustment_id', $successAdjustment->id)->forceDelete();

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $successAdjustment->id,
        'product_id' => $product->id,
        'rak_id' => $successRak->id,
        'current_qty' => 10,
        'adjusted_qty' => 15,
        'difference_qty' => 5,
        'unit_cost' => 10000,
        'difference_value' => 50000,
        'notes' => 'Tambah stok fixture Playwright',
    ]);

    $failedAdjustment = StockAdjustment::withTrashed()->firstOrCreate(
        ['adjustment_number' => 'ADJ-PW-FAIL-001'],
        [
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'decrease',
            'reason' => 'Playwright approval failure fixture',
            'status' => 'draft',
            'created_by' => $user->id,
        ]
    );
    if ($failedAdjustment->trashed()) {
        $failedAdjustment->restore();
    }
    $updateData2 = [
        'adjustment_date' => now()->toDateString(),
        'adjustment_type' => 'decrease',
        'reason' => 'Playwright approval failure fixture',
        'notes' => 'Playwright stock adjustment approval failure case',
        'status' => 'draft',
        'created_by' => $user->id,
        'approved_by' => null,
        'approved_at' => null,
    ];
    if (Schema::hasColumn('stock_adjustments', 'warehouse_id')) {
        $updateData2['warehouse_id'] = $failedWarehouse->id;
    }
    $failedAdjustment->update($updateData2);

    StockMovement::query()
        ->where('from_model_type', StockAdjustment::class)
        ->where('from_model_id', $failedAdjustment->id)
        ->forceDelete();
    StockAdjustmentItem::query()->where('stock_adjustment_id', $failedAdjustment->id)->delete();
    StockAdjustmentItem::query()->where('stock_adjustment_id', $failedAdjustment->id)->forceDelete();

    StockAdjustmentItem::create([
        'stock_adjustment_id' => $failedAdjustment->id,
        'product_id' => $product->id,
        'rak_id' => $failedRak->id,
        'current_qty' => 3,
        'adjusted_qty' => 0,
        'difference_qty' => -7,
        'unit_cost' => 10000,
        'difference_value' => -70000,
        'notes' => 'Kurangi stok fixture Playwright',
    ]);
});

echo "Playwright stock adjustment fixture ready\n";