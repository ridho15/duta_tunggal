<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use Illuminate\Support\Facades\DB;

echo "Resetting test data for Siklus A (P2P)...\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0');

// 1. Delete test receipts & returns
PurchaseReturnItem::truncate();
PurchaseReturn::truncate();
PurchaseReceiptItem::truncate();
PurchaseReceipt::truncate();

// 2. Delete test purchase invoices created for supplier 15
$invIds = Invoice::where('supplier_id', 15)->orWhere('supplier_invoice_number', 'INV-PJ-2026-9901')->pluck('id')->all();
if (!empty($invIds)) {
    InvoiceItem::whereIn('invoice_id', $invIds)->delete();
    AccountPayable::whereIn('invoice_id', $invIds)->delete();
    Invoice::whereIn('id', $invIds)->delete();
}

// 3. Delete test vendor payments for supplier 15
$paymentIds = VendorPayment::where('supplier_id', 15)->orWhere('transfer_reference_number', 'TRF-BCA-202611-9988')->pluck('id')->all();
if (!empty($paymentIds)) {
    VendorPaymentDetail::whereIn('vendor_payment_id', $paymentIds)->delete();
    VendorPayment::whereIn('id', $paymentIds)->delete();
}

// 4. Reset PO 13 status and OR fulfillment
PurchaseOrder::where('id', 13)->update(['status' => 'approved']);
OrderRequestItem::where('id', 146)->update(['fulfilled_quantity' => 0]);

DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo "P2P test data reset completed successfully.\n";

