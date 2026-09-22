<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseInvoice;

echo "=== ORDER REQUEST #3 AUDIT ===\n";
$or = OrderRequest::withTrashed()->find(3);
if (!$or) { echo "OR #3 not found"; exit(1); }

echo "OR #3:\n";
echo "  request_number: {$or->request_number}\n";
echo "  status: {$or->status}\n";
echo "  header supplier_id: " . ($or->supplier_id ?? 'NULL') . "\n";
echo "  warehouse_id: {$or->warehouse_id}\n";
echo "  cabang_id: {$or->cabang_id}\n";

echo "\nItems on OR #3:\n";
$items = OrderRequestItem::where('order_request_id', 3)->get();
foreach ($items as $item) {
    echo "  item_id={$item->id}: product_id={$item->product_id}\n";
    echo "    supplier_id (item-level): " . ($item->supplier_id ?? 'NULL') . "\n";
    echo "    quantity: {$item->quantity}\n";
    echo "    fulfilled_quantity: " . ($item->fulfilled_quantity ?? '0') . "\n";
    echo "    unit_price (stored on item): " . ($item->unit_price ?? 'NULL') . "\n";
    echo "    original_price: " . ($item->original_price ?? 'NULL') . "\n";
    echo "    discount: " . ($item->discount ?? 'NULL') . "\n";
    echo "    tax: " . ($item->tax ?? 'NULL') . "\n";

    // Effective supplier: item-level, fallback to header
    $supplierId = $item->supplier_id ?? $or->supplier_id;
    echo "    effective supplier_id: $supplierId\n";

    // Fetch pivot supplier_price from product_supplier table
    $product = Product::find($item->product_id);
    if ($product && $supplierId) {
        $sp = $product->suppliers()->where('suppliers.id', $supplierId)->first();
        if ($sp) {
            echo "    pivot supplier_price (catalog): " . $sp->pivot->supplier_price . "\n";
        } else {
            echo "    pivot supplier_price: NOT FOUND in catalog\n";
        }
    }

    echo "\n--- Price Analysis ---\n";
    echo "    [approve fillForm logic]\n";
    echo "    Step 1 - start with item->unit_price: " . ($item->unit_price ?? 'NULL') . "\n";
    $supplierPrice = $item->unit_price ?? 0;
    if ($supplierId && $product) {
        $sp = $product->suppliers()->where('suppliers.id', $supplierId)->first();
        if ($sp && $sp->pivot->supplier_price > 0) {
            $supplierPrice = (float) $sp->pivot->supplier_price;
            echo "    Step 2 - OVERRIDDEN by pivot supplier_price: $supplierPrice\n";
        } else {
            echo "    Step 2 - No pivot override, stays at: $supplierPrice\n";
        }
    }
    echo "    Final supplierPrice shown in modal: $supplierPrice\n";
    echo "    Expected unit_price (from item): " . ($item->unit_price ?? 'NULL') . "\n";
    echo "    MISMATCH: " . ($supplierPrice != (float)$item->unit_price ? "YES" : "NO") . "\n";
}

echo "\n=== ALL ORDER REQUESTS ===\n";
$ors = OrderRequest::withTrashed()->orderBy('id')->get(['id', 'request_number', 'status', 'supplier_id', 'deleted_at']);
foreach ($ors as $or2) {
    $itemCount = OrderRequestItem::withTrashed()->where('order_request_id', $or2->id)->count();
    echo "  OR #{$or2->id}: {$or2->request_number}, status={$or2->status}, header_supplier=" . ($or2->supplier_id ?? 'NULL') . ", items={$itemCount}, deleted=" . ($or2->deleted_at ? 'YES' : 'no') . "\n";
}

echo "\n=== PURCHASE ORDERS (all) ===\n";
$pos = PurchaseOrder::with('supplier')->orderBy('id')->get();
foreach ($pos as $po) {
    echo "  PO #{$po->id}: {$po->po_number}, supplier=" . ($po->supplier->perusahaan ?? 'NULL') . ", status={$po->status}\n";
}
