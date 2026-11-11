<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

echo "Resetting test data for Siklus D (Manajemen Pergudangan & Kontrol Persediaan)...\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0');

// 1. Delete test Stock Opnames
$testOpnames = StockOpname::where('opname_number', 'like', 'OPN-D-%')
    ->orWhere('opname_number', 'like', 'OPN-TEST-%')
    ->orWhere('notes', 'like', '%Siklus D%')
    ->pluck('id')->all();

if (!empty($testOpnames)) {
    JournalEntry::where('source_type', StockOpname::class)->whereIn('source_id', $testOpnames)->delete();
    StockMovement::where('from_model_type', StockOpname::class)->whereIn('from_model_id', $testOpnames)->delete();
    StockOpnameItem::whereIn('stock_opname_id', $testOpnames)->delete();
    StockOpname::whereIn('id', $testOpnames)->forceDelete();
    echo "  - Deleted " . count($testOpnames) . " test Stock Opname records.\n";
}

// 2. Delete test Stock Adjustments
$testAdjustments = StockAdjustment::where('adjustment_number', 'like', 'ADJ-D-%')
    ->orWhere('adjustment_number', 'like', 'ADJ-TEST-%')
    ->orWhere('reason', 'like', '%Siklus D%')
    ->pluck('id')->all();

if (!empty($testAdjustments)) {
    JournalEntry::where('source_type', StockAdjustment::class)->whereIn('source_id', $testAdjustments)->delete();
    StockMovement::where('from_model_type', StockAdjustment::class)->whereIn('from_model_id', $testAdjustments)->delete();
    StockAdjustmentItem::whereIn('stock_adjustment_id', $testAdjustments)->delete();
    StockAdjustment::whereIn('id', $testAdjustments)->forceDelete();
    echo "  - Deleted " . count($testAdjustments) . " test Stock Adjustment records.\n";
}

// 3. Delete test Stock Transfers
$testTransfers = StockTransfer::where('transfer_number', 'like', 'TRF-D-%')
    ->orWhere('transfer_number', 'like', 'TRF-TEST-%')
    ->orWhere('notes', 'like', '%Siklus D%')
    ->pluck('id')->all();

if (!empty($testTransfers)) {
    StockMovement::where('from_model_type', StockTransfer::class)->whereIn('from_model_id', $testTransfers)->delete();
    StockTransferItem::whereIn('stock_transfer_id', $testTransfers)->forceDelete();
    StockTransfer::whereIn('id', $testTransfers)->forceDelete();
    echo "  - Deleted " . count($testTransfers) . " test Stock Transfer records.\n";
}

// 4. Ensure Warehouse 1 and Warehouse 2 have valid clean baseline stock for testing
// Product 1 (Panel Kontrol Industri):
// - Gudang Utama (WH 1, Rak 1): 15 units
// - Gudang Cabang A (WH 2, Rak 4): 0 units
InventoryStock::where('product_id', 1)->whereIn('warehouse_id', [1, 2])->forceDelete();

InventoryStock::create([
    'product_id' => 1,
    'warehouse_id' => 1,
    'rak_id' => 1,
    'qty_available' => 15,
    'qty_reserved' => 0,
    'qty_min' => 2,
]);

InventoryStock::create([
    'product_id' => 1,
    'warehouse_id' => 2,
    'rak_id' => 4,
    'qty_available' => 0,
    'qty_reserved' => 0,
    'qty_min' => 2,
]);

// Product 3 (Bahan Baku Plastik Granul):
// - Gudang Utama (WH 1, Rak 1): 40 units
// - Gudang Cabang A (WH 2, Rak 4): 0 units
InventoryStock::where('product_id', 3)->whereIn('warehouse_id', [1, 2])->forceDelete();

InventoryStock::create([
    'product_id' => 3,
    'warehouse_id' => 1,
    'rak_id' => 1,
    'qty_available' => 40,
    'qty_reserved' => 0,
    'qty_min' => 5,
]);

InventoryStock::create([
    'product_id' => 3,
    'warehouse_id' => 2,
    'rak_id' => 4,
    'qty_available' => 0,
    'qty_reserved' => 0,
    'qty_min' => 5,
]);

DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "Reset completed successfully! Product 1 baseline: WH1 = 15, WH2 = 0. Product 3 baseline: WH1 = 40, WH2 = 0.\n";

