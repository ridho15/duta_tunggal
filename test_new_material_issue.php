<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Membuat Test Data Baru untuk Material Issue Reservation ===\n";

// Reset stock untuk test
$productId = 5;
$warehouseId = 3; // menggunakan warehouse yang ada
$inventoryStock = \App\Models\InventoryStock::where('product_id', $productId)
    ->where('warehouse_id', $warehouseId)
    ->first();

if ($inventoryStock) {
    $inventoryStock->update(['qty_available' => 100, 'qty_reserved' => 0]);
    echo "Stock direset - Available: {$inventoryStock->qty_available}, Reserved: {$inventoryStock->qty_reserved}\n";
} else {
    // Create stock if not exists
    $inventoryStock = \App\Models\InventoryStock::create([
        'product_id' => $productId,
        'warehouse_id' => $warehouseId,
        'rak_id' => null,
        'qty_available' => 100,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);
    echo "Stock dibuat - Available: {$inventoryStock->qty_available}, Reserved: {$inventoryStock->qty_reserved}\n";
}

// Hapus stock reservations yang ada
\App\Models\StockReservation::where('material_issue_id', '>', 0)->delete();
echo "Stock reservations dihapus\n";

// Buat material issue baru
$materialIssue = \App\Models\MaterialIssue::create([
    'issue_number' => 'TEST-MI-' . now()->format('YmdHis'),
    'production_plan_id' => null, // tidak ada production plan
    'warehouse_id' => $warehouseId,
    'issue_date' => now(),
    'type' => 'issue',
    'status' => 'draft',
    'total_cost' => 50000
]);
echo "Material Issue baru dibuat: {$materialIssue->issue_number} (ID: {$materialIssue->id})\n";

// Buat material issue item
$materialIssueItem = \App\Models\MaterialIssueItem::create([
    'material_issue_id' => $materialIssue->id,
    'product_id' => $productId,
    'uom_id' => 3,
    'warehouse_id' => $warehouseId,
    'quantity' => 5,
    'cost_per_unit' => 10000,
    'total_cost' => 50000,
    'status' => 'draft'
]);
echo "Material Issue Item dibuat\n";

echo "=== Test Data Baru Siap ===\n";
echo "Material Issue ID: {$materialIssue->id}\n";
echo "Product ID: {$productId}\n";
echo "Warehouse ID: {$warehouseId}\n";