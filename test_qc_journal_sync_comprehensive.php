<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== COMPREHENSIVE QC JOURNAL SYNC TEST ===\n\n";

$qc = App\Models\QualityControl::with(['journalEntries.coa', 'stockMovement', 'fromModel.purchaseOrderItem'])->find(1);

if (!$qc) {
    echo "❌ QC with ID 1 not found\n";
    exit(1);
}

echo "Initial QC State:\n";
echo "  ID: {$qc->id}\n";
echo "  QC Number: {$qc->qc_number}\n";
echo "  Status: {$qc->status} (" . ($qc->status == 1 ? 'completed' : 'pending') . ")\n";
echo "  Passed Quantity: {$qc->passed_quantity}\n";
echo "  From Model: {$qc->from_model_type}\n\n";

// Get unit price
$unitPrice = 0;
if ($qc->fromModel && $qc->fromModel->purchaseOrderItem) {
    $unitPrice = $qc->fromModel->purchaseOrderItem->unit_price;
}
echo "Unit Price: " . number_format($unitPrice, 2) . "\n";
$expectedAmount = round($qc->passed_quantity * $unitPrice, 2);
echo "Expected Amount (passed_qty * unit_price): " . number_format($expectedAmount, 2) . "\n\n";

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

// Test update passed_quantity
$newPassedQuantity = $qc->passed_quantity + 2; // Increase by 2
echo "=== UPDATING PASSED QUANTITY ===\n";
echo "Updating passed_quantity from {$qc->passed_quantity} to {$newPassedQuantity}\n\n";

$qc->passed_quantity = $newPassedQuantity;
$qc->save();

$qc->refresh();
$qc->load(['journalEntries.coa', 'stockMovement']);

echo "Updated QC State:\n";
echo "  Passed Quantity: {$qc->passed_quantity}\n";
$newExpectedAmount = round($qc->passed_quantity * $unitPrice, 2);
echo "Expected Amount: " . number_format($newExpectedAmount, 2) . "\n\n";

echo "Updated Journal Entries:\n";
$updatedEntries = [];
foreach ($qc->journalEntries as $je) {
    $accountName = $je->coa ? $je->coa->name : 'N/A';
    $amount = $je->debit > 0 ? $je->debit : $je->credit;
    echo "  {$je->id}: " . ($je->debit > 0 ? 'DEBIT' : 'CREDIT') . " " . number_format($amount, 2) . " - {$accountName}\n";
    $updatedEntries[] = $amount;
}
echo "\n";

echo "Updated Stock Movement:\n";
if ($qc->stockMovement) {
    echo "  Quantity: {$qc->stockMovement->quantity}\n";
    echo "  Value: " . number_format($qc->stockMovement->value, 2) . "\n";
} else {
    echo "  No stock movement found\n";
}
echo "\n";

// Verification
echo "=== VERIFICATION ===\n";

$journalSyncSuccess = true;
$stockMovementSyncSuccess = true;

if (count($initialEntries) !== count($updatedEntries)) {
    echo "❌ Journal entries count changed (was " . count($initialEntries) . ", now " . count($updatedEntries) . ")\n";
    $journalSyncSuccess = false;
} else {
    echo "✅ Journal entries count unchanged (" . count($updatedEntries) . " entries)\n";
}

foreach ($updatedEntries as $amount) {
    if ($amount != $newExpectedAmount) {
        echo "❌ Journal entry amount mismatch: expected " . number_format($newExpectedAmount, 2) . ", got " . number_format($amount, 2) . "\n";
        $journalSyncSuccess = false;
        break;
    }
}

if ($journalSyncSuccess && count($updatedEntries) > 0) {
    echo "✅ All journal entries amounts updated correctly to " . number_format($newExpectedAmount, 2) . "\n";
}

if ($qc->stockMovement) {
    if ($qc->stockMovement->quantity != $newPassedQuantity) {
        echo "❌ Stock movement quantity mismatch: expected {$newPassedQuantity}, got {$qc->stockMovement->quantity}\n";
        $stockMovementSyncSuccess = false;
    } else {
        echo "✅ Stock movement quantity updated correctly to {$newPassedQuantity}\n";
    }

    if ($qc->stockMovement->value != $newExpectedAmount) {
        echo "❌ Stock movement value mismatch: expected " . number_format($newExpectedAmount, 2) . ", got " . number_format($qc->stockMovement->value, 2) . "\n";
        $stockMovementSyncSuccess = false;
    } else {
        echo "✅ Stock movement value updated correctly to " . number_format($newExpectedAmount, 2) . "\n";
    }
} else {
    echo "⚠️  No stock movement to verify\n";
}

echo "\n=== FINAL RESULT ===\n";
if ($journalSyncSuccess && $stockMovementSyncSuccess) {
    echo "🎉 SUCCESS: All journal entries and stock movements synced correctly!\n";
    echo "   - Journal entries amounts updated from " . number_format($expectedAmount, 2) . " to " . number_format($newExpectedAmount, 2) . "\n";
    echo "   - Stock movement quantity updated from {$qc->getOriginal('passed_quantity')} to {$newPassedQuantity}\n";
    echo "   - Stock movement value updated accordingly\n";
} else {
    echo "❌ FAILURE: Some sync operations failed\n";
    if (!$journalSyncSuccess) echo "   - Journal entries sync failed\n";
    if (!$stockMovementSyncSuccess) echo "   - Stock movement sync failed\n";
}

echo "\nTest completed at " . now()->format('Y-m-d H:i:s') . "\n";