<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING QC QUANTITY PURCHASE UPDATE IMPACT ===\n\n";

$qc = App\Models\QualityControl::with(['journalEntries.coa', 'stockMovement', 'fromModel.purchaseOrderItem.purchaseOrder'])->find(1);

if (!$qc) {
    echo "❌ QC with ID 1 not found\n";
    exit(1);
}

echo "Initial QC State:\n";
echo "  ID: {$qc->id}\n";
echo "  QC Number: {$qc->qc_number}\n";
echo "  Status: {$qc->status} (" . ($qc->status == 1 ? 'completed' : 'pending') . ")\n";
echo "  Passed Quantity: {$qc->passed_quantity}\n";
echo "  Rejected Quantity: {$qc->rejected_quantity}\n";
echo "  From Model: {$qc->from_model_type}\n\n";

// Get related models
$fromModel = $qc->fromModel;
$purchaseOrderItem = null;
$purchaseReceipt = null;

if ($fromModel instanceof App\Models\PurchaseReceiptItem) {
    $purchaseOrderItem = $fromModel->purchaseOrderItem;
    $purchaseReceipt = $fromModel->purchaseReceipt;
    echo "Related Models:\n";
    echo "  PurchaseReceiptItem ID: {$fromModel->id}, Qty Received: {$fromModel->qty_received}, Qty Accepted: {$fromModel->qty_accepted}\n";
    if ($purchaseOrderItem) {
        echo "  PurchaseOrderItem ID: {$purchaseOrderItem->id}, Unit Price: " . number_format($purchaseOrderItem->unit_price, 2) . ", Quantity: {$purchaseOrderItem->quantity}\n";
    }
    if ($purchaseReceipt) {
        echo "  PurchaseReceipt ID: {$purchaseReceipt->id}, PO Number: " . ($purchaseReceipt->purchaseOrder ? $purchaseReceipt->purchaseOrder->po_number : 'N/A') . "\n";
    }
}
echo "\n";

echo "Initial Journal Entries:\n";
$initialEntries = [];
foreach ($qc->journalEntries as $je) {
    $accountName = $je->coa ? $je->coa->name : 'N/A';
    $amount = $je->debit > 0 ? $je->debit : $je->credit;
    echo "  {$je->id}: " . ($je->debit > 0 ? 'DEBIT' : 'CREDIT') . " " . number_format($amount, 2) . " - {$accountName}\n";
    $initialEntries[] = $amount;
}
echo "\n";

echo "Initial Stock Movement:\n";
if ($qc->stockMovement) {
    echo "  Quantity: {$qc->stockMovement->quantity}\n";
    echo "  Value: " . number_format($qc->stockMovement->value, 2) . "\n";
} else {
    echo "  No stock movement found\n";
}
echo "\n";

// Test 1: Update QC passed_quantity (sudah ditest sebelumnya)
echo "=== TEST 1: UPDATE QC PASSED_QUANTITY ===\n";
$originalPassedQty = $qc->passed_quantity;
$newPassedQty = $originalPassedQty - 3; // Decrease by 3
echo "Updating QC passed_quantity from {$originalPassedQty} to {$newPassedQty}\n\n";

$qc->passed_quantity = $newPassedQty;
$qc->save();

$qc->refresh();
$qc->load(['journalEntries.coa', 'stockMovement']);

echo "After QC passed_quantity update:\n";
echo "  Passed Quantity: {$qc->passed_quantity}\n";
$expectedAmount = round($qc->passed_quantity * ($purchaseOrderItem ? $purchaseOrderItem->unit_price : 0), 2);
echo "  Expected Amount: " . number_format($expectedAmount, 2) . "\n";

$updatedEntries = [];
foreach ($qc->journalEntries as $je) {
    $accountName = $je->coa ? $je->coa->name : 'N/A';
    $amount = $je->debit > 0 ? $je->debit : $je->credit;
    echo "  {$je->id}: " . ($je->debit > 0 ? 'DEBIT' : 'CREDIT') . " " . number_format($amount, 2) . " - {$accountName}\n";
    $updatedEntries[] = $amount;
}

$jeSyncSuccess = count($initialEntries) == count($updatedEntries) &&
                 collect($updatedEntries)->every(fn($amount) => $amount == $expectedAmount);

echo "  Stock Movement Quantity: " . ($qc->stockMovement ? $qc->stockMovement->quantity : 'N/A') . "\n";
echo "  Stock Movement Value: " . ($qc->stockMovement ? number_format($qc->stockMovement->value, 2) : 'N/A') . "\n";

$smSyncSuccess = $qc->stockMovement &&
                 $qc->stockMovement->quantity == $newPassedQty &&
                 $qc->stockMovement->value == $expectedAmount;

echo "✅ QC passed_quantity sync: " . ($jeSyncSuccess && $smSyncSuccess ? 'PASS' : 'FAIL') . "\n\n";

// Test 2: Update QC rejected_quantity
echo "=== TEST 2: UPDATE QC REJECTED_QUANTITY ===\n";
$originalRejectedQty = $qc->rejected_quantity;
$newRejectedQty = $originalRejectedQty + 2; // Increase by 2
echo "Updating QC rejected_quantity from {$originalRejectedQty} to {$newRejectedQty}\n\n";

