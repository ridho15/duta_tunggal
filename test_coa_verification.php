<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== TEST VERIFIKASI COA PADA VENDOR PAYMENT ===' . PHP_EOL . PHP_EOL;

// Cari invoice yang sudah ada
$invoice = \App\Models\Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
    ->where('status', '!=', 'paid')
    ->first();

if (!$invoice) {
    echo '❌ Tidak ada invoice yang belum lunas' . PHP_EOL;
    exit;
}

$supplier = $invoice->fromModel->supplier;
$accountPayable = \App\Models\AccountPayable::where('invoice_id', $invoice->id)->first();

if (!$accountPayable) {
    echo '❌ Tidak ada Account Payable untuk invoice ini' . PHP_EOL;
    exit;
}

echo '=== DATA AWAL ===' . PHP_EOL;
echo 'Invoice: ' . $invoice->invoice_number . PHP_EOL;
echo 'Supplier: ' . $supplier->name . PHP_EOL;
echo 'Total Invoice: Rp ' . number_format($invoice->total, 0, ',', '.') . PHP_EOL;
echo 'Account Payable - Paid: Rp ' . number_format($accountPayable->paid, 0, ',', '.') . PHP_EOL;
echo 'Account Payable - Remaining: Rp ' . number_format($accountPayable->remaining, 0, ',', '.') . PHP_EOL;
echo 'Account Payable - Status: ' . $accountPayable->status . PHP_EOL;
echo 'Invoice Status: ' . $invoice->status . PHP_EOL . PHP_EOL;

// Test dengan COA yang berbeda: Bank BCA - Operasional (id: 85)
$testCoaId = 85;
$testCoa = \App\Models\ChartOfAccount::find($testCoaId);
if (!$testCoa) {
    echo '❌ COA dengan id ' . $testCoaId . ' tidak ditemukan' . PHP_EOL;
    exit;
}

echo '=== TEST PEMBAYARAN DENGAN COA DIPILIH ===' . PHP_EOL;
echo 'COA yang dipilih: ' . $testCoa->name . ' (' . $testCoa->code . ')' . PHP_EOL;

$paymentAmount = 50000000; // 50 juta
echo 'Jumlah Pembayaran: Rp ' . number_format($paymentAmount, 0, ',', '.') . PHP_EOL . PHP_EOL;

// Buat Vendor Payment dengan COA yang dipilih
$payment = \App\Models\VendorPayment::create([
    'supplier_id' => $supplier->id,
    'payment_date' => now()->toDateString(),
    'total_payment' => $paymentAmount,
    'payment_method' => 'bank_transfer',
    'coa_id' => $testCoaId, // COA yang dipilih
    'status' => 'partial',
    'selected_invoices' => [$invoice->id],
    'notes' => 'Test verifikasi COA pada vendor payment'
]);

// Buat VendorPaymentDetail
\App\Models\VendorPaymentDetail::create([
    'vendor_payment_id' => $payment->id,
    'invoice_id' => $invoice->id,
    'amount' => $paymentAmount,
    'method' => 'bank_transfer',
    'coa_id' => $testCoaId,
    'payment_date' => $payment->payment_date
]);

echo '✅ Vendor Payment dibuat - ID: ' . $payment->id . PHP_EOL;

// Tunggu observer memproses
sleep(3);

// Cek Journal Entries
$journals = \App\Models\JournalEntry::where('source_type', \App\Models\VendorPayment::class)
    ->where('source_id', $payment->id)
    ->with('coa')
    ->get();

echo 'JOURNAL ENTRIES:' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

$totalDebit = 0;
$totalCredit = 0;

foreach ($journals as $index => $entry) {
    $coa = $entry->coa;
    $coaName = $coa ? $coa->name . ' (' . $coa->code . ')' : 'Unknown COA';

    $debit = (float) $entry->debit;
    $credit = (float) $entry->credit;

    $totalDebit += $debit;
    $totalCredit += $credit;

    echo ($index + 1) . '. ' . $coaName . PHP_EOL;
    echo '   Description: ' . $entry->description . PHP_EOL;
    echo '   Debit: Rp ' . number_format($debit, 0, ',', '.') . PHP_EOL;
    echo '   Credit: Rp ' . number_format($credit, 0, ',', '.') . PHP_EOL;
    echo '   Date: ' . $entry->date . PHP_EOL;
    echo PHP_EOL;
}

echo 'RINGKASAN:' . PHP_EOL;
echo 'Total Debit: Rp ' . number_format($totalDebit, 0, ',', '.') . PHP_EOL;
echo 'Total Credit: Rp ' . number_format($totalCredit, 0, ',', '.') . PHP_EOL;
echo 'Status: ' . ($totalDebit == $totalCredit ? '✅ SEIMBANG' : '❌ TIDAK SEIMBANG') . PHP_EOL . PHP_EOL;

// Verifikasi apakah COA yang digunakan sesuai dengan yang dipilih
$creditEntry = $journals->where('credit', '>', 0)->first();
if ($creditEntry) {
    $usedCoa = $creditEntry->coa;
    if ($usedCoa && $usedCoa->id == $testCoaId) {
        echo '✅ VERIFIKASI BERHASIL: Journal entry menggunakan COA yang dipilih pada vendor payment' . PHP_EOL;
        echo 'COA yang dipilih: ' . $testCoa->name . ' (' . $testCoa->code . ')' . PHP_EOL;
        echo 'COA pada journal: ' . $usedCoa->name . ' (' . $usedCoa->code . ')' . PHP_EOL;
    } else {
        echo '❌ VERIFIKASI GAGAL: Journal entry TIDAK menggunakan COA yang dipilih' . PHP_EOL;
        echo 'COA yang dipilih: ' . $testCoa->name . ' (' . $testCoa->code . ')' . PHP_EOL;
        if ($usedCoa) {
            echo 'COA pada journal: ' . $usedCoa->name . ' (' . $usedCoa->code . ')' . PHP_EOL;
        } else {
            echo 'COA pada journal: Tidak ditemukan' . PHP_EOL;
        }
    }
} else {
    echo '❌ Tidak ada credit entry ditemukan' . PHP_EOL;
}

echo PHP_EOL . 'Vendor Payment: http://localhost:8009/admin/vendor-payments/' . $payment->id . PHP_EOL;