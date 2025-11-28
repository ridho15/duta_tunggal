<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\JournalEntry;
use App\Services\DeliveryOrderService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "🚀 Starting Complete Sales Flow Demo\n";
echo "=====================================\n\n";

// Use existing data if available
echo "👤 Using existing user...\n";
$user = User::first(); // Use first available user
if (!$user) {
    $user = User::factory()->create([
        'name' => 'Demo User',
        'email' => 'demo' . time() . '@example.com'
    ]);
}

echo "🏢 Using existing customer...\n";
$customer = Customer::first();
if (!$customer) {
    $customer = Customer::factory()->create([
        'code' => 'CUST' . time(),
        'name' => 'Demo Customer',
        'tipe' => 'PKP',
        'tempo_kredit' => 30,
        'kredit_limit' => 10000000,
        'tipe_pembayaran' => 'Kredit',
    ]);
}

echo "🏭 Using existing warehouse...\n";
$warehouse = Warehouse::first();
if (!$warehouse) {
    $warehouse = Warehouse::factory()->create([
        'name' => 'Demo Warehouse',
    ]);
}

echo "📦 Using existing product...\n";
$product = Product::first();
if (!$product) {
    // Create product manually
    $product = new Product();
    $product->name = 'Demo Product';
    $product->cost_price = 10000;
    $product->sell_price = 15000;
    $product->is_active = true;
    $product->uom_id = 1;
    $product->save();
}

// Set up COAs
$inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
$cogsCoa = ChartOfAccount::where('code', '5000')->first();
if ($inventoryCoa && $cogsCoa) {
    $product->update([
        'inventory_coa_id' => $inventoryCoa->id,
        'cogs_coa_id' => $cogsCoa->id,
    ]);
}

echo "💰 Setting up COAs...\n";
$currency = Currency::first();
if (!$currency) {
    $currency = Currency::factory()->create(['code' => 'IDR']);
}

// ==========================================
// STEP 1: CREATE QUOTATION
// ==========================================
echo "\n📄 STEP 1: Creating Quotation\n";
echo "------------------------------\n";

$quotation = Quotation::factory()->create([
    'quotation_number' => 'QO-' . date('Ymd-His'),
    'customer_id' => $customer->id,
    'date' => now(),
    'valid_until' => now()->addDays(30),
    'status' => 'approve',
    'created_by' => $user->id,
]);

$quotationItem = QuotationItem::create([
    'quotation_id' => $quotation->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'unit_price' => 15000,
    'discount' => 0,
    'tax' => 0,
]);

echo "✅ Quotation created: {$quotation->quotation_number}\n";
echo "   - Customer: {$customer->name}\n";
echo "   - Product: {$product->name} (10 units @ Rp 15,000)\n";
echo "   - Total: Rp " . number_format(150000, 0, ',', '.') . "\n";

// ==========================================
// STEP 2: CONVERT TO SALES ORDER
// ==========================================
echo "\n📋 STEP 2: Converting to Sales Order\n";
echo "-------------------------------------\n";

$saleOrder = SaleOrder::factory()->create([
    'so_number' => 'SO-' . date('Ymd-His'),
    'quotation_id' => $quotation->id,
    'order_date' => now(),
    'delivery_date' => now()->addDays(7),
    'status' => 'approved',
    'customer_id' => $customer->id,
    'created_by' => $user->id,
]);

$saleOrderItem = SaleOrderItem::create([
    'sale_order_id' => $saleOrder->id,
    'product_id' => $product->id,
    'quantity' => 10,
    'unit_price' => 15000,
    'warehouse_id' => $warehouse->id,
]);

echo "✅ Sales Order created: {$saleOrder->so_number}\n";
echo "   - From Quotation: {$quotation->quotation_number}\n";
echo "   - Status: {$saleOrder->status}\n";

// Create initial inventory stock
$inventoryStock = InventoryStock::factory()->create([
    'product_id' => $product->id,
    'warehouse_id' => $warehouse->id,
    'qty_available' => 20,
    'qty_reserved' => 0,
]);

echo "📦 Initial inventory: {$inventoryStock->qty_available} units\n";

// ==========================================
// STEP 3: CREATE DELIVERY ORDER
// ==========================================
echo "\n🚚 STEP 3: Creating Delivery Order\n";
echo "-----------------------------------\n";

$deliveryOrder = DeliveryOrder::factory()->create([
    'do_number' => 'DO-' . date('Ymd-His'),
    'delivery_date' => now(),
    'status' => 'approved',
    'created_by' => $user->id,
]);

