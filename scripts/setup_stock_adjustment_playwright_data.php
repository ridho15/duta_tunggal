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
        $product = Product::factory()->create([
            'sku' => 'PW-SA-001',
            'name' => 'Playwright Stock Adjustment Product',
            'product_category_id' => $category->id,
            'cabang_id' => $cabang->id,
            'cost_price' => 10000,
            'sell_price' => 15000,
        ]);
    }

    $successWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
        ['kode' => 'GDG-PW-SA-001'],
        [
            'name' => 'Gudang Playwright Adjustment Sukses',
            'cabang_id' => $cabang->id,
            'location' => 'Playwright Adjustment Success',
            'status' => 1,
        ]
    );

    $failedWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
        ['kode' => 'GDG-PW-SA-002'],
        [
            'name' => 'Gudang Playwright Adjustment Gagal',
            'cabang_id' => $cabang->id,
            'location' => 'Playwright Adjustment Fail',
            'status' => 1,
        ]
    );

    $successRak = Rak::query()->firstOrCreate(
        ['code' => 'RAK-PW-SA-001'],
        [
            'name' => 'Rak PW Adjustment Sukses',
            'warehouse_id' => $successWarehouse->id,
        ]
    );

    $failedRak = Rak::query()->firstOrCreate(
        ['code' => 'RAK-PW-SA-002'],
        [
            'name' => 'Rak PW Adjustment Gagal',
            'warehouse_id' => $failedWarehouse->id,
        ]
    );

    InventoryStock::updateOrCreate(
        [
            'product_id' => $product->id,
            'warehouse_id' => $successWarehouse->id,
            'rak_id' => $successRak->id,
        ],
        [
            'qty_available' => 10,
            'qty_reserved' => 0,
            'qty_min' => 0,
        ]
    );

    InventoryStock::updateOrCreate(
        [
            'product_id' => $product->id,
            'warehouse_id' => $failedWarehouse->id,
            'rak_id' => $failedRak->id,
        ],
        [
            'qty_available' => 3,
            'qty_reserved' => 0,
            'qty_min' => 0,
        ]
    );

    $successAdjustment = StockAdjustment::withTrashed()->firstOrCreate(
        ['adjustment_number' => 'ADJ-PW-APPROVE-001'],
        [
            'adjustment_date' => now()->toDateString(),
            'warehouse_id' => $successWarehouse->id,
            'adjustment_type' => 'increase',
            'reason' => 'Playwright approval success fixture',
            'status' => 'draft',
            'created_by' => $user->id,
        ]
    );
    if ($successAdjustment->trashed()) {
        $successAdjustment->restore();
    }
    $successAdjustment->update([
        'adjustment_date' => now()->toDateString(),
        'warehouse_id' => $successWarehouse->id,
        'adjustment_type' => 'increase',
        'reason' => 'Playwright approval success fixture',
        'notes' => 'Playwright stock adjustment approval success case',
        'status' => 'draft',
        'created_by' => $user->id,
        'approved_by' => null,
        'approved_at' => null,
    ]);

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
            'warehouse_id' => $failedWarehouse->id,
            'adjustment_type' => 'decrease',
            'reason' => 'Playwright approval failure fixture',
            'status' => 'draft',
            'created_by' => $user->id,
        ]
    );
    if ($failedAdjustment->trashed()) {
        $failedAdjustment->restore();
    }
    $failedAdjustment->update([
        'adjustment_date' => now()->toDateString(),
        'warehouse_id' => $failedWarehouse->id,
        'adjustment_type' => 'decrease',
        'reason' => 'Playwright approval failure fixture',
        'notes' => 'Playwright stock adjustment approval failure case',
        'status' => 'draft',
        'created_by' => $user->id,
        'approved_by' => null,
        'approved_at' => null,
    ]);

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