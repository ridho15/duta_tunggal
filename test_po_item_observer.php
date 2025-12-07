<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== TESTING PURCHASE ORDER ITEM OBSERVER ===' . PHP_EOL;

// Get a PO with items
$po = \App\Models\PurchaseOrder::with('purchaseOrderItem')->first();
if (!$po) {
    echo 'No PO found' . PHP_EOL;
    exit;
}

echo 'Using PO: ' . $po->po_number . ' with ' . $po->purchaseOrderItem->count() . ' items' . PHP_EOL;

// Create test journal entry
$je = \App\Models\JournalEntry::create([
    'coa_id' => 1,
    'date' => now(),
    'reference' => 'TEST-PO-ITEM',
    'description' => 'Test journal entry for PO item observer',
    'debit' => $po->total_amount,
    'credit' => 0,
    'journal_type' => 'purchase',
    'source_type' => 'App\\Models\\PurchaseOrder',
    'source_id' => $po->id,
    'cabang_id' => $po->cabang_id
]);

echo 'Created test journal entry: ' . $je->reference . PHP_EOL;

// Test 1: Update existing PO item
$item = $po->purchaseOrderItem->first();
if ($item) {
    $oldQty = $item->quantity;
    echo 'Updating PO item quantity from ' . $oldQty . ' to ' . ($oldQty + 1) . PHP_EOL;

    $item->update(['quantity' => $oldQty + 1]);

    $je->refresh();
    echo 'Journal entry after item update:' . PHP_EOL;
    echo '  Reference: ' . $je->reference . PHP_EOL;
    echo '  Description: ' . $je->description . PHP_EOL;

    $synced = ($je->reference === 'PO-' . $po->po_number && $je->description === 'Purchase Order: ' . $po->po_number);
    echo ($synced ? '✅ SYNCED' : '❌ NOT SYNCED') . ' after item update' . PHP_EOL;

    // Restore
    $item->update(['quantity' => $oldQty]);
}

echo PHP_EOL . 'Test completed.' . PHP_EOL;