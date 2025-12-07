<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== TESTING JOURNAL ENTRY SYNC WHEN SOURCE MODEL UPDATES ===' . PHP_EOL;

// Test 1: Purchase Order
echo '--- Testing Purchase Order ---' . PHP_EOL;
$po = \App\Models\PurchaseOrder::first();
if (!$po) {
    echo 'No existing Purchase Order found. Skipping PO test.' . PHP_EOL;
} else {
    echo 'Using existing Purchase Order: ' . $po->po_number . ' with total_amount: ' . $po->total_amount . PHP_EOL;

    // Create a journal entry linked to this PO
    $journalEntry = \App\Models\JournalEntry::create([
        'coa_id' => 1,
        'date' => now(),
        'reference' => 'JE-TEST-' . now()->format('YmdHis'),
        'description' => 'Test journal entry for PO ' . $po->po_number,
        'debit' => $po->total_amount,
        'credit' => 0,
        'journal_type' => 'purchase',
        'source_type' => 'App\\Models\\PurchaseOrder',
        'source_id' => $po->id,
        'cabang_id' => $po->cabang_id
    ]);

    echo 'Created Journal Entry: ' . $journalEntry->reference . ' with debit: ' . $journalEntry->debit . PHP_EOL;

    // Store original values
    $originalReference = $journalEntry->reference;
    $originalDescription = $journalEntry->description;
    $originalDebit = $journalEntry->debit;
    $originalPOAmount = $po->total_amount;

    // Update the Purchase Order by modifying an item quantity to change total_amount legitimately
    $poItem = $po->purchaseOrderItem()->first();
    if ($poItem) {
        $originalQuantity = $poItem->quantity;
        $newQuantity = $originalQuantity + 1; // Increase quantity by 1
        echo 'Updating PO item quantity from ' . $originalQuantity . ' to ' . $newQuantity . PHP_EOL;
        $poItem->update(['quantity' => $newQuantity]);
        $po->refresh(); // Refresh to get updated total_amount
        echo 'PO total_amount after item update: ' . $po->total_amount . PHP_EOL;
    } else {
        echo 'No PO items found, cannot test amount sync' . PHP_EOL;
    }

    echo 'Updated PO total_amount from ' . $originalPOAmount . ' to ' . $po->total_amount . PHP_EOL;

    // Check if journal entry was updated
    $journalEntry->refresh();
    echo 'Journal Entry after PO update:' . PHP_EOL;
    echo '  - Reference: ' . $journalEntry->reference . PHP_EOL;
    echo '  - Description: ' . $journalEntry->description . PHP_EOL;
    echo '  - Debit: ' . $journalEntry->debit . PHP_EOL;

    $referenceUpdated = $journalEntry->reference !== $originalReference;
    $descriptionUpdated = $journalEntry->description !== $originalDescription;
    $amountUpdated = $journalEntry->debit !== $originalDebit;

    if ($referenceUpdated || $descriptionUpdated || $amountUpdated) {
        echo '✅ PO Journal entry was updated when source model changed.' . PHP_EOL;
        if ($referenceUpdated) echo '   - Reference updated' . PHP_EOL;
        if ($descriptionUpdated) echo '   - Description updated' . PHP_EOL;
        if ($amountUpdated) echo '   - Amount updated from ' . $originalDebit . ' to ' . $journalEntry->debit . PHP_EOL;
    } else {
        echo '❌ ISSUE: PO Journal entry was NOT updated when source model changed!' . PHP_EOL;
    }

    // Clean up
    $journalEntry->delete();
    echo PHP_EOL;
}

// Test 2: Sale Order
echo '--- Testing Sale Order ---' . PHP_EOL;
$so = \App\Models\SaleOrder::first();
if (!$so) {
    echo 'No existing Sale Order found. Skipping SO test.' . PHP_EOL;
} else {
    echo 'Using existing Sale Order: ' . $so->so_number . ' with total_amount: ' . $so->total_amount . PHP_EOL;

    // Create a journal entry linked to this SO
    $journalEntrySO = \App\Models\JournalEntry::create([
        'coa_id' => 1,
        'date' => now(),
        'reference' => 'JE-TEST-SO-' . now()->format('YmdHis'),
        'description' => 'Test journal entry for SO ' . $so->so_number,
        'debit' => 0,
        'credit' => $so->total_amount,
        'journal_type' => 'sales',
        'source_type' => 'App\\Models\\SaleOrder',
        'source_id' => $so->id,
        'cabang_id' => $so->cabang_id ?? 1
    ]);

    echo 'Created Journal Entry: ' . $journalEntrySO->reference . ' with credit: ' . $journalEntrySO->credit . PHP_EOL;

    // Store original values
    $originalReferenceSO = $journalEntrySO->reference;
    $originalDescriptionSO = $journalEntrySO->description;
    $originalCreditSO = $journalEntrySO->credit;
    $originalSOAmount = $so->total_amount;

    // Update the Sale Order total_amount to a different value
    $newSOAmount = $so->total_amount + 50000; // Add 50k to ensure it's different
    echo 'Attempting to update SO to new amount: ' . $newSOAmount . PHP_EOL;
    $so->update(['total_amount' => $newSOAmount]);
    $so->refresh();
    echo 'SO total_amount after update: ' . $so->total_amount . PHP_EOL;

    echo 'Updated SO total_amount from ' . $originalSOAmount . ' to ' . $so->total_amount . PHP_EOL;

    // Check if journal entry was updated
    $journalEntrySO->refresh();
    echo 'Journal Entry after SO update:' . PHP_EOL;
    echo '  - Reference: ' . $journalEntrySO->reference . PHP_EOL;
    echo '  - Description: ' . $journalEntrySO->description . PHP_EOL;
    echo '  - Credit: ' . $journalEntrySO->credit . PHP_EOL;

    $referenceUpdatedSO = $journalEntrySO->reference !== $originalReferenceSO;
    $descriptionUpdatedSO = $journalEntrySO->description !== $originalDescriptionSO;
    $amountUpdatedSO = $journalEntrySO->credit !== $originalCreditSO;

    if ($referenceUpdatedSO || $descriptionUpdatedSO || $amountUpdatedSO) {
        echo '✅ SO Journal entry was updated when source model changed.' . PHP_EOL;
        if ($referenceUpdatedSO) echo '   - Reference updated' . PHP_EOL;
        if ($descriptionUpdatedSO) echo '   - Description updated' . PHP_EOL;
        if ($amountUpdatedSO) echo '   - Amount updated from ' . $originalCreditSO . ' to ' . $journalEntrySO->credit . PHP_EOL;
    } else {
        echo '❌ ISSUE: SO Journal entry was NOT updated when source model changed!' . PHP_EOL;
    }

    // Clean up
    $journalEntrySO->delete();
}

echo PHP_EOL . 'All tests completed.' . PHP_EOL;