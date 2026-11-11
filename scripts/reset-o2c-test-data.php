<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use Illuminate\Support\Facades\DB;

echo "Resetting test data for Siklus B (Order-to-Cash / O2C)...\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0');

// 1. Delete customer returns
CustomerReturnItem::truncate();
CustomerReturn::truncate();

// 2. Delete test customer receipts
$testReceipts = CustomerReceipt::where('customer_id', 1)
    ->orWhere('payment_reference', 'TRF-TEST-O2C-001')
    ->pluck('id')->all();

if (!empty($testReceipts)) {
    CustomerReceiptItem::whereIn('customer_receipt_id', $testReceipts)->delete();
    CustomerReceipt::whereIn('id', $testReceipts)->delete();
}

// 3. Delete test sales invoices and AR
$testInvoices = Invoice::where('customer_name', 'like', '%PT Maju Bersama%')
    ->orWhere('invoice_number', 'like', 'INV-20261110-%')
    ->orWhere('invoice_number', 'like', 'INV-SO-%')
    ->orWhere('tax_invoice_number', 'like', '%O2C%')
    ->pluck('id')->all();

if (!empty($testInvoices)) {
    InvoiceItem::whereIn('invoice_id', $testInvoices)->delete();
    AccountReceivable::whereIn('invoice_id', $testInvoices)->delete();
    Invoice::whereIn('id', $testInvoices)->delete();
}

// 4. Delete test Surat Jalan & Delivery Orders
$testSjs = SuratJalan::where('sj_number', 'like', 'SJ-20261110-%')
    ->orWhere('sj_number', 'like', '%O2C%')
    ->pluck('id')->all();

if (!empty($testSjs)) {
    DB::table('surat_jalan_delivery_orders')->whereIn('surat_jalan_id', $testSjs)->delete();
    SuratJalan::whereIn('id', $testSjs)->delete();
}

$testDos = DeliveryOrder::where('do_number', 'like', 'DO-GU001-20261110-%')
    ->orWhere('notes', 'like', '%O2C%')
    ->orWhere('do_number', 'like', 'DO-O2C-%')
    ->orWhere('id', '>=', 26)
    ->pluck('id')->all();

if (!empty($testDos)) {
    DeliveryOrderItem::whereIn('delivery_order_id', $testDos)->delete();
    DeliveryOrder::whereIn('id', $testDos)->delete();
}

// 5. Delete test Sale Orders for customer 1
$testSos = SaleOrder::where('customer_id', 1)
    ->where(function ($q) {
        $q->where('id', '>=', 26)
          ->orWhere('so_number', 'like', 'SO-0000%')
          ->orWhere('so_number', 'like', 'SO-O2C-%');
    })
    ->pluck('id')->all();

if (!empty($testSos)) {
    SaleOrderItem::whereIn('sale_order_id', $testSos)->delete();
    SaleOrder::whereIn('id', $testSos)->delete();
}

// 6. Delete test Quotations for customer 1
$testQuotes = Quotation::where('customer_id', 1)
    ->where(function ($q) {
        $q->where('id', '>=', 26)
          ->orWhere('quotation_number', 'like', 'QO-20261110-%')
          ->orWhere('quotation_number', 'like', 'QT-O2C-%');
    })
    ->pluck('id')->all();

if (!empty($testQuotes)) {
    QuotationItem::whereIn('quotation_id', $testQuotes)->delete();
    Quotation::whereIn('id', $testQuotes)->delete();
}

// 7. Ensure Customer 1 credit limit is clean
Customer::where('id', 1)->update([
    'kredit_limit' => 50000000,
    'tipe_pembayaran' => 'Kredit',
]);

// 8. Reset product 4 stock (Monitor LED 24 inch) in Gudang Utama to 50 available, 0 reserved
InventoryStock::updateOrCreate(
    ['product_id' => 4, 'warehouse_id' => 1],
    ['qty_available' => 50, 'qty_reserved' => 0]
);

DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "Siklus B test data reset completed successfully.\n";
