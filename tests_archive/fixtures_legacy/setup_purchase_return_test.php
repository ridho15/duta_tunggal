<?php
/**
 * Setup script for E2E Purchase Return test.
 * Ensures PO id=2 is 'closed' so it can be used for purchase return.
 * (No UI flow available to close a 'completed' PO — RequestClose is hidden for that status.)
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$po = \App\Models\PurchaseOrder::withoutGlobalScopes()->withTrashed()->find(2);
if (!$po) {
    echo "ERROR: PO id=2 not found\n";
    exit(1);
}

// Restore soft-deleted PO if needed
if ($po->trashed()) {
    $po->restore();
    echo "OK: PO id=2 restored from soft-delete\n";
} else {
    echo "OK: PO id=2 already active\n";
}

// Re-fetch after restore
$po = \App\Models\PurchaseOrder::withoutGlobalScopes()->find(2);
if ($po->status === 'closed') {
    echo "OK: PO id=2 already closed\n";
} else {
    $po->update([
        'status'    => 'closed',
        'closed_at' => now(),
        'closed_by' => 1,
        'close_reason' => 'E2E test setup',
    ]);
    echo "OK: PO id=2 set to closed (was: {$po->getOriginal('status')})\n";
}

// Confirm receipt id=1 exists and restore if soft-deleted
$receipt = \App\Models\PurchaseReceipt::withoutGlobalScopes()->withTrashed()->find(1);
if (!$receipt) {
    echo "ERROR: Receipt id=1 not found\n";
    exit(1);
}
if ($receipt->trashed()) {
    $receipt->restore();
    echo "OK: Receipt id=1 restored from soft-delete\n";
} else {
    echo "OK: Receipt id={$receipt->id} already active\n";
}
echo "OK: Receipt num={$receipt->receipt_number}\n";

// Restore soft-deleted receipt items for receipt id=1
$items = \App\Models\PurchaseReceiptItem::withoutGlobalScopes()->withTrashed()
    ->where('purchase_receipt_id', 1)->get();
foreach ($items as $item) {
    if ($item->trashed()) {
        $item->restore();
        echo "OK: Restored receipt item id={$item->id} (was soft-deleted)\n";
    } else {
        echo "OK: Receipt item id={$item->id} already active\n";
    }
}

// Verify items now available
$activeItems = \App\Models\PurchaseReceiptItem::withoutGlobalScopes()
    ->where('purchase_receipt_id', 1)->with('product')->get();
echo "OK: Receipt id=1 active items=" . $activeItems->count() . "\n";
foreach ($activeItems as $item) {
    echo "  Item id={$item->id} product={$item->product->name} sku={$item->product->sku} qty={$item->qty_received}\n";
}

// Check current stock
$stock = \App\Models\InventoryStock::where('product_id', 101)->where('warehouse_id', 1)->first();
if ($stock) {
    echo "OK: Stock product_id=101 warehouse_id=1 qty_on_hand={$stock->qty_on_hand} qty_available={$stock->qty_available}\n";
} else {
    echo "WARN: No stock record found for product_id=101 warehouse_id=1\n";
}
