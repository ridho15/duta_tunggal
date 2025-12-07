<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== DEMONSTRATING DELETED EVENT MECHANISM ===' . PHP_EOL;

// Get a PO with items
$po = \App\Models\PurchaseOrder::with('purchaseOrderItem')->first();
if (!$po) {
    echo 'No PO found' . PHP_EOL;
    exit;
}

echo 'PO: ' . $po->po_number . ' has ' . $po->purchaseOrderItem->count() . ' items' . PHP_EOL;
echo 'PO total_amount: ' . $po->total_amount . PHP_EOL;

// Create test journal entry
$je = \App\Models\JournalEntry::create([
    'coa_id' => 1,
    'date' => now(),
    'reference' => 'TEST-DELETE',
    'description' => 'Test journal entry for delete mechanism',
    'debit' => $po->total_amount,
    'credit' => 0,
    'journal_type' => 'purchase',
    'source_type' => 'App\\Models\\PurchaseOrder',
    'source_id' => $po->id,
    'cabang_id' => $po->cabang_id
]);

echo 'Created journal entry: ' . $je->reference . PHP_EOL;

// Get first item
$item = $po->purchaseOrderItem->first();
if ($item) {
    echo 'Deleting item with quantity: ' . $item->quantity . ', subtotal: ' . $item->subtotal . PHP_EOL;

    // Delete the item - this should trigger the deleted event
    $item->delete();

    // Check journal entry after deletion
    $je->refresh();
    echo 'Journal entry after item deletion:' . PHP_EOL;
    echo '  Reference: ' . $je->reference . PHP_EOL;
    echo '  Description: ' . $je->description . PHP_EOL;

    $synced = ($je->reference === 'PO-' . $po->po_number && $je->description === 'Purchase Order: ' . $po->po_number);
    echo ($synced ? '✅ SYNCED' : '❌ NOT SYNCED') . ' after item deletion' . PHP_EOL;
}

$je->delete();
echo PHP_EOL . 'Test completed.' . PHP_EOL;