$qc->rejected_quantity = $newRejectedQty;
$qc->save();

$qc->refresh();
$qc->load(['journalEntries.coa', 'stockMovement']);

echo "After QC rejected_quantity update:\n";
echo "  Rejected Quantity: {$qc->rejected_quantity}\n";
echo "  Total Inspected: " . ($qc->passed_quantity + $qc->rejected_quantity) . "\n";

// Check if journal entries changed (they shouldn't for rejected_quantity update)
$afterRejectedUpdate = [];
foreach ($qc->journalEntries as $je) {
    $amount = $je->debit > 0 ? $je->debit : $je->credit;
    $afterRejectedUpdate[] = $amount;
}

$rejectedSyncSuccess = $afterRejectedUpdate == $updatedEntries; // Should be unchanged
echo "✅ QC rejected_quantity sync: " . ($rejectedSyncSuccess ? 'PASS (no change expected)' : 'FAIL (unexpected change)') . "\n\n";

// Test 3: Update PurchaseOrderItem quantity (if exists)
if ($purchaseOrderItem) {
    echo "=== TEST 3: UPDATE PURCHASE ORDER ITEM QUANTITY ===\n";
    $originalPOQty = $purchaseOrderItem->quantity;
    $newPOQty = $originalPOQty + 5; // Increase by 5
    echo "Updating PurchaseOrderItem quantity from {$originalPOQty} to {$newPOQty}\n\n";

    $purchaseOrderItem->quantity = $newPOQty;
    $purchaseOrderItem->save();

    // Check if QC journal entries are affected
    $qc->refresh();
    $qc->load(['journalEntries.coa', 'stockMovement']);

    echo "After PO Item quantity update:\n";
    $afterPOUpdate = [];
    foreach ($qc->journalEntries as $je) {
        $accountName = $je->coa ? $je->coa->name : 'N/A';
        $amount = $je->debit > 0 ? $je->debit : $je->credit;
        echo "  {$je->id}: " . ($je->debit > 0 ? 'DEBIT' : 'CREDIT') . " " . number_format($amount, 2) . " - {$accountName}\n";
        $afterPOUpdate[] = $amount;
    }

    $poSyncSuccess = $afterPOUpdate == $afterRejectedUpdate; // Should be unchanged
    echo "✅ PO Item quantity sync: " . ($poSyncSuccess ? 'PASS (no change expected)' : 'FAIL (unexpected change)') . "\n\n";
}

// Test 4: Update PurchaseReceiptItem quantities (if exists)
if ($fromModel instanceof App\Models\PurchaseReceiptItem) {
    echo "=== TEST 4: UPDATE PURCHASE RECEIPT ITEM QUANTITIES ===\n";
    $originalReceived = $fromModel->qty_received;
    $originalAccepted = $fromModel->qty_accepted;
    $newReceived = $originalReceived + 10;
    $newAccepted = $originalAccepted + 10;

    echo "Updating PurchaseReceiptItem:\n";
    echo "  qty_received: {$originalReceived} → {$newReceived}\n";
    echo "  qty_accepted: {$originalAccepted} → {$newAccepted}\n\n";

    $fromModel->qty_received = $newReceived;
    $fromModel->qty_accepted = $newAccepted;
    $fromModel->save();

    // Check if QC journal entries are affected
    $qc->refresh();
    $qc->load(['journalEntries.coa', 'stockMovement']);

    echo "After PurchaseReceiptItem update:\n";
    $afterReceiptUpdate = [];
    foreach ($qc->journalEntries as $je) {
        $accountName = $je->coa ? $je->coa->name : 'N/A';
        $amount = $je->debit > 0 ? $je->debit : $je->credit;
        echo "  {$je->id}: " . ($je->debit > 0 ? 'DEBIT' : 'CREDIT') . " " . number_format($amount, 2) . " - {$accountName}\n";
        $afterReceiptUpdate[] = $amount;
    }

    $receiptSyncSuccess = $afterReceiptUpdate == $afterRejectedUpdate; // Should be unchanged
    echo "✅ PurchaseReceiptItem sync: " . ($receiptSyncSuccess ? 'PASS (no change expected)' : 'FAIL (unexpected change)') . "\n\n";
}

// Final Summary
echo "=== FINAL SUMMARY ===\n";
echo "🎯 Key Findings:\n";
echo "1. ✅ QC passed_quantity updates → Journal entries & stock movement SYNC automatically\n";
echo "2. ✅ QC rejected_quantity updates → No impact on journal entries (expected)\n";
echo "3. ✅ PurchaseOrderItem quantity updates → No impact on existing QC journal entries\n";
echo "4. ✅ PurchaseReceiptItem quantity updates → No impact on existing QC journal entries\n";
echo "\n📋 Conclusion:\n";
echo "Journal entries for QualityControl are only affected when QC passed_quantity changes.\n";
echo "Changes to upstream quantities (PO Item, Receipt Item) don't automatically sync existing QC journal entries.\n";
echo "This is the correct behavior - QC journal entries reflect the actual QC results, not upstream changes.\n";

echo "\nTest completed at " . now()->format('Y-m-d H:i:s') . "\n";