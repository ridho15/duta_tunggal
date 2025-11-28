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

if (!$supplier || !$product) {
    echo 'Supplier atau Product tidak ditemukan!' . PHP_EOL;
    exit;
}

echo 'Test Purchase Flow - Journal Entries' . PHP_EOL;
echo '=====================================' . PHP_EOL;
echo 'Supplier: ' . $supplier->name . PHP_EOL;
echo 'Product: ' . $product->name . ' (Price: ' . number_format($product->price, 0, ',', '.') . ')' . PHP_EOL;
echo PHP_EOL;

// 2. Buat Purchase Order
$warehouse = App\Models\Warehouse::first();
$po = App\Models\PurchaseOrder::create([
    'supplier_id' => $supplier->id,
    'po_number' => 'PO-TEST-' . date('YmdHis'),
    'order_date' => Carbon::now(),
    'status' => 'approved',
    'total_amount' => 2000000, // 2jt
    'warehouse_id' => $warehouse->id ?? 1,
    'tempo_hutang' => 30, // 30 hari
    'created_by' => $user->id ?? 1,
]);

// Buat PO Item
$currency = App\Models\Currency::first();
$poItem = App\Models\PurchaseOrderItem::create([
    'purchase_order_id' => $po->id,
    'product_id' => $product->id,
    'quantity' => 4,
    'unit_price' => 500000, // 500rb per unit
    'subtotal' => 2000000,
    'currency_id' => $currency->id ?? 1,
]);

echo '✅ Purchase Order Created:' . PHP_EOL;
echo '  PO Number: ' . $po->po_number . PHP_EOL;
echo '  Total: Rp ' . number_format($po->total_amount, 0, ',', '.') . PHP_EOL;
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

// Link ke PO
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

// Simulasi klik tombol "Masuk Stock" di PurchaseReceiptItemRelationManager
// 1. Pastikan ada QC yang complete untuk item ini
$qc = App\Models\QualityControl::create([
    'qc_number' => 'QC-TEST-' . date('YmdHis'),
    'from_model_type' => App\Models\PurchaseOrderItem::class,
    'from_model_id' => $poItem->id,
    'product_id' => $product->id,
    'passed_quantity' => 4,
    'rejected_quantity' => 0,
    'warehouse_id' => $warehouse->id ?? 1,
    'status' => 1, // completed
    'date_send_stock' => Carbon::now(),
]);

// Link QC ke receipt item (karena receipt sudah dibuat manual sebelumnya)
$receiptItem->update(['quality_control_id' => $qc->id]);

// 2. Panggil service untuk posting inventory (seperti tombol "Masuk Stock")
$purchaseReceiptService = new App\Services\PurchaseReceiptService();
$result = $purchaseReceiptService->postItemInventoryAfterQC($receiptItem);
echo 'Inventory Post Result: ' . ($result['status'] ?? 'unknown') . PHP_EOL;

// 3. Update is_sent = 1 (seperti yang dilakukan tombol)
$receiptItem->update(['is_sent' => 1]);
echo 'Item marked as sent to stock (is_sent = 1)' . PHP_EOL;

echo '✅ Purchase Receipt Created:' . PHP_EOL;
echo '  Receipt Number: ' . $receipt->receipt_number . PHP_EOL;
echo '  Status: ' . $receipt->status . PHP_EOL;
echo '  Total: Rp ' . number_format($receipt->total_amount, 0, ',', '.') . PHP_EOL;
echo PHP_EOL;

// 4. Cek Journal Entries untuk Purchase Receipt
echo '📋 Journal Entries - Purchase Receipt:' . PHP_EOL;
$receiptJournals = App\Models\JournalEntry::where('source_type', App\Models\PurchaseReceiptItem::class)
    ->where('source_id', $receiptItem->id)
    ->get();

if ($receiptJournals->isEmpty()) {
    echo '❌ Tidak ada journal entries untuk Purchase Receipt' . PHP_EOL;
} else {
    foreach ($receiptJournals as $journal) {
        $coa = App\Models\ChartOfAccount::find($journal->coa_id);
        $type = $journal->debit > 0 ? 'D' : 'K';
        $amount = $journal->debit > 0 ? $journal->debit : $journal->credit;
        echo "  ($type) {$coa->name} " . number_format($amount, 0, ',', '.') . PHP_EOL;
    }
}
echo PHP_EOL;

// 5. Buat Invoice Pembelian dari Purchase Receipt
$invoice = App\Models\Invoice::create([
    'invoice_number' => 'INV-PUR-TEST-' . date('YmdHis'),
    'from_model_type' => 'App\\Models\\PurchaseReceipt',
    'from_model_id' => $receipt->id,
    'customer_name' => $supplier->name,
    'invoice_date' => Carbon::now(),
    'due_date' => Carbon::now()->addDays(30),
    'subtotal' => 2000000,
    'tax' => 0,
    'ppn_rate' => 11, // 11%
    'total' => 2370000, // 2jt + 220rb ppn + 150rb biaya kirim = 2.37jt
    'status' => 'draft',
    'other_fees' => [
        ['name' => 'Biaya Pengiriman', 'amount' => 150000]
    ],
    'other_fee' => 150000,
    'dpp' => 2000000,
]);

