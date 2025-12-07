<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Support\Facades\Log;

echo "=== TEST PURCHASE RECEIPT ITEM SYNC WITH PURCHASE RETURN ===\n\n";

// Test 1: Update qty_rejected
echo "TEST 1: Update qty_rejected from existing PurchaseReceiptItem\n";
echo "--------------------------------------------------------\n";

$receiptItem = PurchaseReceiptItem::where('qty_rejected', '>', 0)->first();

if ($receiptItem) {
    echo "Found PurchaseReceiptItem ID: {$receiptItem->id}\n";
    echo "Current qty_rejected: {$receiptItem->qty_rejected}\n";

    // Check existing PurchaseReturn
    $existingReturn = PurchaseReturn::where('purchase_receipt_id', $receiptItem->purchase_receipt_id)->first();
    if ($existingReturn) {
        echo "Existing PurchaseReturn: {$existingReturn->nota_retur} (ID: {$existingReturn->id})\n";

        $returnItems = PurchaseReturnItem::where('purchase_return_id', $existingReturn->id)->get();
        echo "Current PurchaseReturnItems: " . $returnItems->count() . "\n";
        foreach ($returnItems as $item) {
            echo "  - Item ID {$item->id}: qty_returned = {$item->qty_returned}, receipt_item_id = {$item->purchase_receipt_item_id}\n";
        }
    } else {
        echo "No existing PurchaseReturn found\n";
    }

    // Update qty_rejected
    $oldQty = $receiptItem->qty_rejected;
    $newQty = $oldQty + 2; // Increase by 2

    echo "\nUpdating qty_rejected from {$oldQty} to {$newQty}...\n";
    $receiptItem->update(['qty_rejected' => $newQty]);

    // Check after update
    $updatedReturn = PurchaseReturn::where('purchase_receipt_id', $receiptItem->purchase_receipt_id)->first();
    if ($updatedReturn) {
        echo "After update - PurchaseReturn: {$updatedReturn->nota_retur}\n";

        $updatedReturnItems = PurchaseReturnItem::where('purchase_return_id', $updatedReturn->id)->get();
        echo "After update - PurchaseReturnItems: " . $updatedReturnItems->count() . "\n";
        foreach ($updatedReturnItems as $item) {
            echo "  - Item ID {$item->id}: qty_returned = {$item->qty_returned}, receipt_item_id = {$item->purchase_receipt_item_id}\n";
        }
    }

    // Test 2: Set qty_rejected to 0
    echo "\nTEST 2: Set qty_rejected to 0 (should delete PurchaseReturnItem)\n";
    echo "---------------------------------------------------------------\n";

    echo "Setting qty_rejected to 0...\n";
    $receiptItem->update(['qty_rejected' => 0]);

    // Check after setting to 0
    $afterZeroReturn = PurchaseReturn::where('purchase_receipt_id', $receiptItem->purchase_receipt_id)->first();
    if ($afterZeroReturn) {
        $afterZeroItems = PurchaseReturnItem::where('purchase_return_id', $afterZeroReturn->id)->get();
        echo "After setting to 0 - PurchaseReturnItems: " . $afterZeroItems->count() . "\n";
        foreach ($afterZeroItems as $item) {
            echo "  - Item ID {$item->id}: qty_returned = {$item->qty_returned}, receipt_item_id = {$item->purchase_receipt_item_id}\n";
        }
    } else {
        echo "PurchaseReturn has been deleted (no items left)\n";
    }

} else {
    echo "No PurchaseReceiptItem with qty_rejected > 0 found. Creating test data...\n";

    // Create test data
    $testReceiptItem = PurchaseReceiptItem::first();
    if ($testReceiptItem) {
        echo "Using existing PurchaseReceiptItem ID: {$testReceiptItem->id}\n";
        $testReceiptItem->update(['qty_rejected' => 3]);
        echo "Set qty_rejected to 3\n";

        // Check if PurchaseReturn was created
        sleep(1); // Give time for observer
        $testReturn = PurchaseReturn::where('purchase_receipt_id', $testReceiptItem->purchase_receipt_id)->first();
        if ($testReturn) {
            echo "PurchaseReturn created: {$testReturn->nota_retur}\n";
            $testReturnItems = PurchaseReturnItem::where('purchase_return_id', $testReturn->id)->get();
            echo "PurchaseReturnItems: " . $testReturnItems->count() . "\n";
        }
    }
}

// Test 3: Delete PurchaseReceiptItem
echo "\nTEST 3: Delete PurchaseReceiptItem (should delete related PurchaseReturn)\n";
echo "-----------------------------------------------------------------------\n";

$deleteItem = PurchaseReceiptItem::where('qty_rejected', '>', 0)->first();

if (!$deleteItem) {
    // Create one for testing
    $deleteItem = PurchaseReceiptItem::first();
    if ($deleteItem) {
        $deleteItem->update(['qty_rejected' => 1]);
        echo "Created test item with qty_rejected = 1\n";
        sleep(1); // Give time for observer
    }
}

if ($deleteItem) {
    echo "Will delete PurchaseReceiptItem ID: {$deleteItem->id} (qty_rejected: {$deleteItem->qty_rejected})\n";

    // Check existing PurchaseReturn before delete
    $beforeDeleteReturn = PurchaseReturn::where('purchase_receipt_id', $deleteItem->purchase_receipt_id)->first();
    if ($beforeDeleteReturn) {
        echo "Before delete - PurchaseReturn: {$beforeDeleteReturn->nota_retur}\n";
        $beforeDeleteItems = PurchaseReturnItem::where('purchase_return_id', $beforeDeleteReturn->id)->get();
        echo "Before delete - PurchaseReturnItems: " . $beforeDeleteItems->count() . "\n";
    }

    // Delete the item
    echo "Deleting PurchaseReceiptItem...\n";
    $deleteItem->delete();

    // Check after delete
    $afterDeleteReturn = PurchaseReturn::where('purchase_receipt_id', $deleteItem->purchase_receipt_id)->first();
    if ($afterDeleteReturn) {
        $afterDeleteItems = PurchaseReturnItem::where('purchase_return_id', $afterDeleteReturn->id)->get();
        echo "After delete - PurchaseReturn still exists with {$afterDeleteItems->count()} items\n";
    } else {
        echo "After delete - PurchaseReturn has been deleted\n";
    }

} else {
    echo "No suitable PurchaseReceiptItem found for delete test\n";
}

echo "\n=== TEST COMPLETED ===\n";