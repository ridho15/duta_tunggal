<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Carbon\Carbon;

// 1. Ambil supplier dan product yang ada
$supplier = App\Models\Supplier::first();
$product = App\Models\Product::first();
$user = App\Models\User::first();
$warehouse = App\Models\Warehouse::first();

if (!$supplier || !$product) {
    echo 'Supplier atau Product tidak ditemukan!' . PHP_EOL;
    exit;
}

echo 'Test Purchase Invoice dari Purchase Order - Journal Entries' . PHP_EOL;
echo '==========================================================' . PHP_EOL;
echo 'Supplier: ' . $supplier->name . PHP_EOL;
echo 'Product: ' . $product->name . ' (Price: ' . number_format($product->price, 0, ',', '.') . ')' . PHP_EOL;
echo PHP_EOL;

// 2. Buat Purchase Order dengan biaya lainnya
$po = App\Models\PurchaseOrder::create([
    'supplier_id' => $supplier->id,
    'po_number' => 'PO-TEST-' . date('YmdHis'),
    'order_date' => Carbon::now(),
    'status' => 'approved',
    'total_amount' => 2000000,
    'warehouse_id' => $warehouse->id ?? 1,
    'tempo_hutang' => 30,
    'created_by' => $user->id ?? 1,
]);

// Buat PO Item
$currency = App\Models\Currency::first();
$poItem = App\Models\PurchaseOrderItem::create([
    'purchase_order_id' => $po->id,
    'product_id' => $product->id,
    'quantity' => 4,
    'unit_price' => 500000,
    'subtotal' => 2000000,
    'currency_id' => $currency->id ?? 1,
]);

// Tambahkan biaya lainnya ke PO
$biayaPengirimanCoa = App\Models\ChartOfAccount::where('code', '6100.02')->first();
$poBiaya = App\Models\PurchaseOrderBiaya::create([
    'purchase_order_id' => $po->id,
    'nama_biaya' => 'Biaya Pengiriman',
    'total' => 150000,
    'currency_id' => $currency->id ?? 1,
    'coa_id' => $biayaPengirimanCoa->id ?? null,
    'masuk_invoice' => 1, // Biaya ini masuk ke invoice
]);

echo '✅ Purchase Order Created:' . PHP_EOL;
echo '  PO Number: ' . $po->po_number . PHP_EOL;
echo '  Total: Rp ' . number_format($po->total_amount, 0, ',', '.') . PHP_EOL;
echo '  Biaya Pengiriman: Rp ' . number_format(150000, 0, ',', '.') . PHP_EOL;
echo '  Items: 4 x ' . $product->name . ' @ Rp 500.000 = Rp 2.000.000' . PHP_EOL;
echo PHP_EOL;

// 3. Buat Purchase Receipt dari PO
$receipt = App\Models\PurchaseReceipt::create([
    'purchase_order_id' => $po->id,
    'supplier_id' => $supplier->id,
    'receipt_number' => 'RC-TEST-' . date('YmdHis'),
    'receipt_date' => Carbon::now(),
    'status' => 'completed',
    'total_amount' => 2000000,
    'currency_id' => $currency->id ?? 1,
    'received_by' => $user->id ?? 1,
    'created_by' => $user->id ?? 1,
]);

$receiptItem = App\Models\PurchaseReceiptItem::create([
    'purchase_receipt_id' => $receipt->id,
    'purchase_order_item_id' => $poItem->id,
    'product_id' => $product->id,
    'qty_received' => 4,
    'qty_accepted' => 4,
    'qty_rejected' => 0,
    'warehouse_id' => $warehouse->id ?? 1,
    'is_sent' => false,
]);

// Simulasi QC dan inventory posting
$qc = App\Models\QualityControl::create([
    'qc_number' => 'QC-TEST-' . date('YmdHis'),
    'from_model_type' => App\Models\PurchaseOrderItem::class,
    'from_model_id' => $poItem->id,
    'product_id' => $product->id,
    'passed_quantity' => 4,
    'rejected_quantity' => 0,
    'warehouse_id' => $warehouse->id ?? 1,
    'status' => 1,
    'date_send_stock' => Carbon::now(),
]);

$receiptItem->update(['quality_control_id' => $qc->id]);

$purchaseReceiptService = new App\Services\PurchaseReceiptService();
$result = $purchaseReceiptService->postItemInventoryAfterQC($receiptItem);
$receiptItem->update(['is_sent' => 1]);

echo '✅ Purchase Receipt Created:' . PHP_EOL;
echo '  Receipt Number: ' . $receipt->receipt_number . PHP_EOL;
echo '  Status: ' . $receipt->status . PHP_EOL;
echo PHP_EOL;

