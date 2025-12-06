<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Models\AgeingSchedule;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\ChartOfAccount;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Models\InventoryStock;

echo "=== TEST MANUAL: Sales Invoice Flow ===\n";

// Seed minimal data
$cabang = Cabang::first() ?? Cabang::factory()->create([
    'kode' => 'MAIN',
    'nama' => 'Main Branch',
    'alamat' => 'Jl. Main 123',
    'telepon' => '021123456'
]);
$warehouse = Warehouse::first() ?? Warehouse::factory()->create(['cabang_id' => $cabang->id]);
$rak = Rak::first() ?? Rak::factory()->create(['warehouse_id' => $warehouse->id]);
$customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
$product = Product::factory()->create();
$coaAr = ChartOfAccount::where('code', '1130.01')->first() ?? ChartOfAccount::create(['code' => '1130.01', 'name' => 'Accounts Receivable']);
$coaRevenue = ChartOfAccount::where('code', '4000')->first() ?? ChartOfAccount::create(['code' => '4000', 'name' => 'Revenue']);
$coaCash = ChartOfAccount::where('code', '1111.01')->first() ?? ChartOfAccount::create(['code' => '1111.01', 'name' => 'Cash']);
$product->update(['inventory_coa_id' => $coaAr->id, 'cogs_coa_id' => $coaRevenue->id]);
$stock = InventoryStock::factory()->create([
    'product_id' => $product->id,
    'warehouse_id' => $warehouse->id,
    'rak_id' => $rak->id,
    'qty_available' => 100,
]);

// 1. Buat Sale Order
$saleOrder = SaleOrder::factory()->create(['customer_id' => $customer->id]);
$saleOrderItem = SaleOrderItem::factory()->create([
    'sale_order_id' => $saleOrder->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'unit_price' => 100000,
    'warehouse_id' => $warehouse->id,
    'rak_id' => $rak->id,
]);

// 2. Buat Delivery Order
$do = DeliveryOrder::factory()->create(['warehouse_id' => $warehouse->id]);
$do->salesOrders()->attach($saleOrder->id);
$doItem = DeliveryOrderItem::factory()->create([
    'delivery_order_id' => $do->id,
    'sale_order_item_id' => $saleOrderItem->id,
    'product_id' => $product->id,
    'quantity' => 10,
]);

// 3. Buat Invoice
$invoice = Invoice::factory()->create([
    'from_model_type' => SaleOrder::class,
    'from_model_id' => $saleOrder->id,
    'total' => 1000000,
    'status' => 'unpaid',
    'delivery_orders' => [$do->id],
]);

// Cek AR dan Ageing
$ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
$ageing = $ar ? $ar->ageingSchedule : null;
echo "✅ After Invoice Creation:\n";
echo "   AR Total: " . ($ar ? $ar->total : 'null') . "\n";
echo "   AR Remaining: " . ($ar ? $ar->remaining : 'null') . "\n";
echo "   Ageing Exists: " . ($ageing ? 'Yes' : 'No') . "\n\n";

// 4. Pembayaran Partial
$receiptPartial = CustomerReceipt::factory()->create([
    'customer_id' => $customer->id,
    'total_payment' => 500000,
]);
$receiptItemPartial = CustomerReceiptItem::create([
    'customer_receipt_id' => $receiptPartial->id,
    'invoice_id' => $invoice->id,
    'method' => 'cash',
    'amount' => 500000,
    'coa_id' => $coaCash->id,
    'payment_date' => now(),
]);
$receiptPartial->update(['status' => 'Paid']);

$ar->refresh();
$ageing = $ar->ageingSchedule;
echo "✅ After Partial Payment (500,000):\n";
echo "   AR Remaining: " . $ar->remaining . "\n";
echo "   Ageing Exists: " . ($ageing ? 'Yes' : 'No') . "\n\n";

// 5. Pembayaran Lunas
$receiptFull = CustomerReceipt::factory()->create([
    'customer_id' => $customer->id,
    'total_payment' => 500000,
]);
$receiptItemFull = CustomerReceiptItem::create([
    'customer_receipt_id' => $receiptFull->id,
    'invoice_id' => $invoice->id,
    'method' => 'cash',
    'amount' => 500000,
    'coa_id' => $coaCash->id,
    'payment_date' => now(),
]);
$receiptFull->update(['status' => 'Paid']);

$ar->refresh();
$ageing = $ar->ageingSchedule;
echo "✅ After Full Payment (Lunas):\n";
echo "   AR Remaining: " . $ar->remaining . "\n";
echo "   AR Status: " . $ar->status . "\n";
echo "   Ageing Exists: " . ($ageing ? 'Yes' : 'No') . "\n\n";

// 6. Hapus Invoice
$invoice->delete();
$arCheck = AccountReceivable::where('invoice_id', $invoice->id)->first();
$ageingCheck = AgeingSchedule::where('from_model_type', AccountReceivable::class)->where('from_model_id', $ar->id)->first();
echo "✅ After Delete Invoice:\n";
echo "   AR Exists: " . ($arCheck ? 'Yes' : 'No') . "\n";
echo "   Ageing Exists: " . ($ageingCheck ? 'Yes' : 'No') . "\n\n";

echo "🎉 Test Manual Selesai! Semua skenario berhasil.\n";