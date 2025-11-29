<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;

echo "Test Vendor Payment - Partial and Full Payment\n";
echo "==============================================\n";

// 1. Get existing supplier and product
$supplier = App\Models\Supplier::first();
$product = App\Models\Product::first();
$user = App\Models\User::first();
$warehouse = App\Models\Warehouse::first();

if (!$supplier || !$product || !$user) {
    echo 'Supplier, Product, or User not found!' . PHP_EOL;
    exit;
}

echo 'Supplier: ' . $supplier->name . PHP_EOL;
echo 'Product: ' . $product->name . PHP_EOL;
echo PHP_EOL;

// 2. Create Purchase Order
$po = App\Models\PurchaseOrder::create([
    'supplier_id' => $supplier->id,
    'po_number' => 'PO-TEST-VP-' . date('YmdHis'),
    'order_date' => Carbon::now(),
    'status' => 'approved',
    'total_amount' => 10000000, // 10jt
    'warehouse_id' => $warehouse->id ?? 1,
    'tempo_hutang' => 30,
    'created_by' => $user->id,
]);

// Create PO Item
$currency = App\Models\Currency::first();
$poItem = App\Models\PurchaseOrderItem::create([
    'purchase_order_id' => $po->id,
    'product_id' => $product->id,
    'quantity' => 2,
    'unit_price' => 5000000,
    'subtotal' => 10000000,
    'currency_id' => $currency->id ?? 1,
]);

echo '✅ Purchase Order Created: ' . $po->po_number . PHP_EOL;

// 3. Create Purchase Invoice from PO
$invoice = App\Models\Invoice::create([
    'invoice_number' => 'INV-TEST-VP-' . date('YmdHis'),
    'from_model_type' => App\Models\Supplier::class,
    'from_model_id' => $supplier->id,
    'supplier_id' => $supplier->id,
    'invoice_date' => Carbon::now(),
    'due_date' => Carbon::now()->addDays(30),
    'total' => 10000000,
    'status' => 'unpaid',
    'created_by' => $user->id,
]);

// Create Invoice Item
App\Models\InvoiceItem::create([
    'invoice_id' => $invoice->id,
    'product_id' => $product->id,
    'quantity' => 2,
    'unit_price' => 5000000,
    'subtotal' => 10000000,
]);

// Create Account Payable
$accountPayable = App\Models\AccountPayable::create([
    'invoice_id' => $invoice->id,
    'supplier_id' => $supplier->id,
    'total' => 10000000,
    'paid' => 0,
    'remaining' => 10000000,
    'status' => 'Belum Lunas',
    'created_by' => $user->id,
]);

echo '✅ Invoice Created: ' . $invoice->invoice_number . ' - Total: ' . number_format($invoice->total, 0, ',', '.') . PHP_EOL;
echo '✅ Account Payable Created - Remaining: ' . number_format($accountPayable->remaining, 0, ',', '.') . PHP_EOL;
echo PHP_EOL;

// 4. Test Partial Payment
echo "=== PARTIAL PAYMENT TEST ===\n";
$coa = App\Models\ChartOfAccount::where('code', '1111.01')->first(); // Bank account

$partialPayment = App\Models\VendorPayment::create([
    'supplier_id' => $supplier->id,
    'selected_invoices' => [$invoice->id],
    'invoice_receipts' => [['amount' => 4000000, 'invoice_id' => $invoice->id]], // Partial 4jt
    'payment_date' => Carbon::now(),
    'total_payment' => 4000000,
    'coa_id' => $coa->id ?? 1,
    'payment_method' => 'Bank Transfer',
    'notes' => 'Partial payment test',
    'status' => 'Partial',
]);

// Create VendorPaymentDetail for partial payment
$partialPayment->vendorPaymentDetail()->create([
    'invoice_id' => $invoice->id,
    'amount' => 4000000,
    'notes' => 'Partial payment test',
    'method' => 'Bank Transfer',
    'coa_id' => $coa->id ?? 1,
    'payment_date' => Carbon::now(),
]);

