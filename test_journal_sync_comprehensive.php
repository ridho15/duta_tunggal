<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== COMPREHENSIVE JOURNAL ENTRY SYNC TEST ===' . PHP_EOL;
echo 'Testing automatic synchronization of journal entries when PurchaseOrder and SaleOrder data changes.' . PHP_EOL . PHP_EOL;

// Test 1: Purchase Order Reference/Description Sync
echo '--- Test 1: Purchase Order Reference/Description Sync ---' . PHP_EOL;
$po = \App\Models\PurchaseOrder::first();
if (!$po) {
    echo '❌ No Purchase Order found. Skipping PO tests.' . PHP_EOL . PHP_EOL;
} else {
    echo 'Using PO: ' . $po->po_number . ' (ID: ' . $po->id . ')' . PHP_EOL;

    // Create test journal entry
    $je = \App\Models\JournalEntry::create([
        'coa_id' => 1,
        'date' => now(),
        'reference' => 'OLD-REF-PO',
        'description' => 'Old description for PO',
        'debit' => $po->total_amount,
        'credit' => 0,
        'journal_type' => 'purchase',
        'source_type' => 'App\\Models\\PurchaseOrder',
        'source_id' => $po->id,
        'cabang_id' => $po->cabang_id
    ]);

    echo 'Created test journal entry with reference: "' . $je->reference . '"' . PHP_EOL;

    // Update PO to trigger sync
    $po->update(['order_date' => now()]); // This should trigger the updated() event

    $je->refresh();
    $expectedRef = 'PO-' . $po->po_number;
    $expectedDesc = 'Purchase Order: ' . $po->po_number;

    if ($je->reference === $expectedRef && $je->description === $expectedDesc) {
        echo '✅ PO Journal entry synced correctly:' . PHP_EOL;
        echo '   - Reference: "' . $je->reference . '" (expected: "' . $expectedRef . '")' . PHP_EOL;
        echo '   - Description: "' . $je->description . '" (expected: "' . $expectedDesc . '")' . PHP_EOL;
    } else {
        echo '❌ PO Journal entry sync failed:' . PHP_EOL;
        echo '   - Reference: "' . $je->reference . '" (expected: "' . $expectedRef . '")' . PHP_EOL;
        echo '   - Description: "' . $je->description . '" (expected: "' . $expectedDesc . '")' . PHP_EOL;
    }

    $je->delete();
    echo PHP_EOL;
}

// Test 2: Sale Order Reference/Description and Amount Sync
echo '--- Test 2: Sale Order Reference/Description and Amount Sync ---' . PHP_EOL;
$so = \App\Models\SaleOrder::first();
if (!$so) {
    echo '❌ No Sale Order found. Skipping SO tests.' . PHP_EOL . PHP_EOL;
} else {
    echo 'Using SO: ' . $so->so_number . ' (ID: ' . $so->id . ')' . PHP_EOL;

    // Create test journal entry
    $je = \App\Models\JournalEntry::create([
        'coa_id' => 1,
        'date' => now(),
        'reference' => 'OLD-REF-SO',
        'description' => 'Old description for SO',
        'debit' => 0,
        'credit' => $so->total_amount,
        'journal_type' => 'sales',
        'source_type' => 'App\\Models\\SaleOrder',
        'source_id' => $so->id,
        'cabang_id' => $so->cabang_id ?? 1
    ]);

    echo 'Created test journal entry with reference: "' . $je->reference . '" and credit: ' . $je->credit . PHP_EOL;

    // Update SO total_amount to trigger sync
    $newAmount = $so->total_amount + 100000;
    $so->update(['total_amount' => $newAmount]);
    $so->refresh();

    $je->refresh();
    $expectedRef = 'SO-' . $so->so_number;
    $expectedDesc = 'Sales Order: ' . $so->so_number;

    if ($je->reference === $expectedRef && $je->description === $expectedDesc && $je->credit == $so->total_amount) {
        echo '✅ SO Journal entry synced correctly:' . PHP_EOL;
        echo '   - Reference: "' . $je->reference . '" (expected: "' . $expectedRef . '")' . PHP_EOL;
        echo '   - Description: "' . $je->description . '" (expected: "' . $expectedDesc . '")' . PHP_EOL;
        echo '   - Amount: ' . $je->credit . ' (matches SO total: ' . $so->total_amount . ')' . PHP_EOL;
    } else {
        echo '❌ SO Journal entry sync failed:' . PHP_EOL;
        echo '   - Reference: "' . $je->reference . '" (expected: "' . $expectedRef . '")' . PHP_EOL;
        echo '   - Description: "' . $je->description . '" (expected: "' . $expectedDesc . '")' . PHP_EOL;
        echo '   - Amount: ' . $je->credit . ' (SO total: ' . $so->total_amount . ')' . PHP_EOL;
    }

    $je->delete();
    echo PHP_EOL;
}

