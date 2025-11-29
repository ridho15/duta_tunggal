<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Membuat Test Data untuk Material Issue Reservation ===\n";

// Cari produk yang ada
$product = \App\Models\Product::where('is_active', true)->first();
if (!$product) {
    echo "Tidak ada produk aktif, membuat produk test...\n";
    $product = \App\Models\Product::create([
        'name' => 'Test Product for Material Issue',
        'code' => 'TEST-MI-001',
        'is_active' => true,
        'cost_price' => 10000,
        'is_manufacture' => true
    ]);
}
echo "Produk: {$product->name} (ID: {$product->id})\n";

// Cari warehouse
$warehouse = \App\Models\Warehouse::first();
if (!$warehouse) {
    echo "Tidak ada warehouse, membuat warehouse test...\n";
    $warehouse = \App\Models\Warehouse::create([
        'name' => 'Test Warehouse',
        'code' => 'TEST-WH'
    ]);
}
echo "Warehouse: {$warehouse->name} (ID: {$warehouse->id})\n";

// Buat atau update inventory stock
$inventoryStock = \App\Models\InventoryStock::where('product_id', $product->id)
    ->where('warehouse_id', $warehouse->id)
    ->first();

if (!$inventoryStock) {
    $inventoryStock = \App\Models\InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty_available' => 100,
        'qty_reserved' => 0,
        'qty_min' => 10
    ]);
    echo "Inventory stock dibuat\n";
} else {
    $inventoryStock->update(['qty_available' => 100, 'qty_reserved' => 0]);
    echo "Inventory stock diupdate\n";
}

echo "Stock awal - Available: {$inventoryStock->qty_available}, Reserved: {$inventoryStock->qty_reserved}\n";

// Buat production plan
$productionPlan = \App\Models\ProductionPlan::create([
    'plan_number' => 'TEST-PP-' . now()->format('YmdHis'),
    'name' => 'Test Production Plan for Material Issue',
    'source_type' => 'manual',
    'product_id' => $product->id,
    'quantity' => 10,
    'uom_id' => 1, // assuming UOM exists
    'start_date' => now(),
    'end_date' => now()->addDays(1),
    'status' => 'scheduled',
    'created_by' => 1 // assuming user exists
]);
echo "Production Plan dibuat: {$productionPlan->plan_number}\n";

// Buat material issue
$materialIssue = \App\Models\MaterialIssue::create([
    'issue_number' => 'TEST-MI-' . now()->format('YmdHis'),
    'production_plan_id' => $productionPlan->id,
    'warehouse_id' => $warehouse->id,
    'issue_date' => now(),
    'type' => 'issue',
    'status' => 'draft',
    'total_cost' => 50000
]);
echo "Material Issue dibuat: {$materialIssue->issue_number}\n";

// Buat material issue item
$materialIssueItem = \App\Models\MaterialIssueItem::create([
    'material_issue_id' => $materialIssue->id,
    'product_id' => $product->id,
    'uom_id' => 1, // assuming UOM exists
    'warehouse_id' => $warehouse->id,
    'quantity' => 5,
    'cost_per_unit' => 10000,
    'total_cost' => 50000,
    'status' => 'draft'
]);
echo "Material Issue Item dibuat\n";

echo "=== Test Data Siap ===\n";
echo "Material Issue ID: {$materialIssue->id}\n";
echo "Production Plan ID: {$productionPlan->id}\n";
echo "Product ID: {$product->id}\n";
echo "Warehouse ID: {$warehouse->id}\n";
echo "Inventory Stock ID: {$inventoryStock->id}\n";