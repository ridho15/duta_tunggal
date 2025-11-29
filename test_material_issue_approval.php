<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING MATERIAL ISSUE APPROVAL FLOW ===\n";

// Gunakan Material Issue yang sudah ada atau buat baru
$materialIssueId = 16; // dari test sebelumnya
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
        return $stock;
    } else {
        echo "{$label}Stock tidak ditemukan\n";
        return null;
    }
}

// Fungsi untuk cek material issue items
function checkMaterialIssueItems($materialIssueId, $label = "") {
    $items = \App\Models\MaterialIssueItem::where('material_issue_id', $materialIssueId)->get();
    echo "{$label}Material Issue Items ({$items->count()} items):\n";
    foreach ($items as $item) {
        echo "  - Item ID: {$item->id}, Product: {$item->product_id}, Quantity: {$item->quantity}, Status: {$item->status}\n";
    }
    return $items;
}

// Reset ke kondisi awal
echo "\n=== RESET TO DRAFT ===\n";
$materialIssue->update(['status' => 'draft']);
echo "Status: {$materialIssue->status}\n";

$productId = $materialIssue->items->first()->product_id;
$warehouseId = $materialIssue->warehouse_id;

// Reset stock untuk testing
$stock = \App\Models\InventoryStock::where('product_id', $productId)
    ->where('warehouse_id', $warehouseId)
    ->first();
if ($stock) {
    $stock->update(['qty_available' => 100, 'qty_reserved' => 0]);
    echo "Stock reset to Available: 100, Reserved: 0\n";
}

// Hapus reservasi yang ada
\App\Models\StockReservation::where('material_issue_id', $materialIssueId)->delete();
echo "Deleted existing reservations\n";

checkStock($productId, $warehouseId, "[INITIAL] ");
checkMaterialIssueItems($materialIssueId, "[INITIAL] ");

echo "\n=== STEP 1: REQUEST APPROVAL (Draft -> Pending Approval) ===\n";
$materialIssue->update(['status' => 'pending_approval']);
echo "Status: {$materialIssue->status}\n";
checkStock($productId, $warehouseId, "[PENDING] ");
checkMaterialIssueItems($materialIssueId, "[PENDING] ");

echo "\n=== STEP 2: APPROVE MATERIAL ISSUE (Pending Approval -> Approved) ===\n";
$materialIssue->update([
    'status' => 'approved',
    'approved_at' => now(),
    'approved_by' => 3
]);
echo "Status: {$materialIssue->status}\n";
checkStock($productId, $warehouseId, "[APPROVED] ");
checkMaterialIssueItems($materialIssueId, "[APPROVED] ");

// Cek reservasi yang dibuat
$reservations = \App\Models\StockReservation::where('material_issue_id', $materialIssueId)->get();
echo "[APPROVED] Stock Reservations ({$reservations->count()} reservations):\n";
foreach ($reservations as $reservation) {
    echo "  - Reservation ID: {$reservation->id}, Product: {$reservation->product_id}, Quantity: {$reservation->quantity}, Warehouse: {$reservation->warehouse_id}\n";
}

echo "\n=== STEP 3: COMPLETE MATERIAL ISSUE (Approved -> Completed) ===\n";
$materialIssue->update(['status' => 'completed']);
echo "Status: {$materialIssue->status}\n";
checkStock($productId, $warehouseId, "[COMPLETED] ");
checkMaterialIssueItems($materialIssueId, "[COMPLETED] ");

// Cek reservasi setelah completed (harusnya sudah dihapus)
$reservationsAfter = \App\Models\StockReservation::where('material_issue_id', $materialIssueId)->get();
echo "[COMPLETED] Stock Reservations ({$reservationsAfter->count()} reservations):\n";

echo "\n=== TESTING COMPLETED ===\n";
echo "Final Status: {$materialIssue->status}\n";
echo "Final Stock - Available: " . checkStock($productId, $warehouseId, "[FINAL] ")->qty_available . "\n";