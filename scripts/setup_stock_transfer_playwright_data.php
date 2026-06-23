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
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

DB::transaction(function () {
    $cabang = Cabang::query()->first() ?? Cabang::factory()->create([
        'name' => 'Cabang Playwright Gudang',
    ]);

    $category = ProductCategory::query()->firstOrCreate(
        ['kode' => 'PW-ST-CAT'],
        ['name' => 'Playwright Stock Transfer Category']
    );

    $product = Product::query()->where('sku', 'PW-ST-001')->first();

    if (! $product) {
        $product = Product::factory()->forCabang($cabang)->create([
            'sku' => 'PW-ST-001',
            'name' => 'Playwright Stock Transfer Product',
            'product_category_id' => $category->id,
            'cost_price' => 10000,
            'sell_price' => 15000,
        ]);
    }

    $fromWarehouseData = [
        'name' => 'Gudang Asal Playwright Transfer',
        'location' => 'Playwright Source',
        'status' => 1,
    ];
    if (Schema::hasColumn('warehouses', 'cabang_id')) {
        $fromWarehouseData['cabang_id'] = $cabang->id;
    }
    $fromWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(['kode' => 'GDG-PW-ST-001'], $fromWarehouseData);

    $toWarehouseData = [
        'name' => 'Gudang Tujuan Playwright Transfer',
        'location' => 'Playwright Destination',
        'status' => 1,
    ];
    if (Schema::hasColumn('warehouses', 'cabang_id')) {
        $toWarehouseData['cabang_id'] = $cabang->id;
    }
    $toWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(['kode' => 'GDG-PW-ST-002'], $toWarehouseData);

    $failedFromWarehouseData = [
        'name' => 'Gudang Asal Playwright Transfer Gagal',
        'location' => 'Playwright Failed Source',
        'status' => 1,
    ];
    if (Schema::hasColumn('warehouses', 'cabang_id')) {
        $failedFromWarehouseData['cabang_id'] = $cabang->id;
    }
    $failedFromWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(['kode' => 'GDG-PW-ST-003'], $failedFromWarehouseData);

    $failedToWarehouseData = [
        'name' => 'Gudang Tujuan Playwright Transfer Gagal',
        'location' => 'Playwright Failed Destination',
        'status' => 1,
    ];
    if (Schema::hasColumn('warehouses', 'cabang_id')) {
        $failedToWarehouseData['cabang_id'] = $cabang->id;
    }
    $failedToWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(['kode' => 'GDG-PW-ST-004'], $failedToWarehouseData);

    $fromRakData = ['name' => 'Rak Asal PW'];
    if (Schema::hasColumn('raks', 'warehouse_id')) {
        $fromRakData['warehouse_id'] = $fromWarehouse->id;
    }
    $fromRak = Rak::query()->firstOrCreate(['code' => 'RAK-PW-ST-001'], $fromRakData);

    $toRakData = ['name' => 'Rak Tujuan PW'];
    if (Schema::hasColumn('raks', 'warehouse_id')) {
        $toRakData['warehouse_id'] = $toWarehouse->id;
    }
    $toRak = Rak::query()->firstOrCreate(['code' => 'RAK-PW-ST-002'], $toRakData);

    $failedFromRakData = ['name' => 'Rak Asal PW Gagal'];
    if (Schema::hasColumn('raks', 'warehouse_id')) {
        $failedFromRakData['warehouse_id'] = $failedFromWarehouse->id;
    }
    $failedFromRak = Rak::query()->firstOrCreate(['code' => 'RAK-PW-ST-003'], $failedFromRakData);

    $failedToRakData = ['name' => 'Rak Tujuan PW Gagal'];
    if (Schema::hasColumn('raks', 'warehouse_id')) {
        $failedToRakData['warehouse_id'] = $failedToWarehouse->id;
    }
    $failedToRak = Rak::query()->firstOrCreate(['code' => 'RAK-PW-ST-004'], $failedToRakData);

    $stockKeyA = [
        'product_id' => $product->id,
        'rak_id' => $fromRak->id,
    ];
    if (Schema::hasColumn('inventory_stocks', 'warehouse_id')) {
        $stockKeyA['warehouse_id'] = $fromWarehouse->id;
    }
    InventoryStock::updateOrCreate($stockKeyA, [
        'qty_available' => 20,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $stockKeyB = [
        'product_id' => $product->id,
        'rak_id' => $toRak->id,
    ];
    if (Schema::hasColumn('inventory_stocks', 'warehouse_id')) {
        $stockKeyB['warehouse_id'] = $toWarehouse->id;
    }
    InventoryStock::updateOrCreate($stockKeyB, [
        'qty_available' => 0,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $stockKeyC = [
        'product_id' => $product->id,
        'rak_id' => $failedFromRak->id,
    ];
    if (Schema::hasColumn('inventory_stocks', 'warehouse_id')) {
        $stockKeyC['warehouse_id'] = $failedFromWarehouse->id;
    }
    InventoryStock::updateOrCreate($stockKeyC, [
        'qty_available' => 3,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $stockKeyD = [
        'product_id' => $product->id,
        'rak_id' => $failedToRak->id,
    ];
    if (Schema::hasColumn('inventory_stocks', 'warehouse_id')) {
        $stockKeyD['warehouse_id'] = $failedToWarehouse->id;
    }
    InventoryStock::updateOrCreate($stockKeyD, [
        'qty_available' => 0,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $stData = [
        'transfer_date' => now(),
        'status' => 'Request',
    ];
    if (Schema::hasColumn('stock_transfers', 'from_warehouse_id')) {
        $stData['from_warehouse_id'] = $fromWarehouse->id;
    }
    if (Schema::hasColumn('stock_transfers', 'to_warehouse_id')) {
        $stData['to_warehouse_id'] = $toWarehouse->id;
    }
    $successTransfer = StockTransfer::withTrashed()->firstOrCreate(['transfer_number' => 'ST-PW-APPROVE-001'], $stData);
    if ($successTransfer->trashed()) {
        $successTransfer->restore();
    }
    $updateSt1 = [
        'transfer_date' => now(),
        'status' => 'Request',
    ];
    if (Schema::hasColumn('stock_transfers', 'from_warehouse_id')) {
        $updateSt1['from_warehouse_id'] = $fromWarehouse->id;
    }
    if (Schema::hasColumn('stock_transfers', 'to_warehouse_id')) {
        $updateSt1['to_warehouse_id'] = $toWarehouse->id;
    }
    $successTransfer->update($updateSt1);

    StockMovement::query()
        ->where('from_model_type', StockTransfer::class)
        ->where('from_model_id', $successTransfer->id)
        ->forceDelete();
    StockTransferItem::withTrashed()->where('stock_transfer_id', $successTransfer->id)->forceDelete();

    $sti = [
        'stock_transfer_id' => $successTransfer->id,
        'product_id' => $product->id,
        'quantity' => 7,
        'from_rak_id' => $fromRak->id,
        'to_rak_id' => $toRak->id,
    ];
    if (Schema::hasColumn('stock_transfer_items', 'from_warehouse_id')) {
        $sti['from_warehouse_id'] = $fromWarehouse->id;
    }
    if (Schema::hasColumn('stock_transfer_items', 'to_warehouse_id')) {
        $sti['to_warehouse_id'] = $toWarehouse->id;
    }
    StockTransferItem::create($sti);

    $ftData = [
        'transfer_date' => now(),
        'status' => 'Request',
    ];
    if (Schema::hasColumn('stock_transfers', 'from_warehouse_id')) {
        $ftData['from_warehouse_id'] = $failedFromWarehouse->id;
    }
    if (Schema::hasColumn('stock_transfers', 'to_warehouse_id')) {
        $ftData['to_warehouse_id'] = $failedToWarehouse->id;
    }
    $failedTransfer = StockTransfer::withTrashed()->firstOrCreate(['transfer_number' => 'ST-PW-FAIL-001'], $ftData);
    if ($failedTransfer->trashed()) {
        $failedTransfer->restore();
    }
    $updateFt = [
        'transfer_date' => now(),
        'status' => 'Request',
    ];
    if (Schema::hasColumn('stock_transfers', 'from_warehouse_id')) {
        $updateFt['from_warehouse_id'] = $failedFromWarehouse->id;
    }
    if (Schema::hasColumn('stock_transfers', 'to_warehouse_id')) {
        $updateFt['to_warehouse_id'] = $failedToWarehouse->id;
    }
    $failedTransfer->update($updateFt);

    StockMovement::query()
        ->where('from_model_type', StockTransfer::class)
        ->where('from_model_id', $failedTransfer->id)
        ->forceDelete();
    StockTransferItem::withTrashed()->where('stock_transfer_id', $failedTransfer->id)->forceDelete();

    StockTransferItem::create([
        'stock_transfer_id' => $failedTransfer->id,
        'product_id' => $product->id,
        'quantity' => 9,
        'from_warehouse_id' => $failedFromWarehouse->id,
        'from_rak_id' => $failedFromRak->id,
        'to_warehouse_id' => $failedToWarehouse->id,
        'to_rak_id' => $failedToRak->id,
    ]);
});

echo "Playwright stock transfer fixture ready\n";
