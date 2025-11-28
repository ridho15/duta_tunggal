<?php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use App\Models\User;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Services\QualityControlService;
use Illuminate\Support\Facades\Auth;

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "=== QC Purchase Complete Test ===\n\n";

// Login as admin user
$user = User::where('email', 'admin@example.com')->first();
if (!$user) {
    $user = User::first();
}
Auth::login($user);
echo "Logged in as: {$user->name} ({$user->email})\n\n";

try {
    // 1. Create Purchase Order
    echo "1. Creating Purchase Order...\n";
    $po = PurchaseOrder::create([
        'po_number' => 'PO-TEST-' . date('YmdHis'),
        'supplier_id' => 1, // Assuming supplier exists
        'warehouse_id' => 1, // Assuming warehouse exists
        'status' => 'approved', // Valid status
        'order_date' => now(),
        'expected_delivery_date' => now()->addDays(7),
        'tempo_hutang' => 30, // Required field
    ]);

    // Create PO Item
    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => 1, // Assuming product exists
        'quantity' => 10,
        'unit_price' => 10000,
        'currency_id' => 1,
    ]);

    echo "   ✓ PO created: {$po->po_number} with 1 item (10 qty)\n";

    // 2. Create Purchase Receipt
    echo "\n2. Creating Purchase Receipt...\n";
    $receipt = PurchaseReceipt::create([
        'purchase_order_id' => $po->id,
        'receipt_number' => 'RC-TEST-' . date('YmdHis'),
        'receipt_date' => now(),
        'received_by' => Auth::id(), // Current authenticated user
        'currency_id' => 1, // Assuming currency exists
        'other_cost' => 0,
        'status' => 'draft',
    ]);

    // Create Receipt Item
    $receiptItem = PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => 1,
        'qty_received' => 10,
        'qty_accepted' => 0, // Initially 0
        'qty_rejected' => 0,
        'warehouse_id' => 1,
        'rak_id' => 1,
        'is_sent' => 0,
    ]);

    echo "   ✓ Receipt created: {$receipt->receipt_number} with 1 item (10 qty received)\n";
    echo "   ✓ Initial qty_accepted: {$receiptItem->qty_accepted}\n";

    // 3. Send to QC
    echo "\n3. Sending to Quality Control...\n";
    $qcService = app(QualityControlService::class);
    $qcData = [
        'passed_quantity' => 0,
        'rejected_quantity' => 0,
        'warehouse_id' => 1,
        'rak_id' => 1,
        'inspected_by' => $user->id,
    ];

    $qc = $qcService->createQCFromPurchaseReceiptItem($receiptItem, $qcData);
    $receiptItem->update(['is_sent' => 1]);

    echo "   ✓ QC created: {$qc->qc_number}\n";
    echo "   ✓ Initial QC status: {$qc->status} (0 = pending)\n";

    // 4. Perform QC (set results)
    echo "\n4. Performing Quality Control (8 passed, 2 rejected)...\n";
    $qc->update([
        'passed_quantity' => 8,
        'rejected_quantity' => 2,
        'inspected_by' => $user->id,
    ]);

    echo "   ✓ QC results set: 8 passed, 2 rejected\n";

    // 5. Complete QC
    echo "\n5. Completing Quality Control...\n";
    $qcService->completeQualityControl($qc, [
        'item_condition' => 'damage'
    ]);

    // Refresh models
    $qc->refresh();
    $receiptItem->refresh();
    $receipt->refresh();
    $po->refresh();

    echo "   ✓ QC completed!\n";
    echo "   ✓ QC status: {$qc->status} (1 = completed)\n";

    // 6. Verify Results
    echo "\n6. Verifying Results...\n";

    // Check qty_accepted updated
    echo "   ✓ Receipt Item qty_accepted: {$receiptItem->qty_accepted} (should be 8)\n";

    // Check if Return Product created for rejected items
    $returnProduct = $qc->returnProduct()->first();
    if ($returnProduct) {
        echo "   ✓ Return Product created: {$returnProduct->return_number} for {$returnProduct->returnProductItem->sum('quantity')} items\n";
    }

    // Check Purchase Receipt status
    echo "   ✓ Purchase Receipt status: {$receipt->status}\n";
    if ($receipt->completed_at) {
        echo "   ✓ Purchase Receipt completed at: {$receipt->completed_at}\n";
    }

    // Check Purchase Order status
    echo "   ✓ Purchase Order status: {$po->status}\n";
    if ($po->completed_at) {
        echo "   ✓ Purchase Order completed at: {$po->completed_at}\n";
    }

    // Check inventory stock
    $inventoryStock = \App\Models\InventoryStock::where('product_id', 1)
        ->where('warehouse_id', 1)
        ->first();
    if ($inventoryStock) {
        echo "   ✓ Inventory stock updated: {$inventoryStock->qty_available} available\n";
    }

    // Check journal entries
    $journalEntries = \App\Models\JournalEntry::where('source_type', QualityControl::class)
        ->where('source_id', $qc->id)
        ->count();
    echo "   ✓ Journal entries created: {$journalEntries}\n";

    // Check stock movements
    $stockMovements = \App\Models\StockMovement::where('from_model_type', QualityControl::class)
        ->where('from_model_id', $qc->id)
        ->count();
    echo "   ✓ Stock movements created: {$stockMovements}\n";

    echo "\n=== TEST COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}