// 4. Buat Invoice Pembelian dari Purchase Order
try {
    $invoice = App\Models\Invoice::create([
        'invoice_number' => 'INV-PO-TEST-' . date('YmdHis'),
        'from_model_type' => 'App\\Models\\PurchaseOrder',
        'from_model_id' => $po->id,
        'supplier_name' => $supplier->name,
        'supplier_phone' => $supplier->phone ?? '',
        'invoice_date' => Carbon::now(),
        'due_date' => Carbon::now()->addDays(30),
        'subtotal' => 2000000,
        'tax' => 0, // Tidak ada tax tambahan
        'ppn_rate' => 11, // 11% PPN
        'dpp' => 2000000,
        'other_fee' => 150000, // Biaya pengiriman
        'total' => 2370000, // 2jt + 220rb PPN + 150rb biaya = 2.37jt
        'status' => 'paid',
        'purchase_receipts' => [$receipt->id],
        // COA selections
        'accounts_payable_coa_id' => App\Models\ChartOfAccount::where('code', '2110')->first()?->id,
        'ppn_masukan_coa_id' => App\Models\ChartOfAccount::where('code', '1170.06')->first()?->id,
        'inventory_coa_id' => App\Models\ChartOfAccount::where('code', '1140.01')->first()?->id,
        'expense_coa_id' => App\Models\ChartOfAccount::where('code', '6100.02')->first()?->id,
    ]);

    echo '✅ Purchase Invoice Created:' . PHP_EOL;
    echo '  Invoice Number: ' . $invoice->invoice_number . PHP_EOL;
    echo '  From PO: ' . $po->po_number . PHP_EOL;
    echo '  Subtotal: Rp ' . number_format($invoice->subtotal, 0, ',', '.') . PHP_EOL;
    echo '  PPN Rate: ' . $invoice->ppn_rate . '%' . PHP_EOL;
    echo '  PPN Amount: Rp ' . number_format($invoice->subtotal * $invoice->ppn_rate / 100, 0, ',', '.') . PHP_EOL;
    echo '  Biaya Pengiriman: Rp ' . number_format($invoice->other_fee_total, 0, ',', '.') . PHP_EOL;
    echo '  Total: Rp ' . number_format($invoice->total, 0, ',', '.') . PHP_EOL;
    echo PHP_EOL;

} catch (\Exception $e) {
    echo '❌ Error creating invoice: ' . $e->getMessage() . PHP_EOL;
    // Cari invoice yang mungkin sudah dibuat
    $invoice = App\Models\Invoice::where('from_model_type', 'App\\Models\\PurchaseOrder')
        ->where('from_model_id', $po->id)
        ->latest()
        ->first();
    if ($invoice) {
        echo 'Found existing invoice: ' . $invoice->invoice_number . PHP_EOL;
    } else {
        exit;
    }
}

// 5. Cek Journal Entries untuk Invoice Pembelian
echo '📋 Journal Entries - Purchase Invoice:' . PHP_EOL;
$invoiceJournals = App\Models\JournalEntry::where('source_type', 'App\\Models\\Invoice')
    ->where('source_id', $invoice->id)
    ->orderBy('debit', 'desc')
    ->orderBy('credit', 'desc')
    ->get();

if ($invoiceJournals->isEmpty()) {
    echo '❌ Tidak ada journal entries untuk Purchase Invoice' . PHP_EOL;
} else {
    foreach ($invoiceJournals as $journal) {
        $coa = App\Models\ChartOfAccount::find($journal->coa_id);
        $type = $journal->debit > 0 ? 'D' : 'K';
        $amount = $journal->debit > 0 ? $journal->debit : $journal->credit;
        echo "  ($type) {$coa->code} - {$coa->name}: " . number_format($amount, 0, ',', '.') . PHP_EOL;
        echo "      Description: {$journal->description}" . PHP_EOL;
    }
}
echo PHP_EOL;

// 6. Validasi Balance
$totalDebit = $invoiceJournals->sum('debit');
$totalCredit = $invoiceJournals->sum('credit');

echo '🔍 Journal Balance Check:' . PHP_EOL;
echo '  Total Debit: Rp ' . number_format($totalDebit, 0, ',', '.') . PHP_EOL;
echo '  Total Credit: Rp ' . number_format($totalCredit, 0, ',', '.') . PHP_EOL;
if ($totalDebit == $totalCredit) {
    echo '  ✅ Journal entries are balanced' . PHP_EOL;
} else {
    echo '  ❌ Journal entries are NOT balanced! Difference: Rp ' . number_format($totalDebit - $totalCredit, 0, ',', '.') . PHP_EOL;
}
echo PHP_EOL;

echo '🎯 Expected Journal Entries:' . PHP_EOL;
echo '  (D) Penerimaan Barang belum tertagih: 2.000.000 (close unbilled)' . PHP_EOL;
echo '  (D) PPn Masukan (PPN Rate): 220.000' . PHP_EOL;
echo '  (D) Biaya Pengiriman: 150.000' . PHP_EOL;
echo '  (K) Hutang Supplier: 2.370.000' . PHP_EOL;
echo PHP_EOL;

echo 'Test completed!' . PHP_EOL;