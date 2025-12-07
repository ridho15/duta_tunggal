<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING DELIVERY ORDER QUANTITY UPDATE AFTER APPROVAL ===\n\n";

$doItem = App\Models\DeliveryOrderItem::find(1);
$do = App\Models\DeliveryOrder::with('journalEntries.coa')->find($doItem->delivery_order_id);

if ($do && $doItem) {
    echo "Delivery Order Status: {$do->status}\n";
    echo "Item Current Quantity: {$doItem->quantity}\n";

    // Get current journal entry amounts
    $currentAmounts = [];
    foreach ($do->journalEntries as $je) {
        $amount = $je->debit > 0 ? $je->debit : $je->credit;
        $currentAmounts[] = $amount;
    }

    echo "Current Journal Entry Amounts: " . implode(', ', array_map(function($amt) {
        return number_format($amt, 2);
    }, $currentAmounts)) . "\n";

    // Calculate expected amounts based on current quantity
    $unitPrice = 100000; // Assuming unit price
    $expectedAmounts = array_map(function($amt) use ($doItem, $unitPrice) {
        return $doItem->quantity * $unitPrice;
    }, $currentAmounts);

    echo "Expected Amounts (current qty): " . implode(', ', array_map(function($amt) {
        return number_format($amt, 2);
    }, $expectedAmounts)) . "\n";

    // Now decrease quantity
    $originalQty = $doItem->quantity;
    $newQty = $originalQty - 3; // Decrease by 3

    echo "\n=== CHANGING QUANTITY FROM {$originalQty} TO {$newQty} ===\n";

    $doItem->quantity = $newQty;
    $doItem->save();

    // Refresh data
    $do->refresh();
    $do->load('journalEntries.coa');
    $doItem->refresh();

    // Get new amounts
    $newAmounts = [];
    foreach ($do->journalEntries as $je) {
        $amount = $je->debit > 0 ? $je->debit : $je->credit;
        $newAmounts[] = $amount;
    }

    echo "After quantity change:\n";
    echo "  Item Quantity: {$doItem->quantity}\n";
    echo "  Journal Entry Amounts: " . implode(', ', array_map(function($amt) {
        return number_format($amt, 2);
    }, $newAmounts)) . "\n";

    // Calculate new expected amounts
    $newExpectedAmounts = array_map(function($amt) use ($newQty, $unitPrice) {
        return $newQty * $unitPrice;
    }, $currentAmounts);

    echo "  Expected New Amounts: " . implode(', ', array_map(function($amt) {
        return number_format($amt, 2);
    }, $newExpectedAmounts)) . "\n";

    // Check if amounts changed
    $amountsChanged = $currentAmounts !== $newAmounts;
    $amountsCorrect = $newAmounts === $newExpectedAmounts;

    echo "\n=== RESULTS ===\n";
    echo "Journal entry amounts changed: " . ($amountsChanged ? 'YES' : 'NO') . "\n";
    echo "Journal entry amounts correct: " . ($amountsCorrect ? 'YES' : 'NO') . "\n";
    echo "Journal entries auto-updated: " . ($amountsChanged && $amountsCorrect ? 'YES' : 'NO') . "\n";

    if (!$amountsChanged) {
        echo "\n⚠️  WARNING: Journal entries not updated! This creates accounting discrepancy.\n";
        echo "   - Delivered quantity changed but journal entries unchanged\n";
        echo "   - Cost of goods sold still reflects old quantity\n";
    }

} else {
    echo "Data not found\n";
}

echo "\nTest completed at " . now()->format('Y-m-d H:i:s') . "\n";