echo '✅ Partial Vendor Payment Created: ID ' . $partialPayment->id . ' - Amount: ' . number_format($partialPayment->total_payment, 0, ',', '.') . PHP_EOL;

// Manually update Account Payable for partial payment
$observer = new App\Observers\VendorPaymentObserver();
$observer->updateAccountPayableAndInvoiceStatus($partialPayment);

// Check Account Payable after partial payment
$accountPayable->refresh();
echo 'Account Payable Status: Total=' . number_format($accountPayable->total, 0, ',', '.') .
     ', Paid=' . number_format($accountPayable->paid, 0, ',', '.') .
     ', Remaining=' . number_format($accountPayable->remaining, 0, ',', '.') .
     ', Status=' . $accountPayable->status . PHP_EOL;

// Check Invoice status
$invoice->refresh();
echo 'Invoice Status: ' . $invoice->status . PHP_EOL;

// Check Journal Entries for partial payment
$journals = $partialPayment->journalEntries;
echo 'Journal Entries for Partial Payment: ' . $journals->count() . ' entries' . PHP_EOL;
foreach ($journals as $j) {
    echo '  - ' . ($j->debit > 0 ? 'Debit' : 'Credit') . ': ' . number_format(abs($j->debit + $j->credit), 0, ',', '.') .
         ' - Account: ' . $j->coa->name . ' - Desc: ' . $j->description . PHP_EOL;
}
echo PHP_EOL;

// 5. Test Full Payment
echo "=== FULL PAYMENT TEST ===\n";

$fullPayment = App\Models\VendorPayment::create([
    'supplier_id' => $supplier->id,
    'selected_invoices' => [$invoice->id],
    'invoice_receipts' => [['amount' => 6000000, 'invoice_id' => $invoice->id]], // Remaining 6jt
    'payment_date' => Carbon::now(),
    'total_payment' => 6000000,
    'coa_id' => $coa->id ?? 1,
    'payment_method' => 'Bank Transfer',
    'notes' => 'Full payment test',
    'status' => 'Paid', // This should trigger journal creation
]);

// Create VendorPaymentDetail for full payment
$fullPayment->vendorPaymentDetail()->create([
    'invoice_id' => $invoice->id,
    'amount' => 6000000,
    'notes' => 'Full payment test',
    'method' => 'Bank Transfer',
    'coa_id' => $coa->id ?? 1,
    'payment_date' => Carbon::now(),
]);

echo '✅ Full Vendor Payment Created: ID ' . $fullPayment->id . ' - Amount: ' . number_format($fullPayment->total_payment, 0, ',', '.') . PHP_EOL;

// Check Account Payable after full payment
$accountPayable->refresh();
echo 'Account Payable Status: Total=' . number_format($accountPayable->total, 0, ',', '.') .
     ', Paid=' . number_format($accountPayable->paid, 0, ',', '.') .
     ', Remaining=' . number_format($accountPayable->remaining, 0, ',', '.') .
     ', Status=' . $accountPayable->status . PHP_EOL;

// Check Invoice status
$invoice->refresh();
echo 'Invoice Status: ' . $invoice->status . PHP_EOL;
echo 'Invoice exists in AP: ' . ($accountPayable->invoice ? 'Yes' : 'No') . PHP_EOL;

// Manually update invoice status if needed
if ($accountPayable->remaining <= 0.01) {
    $invoice->status = 'paid';
    $invoice->save();
    echo 'Manually updated Invoice Status to: ' . $invoice->status . PHP_EOL;
}

// Check Journal Entries for full payment
$journals = $fullPayment->journalEntries;
echo 'Journal Entries for Full Payment: ' . $journals->count() . ' entries' . PHP_EOL;
foreach ($journals as $j) {
    echo '  - ' . ($j->debit > 0 ? 'Debit' : 'Credit') . ': ' . number_format(abs($j->debit + $j->credit), 0, ',', '.') .
         ' - Account: ' . $j->coa->name . ' - Desc: ' . $j->description . PHP_EOL;
}

echo PHP_EOL;
echo "Test completed successfully!\n";