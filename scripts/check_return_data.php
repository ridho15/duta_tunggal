<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== POs completed/closed ===\n";
$pos = \App\Models\PurchaseOrder::whereIn('status', ['completed', 'closed'])->get();
foreach ($pos as $po) {
    echo "PO id={$po->id} num={$po->po_number} status={$po->status}\n";
    $receipts = \App\Models\PurchaseReceipt::where('purchase_order_id', $po->id)
        ->with('purchaseReceiptItem.product')->get();
    foreach ($receipts as $r) {
        echo "  Receipt id={$r->id} num={$r->receipt_number} status={$r->status}\n";
        foreach ($r->purchaseReceiptItem as $item) {
            echo "    Item id={$item->id} product={$item->product->name} sku={$item->product->sku} qty={$item->qty_received}\n";
        }
    }
}

echo "\n=== Existing Purchase Returns ===\n";
$returns = \App\Models\PurchaseReturn::with(['purchaseReceipt','purchaseReturnItem.product'])->get();
foreach ($returns as $ret) {
    echo "Return id={$ret->id} nota={$ret->nota_retur} status={$ret->status}\n";
}

echo "\n=== Inventory Stock (top 5) ===\n";
$stocks = \App\Models\InventoryStock::with(['product', 'warehouse'])->take(5)->get();
foreach ($stocks as $s) {
    echo "product_id={$s->product_id} name={$s->product->name} warehouse={$s->warehouse->name} qty_on_hand={$s->qty_on_hand} qty_available={$s->qty_available}\n";
}
