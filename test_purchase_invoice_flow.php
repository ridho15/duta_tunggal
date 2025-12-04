<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Services\LedgerPostingService;

// Cari supplier, product, dan COA yang diperlukan
$supplier = Supplier::first();
$product = Product::first();
$inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
$unbilledPurchaseCoa = ChartOfAccount::where('code', '2100.10')->first();

if (!$supplier || !$product || !$inventoryCoa || !$unbilledPurchaseCoa) {
    echo 'Missing required data: supplier, product, or COAs' . PHP_EOL;
    exit;
}

// Pastikan product memiliki COA yang diperlukan
$product->update([
    'inventory_coa_id' => $inventoryCoa->id,
    'unbilled_purchase_coa_id' => $unbilledPurchaseCoa->id,
    'temporary_procurement_coa_id' => ChartOfAccount::where('code', '1140.99')->first()?->id ?? $inventoryCoa->id,
]);

// Buat Purchase Order
$po = PurchaseOrder::create([
    'po_number' => 'TEST-PO-' . now()->format('YmdHis'),
    'supplier_id' => $supplier->id,
    'warehouse_id' => 1,
    'tempo_hutang' => 30, // Tambahkan tempo_hutang
    'order_date' => now()->toDateString(),
    'expected_date' => now()->addDays(7)->toDateString(),
    'status' => 'approved',
    'subtotal' => 100000,
    'tax' => 10000,
    'total' => 110000,
    'ppn_rate' => 10,
]);

// Buat PO Item
PurchaseOrderItem::create([
    'purchase_order_id' => $po->id,
    'product_id' => $product->id,
    'currency_id' => 1, // Tambahkan currency_id
    'quantity' => 10,
    'unit_price' => 10000,
    'tax' => 1000,
    'subtotal' => 100000,
    'total' => 110000,
]);

echo 'Created PO: ' . $po->po_number . PHP_EOL;

// Buat Purchase Receipt
$receipt = PurchaseReceipt::create([
    'receipt_number' => 'TEST-RC-' . now()->format('YmdHis'),
    'purchase_order_id' => $po->id,
    'supplier_id' => $supplier->id,
    'receipt_date' => now()->toDateString(),
    'received_by' => 1,
    'currency_id' => 1, // Tambahkan currency_id
    'status' => 'completed',
    'subtotal' => 100000,
    'tax' => 10000,
    'total' => 110000,
]);

// Buat Receipt Item
PurchaseReceiptItem::create([
    'purchase_receipt_id' => $receipt->id,
    'product_id' => $product->id,
    'warehouse_id' => 1, // Tambahkan warehouse_id
    'quantity' => 10,
    'unit_price' => 10000,
    'tax' => 1000,
    'subtotal' => 100000,
    'total' => 110000,
]);

echo 'Created Receipt: ' . $receipt->receipt_number . PHP_EOL;

// Buat Invoice dari Receipt
$invoice = Invoice::create([
    'invoice_number' => 'TEST-INV-' . now()->format('YmdHis'),
    'from_model_type' => PurchaseOrder::class,
    'from_model_id' => $po->id,
    'customer_name' => $supplier->name,
    'customer_phone' => $supplier->phone ?? '',
    'invoice_date' => now()->toDateString(),
    'due_date' => now()->addDays(30)->toDateString(),
    'subtotal' => 100000,
    'tax' => 10000,
    'ppn_rate' => 10,
    'total' => 110000,
    'status' => 'draft',
]);

echo 'Created Invoice: ' . $invoice->invoice_number . PHP_EOL;

// Simulasi posting journal (biasanya dipanggil oleh observer)
$ledger = new LedgerPostingService();
$result = $ledger->postInvoice($invoice);

echo 'Journal posting result: ' . ($result['status'] ?? 'unknown') . PHP_EOL;

// Tampilkan journal entries
$entries = JournalEntry::where('source_type', Invoice::class)
    ->where('source_id', $invoice->id)
    ->with('coa')
    ->get();

echo PHP_EOL . 'JOURNAL ENTRIES FOR PURCHASE INVOICE:' . PHP_EOL;
echo str_repeat('=', 50) . PHP_EOL;

foreach ($entries as $entry) {
    $type = $entry->debit > 0 ? '(D) Debit' : '(K) Kredit';
    $amount = $entry->debit > 0 ? $entry->debit : $entry->credit;
    echo $type . ' ' . $entry->coa->name . ' (' . $entry->coa->code . ')' . PHP_EOL;
    echo '    Amount: Rp ' . number_format($amount, 0, ',', '.') . PHP_EOL;
    echo '    Description: ' . $entry->description . PHP_EOL;
    echo PHP_EOL;
}

echo 'Total entries: ' . $entries->count() . PHP_EOL;