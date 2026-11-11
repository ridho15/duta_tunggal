<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\WarehouseConfirmation;
use Illuminate\Support\Facades\DB;

echo "Resetting test data for Siklus C (Manufaktur & Perakitan Barang)...\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0');

// 1. Delete Quality Control test records
$testQcs = QualityControl::withTrashed()->where('qc_number', 'like', 'QC-C-%')
    ->orWhere('qc_number', 'like', 'QC-M-%')
    ->orWhere('qc_number', 'like', 'QC-TEST-%')
    ->orWhere('qc_number', 'like', 'QC-20261110-%')
    ->orWhere('notes', 'like', '%Siklus C%')
    ->pluck('id')->all();

if (!empty($testQcs)) {
    JournalEntry::where('source_type', QualityControl::class)->whereIn('source_id', $testQcs)->delete();
    StockMovement::where('from_model_type', QualityControl::class)->whereIn('from_model_id', $testQcs)->delete();
    QualityControl::withTrashed()->whereIn('id', $testQcs)->forceDelete();
    echo "  - Deleted " . count($testQcs) . " test QC records.\n";
}

// 2. Delete Production test records
$testProductions = Production::withTrashed()->where('production_number', 'like', 'PROD-C-%')
    ->orWhere('production_number', 'like', 'PRO-C-%')
    ->orWhere('production_number', 'like', 'PRO-20261110-%')
    ->orWhere('production_number', 'like', 'PROD-TEST-%')
    ->pluck('id')->all();

if (!empty($testProductions)) {
    JournalEntry::where('source_type', Production::class)->whereIn('source_id', $testProductions)->delete();
    Production::withTrashed()->whereIn('id', $testProductions)->forceDelete();
    echo "  - Deleted " . count($testProductions) . " test Production records.\n";
}

// 3. Delete Material Issue test records
$testIssues = MaterialIssue::withTrashed()->where('issue_number', 'like', 'MI-C-%')
    ->orWhere('issue_number', 'like', 'MI-TEST-%')
    ->orWhere('issue_number', 'like', 'ISU-20261110-%')
    ->orWhere('notes', 'like', '%Siklus C%')
    ->pluck('id')->all();

if (!empty($testIssues)) {
    MaterialIssueItem::whereIn('material_issue_id', $testIssues)->delete();
    WarehouseConfirmation::where('confirmable_type', MaterialIssue::class)->whereIn('confirmable_id', $testIssues)->delete();
    StockReservation::whereIn('material_issue_id', $testIssues)->delete();
    JournalEntry::where('source_type', MaterialIssue::class)->whereIn('source_id', $testIssues)->delete();
    StockMovement::where('from_model_type', MaterialIssue::class)->whereIn('from_model_id', $testIssues)->delete();
    MaterialIssue::withTrashed()->whereIn('id', $testIssues)->forceDelete();
    echo "  - Deleted " . count($testIssues) . " test Material Issue records.\n";
}

// 4. Delete Manufacturing Order (SPK) test records
$testMos = ManufacturingOrder::withoutGlobalScopes()->withTrashed()->where('mo_number', 'like', 'MO-C-%')
    ->orWhere('mo_number', 'like', 'MO-TEST-%')
    ->orWhere('mo_number', 'like', 'MO-20261110-%')
    ->pluck('id')->all();

if (!empty($testMos)) {
    ManufacturingOrder::withoutGlobalScopes()->withTrashed()->whereIn('id', $testMos)->forceDelete();
    echo "  - Deleted " . count($testMos) . " test MO records.\n";
}

// 5. Delete Production Plans linked to test SPKs or test plans
$testPlans = ProductionPlan::withoutGlobalScopes()->withTrashed()->where('plan_number', 'like', 'PLN-20261110-%')
    ->orWhere('name', 'like', '%Siklus C%')
    ->orWhere('name', 'like', '%BOM-C-%')
    ->pluck('id')->all();

if (!empty($testPlans)) {
    ProductionPlan::withoutGlobalScopes()->withTrashed()->whereIn('id', $testPlans)->forceDelete();
    echo "  - Deleted " . count($testPlans) . " test Production Plans.\n";
}

// 6. Delete test Bill of Materials (BOM)
$testBoms = BillOfMaterial::withoutGlobalScopes()->withTrashed()->where('code', 'like', 'BOM-C-%')
    ->orWhere('code', 'like', 'BOM-TEST-%')
    ->orWhere('nama_bom', 'like', '%Siklus C%')
    ->pluck('id')->all();

if (!empty($testBoms)) {
    BillOfMaterialItem::whereIn('bill_of_material_id', $testBoms)->delete();
    BillOfMaterial::withoutGlobalScopes()->withTrashed()->whereIn('id', $testBoms)->forceDelete();
    echo "  - Deleted " . count($testBoms) . " test BOM records.\n";
}

// 7. Ensure test products exist and have correct flags
$fg = Product::find(1);
if ($fg) {
    $fg->update([
        'is_manufacture' => true,
        'is_active' => true,
    ]);
}

$rm = Product::find(3);
if ($rm) {
    $rm->update([
        'is_raw_material' => true,
        'is_active' => true,
        'cost_price' => 180000,
    ]);
}

// 8. Reset Inventory Stocks
// Raw material Product 3 in Gudang Utama (Warehouse 1, Rak 1): 50 units
InventoryStock::withTrashed()->where('product_id', 3)->where('warehouse_id', 1)->forceDelete();
InventoryStock::create([
    'product_id' => 3,
    'warehouse_id' => 1,
    'rak_id' => 1,
    'qty_available' => 50,
    'qty_reserved' => 0,
    'qty_min' => 5,
]);

// Finished Goods Product 1 in Gudang Utama (Warehouse 1, Rak 1): baseline 10 units
InventoryStock::withTrashed()->where('product_id', 1)->where('warehouse_id', 1)->forceDelete();
InventoryStock::create([
    'product_id' => 1,
    'warehouse_id' => 1,
    'rak_id' => 1,
    'qty_available' => 10,
    'qty_reserved' => 0,
    'qty_min' => 2,
]);

DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "Reset completed successfully! Raw Material stock set to 50, Finished Good stock set to 10.\n";
