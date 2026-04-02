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
        $product = Product::factory()->create([
            'sku' => 'PW-ST-001',
            'name' => 'Playwright Stock Transfer Product',
            'product_category_id' => $category->id,
            'cabang_id' => $cabang->id,
            'cost_price' => 10000,
            'sell_price' => 15000,
        ]);
    }

    $fromWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
        ['kode' => 'GDG-PW-ST-001'],
        [
            'name' => 'Gudang Asal Playwright Transfer',
            'cabang_id' => $cabang->id,
            'location' => 'Playwright Source',
            'status' => 1,
        ]
    );

    $toWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
        ['kode' => 'GDG-PW-ST-002'],
        [
            'name' => 'Gudang Tujuan Playwright Transfer',
            'cabang_id' => $cabang->id,
            'location' => 'Playwright Destination',
            'status' => 1,
        ]
    );

    $failedFromWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
        ['kode' => 'GDG-PW-ST-003'],
        [
            'name' => 'Gudang Asal Playwright Transfer Gagal',
            'cabang_id' => $cabang->id,
            'location' => 'Playwright Failed Source',
            'status' => 1,
        ]
    );

    $failedToWarehouse = Warehouse::withoutGlobalScopes()->firstOrCreate(
        ['kode' => 'GDG-PW-ST-004'],
        [
            'name' => 'Gudang Tujuan Playwright Transfer Gagal',
            'cabang_id' => $cabang->id,
            'location' => 'Playwright Failed Destination',
            'status' => 1,
        ]
    );

    $fromRak = Rak::query()->firstOrCreate(
        ['code' => 'RAK-PW-ST-001'],
        [
            'name' => 'Rak Asal PW',
            'warehouse_id' => $fromWarehouse->id,
        ]
    );

    $toRak = Rak::query()->firstOrCreate(
        ['code' => 'RAK-PW-ST-002'],
        [
            'name' => 'Rak Tujuan PW',
            'warehouse_id' => $toWarehouse->id,
        ]
    );

    $failedFromRak = Rak::query()->firstOrCreate(
        ['code' => 'RAK-PW-ST-003'],
        [
            'name' => 'Rak Asal PW Gagal',
            'warehouse_id' => $failedFromWarehouse->id,
        ]
    );

    $failedToRak = Rak::query()->firstOrCreate(
        ['code' => 'RAK-PW-ST-004'],
        [
            'name' => 'Rak Tujuan PW Gagal',
            'warehouse_id' => $failedToWarehouse->id,
        ]
    );

    InventoryStock::updateOrCreate(
        [
            'product_id' => $product->id,
            'warehouse_id' => $fromWarehouse->id,
            'rak_id' => $fromRak->id,
        ],
        [
            'qty_available' => 20,
            'qty_reserved' => 0,
            'qty_min' => 0,
        ]
    );

    InventoryStock::updateOrCreate(
        [
            'product_id' => $product->id,
            'warehouse_id' => $toWarehouse->id,
            'rak_id' => $toRak->id,
        ],
        [
            'qty_available' => 0,
            'qty_reserved' => 0,
            'qty_min' => 0,
        ]
    );

    InventoryStock::updateOrCreate(
        [
            'product_id' => $product->id,
            'warehouse_id' => $failedFromWarehouse->id,
            'rak_id' => $failedFromRak->id,
        ],
        [
            'qty_available' => 3,
            'qty_reserved' => 0,
            'qty_min' => 0,
        ]
    );

    InventoryStock::updateOrCreate(
        [
            'product_id' => $product->id,
            'warehouse_id' => $failedToWarehouse->id,
            'rak_id' => $failedToRak->id,
        ],
        [
            'qty_available' => 0,
            'qty_reserved' => 0,
            'qty_min' => 0,
        ]
    );

    $successTransfer = StockTransfer::withTrashed()->firstOrCreate(
        ['transfer_number' => 'ST-PW-APPROVE-001'],
        [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_date' => now(),
            'status' => 'Request',
        ]
    );
    if ($successTransfer->trashed()) {
        $successTransfer->restore();
    }
    $successTransfer->update([
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'transfer_date' => now(),
        'status' => 'Request',
    ]);

    StockMovement::query()
        ->where('from_model_type', StockTransfer::class)
        ->where('from_model_id', $successTransfer->id)
        ->forceDelete();
    StockTransferItem::withTrashed()->where('stock_transfer_id', $successTransfer->id)->forceDelete();

    StockTransferItem::create([
        'stock_transfer_id' => $successTransfer->id,
        'product_id' => $product->id,
        'quantity' => 7,
        'from_warehouse_id' => $fromWarehouse->id,
        'from_rak_id' => $fromRak->id,
        'to_warehouse_id' => $toWarehouse->id,
        'to_rak_id' => $toRak->id,
    ]);

    $failedTransfer = StockTransfer::withTrashed()->firstOrCreate(
        ['transfer_number' => 'ST-PW-FAIL-001'],
        [
            'from_warehouse_id' => $failedFromWarehouse->id,
            'to_warehouse_id' => $failedToWarehouse->id,
            'transfer_date' => now(),
            'status' => 'Request',
        ]
    );
    if ($failedTransfer->trashed()) {
        $failedTransfer->restore();
    }
    $failedTransfer->update([
        'from_warehouse_id' => $failedFromWarehouse->id,
        'to_warehouse_id' => $failedToWarehouse->id,
        'transfer_date' => now(),
        'status' => 'Request',
    ]);

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
