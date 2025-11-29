<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;

echo "Test Customer Receipt - Partial and Full Payment\n";
echo "================================================\n";

// 1. Get existing customer and product
$customer = App\Models\Customer::first();
$product = App\Models\Product::first();
$user = App\Models\User::first();
$warehouse = App\Models\Warehouse::first();

if (!$customer || !$product || !$user) {
    echo 'Customer, Product, or User not found!' . PHP_EOL;
    exit;
}

echo 'Customer: ' . $customer->name . PHP_EOL;
echo 'Product: ' . $product->name . PHP_EOL;
echo PHP_EOL;

// 2. Create Sale Order
$so = App\Models\SaleOrder::create([
    'customer_id' => $customer->id,
    'so_number' => 'SO-TEST-CR-' . date('YmdHis'),
    'order_date' => Carbon::now(),
    'status' => 'approved',
    'total_amount' => 10000000, // 10jt
    'warehouse_id' => $warehouse->id ?? 1,
    'created_by' => $user->id,
]);

// Create SO Item
$soItem = App\Models\SaleOrderItem::create([
    'sale_order_id' => $so->id,
    'product_id' => $product->id,
    'quantity' => 2,
    'unit_price' => 5000000,
    'subtotal' => 10000000,
    'warehouse_id' => $warehouse->id ?? 1,
]);

echo '✅ Sale Order Created: ' . $so->so_number . PHP_EOL;