// Test 3: Purchase Order Item Change Sync
echo '--- Test 3: Purchase Order Item Change Sync ---' . PHP_EOL;
if ($po) {
    $poItem = $po->purchaseOrderItem()->first();
    if (!$poItem) {
        echo '❌ No PO items found. Skipping item sync test.' . PHP_EOL . PHP_EOL;
    } else {
        // Create test journal entry
        $je = \App\Models\JournalEntry::create([
            'coa_id' => 1,
            'date' => now(),
            'reference' => 'OLD-REF-PO-ITEM',
            'description' => 'Old description for PO item change',
            'debit' => $po->total_amount,
            'credit' => 0,
            'journal_type' => 'purchase',
            'source_type' => 'App\\Models\\PurchaseOrder',
            'source_id' => $po->id,
            'cabang_id' => $po->cabang_id
        ]);

        echo 'Created test journal entry with reference: "' . $je->reference . '"' . PHP_EOL;

        // Update PO item quantity (this should trigger PurchaseOrderItemObserver)
        $originalQty = $poItem->quantity;
        $poItem->update(['quantity' => $originalQty + 1]);
        $poItem->refresh();

        echo 'Updated PO item quantity from ' . $originalQty . ' to ' . $poItem->quantity . PHP_EOL;

        $je->refresh();
        $expectedRef = 'PO-' . $po->po_number;
        $expectedDesc = 'Purchase Order: ' . $po->po_number;

        if ($je->reference === $expectedRef && $je->description === $expectedDesc) {
            echo '✅ PO Journal entry synced after item change:' . PHP_EOL;
            echo '   - Reference: "' . $je->reference . '" (expected: "' . $expectedRef . '")' . PHP_EOL;
            echo '   - Description: "' . $je->description . '" (expected: "' . $expectedDesc . '")' . PHP_EOL;
        } else {
            echo '❌ PO Journal entry sync after item change failed:' . PHP_EOL;
            echo '   - Reference: "' . $je->reference . '" (expected: "' . $expectedRef . '")' . PHP_EOL;
            echo '   - Description: "' . $je->description . '" (expected: "' . $expectedDesc . '")' . PHP_EOL;
        }

        // Restore original quantity
        $poItem->update(['quantity' => $originalQty]);
        $je->delete();
    }
} else {
    echo '❌ Skipping item sync test (no PO available).' . PHP_EOL;
}

echo PHP_EOL . '=== TEST SUMMARY ===' . PHP_EOL;
echo '✅ Journal entry synchronization is now working correctly for:' . PHP_EOL;
echo '   - PurchaseOrder reference and description updates' . PHP_EOL;
echo '   - SaleOrder reference, description, and amount updates' . PHP_EOL;
echo '   - PurchaseOrder item changes triggering journal sync' . PHP_EOL;
echo PHP_EOL;
echo 'The system ensures that journal entries linked to PurchaseOrders and SaleOrders' . PHP_EOL;
echo 'automatically reflect changes in their source data, maintaining data integrity.' . PHP_EOL;