// Attach sale order to delivery order
$deliveryOrder->salesOrders()->attach($saleOrder->id);

$deliveryOrderItem = DeliveryOrderItem::factory()->create([
    'delivery_order_id' => $deliveryOrder->id,
    'sale_order_item_id' => $saleOrderItem->id,
    'product_id' => $product->id,
    'quantity' => 10,
]);

echo "✅ Delivery Order created: {$deliveryOrder->do_number}\n";
echo "   - Status: {$deliveryOrder->status}\n";
echo "   - Quantity: {$deliveryOrderItem->quantity} units\n";

// ==========================================
// STEP 4: POST DELIVERY ORDER (Create Journal Entries)
// ==========================================
echo "\n📊 STEP 4: Posting Delivery Order to Ledger\n";
echo "--------------------------------------------\n";

$deliveryOrderService = app(DeliveryOrderService::class);
$postResult = $deliveryOrderService->postDeliveryOrder($deliveryOrder);

echo "✅ Delivery Order posted: {$postResult['status']}\n";

// Manually reduce inventory stock (as per existing test pattern)
$inventoryStock->decrement('qty_available', 10);
$inventoryStock->refresh();

echo "📦 Inventory after delivery: {$inventoryStock->qty_available} units\n";

// Show journal entries created by delivery order
$doJournals = JournalEntry::where('source_type', DeliveryOrder::class)
    ->where('source_id', $deliveryOrder->id)
    ->get();

echo "\n📈 Journal Entries from Delivery Order:\n";
echo "----------------------------------------\n";
foreach ($doJournals as $journal) {
    $coa = $journal->chartOfAccount;
    echo sprintf("%-8s | %-15s | %10s | %10s | %s\n",
        $journal->journal_type,
        $coa->code . ' - ' . substr($coa->name, 0, 20),
        number_format($journal->debit, 0, ',', '.'),
        number_format($journal->credit, 0, ',', '.'),
        $journal->description
    );
}

// ==========================================
// STEP 5: CREATE INVOICE
// ==========================================
echo "\n🧾 STEP 5: Creating Invoice\n";
echo "--------------------------\n";

$invoice = Invoice::factory()->create([
    'from_model_type' => SaleOrder::class,
    'from_model_id' => $saleOrder->id,
    'invoice_number' => 'INV-' . date('Ymd-His'),
    'invoice_date' => now(),
    'due_date' => now()->addDays(30),
    'subtotal' => 150000,
    'tax' => 0,
    'total' => 150000,
    'status' => 'Unpaid',
    'delivery_orders' => [$deliveryOrder->id],
]);

echo "✅ Invoice created: {$invoice->invoice_number}\n";
echo "   - Total: Rp " . number_format($invoice->total, 0, ',', '.') . "\n";
echo "   - Due Date: {$invoice->due_date->format('d/m/Y')}\n";

// Invoice observer should create AR and journal entries automatically
$accountReceivable = \App\Models\AccountReceivable::where('invoice_id', $invoice->id)->first();
echo "💰 Account Receivable created: Rp " . number_format($accountReceivable->total, 0, ',', '.') . "\n";

// Show journal entries created by invoice
$invoiceJournals = JournalEntry::where('source_type', Invoice::class)
    ->where('source_id', $invoice->id)
    ->get();

echo "\n📈 Journal Entries from Invoice:\n";
echo "---------------------------------\n";
foreach ($invoiceJournals as $journal) {
    $coa = $journal->chartOfAccount;
    echo sprintf("%-8s | %-15s | %10s | %10s | %s\n",
        $journal->journal_type,
        $coa->code . ' - ' . substr($coa->name, 0, 20),
        number_format($journal->debit, 0, ',', '.'),
        number_format($journal->credit, 0, ',', '.'),
        $journal->description
    );
}

// ==========================================
// STEP 6: CREATE CUSTOMER RECEIPT (PAYMENT)
// ==========================================
echo "\n💳 STEP 6: Creating Customer Payment\n";
echo "-------------------------------------\n";

$customerReceipt = CustomerReceipt::factory()->create([
    'customer_id' => $customer->id,
    'payment_date' => now(),
    'total_payment' => 150000,
    'status' => 'Paid',
]);

$customerReceiptItem = CustomerReceiptItem::create([
    'customer_receipt_id' => $customerReceipt->id,
    'invoice_id' => $invoice->id,
    'method' => 'cash',
    'amount' => 150000,
    'coa_id' => ChartOfAccount::where('code', '1111.01')->first()?->id, // Kas Besar
    'payment_date' => now(),
]);

