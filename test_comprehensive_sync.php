<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Support\Facades\Log;

echo "=== COMPREHENSIVE TEST: PURCHASE RECEIPT ITEM SYNC ===\n\n";

function checkPurchaseReturn($receiptId, $label) {
    $purchaseReturn = PurchaseReturn::where('purchase_receipt_id', $receiptId)->first();
    if ($purchaseReturn) {
        $returnItems = PurchaseReturnItem::where('purchase_return_id', $purchaseReturn->id)->get();
        echo "{$label} - PurchaseReturn: {$purchaseReturn->nota_retur} (ID: {$purchaseReturn->id})\n";
        echo "{$label} - Items: {$returnItems->count()}\n";
        foreach ($returnItems as $item) {
            echo "  - Item ID {$item->id}: qty_returned={$item->qty_returned}, receipt_item_id={$item->purchase_receipt_item_id}\n";
        }
        return ['return' => $purchaseReturn, 'items' => $returnItems];
    } else {
        echo "{$label} - No PurchaseReturn found\n";
        return null;
    }
}

// Test 1: Update existing qty_rejected
echo "TEST 1: Update existing qty_rejected\n";
echo "===================================\n";

$item1 = PurchaseReceiptItem::where('qty_rejected', '>', 0)->first();
if ($item1) {
    echo "Using PurchaseReceiptItem ID: {$item1->id}\n";
    echo "Current qty_rejected: {$item1->qty_rejected}\n";

    checkPurchaseReturn($item1->purchase_receipt_id, "BEFORE UPDATE");

    $oldQty = $item1->qty_rejected;
    $newQty = $oldQty + 1;

    echo "\nUpdating qty_rejected from {$oldQty} to {$newQty}...\n";
    $item1->update(['qty_rejected' => $newQty]);

    checkPurchaseReturn($item1->purchase_receipt_id, "AFTER UPDATE");
} else {
    echo "No item with qty_rejected > 0 found for Test 1\n";
}

// Test 2: Create new rejected item (should create PurchaseReturn)
echo "\nTEST 2: Create new rejected item\n";
echo "=================================\n";

$item2 = PurchaseReceiptItem::where('qty_rejected', '=', 0)->orWhereNull('qty_rejected')->first();
if ($item2) {
    echo "Using PurchaseReceiptItem ID: {$item2->id}\n";
    echo "Current qty_rejected: " . ($item2->qty_rejected ?? 'null') . "\n";

    checkPurchaseReturn($item2->purchase_receipt_id, "BEFORE CREATE");

    echo "\nSetting qty_rejected to 2...\n";
    $item2->update(['qty_rejected' => 2]);

    checkPurchaseReturn($item2->purchase_receipt_id, "AFTER CREATE");
} else {
    echo "No suitable item found for Test 2\n";
}

// Test 3: Set qty_rejected to 0 (should delete PurchaseReturnItem)
echo "\nTEST 3: Set qty_rejected to 0\n";
echo "=============================\n";

$item3 = PurchaseReceiptItem::where('qty_rejected', '>', 0)->where('id', '!=', $item1->id ?? 0)->first();
if ($item3) {
    echo "Using PurchaseReceiptItem ID: {$item3->id}\n";
    echo "Current qty_rejected: {$item3->qty_rejected}\n";

    checkPurchaseReturn($item3->purchase_receipt_id, "BEFORE SET TO ZERO");

    echo "\nSetting qty_rejected to 0...\n";
    $item3->update(['qty_rejected' => 0]);

    checkPurchaseReturn($item3->purchase_receipt_id, "AFTER SET TO ZERO");
} else {
    echo "No suitable item found for Test 3\n";
}

// Test 4: Delete PurchaseReceiptItem (should delete related PurchaseReturn)
echo "\nTEST 4: Delete PurchaseReceiptItem\n";
echo "==================================\n";

$item4 = PurchaseReceiptItem::where('qty_rejected', '>', 0)->first();
if ($item4) {
    echo "Will delete PurchaseReceiptItem ID: {$item4->id}\n";
    echo "qty_rejected: {$item4->qty_rejected}\n";

    checkPurchaseReturn($item4->purchase_receipt_id, "BEFORE DELETE");

    echo "\nDeleting PurchaseReceiptItem...\n";
    $receiptId = $item4->purchase_receipt_id;
    $item4->delete();

    checkPurchaseReturn($receiptId, "AFTER DELETE");
} else {
    echo "No suitable item found for Test 4\n";
}

// Test 5: Multiple items in same receipt
echo "\nTEST 5: Multiple rejected items in same receipt\n";
echo "================================================\n";

$receiptWithMultiple = PurchaseReceiptItem::where('qty_rejected', '>', 0)
    ->with('purchaseReceipt.purchaseReceiptItem')
    ->first();

if ($receiptWithMultiple) {
    $receipt = $receiptWithMultiple->purchaseReceipt;
    $allItemsInReceipt = $receipt->purchaseReceiptItem;
    $rejectedItems = $allItemsInReceipt->where('qty_rejected', '>', 0);

    echo "Using receipt ID: {$receipt->id}\n";
    echo "Total items in receipt: {$allItemsInReceipt->count()}\n";
    echo "Rejected items in receipt: {$rejectedItems->count()}\n";

    if ($rejectedItems->count() > 1) {
        checkPurchaseReturn($receipt->id, "BEFORE MULTIPLE TEST");

        // Delete one item
        $itemToDelete = $rejectedItems->first();
        echo "\nDeleting one item (ID: {$itemToDelete->id}) from receipt with multiple items...\n";
        $itemToDelete->delete();

        checkPurchaseReturn($receipt->id, "AFTER DELETING ONE ITEM");
    } else {
        echo "Receipt doesn't have multiple rejected items\n";
    }

} else {
    echo "No receipt with rejected items found for Test 5\n";
}

echo "\n=== ALL TESTS COMPLETED ===\n";
echo "Check logs for detailed observer execution\n";