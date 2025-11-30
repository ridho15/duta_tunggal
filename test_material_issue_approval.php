<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\InventoryStock;
use App\Models\StockReservation;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing MaterialIssue Approval Logic (Full Issue)\n";
echo "=================================================\n";

try {
    // Find a material issue with items in draft status
    $materialIssue = MaterialIssue::with(['items.product', 'items.warehouse'])
        ->where('status', 'draft')
        ->first();

    if (!$materialIssue) {
        echo "❌ No draft MaterialIssue found\n";
        exit(1);
    }

    echo "Found MaterialIssue: {$materialIssue->issue_number}\n";
    echo "Status: {$materialIssue->status}\n";
    echo "Items count: {$materialIssue->items->count()}\n\n";

    // Check inventory stock for all items before approval
    echo "BEFORE MATERIAL ISSUE APPROVAL:\n";
    foreach ($materialIssue->items as $item) {
        $inventoryStock = InventoryStock::where('product_id', $item->product_id)
            ->where('warehouse_id', $item->warehouse_id ?? $materialIssue->warehouse_id)
            ->first();

        if ($inventoryStock) {
            echo "- {$item->product->name}: available={$inventoryStock->qty_available}, reserved={$inventoryStock->qty_reserved}\n";
        }
    }
    echo "\n";

    // Check existing reservations
    $existingReservations = StockReservation::where('material_issue_id', $materialIssue->id)->count();
    echo "Existing reservations for this material issue: {$existingReservations}\n\n";

    // Approve the entire material issue
    echo "Approving entire MaterialIssue...\n";
    $materialIssue->update([
        'status' => MaterialIssue::STATUS_APPROVED,
        'approved_by' => 1,
        'approved_at' => now(),
    ]);

    echo "✅ MaterialIssue approved successfully\n";
    echo "New status: {$materialIssue->status}\n\n";

    // Check inventory stock after approval
    echo "AFTER MATERIAL ISSUE APPROVAL:\n";
    foreach ($materialIssue->items as $item) {
        $inventoryStock = InventoryStock::where('product_id', $item->product_id)
            ->where('warehouse_id', $item->warehouse_id ?? $materialIssue->warehouse_id)
            ->first();

        if ($inventoryStock) {
            echo "- {$item->product->name}: available={$inventoryStock->qty_available}, reserved={$inventoryStock->qty_reserved}\n";
        }
    }
    echo "\n";

    // Check reservations after approval
    $reservationsAfter = StockReservation::where('material_issue_id', $materialIssue->id)->get();
    echo "Reservations created: {$reservationsAfter->count()}\n";
    foreach ($reservationsAfter as $reservation) {
        $product = \App\Models\Product::find($reservation->product_id);
        echo "- {$product->name}: {$reservation->quantity} units\n";
    }
    echo "\n";

    // Verify the logic
    $totalReserved = $reservationsAfter->sum('quantity');
    $expectedReserved = $materialIssue->items->sum('quantity');

    echo "VERIFICATION:\n";
    echo "- Total quantity in items: {$expectedReserved}\n";
    echo "- Total quantity reserved: {$totalReserved}\n";

    if ($totalReserved == $expectedReserved) {
        echo "✅ Stock reservation logic works correctly at MaterialIssue level\n";
        echo "✅ Quantity available should decrease and quantity reserved should increase when items are completed\n";
    } else {
        echo "❌ Stock reservation logic has issues\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nTest completed!\n";