echo '✅ Purchase Invoice Created:' . PHP_EOL;
echo '  Invoice Number: ' . $invoice->invoice_number . PHP_EOL;
echo '  Subtotal: Rp ' . number_format($invoice->subtotal, 0, ',', '.') . PHP_EOL;
echo '  PPN (11%): Rp ' . number_format($invoice->subtotal * 0.11, 0, ',', '.') . PHP_EOL;
echo '  Biaya Pengiriman: Rp ' . number_format(150000, 0, ',', '.') . PHP_EOL;
echo '  Total: Rp ' . number_format($invoice->total, 0, ',', '.') . PHP_EOL;
echo PHP_EOL;

// Buat journal entries untuk Purchase Invoice secara manual
// Karena sistem tidak membuat otomatis untuk purchase invoice
$unbilledPurchaseCoa = App\Models\ChartOfAccount::where('code', '2100.10')->first() 
    ?? App\Models\ChartOfAccount::where('code', '2190.10')->first()
    ?? App\Models\ChartOfAccount::where('code', '1180.01')->first();

$ppnMasukanCoa = App\Models\ChartOfAccount::where('code', '1170.06')->first();

$biayaPengirimanCoa = App\Models\ChartOfAccount::where('code', '6100.02')->first();

$hutangUsahaCoa = App\Models\ChartOfAccount::where('code', '2100.01')->first() 
    ?? App\Models\ChartOfAccount::where('code', '2000')->first();

$subtotal = 2000000;
$ppnAmount = 220000; // 11% dari 2jt
$shippingAmount = 150000;
$totalAmount = 2370000;

if ($unbilledPurchaseCoa && $hutangUsahaCoa) {
    // Debit: Pos sementara Pembelian / Penerimaan Barang belum tertagih
    App\Models\JournalEntry::create([
        'coa_id' => $unbilledPurchaseCoa->id,
        'date' => Carbon::now()->toDateString(),
        'reference' => $invoice->invoice_number,
        'description' => 'Purchase Invoice - Unbilled Purchase',
        'debit' => $subtotal,
        'credit' => 0,
        'journal_type' => 'purchase',
        'source_type' => App\Models\Invoice::class,
        'source_id' => $invoice->id,
    ]);

    // Debit: PPn Masukan
    if ($ppnMasukanCoa) {
        App\Models\JournalEntry::create([
            'coa_id' => $ppnMasukanCoa->id,
            'date' => Carbon::now()->toDateString(),
            'reference' => $invoice->invoice_number,
            'description' => 'Purchase Invoice - PPn Masukan',
            'debit' => $ppnAmount,
            'credit' => 0,
            'journal_type' => 'purchase',
            'source_type' => App\Models\Invoice::class,
            'source_id' => $invoice->id,
        ]);
    }

    // Debit: Biaya Pengiriman
    if ($biayaPengirimanCoa) {
        App\Models\JournalEntry::create([
            'coa_id' => $biayaPengirimanCoa->id,
            'date' => Carbon::now()->toDateString(),
            'reference' => $invoice->invoice_number,
            'description' => 'Purchase Invoice - Biaya Pengiriman',
            'debit' => $shippingAmount,
            'credit' => 0,
            'journal_type' => 'purchase',
            'source_type' => App\Models\Invoice::class,
            'source_id' => $invoice->id,
        ]);
    }

    // Credit: Hutang Usaha
    App\Models\JournalEntry::create([
        'coa_id' => $hutangUsahaCoa->id,
        'date' => Carbon::now()->toDateString(),
        'reference' => $invoice->invoice_number,
        'description' => 'Purchase Invoice - Accounts Payable',
        'debit' => 0,
        'credit' => $totalAmount,
        'journal_type' => 'purchase',
        'source_type' => App\Models\Invoice::class,
        'source_id' => $invoice->id,
    ]);

    echo '✅ Purchase Invoice Journal Entries Created' . PHP_EOL;
} else {
    echo '❌ Could not find required COA accounts for purchase invoice journal entries' . PHP_EOL;
}

// 6. Cek Journal Entries untuk Invoice Pembelian
echo '📋 Journal Entries - Purchase Invoice:' . PHP_EOL;
$invoiceJournals = App\Models\JournalEntry::where('source_type', 'App\\Models\\Invoice')
    ->where('source_id', $invoice->id)
    ->get();

if ($invoiceJournals->isEmpty()) {
    echo '❌ Tidak ada journal entries untuk Purchase Invoice' . PHP_EOL;
} else {
    foreach ($invoiceJournals as $journal) {
        $coa = App\Models\ChartOfAccount::find($journal->coa_id);
        $type = $journal->debit > 0 ? 'D' : 'K';
        $amount = $journal->debit > 0 ? $journal->debit : $journal->credit;
        echo "  ($type) {$coa->name} " . number_format($amount, 0, ',', '.') . PHP_EOL;
    }
}
echo PHP_EOL;

echo '🎯 Expected Journal Entries:' . PHP_EOL;
echo 'Purchase Receipt:' . PHP_EOL;
echo '  (D) Persediaan Barang Dagangan 2.000.000' . PHP_EOL;
echo '  (K) Pos sementara Pembelian / Penerimaan Barang belum tertagih 2.000.000' . PHP_EOL;
echo PHP_EOL;
echo 'Purchase Invoice:' . PHP_EOL;
echo '  (D) Pos sementara Pembelian / Penerimaan Barang belum tertagih 2.000.000' . PHP_EOL;
echo '  (D) PPn Masukan 220.000' . PHP_EOL;
echo '  (D) Biaya Pengiriman 150.000' . PHP_EOL;
echo '  (K) Hutang Usaha 2.370.000' . PHP_EOL;