<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\Cabang;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Setup data
$user = User::first() ?? User::factory()->create();

// Set auth user
Auth::login($user);
$customer = Customer::factory()->create();
$product = Product::factory()->create(['cost_price' => 50000]);
$branch = Cabang::factory()->create();
$warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

// Create deposit for customer
$deposit = Deposit::create([
    'from_model_type' => Customer::class,
    'from_model_id' => $customer->id,
    'amount' => 1000000,
    'used_amount' => 0,
    'remaining_amount' => 1000000,
    'coa_id' => ChartOfAccount::where('code', '1111.01')->first()?->id,
    'status' => 'active',
    'created_by' => $user->id,
]);

echo "✅ Created deposit: {$deposit->id} with amount Rp " . number_format($deposit->amount, 0, ',', '.') . "\n";

// Create sales order
$saleOrder = SaleOrder::factory()->create([
    'customer_id' => $customer->id,
    'status' => 'approved',
    'created_by' => $user->id,
]);

$saleOrder->saleOrderItem()->create([
    'product_id' => $product->id,
    'quantity' => 10,
    'unit_price' => 100000,
    'warehouse_id' => $warehouse->id,
]);

echo "✅ Created sales order: {$saleOrder->so_number}\n";

// Create delivery order
$deliveryOrder = DeliveryOrder::factory()->create([
    'status' => 'confirmed',
    'warehouse_id' => $warehouse->id,
]);

echo "✅ Created delivery order: {$deliveryOrder->do_number}\n";

// Create invoice
$invoice = Invoice::factory()->create([
    'from_model_type' => SaleOrder::class,
    'from_model_id' => $saleOrder->id,
    'total' => 1000000,
    'status' => 'unpaid',
]);

echo "✅ Created invoice: {$invoice->invoice_number} with total Rp " . number_format($invoice->total, 0, ',', '.') . "\n";

// Create customer receipt with deposit payment
$customerReceipt = CustomerReceipt::factory()->create([
    'customer_id' => $customer->id,
    'payment_date' => now(),
    'total_payment' => 500000,
    'status' => 'Paid',
    'payment_method' => 'Deposit',
]);

$customerReceiptItem = CustomerReceiptItem::create([
    'customer_receipt_id' => $customerReceipt->id,
    'invoice_id' => $invoice->id,
    'method' => 'Deposit',
    'amount' => 500000,
    'payment_date' => now(),
]);

echo "✅ Created customer receipt with deposit payment: {$customerReceipt->id}\n";
echo "   - Payment Method: {$customerReceipt->payment_method}\n";
echo "   - Amount: Rp " . number_format($customerReceiptItem->amount, 0, ',', '.') . "\n";

// Check deposit update
$deposit->refresh();
echo "\n💰 Deposit Status:\n";
echo "- Total: Rp " . number_format($deposit->amount, 0, ',', '.') . "\n";
echo "- Used: Rp " . number_format($deposit->used_amount, 0, ',', '.') . "\n";
echo "- Remaining: Rp " . number_format($deposit->remaining_amount, 0, ',', '.') . "\n";

// Check journal entries
$journalEntries = JournalEntry::where('source_type', CustomerReceiptItem::class)
    ->where('source_id', $customerReceiptItem->id)
    ->with('coa')
    ->get();

echo "\n📈 Journal Entries from Deposit Payment:\n";
echo "=========================================\n";
$totalDebit = 0;
$totalCredit = 0;
foreach ($journalEntries as $journal) {
    $coa = $journal->coa;
    if (!$coa) {
        echo sprintf("%-8s | %-15s | %10s | %10s | %s\n",
            $journal->journal_type,
            'NO COA',
            number_format($journal->debit, 0, ',', '.'),
            number_format($journal->credit, 0, ',', '.'),
            $journal->description
        );
        continue;
    }
    echo sprintf("%-8s | %-15s | %10s | %10s | %s\n",
        $journal->journal_type,
        $coa->code . ' - ' . substr($coa->name, 0, 20),
        number_format($journal->debit, 0, ',', '.'),
        number_format($journal->credit, 0, ',', '.'),
        $journal->description
    );
    $totalDebit += $journal->debit;
    $totalCredit += $journal->credit;
}

echo "\nBalance Check:\n";
echo "- Total Debit: Rp " . number_format($totalDebit, 0, ',', '.') . "\n";
echo "- Total Credit: Rp " . number_format($totalCredit, 0, ',', '.') . "\n";
echo "- Difference: Rp " . number_format($totalDebit - $totalCredit, 0, ',', '.') . "\n";

if ($totalDebit == $totalCredit) {
    echo "✅ Journal entries are balanced!\n";
} else {
    echo "❌ Journal entries are NOT balanced!\n";
}

echo "\n🎉 DEPOSIT PAYMENT TEST COMPLETED!\n";