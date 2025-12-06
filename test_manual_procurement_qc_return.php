<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cabang;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Models\ChartOfAccount;

echo "=== TEST MANUAL: Purchase Order with Return Product & QC ===\n";

// Seed minimal data
$cabang = Cabang::first() ?? Cabang::factory()->create([
    'kode' => 'MAIN',
    'nama' => 'Main Branch',
    'alamat' => 'Jl. Main 123',
    'telepon' => '021123456'
]);
$warehouse = Warehouse::first() ?? Warehouse::factory()->create(['cabang_id' => $cabang->id]);
$rak = Rak::first() ?? Rak::factory()->create(['warehouse_id' => $warehouse->id]);
$supplier = Supplier::factory()->create();
$product = Product::factory()->create();
$coaAp = ChartOfAccount::where('code', '2100')->first() ?? ChartOfAccount::create(['code' => '2100', 'name' => 'Accounts Payable']);
$coaInventory = ChartOfAccount::where('code', '1140.01')->first() ?? ChartOfAccount::create(['code' => '1140.01', 'name' => 'Inventory']);
$product->update(['inventory_coa_id' => $coaInventory->id]);

// 1. Buat Purchase Order
$po = PurchaseOrder::factory()->create([
    'supplier_id' => $supplier->id,
    'status' => 'approved',
]);
$poItem = PurchaseOrderItem::factory()->create([
    'purchase_order_id' => $po->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'unit_price' => 100000,
]);

echo "✅ Purchase Order Created: Qty 10\n";

// 2. Buat Purchase Receipt (Penerimaan Barang)
$receipt = PurchaseReceipt::factory()->create([
    'purchase_order_id' => $po->id,
    'status' => 'received',
]);
$receiptItem = PurchaseReceiptItem::factory()->create([
    'purchase_receipt_id' => $receipt->id,
    'purchase_order_item_id' => $poItem->id,
    'product_id' => $product->id,
    'quantity_received' => 10,
    'quantity_accepted' => 0, // Akan diupdate oleh QC
]);

echo "✅ Purchase Receipt Created: Qty Received 10\n";

// 3. Lakukan Quality Control
$qc = QualityControl::factory()->create([
    'from_model_type' => PurchaseReceiptItem::class,
    'from_model_id' => $receiptItem->id,
    'product_id' => $product->id,
    'passed_quantity' => 7,
    'rejected_quantity' => 3,
    'status' => 1, // Sudah diproses
    'reason_reject' => 'Barang rusak',
]);

// Update receipt item berdasarkan QC
$receiptItem->update([
    'qty_accepted' => 7,
    'qty_rejected' => 3,
]);

// Stok masuk untuk yang lolos
$stock = InventoryStock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first();
if (!$stock) {
    $stock = InventoryStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 0,
    ]);
}
$stock->increment('qty_available', 7);

echo "✅ QC Completed: 7 Passed (masuk stok), 3 Rejected\n";
echo "   Stok Available: " . $stock->fresh()->qty_available . "\n";

// 4. Retur By DO untuk barang rusak (otomatis dari QC)
$return = ReturnProduct::factory()->create([
    'from_model_type' => QualityControl::class,
    'from_model_id' => $qc->id,
    'warehouse_id' => $warehouse->id,
    'status' => 'approved',
    'return_action' => 'reduce_quantity_only', // Reduce qty penerimaan
    'reason' => 'QC Rejection',
]);
$returnItem = ReturnProductItem::factory()->create([
    'return_product_id' => $return->id,
    'product_id' => $product->id,
    'quantity' => 3,
    'reason' => 'Barang rusak',
]);

// Kurangi qty penerimaan (otomatis)
$receiptItem->decrement('qty_received', 3);

echo "✅ Return By DO Created: Qty 3\n";
echo "   Receipt Qty Received Now: " . $receiptItem->fresh()->qty_received . "\n";

// 5. Close PO jika semua selesai
$po->update(['status' => 'closed']);

echo "✅ PO Closed\n";

echo "🎉 Test Manual Procurement with QC & Return Selesai!\n";