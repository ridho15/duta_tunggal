<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING MATERIAL ISSUE STOCK RESERVATION FLOW ===\n";

// Gunakan Material Issue yang sudah direset
$materialIssueId = 3; // material issue yang sudah direset ke draft
$materialIssue = \App\Models\MaterialIssue::find($materialIssueId);

if (!$materialIssue) {
    echo "Material Issue tidak ditemukan!\n";
    exit(1);
}

echo "Material Issue: {$materialIssue->issue_number} (ID: {$materialIssue->id})\n";

// Fungsi untuk cek stock
function checkStock($productId, $warehouseId, $label = "") {
    $stock = \App\Models\InventoryStock::where('product_id', $productId)
        ->where('warehouse_id', $warehouseId)
        ->first();

    if ($stock) {
        echo "{$label}Stock - Available: {$stock->qty_available}, Reserved: {$stock->qty_reserved}\n";
        
        // Double-check by querying fresh
        $freshStock = \App\Models\InventoryStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();
        echo "{$label}Fresh Stock - Available: {$freshStock->qty_available}, Reserved: {$freshStock->qty_reserved}\n";
        
        return $stock;
    } else {
        echo "{$label}Stock tidak ditemukan\n";
        return null;
    }
}

// Fungsi untuk cek reservasi
function checkReservations($materialIssueId, $label = "") {
    $reservations = \App\Models\StockReservation::where('material_issue_id', $materialIssueId)->get();
    echo "{$label}Jumlah reservasi: {$reservations->count()}\n";
    foreach ($reservations as $reservation) {
        echo "  - Product ID: {$reservation->product_id}, Quantity: {$reservation->quantity}, Warehouse: {$reservation->warehouse_id}\n";
    }
    return $reservations;
}

// Fungsi untuk reset stock
function resetStock($productId, $warehouseId) {
    $stock = \App\Models\InventoryStock::where('product_id', $productId)
        ->where('warehouse_id', $warehouseId)
        ->first();
    
    if ($stock) {
        $stock->update(['qty_available' => 100, 'qty_reserved' => 0]);
        echo "Stock reset to Available: 100, Reserved: 0\n";
    }
}

// Fungsi untuk reset material issue
function resetMaterialIssue($materialIssueId) {
    $mi = \App\Models\MaterialIssue::find($materialIssueId);
    if ($mi) {
        $mi->update(['status' => 'draft']);
        \App\Models\StockReservation::where('material_issue_id', $materialIssueId)->delete();
        echo "Material Issue reset to draft, reservations deleted\n";
    }
}

$productId = $materialIssue->items->first()->product_id;
$warehouseId = $materialIssue->warehouse_id;

// Reset everything
resetStock($productId, $warehouseId);
resetMaterialIssue($materialIssueId);

// Check stock right after reset
echo "After reset:\n";
checkStock($productId, $warehouseId, "[AFTER_RESET] ");

echo "\n=== STEP 0: Kondisi Awal ===\n";
checkStock($productId, $warehouseId, "[AWAL] ");
checkReservations($materialIssueId, "[AWAL] ");

echo "\n=== STEP 1: Request Approval (Draft -> Pending Approval) ===\n";
$materialIssue->status = 'pending_approval';
$materialIssue->save();
echo "Status: {$materialIssue->status}\n";
checkStock($productId, $warehouseId, "[PENDING] ");
checkReservations($materialIssueId, "[PENDING] ");

echo "\n=== STEP 2: Approve Material Issue (Pending Approval -> Approved) ===\n";
$materialIssue->status = 'approved';
$materialIssue->approved_at = now();
$materialIssue->approved_by = 1;
$materialIssue->save();
echo "Status: {$materialIssue->status}\n";
checkStock($productId, $warehouseId, "[APPROVED] ");
checkReservations($materialIssueId, "[APPROVED] ");

echo "\n=== STEP 3: Complete Material Issue (Approved -> Completed) ===\n";
$materialIssue->status = 'completed';
$materialIssue->save();
echo "Status: {$materialIssue->status}\n";
checkStock($productId, $warehouseId, "[COMPLETED] ");
checkReservations($materialIssueId, "[COMPLETED] ");

echo "\n=== TESTING SELESAI ===\n";
echo "Final Status: {$materialIssue->status}\n";
echo "Stock akhir - Available: " . checkStock($productId, $warehouseId, "[FINAL] ")->qty_available . "\n";