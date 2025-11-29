<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Material Issue Stock Reservation ===\n";

// Gunakan Material Issue yang sudah dibuat
$materialIssueId = 2; // dari script sebelumnya
$materialIssue = \App\Models\MaterialIssue::find($materialIssueId);

if (!$materialIssue) {
    echo "Material Issue tidak ditemukan!\n";
    exit(1);
}

echo "Material Issue: {$materialIssue->issue_number} (ID: {$materialIssue->id})\n";
echo "Status awal: {$materialIssue->status}\n";

// Fungsi untuk cek stock
function checkStock($productId, $warehouseId) {
    $stock = \App\Models\InventoryStock::where('product_id', $productId)
        ->where('warehouse_id', $warehouseId)
        ->first();

    if ($stock) {
        echo "Stock - Available: {$stock->qty_available}, Reserved: {$stock->qty_reserved}\n";
        return $stock;
    } else {
        echo "Stock tidak ditemukan\n";
        return null;
    }
}

// Fungsi untuk cek reservasi
function checkReservations($materialIssueId) {
    $reservations = \App\Models\StockReservation::where('material_issue_id', $materialIssueId)->get();
    echo "Jumlah reservasi: {$reservations->count()}\n";
    foreach ($reservations as $reservation) {
        echo "  - Product ID: {$reservation->product_id}, Quantity: {$reservation->quantity}\n";
    }
    return $reservations;
}

echo "\n=== STEP 1: Cek Stock Awal ===\n";
$productId = $materialIssue->items->first()->product_id;
$warehouseId = $materialIssue->warehouse_id;
checkStock($productId, $warehouseId);
checkReservations($materialIssueId);

echo "\n=== STEP 2: Request Approval (Draft -> Pending Approval) ===\n";
// Simulasi request approval
$materialIssue->update(['status' => 'pending_approval']);
echo "Status setelah request approval: {$materialIssue->status}\n";
checkStock($productId, $warehouseId);
checkReservations($materialIssueId);

echo "\n=== STEP 3: Approve Material Issue (Pending Approval -> Approved) ===\n";
// Simulasi approve - ini akan trigger stock reservation
$materialIssue->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => 1]);
echo "Status setelah approve: {$materialIssue->status}\n";
checkStock($productId, $warehouseId);
checkReservations($materialIssueId);

echo "\n=== STEP 4: Complete Material Issue (Approved -> Completed) ===\n";
// Simulasi complete - ini akan consume reserved stock
$materialIssue->update(['status' => 'completed']);
echo "Status setelah complete: {$materialIssue->status}\n";
checkStock($productId, $warehouseId);
checkReservations($materialIssueId);

echo "\n=== Testing Selesai ===\n";
echo "Material Issue final status: {$materialIssue->status}\n";