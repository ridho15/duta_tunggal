<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing StockReservationService Methods ===\n";

$materialIssueId = 2;
$materialIssue = \App\Models\MaterialIssue::find($materialIssueId);

if (!$materialIssue) {
    echo "Material Issue tidak ditemukan!\n";
    exit(1);
}

echo "Material Issue: {$materialIssue->issue_number} (Status: {$materialIssue->status})\n";

// Cek stock awal
$productId = $materialIssue->items->first()->product_id;
$warehouseId = $materialIssue->warehouse_id;
$stock = \App\Models\InventoryStock::where('product_id', $productId)
    ->where('warehouse_id', $warehouseId)
    ->first();

echo "Stock awal - Available: {$stock->qty_available}, Reserved: {$stock->qty_reserved}\n";

// Test reserve stock
echo "\n=== Testing Reserve Stock ===\n";
try {
    $stockReservationService = app(\App\Services\StockReservationService::class);
    $stockReservationService->reserveStockForMaterialIssue($materialIssue);
    echo "Reserve stock berhasil\n";

    $stock->refresh();
    echo "Stock setelah reserve - Available: {$stock->qty_available}, Reserved: {$stock->qty_reserved}\n";
} catch (\Exception $e) {
    echo "Error reserve stock: {$e->getMessage()}\n";
}

// Test consume stock
echo "\n=== Testing Consume Stock ===\n";
try {
    $stockReservationService->consumeReservedStockForMaterialIssue($materialIssue);
    echo "Consume stock berhasil\n";

    $stock->refresh();
    echo "Stock setelah consume - Available: {$stock->qty_available}, Reserved: {$stock->qty_reserved}\n";
} catch (\Exception $e) {
    echo "Error consume stock: {$e->getMessage()}\n";
}

echo "\n=== Testing Selesai ===\n";