// 3. Create Sales Invoice from SO
$invoice = App\Models\Invoice::create([
    'invoice_number' => 'INV-TEST-CR-' . date('YmdHis'),
    'from_model_type' => App\Models\SaleOrder::class,
    'from_model_id' => $so->id,
    'customer_id' => $customer->id,
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

// Create Account Receivable
$accountReceivable = App\Models\AccountReceivable::create([
    'invoice_id' => $invoice->id,
    'customer_id' => $customer->id,
    'total' => 10000000,
    'paid' => 0,
    'remaining' => 10000000,
    'status' => 'Belum Lunas',
    'created_by' => $user->id,
]);

echo '✅ Invoice Created: ' . $invoice->invoice_number . ' - Total: ' . number_format($invoice->total, 0, ',', '.') . PHP_EOL;
echo '✅ Account Receivable Created - Remaining: ' . number_format($accountReceivable->remaining, 0, ',', '.') . PHP_EOL;
echo PHP_EOL;

// 4. Test Partial Receipt
echo "=== PARTIAL RECEIPT TEST ===\n";
$coa = App\Models\ChartOfAccount::where('code', '1111.01')->first(); // Bank account

$partialReceipt = App\Models\CustomerReceipt::create([
    'customer_id' => $customer->id,
    'selected_invoices' => [$invoice->id],
    'invoice_receipts' => [['amount' => 4000000, 'invoice_id' => $invoice->id]], // Partial 4jt
    'payment_date' => Carbon::now(),
    'total_payment' => 4000000,
    'coa_id' => $coa->id ?? 1,
    'payment_method' => 'Bank Transfer',
    'notes' => 'Partial receipt test',
    'status' => 'Partial',
]);

// Create CustomerReceiptItem for partial receipt
$partialReceipt->customerReceiptItem()->create([
    'invoice_id' => $invoice->id,
    'amount' => 4000000,
    'notes' => 'Partial receipt test',
    'method' => 'Bank Transfer',
    'coa_id' => $coa->id ?? 1,
    'payment_date' => Carbon::now(),
]);

echo '✅ Partial Customer Receipt Created: ID ' . $partialReceipt->id . ' - Amount: ' . number_format($partialReceipt->total_payment, 0, ',', '.') . PHP_EOL;

// Check Account Receivable after partial receipt
$accountReceivable->refresh();
echo 'Account Receivable Status: Total=' . number_format($accountReceivable->total, 0, ',', '.') .
     ', Paid=' . number_format($accountReceivable->paid, 0, ',', '.') .
     ', Remaining=' . number_format($accountReceivable->remaining, 0, ',', '.') .
     ', Status=' . $accountReceivable->status . PHP_EOL;

// Debug: Check CustomerReceiptItem
$partialItems = $partialReceipt->customerReceiptItem;
echo 'Partial Receipt Items: ' . $partialItems->count() . PHP_EOL;
foreach ($partialItems as $item) {
    echo '  - Item ID: ' . $item->id . ', Amount: ' . $item->amount . ', Invoice: ' . $item->invoice_id . PHP_EOL;
}

// Check Invoice status
$invoice->refresh();
echo 'Invoice Status: ' . $invoice->status . PHP_EOL;

// Check Journal Entries for partial receipt
$journals = $partialReceipt->journalEntries;
echo 'Journal Entries for Partial Receipt: ' . $journals->count() . ' entries' . PHP_EOL;
foreach ($journals as $j) {
    echo '  - ' . ($j->debit > 0 ? 'Debit' : 'Credit') . ': ' . number_format(abs($j->debit + $j->credit), 0, ',', '.') .
         ' - Account: ' . $j->coa->name . ' - Desc: ' . $j->description . PHP_EOL;
}
echo PHP_EOL;

// 5. Test Full Receipt
echo "=== FULL RECEIPT TEST ===\n";

$fullReceipt = App\Models\CustomerReceipt::create([
    'customer_id' => $customer->id,
    'selected_invoices' => [$invoice->id],
    'invoice_receipts' => [['amount' => 6000000, 'invoice_id' => $invoice->id]], // Remaining 6jt
    'payment_date' => Carbon::now(),
    'total_payment' => 6000000,
    'coa_id' => $coa->id ?? 1,
    'payment_method' => 'Bank Transfer',
    'notes' => 'Full receipt test',
    'status' => 'Paid', // This should trigger journal creation
]);

// Create CustomerReceiptItem for full receipt
$fullReceipt->customerReceiptItem()->create([
    'invoice_id' => $invoice->id,
    'amount' => 6000000,
    'notes' => 'Full receipt test',
    'method' => 'Bank Transfer',
    'coa_id' => $coa->id ?? 1,
    'payment_date' => Carbon::now(),
]);

echo '✅ Full Customer Receipt Created: ID ' . $fullReceipt->id . ' - Amount: ' . number_format($fullReceipt->total_payment, 0, ',', '.') . PHP_EOL;

// Manually trigger AR update for all receipt items
$observer = new App\Observers\CustomerReceiptItemObserver();
$allItems = App\Models\CustomerReceiptItem::where('invoice_id', $invoice->id)->get();
foreach ($allItems as $item) {
    // Reset AR first
    $ar = App\Models\AccountReceivable::where('invoice_id', $item->invoice_id)->first();
    if ($ar) {
        $ar->paid = 0;
        $ar->remaining = $ar->total;
        $ar->save();
    }
}

// Then recalculate from all items
$totalPaid = 0;
foreach ($allItems as $item) {
    $totalPaid += $item->amount;
    echo 'Calling observer for item ' . $item->id . ' amount ' . $item->amount . PHP_EOL;
    $observer->created($item);
    $ar = App\Models\AccountReceivable::where('invoice_id', $item->invoice_id)->first();
    if ($ar) {
        echo 'AR paid after item ' . $item->id . ': ' . $ar->paid . PHP_EOL;
    }
}

echo 'Total paid from all items: ' . $totalPaid . PHP_EOL;

// Check final Account Receivable status
$finalAR = App\Models\AccountReceivable::where('invoice_id', $invoice->id)->first();
echo 'Final Account Receivable Status: Total=' . number_format($finalAR->total, 0, ',', '.') .
     ', Paid=' . number_format($finalAR->paid, 0, ',', '.') .
     ', Remaining=' . number_format($finalAR->remaining, 0, ',', '.') .
     ', Status=' . $finalAR->status . PHP_EOL;

// Check Invoice status
$invoice->refresh();
echo 'Invoice Status: ' . $invoice->status . PHP_EOL;

// Check Journal Entries for full receipt
$journals = $fullReceipt->journalEntries;
echo 'Journal Entries for Full Receipt: ' . $journals->count() . ' entries' . PHP_EOL;
foreach ($journals as $j) {
    echo '  - ' . ($j->debit > 0 ? 'Debit' : 'Credit') . ': ' . number_format(abs($j->debit + $j->credit), 0, ',', '.') .
         ' - Account: ' . $j->coa->name . ' - Desc: ' . $j->description . PHP_EOL;
}

echo PHP_EOL;
echo "Test completed successfully!\n";