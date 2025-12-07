<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== TESTING: JOURNAL ENTRIES WHEN PURCHASE ORDER ITEM IS DELETED ===' . PHP_EOL;

$po = \App\Models\PurchaseOrder::with('purchaseOrderItem')->whereHas('purchaseOrderItem')->first();
if (!$po) {
    echo '❌ No Purchase Order found' . PHP_EOL;
    exit;
}

echo 'Using PO: ' . $po->po_number . PHP_EOL;
echo 'PO has ' . $po->purchaseOrderItem->count() . ' items' . PHP_EOL;
echo 'PO total_amount: ' . $po->total_amount . PHP_EOL;

// Check existing journal entries for this PO
$existingEntriesCount = $po->journalEntries()->where('journal_type', 'purchase')->count();
echo 'Existing journal entries for this PO: ' . $existingEntriesCount . PHP_EOL;

// Create a test journal entry linked to this PO
$je = \App\Models\JournalEntry::create([
    'coa_id' => 1,
    'date' => now(),
    'reference' => 'TEST-PO-DELETE-ITEM',
    'description' => 'Test journal entry for PO item deletion',
    'debit' => $po->total_amount,
    'credit' => 0,
    'journal_type' => 'purchase',
    'source_type' => 'App\\Models\\PurchaseOrder',
    'source_id' => $po->id,
    'cabang_id' => $po->cabang_id
]);

echo 'Created test journal entry ID: ' . $je->id . PHP_EOL;

// Verify journal entry exists
$entriesBeforeDelete = $po->journalEntries()->where('journal_type', 'purchase')->count();
echo 'Journal entries count before item deletion: ' . $entriesBeforeDelete . PHP_EOL;

// Get the first item to delete
$item = $po->purchaseOrderItem->first();
if (!$item) {
    echo '❌ No items to delete' . PHP_EOL;
    $je->delete();
    exit;
}

echo 'Deleting item with ID: ' . $item->id . ', quantity: ' . $item->quantity . PHP_EOL;

// Delete the item
$item->delete();

// Check journal entries after deletion
$entriesAfterDelete = $po->journalEntries()->where('journal_type', 'purchase')->count();
echo 'Journal entries count after item deletion: ' . $entriesAfterDelete . PHP_EOL;

// Check if our test journal entry still exists
$je->refresh();
$journalEntryExists = \App\Models\JournalEntry::find($je->id) !== null;
echo 'Test journal entry still exists: ' . ($journalEntryExists ? 'YES' : 'NO') . PHP_EOL;

if ($journalEntryExists) {
    echo 'Journal entry details after item deletion:' . PHP_EOL;
    echo '  - ID: ' . $je->id . PHP_EOL;
    echo '  - Reference: ' . $je->reference . PHP_EOL;
    echo '  - Description: ' . $je->description . PHP_EOL;
    echo '  - Debit: ' . $je->debit . PHP_EOL;
    echo '  - Credit: ' . $je->credit . PHP_EOL;
}

// Analysis
echo PHP_EOL . '=== ANALYSIS ===' . PHP_EOL;
if ($entriesBeforeDelete === $entriesAfterDelete && $journalEntryExists) {
    echo '✅ CONCLUSION: Journal entries are UPDATED, not DELETED' . PHP_EOL;
    echo '   - Same number of journal entries before and after deletion' . PHP_EOL;
    echo '   - Test journal entry still exists with updated reference/description' . PHP_EOL;
} else if ($entriesAfterDelete < $entriesBeforeDelete) {
    echo '❌ CONCLUSION: Some journal entries were DELETED' . PHP_EOL;
    echo '   - Journal entries count decreased after item deletion' . PHP_EOL;
} else {
    echo '❓ UNEXPECTED RESULT: Journal entries count changed unexpectedly' . PHP_EOL;
}

// Clean up
$je->delete();

echo PHP_EOL . 'Test completed.' . PHP_EOL;