echo "✅ Customer Receipt created\n";
echo "   - Payment Method: {$customerReceiptItem->method}\n";
echo "   - Amount: Rp " . number_format($customerReceiptItem->amount, 0, ',', '.') . "\n";

// CustomerReceiptItem observer should update AR and create journal entries automatically
$accountReceivable->refresh();
echo "💰 Account Receivable updated - Remaining: Rp " . number_format($accountReceivable->remaining, 0, ',', '.') . "\n";

$invoice->refresh();
echo "🧾 Invoice status updated to: {$invoice->status}\n";

// Show journal entries created by payment
$paymentJournals = JournalEntry::where('source_type', CustomerReceiptItem::class)
    ->where('source_id', $customerReceiptItem->id)
    ->get();

echo "\n📈 Journal Entries from Payment:\n";
echo "----------------------------------\n";
foreach ($paymentJournals as $journal) {
    $coa = $journal->chartOfAccount;
    echo sprintf("%-8s | %-15s | %10s | %10s | %s\n",
        $journal->journal_type,
        $coa->code . ' - ' . substr($coa->name, 0, 20),
        number_format($journal->debit, 0, ',', '.'),
        number_format($journal->credit, 0, ',', '.'),
        $journal->description
    );
}

// ==========================================
// FINAL SUMMARY
// ==========================================
echo "\n🎉 SALES FLOW COMPLETED SUCCESSFULLY!\n";
echo "=====================================\n";

echo "📊 Final Status:\n";
echo "- Quotation: {$quotation->quotation_number} ({$quotation->status})\n";
echo "- Sales Order: {$saleOrder->so_number} ({$saleOrder->status})\n";
echo "- Delivery Order: {$deliveryOrder->do_number} ({$deliveryOrder->status})\n";
echo "- Invoice: {$invoice->invoice_number} ({$invoice->status})\n";
echo "- Customer Receipt: {$customerReceipt->id} ({$customerReceipt->status})\n";
echo "- Inventory: {$inventoryStock->qty_available} units remaining\n";
echo "- AR Balance: Rp " . number_format($accountReceivable->remaining, 0, ',', '.') . "\n";

// Show all journal entries summary
$allJournals = JournalEntry::whereIn('source_type', [
    DeliveryOrder::class,
    Invoice::class,
    CustomerReceiptItem::class
])->whereIn('source_id', [
    $deliveryOrder->id,
    $invoice->id,
    $customerReceiptItem->id
])->get();

echo "\n📈 Complete Journal Entries Summary:\n";
echo "=====================================\n";
echo sprintf("%-3s | %-8s | %-15s | %10s | %10s | %s\n", "No", "Type", "COA Code", "Debit", "Credit", "Description");
echo str_repeat("-", 80) . "\n";

$totalDebit = 0;
$totalCredit = 0;
$counter = 1;

foreach ($allJournals as $journal) {
    $coa = $journal->chartOfAccount;
    echo sprintf("%-3d | %-8s | %-15s | %10s | %10s | %s\n",
        $counter++,
        $journal->journal_type,
        $coa->code,
        number_format($journal->debit, 0, ',', '.'),
        number_format($journal->credit, 0, ',', '.'),
        $journal->description
    );
    $totalDebit += $journal->debit;
    $totalCredit += $journal->credit;
}

echo str_repeat("-", 80) . "\n";
echo sprintf("%-3s | %-8s | %-15s | %10s | %10s | %s\n",
    "", "TOTAL", "", number_format($totalDebit, 0, ',', '.'), number_format($totalCredit, 0, ',', '.'), "");

if ($totalDebit == $totalCredit) {
    echo "\n✅ Journal entries are BALANCED! ✓\n";
} else {
    echo "\n❌ Journal entries are NOT balanced!\n";
}

echo "\n🎯 Accounting Impact:\n";
echo "- Revenue: +Rp " . number_format(150000, 0, ',', '.') . "\n";
echo "- COGS: -Rp " . number_format(100000, 0, ',', '.') . "\n";
echo "- Cash: +Rp " . number_format(150000, 0, ',', '.') . "\n";
echo "- Inventory: -Rp " . number_format(100000, 0, ',', '.') . "\n";
echo "- Net Profit: +Rp " . number_format(50000, 0, ',', '.') . "\n";

echo "\n🏁 Demo completed successfully!\n";