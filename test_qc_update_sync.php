<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== TESTING: JOURNAL ENTRIES SYNC WHEN QC PASSED_QUANTITY IS UPDATED ===' . PHP_EOL;

// Find a completed QC
$qc = \App\Models\QualityControl::where('status', 1)->first();
if (!$qc) {
    echo '❌ No completed QC found. Creating a test QC...' . PHP_EOL;

    // Create a test PO item first
    $po = \App\Models\PurchaseOrder::first();
    if ($po) {
        $poItem = \App\Models\PurchaseOrderItem::where('purchase_order_id', $po->id)->first();
        if (!$poItem) {
            $poItem = \App\Models\PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'product_id' => 1,
                'quantity' => 10,
                'unit_price' => 10000,
                'discount' => 0,
                'tax' => 0,
                'subtotal' => 100000,
                'currency_id' => 1
            ]);
        }

        // Create QC
        $qc = \App\Models\QualityControl::create([
            'qc_number' => 'QC-TEST-' . now()->format('YmdHis'),
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
            'status' => 1, // Already completed
            'from_model_type' => 'App\\Models\\PurchaseOrderItem',
            'from_model_id' => $poItem->id,
            'product_id' => 1,
            'warehouse_id' => 1
        ]);

        echo 'Created test QC: ' . $qc->qc_number . PHP_EOL;
    } else {
        echo '❌ No PO found to create test QC' . PHP_EOL;
        exit;
    }
}

echo 'Using QC: ' . $qc->qc_number . ' (ID: ' . $qc->id . ')' . PHP_EOL;
echo 'Current passed_quantity: ' . $qc->passed_quantity . PHP_EOL;
echo 'QC status: ' . $qc->status . ' (1 = completed)' . PHP_EOL;

// Check existing journal entries for this QC
$initialEntries = $qc->journalEntries()->count();
echo 'Current journal entries for this QC: ' . $initialEntries . PHP_EOL;

if ($initialEntries > 0) {
    $firstEntry = $qc->journalEntries()->first();
    echo 'Sample journal entry:' . PHP_EOL;
    echo '  - Debit: ' . $firstEntry->debit . PHP_EOL;
    echo '  - Credit: ' . $firstEntry->credit . PHP_EOL;
    echo '  - Reference: ' . $firstEntry->reference . PHP_EOL;
    $originalAmount = $firstEntry->debit > 0 ? $firstEntry->debit : $firstEntry->credit;
    echo '  - Amount: ' . $originalAmount . PHP_EOL;
}

// Update passed_quantity
$originalPassed = $qc->passed_quantity;
$newPassed = $originalPassed + 2; // Increase by 2
echo PHP_EOL . 'Updating passed_quantity from ' . $originalPassed . ' to ' . $newPassed . PHP_EOL;

$qc->update(['passed_quantity' => $newPassed]);
$qc->refresh();

echo 'QC passed_quantity after update: ' . $qc->passed_quantity . PHP_EOL;

// Check if journal entries were updated
$afterEntries = $qc->journalEntries()->count();
echo 'Journal entries count after update: ' . $afterEntries . PHP_EOL;

if ($initialEntries > 0 && $afterEntries > 0) {
    $firstEntry->refresh();
    $newAmount = $firstEntry->debit > 0 ? $firstEntry->debit : $firstEntry->credit;
    echo 'Journal entry amount after update: ' . $newAmount . PHP_EOL;

    $expectedAmount = $newPassed * ($qc->fromModel->unit_price ?? 10000); // Rough calculation
    echo 'Expected amount (passed_qty * unit_price): ' . $expectedAmount . PHP_EOL;

    if ($newAmount == $originalAmount) {
        echo '❌ JOURNAL ENTRIES DID NOT SYNC - Amount unchanged!' . PHP_EOL;
    } else {
        echo '✅ JOURNAL ENTRIES DID SYNC - Amount updated!' . PHP_EOL;
    }
}

echo PHP_EOL . 'CONCLUSION: ' . PHP_EOL;
if ($initialEntries == 0) {
    echo 'No journal entries exist for this QC (created before completion?)' . PHP_EOL;
} elseif ($afterEntries == $initialEntries) {
    echo 'Journal entries count unchanged, but amounts may or may not have synced' . PHP_EOL;
} else {
    echo 'Journal entries count changed unexpectedly' . PHP_EOL;
}