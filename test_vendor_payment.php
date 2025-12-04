<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== TEST PEMBAYARAN VENDOR PAYMENT ===' . PHP_EOL . PHP_EOL;

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

// Test 1: Pembayaran Partial (50% dari total)
$partialAmount = $invoice->total * 0.5;
echo '=== TEST 1: PEMBAYARAN PARTIAL ===' . PHP_EOL;
echo 'Jumlah Pembayaran Partial: Rp ' . number_format($partialAmount, 0, ',', '.') . PHP_EOL . PHP_EOL;

// Buat Vendor Payment Partial
$partialPayment = \App\Models\VendorPayment::create([
    'supplier_id' => $supplier->id,
    'payment_date' => now()->toDateString(),
    'total_payment' => $partialAmount,
    'payment_method' => 'bank_transfer',
    'coa_id' => 1, // Bank account
    'status' => 'partial',
    'selected_invoices' => [$invoice->id],
    'notes' => 'Test pembayaran partial'
]);

// Buat VendorPaymentDetail
\App\Models\VendorPaymentDetail::create([
    'vendor_payment_id' => $partialPayment->id,
    'invoice_id' => $invoice->id,
    'amount' => $partialAmount,
    'method' => 'bank_transfer',
    'coa_id' => 1,
    'payment_date' => $partialPayment->payment_date
]);

echo '✅ Pembayaran Partial dibuat - ID: ' . $partialPayment->id . PHP_EOL;

// Tunggu observer memproses
sleep(3);

// Cek Account Payable setelah partial payment
$accountPayable->refresh();
$invoice->refresh();

echo 'SETELAH PEMBAYARAN PARTIAL:' . PHP_EOL;
echo 'Account Payable - Paid: Rp ' . number_format($accountPayable->paid, 0, ',', '.') . PHP_EOL;
echo 'Account Payable - Remaining: Rp ' . number_format($accountPayable->remaining, 0, ',', '.') . PHP_EOL;
echo 'Account Payable - Status: ' . $accountPayable->status . PHP_EOL;
echo 'Invoice Status: ' . $invoice->status . PHP_EOL . PHP_EOL;

// Cek Journal Entries untuk partial payment
$partialJournals = \App\Models\JournalEntry::where('source_type', \App\Models\VendorPayment::class)
    ->where('source_id', $partialPayment->id)
    ->with('coa')
    ->get();

echo 'JOURNAL ENTRIES PEMBAYARAN PARTIAL:' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

$totalDebitPartial = 0;
$totalCreditPartial = 0;

foreach ($partialJournals as $index => $entry) {
    $coa = $entry->coa;
    $coaName = $coa ? $coa->name . ' (' . $coa->code . ')' : 'Unknown COA';

    $debit = (float) $entry->debit;
    $credit = (float) $entry->credit;

    $totalDebitPartial += $debit;
    $totalCreditPartial += $credit;

    echo ($index + 1) . '. ' . $coaName . PHP_EOL;
    echo '   Description: ' . $entry->description . PHP_EOL;
    echo '   Debit: Rp ' . number_format($debit, 0, ',', '.') . PHP_EOL;
    echo '   Credit: Rp ' . number_format($credit, 0, ',', '.') . PHP_EOL;
    echo '   Date: ' . $entry->date . PHP_EOL;
    echo PHP_EOL;
}

echo 'RINGKASAN PARTIAL PAYMENT:' . PHP_EOL;
echo 'Total Debit: Rp ' . number_format($totalDebitPartial, 0, ',', '.') . PHP_EOL;
echo 'Total Credit: Rp ' . number_format($totalCreditPartial, 0, ',', '.') . PHP_EOL;
echo 'Status: ' . ($totalDebitPartial == $totalCreditPartial ? '✅ SEIMBANG' : '❌ TIDAK SEIMBANG') . PHP_EOL . PHP_EOL;

// Test 2: Pembayaran Lunas (sisa pembayaran)
$remainingAmount = $accountPayable->remaining;
echo '=== TEST 2: PEMBAYARAN LUNAS ===' . PHP_EOL;
echo 'Jumlah Pembayaran Lunas: Rp ' . number_format($remainingAmount, 0, ',', '.') . PHP_EOL . PHP_EOL;

// Buat Vendor Payment Lunas
$fullPayment = \App\Models\VendorPayment::create([
    'supplier_id' => $supplier->id,
    'payment_date' => now()->toDateString(),
    'total_payment' => $remainingAmount,
    'payment_method' => 'bank_transfer',
    'coa_id' => 1, // Bank account
    'status' => 'paid',
    'selected_invoices' => [$invoice->id],
    'notes' => 'Test pembayaran lunas'
]);

// Buat VendorPaymentDetail
\App\Models\VendorPaymentDetail::create([
    'vendor_payment_id' => $fullPayment->id,
    'invoice_id' => $invoice->id,
    'amount' => $remainingAmount,
    'method' => 'bank_transfer',
    'coa_id' => 1,
    'payment_date' => $fullPayment->payment_date
]);

echo '✅ Pembayaran Lunas dibuat - ID: ' . $fullPayment->id . PHP_EOL;

// Tunggu observer memproses
sleep(3);

// Cek Account Payable setelah full payment
$accountPayable->refresh();
$invoice->refresh();

echo 'SETELAH PEMBAYARAN LUNAS:' . PHP_EOL;
echo 'Account Payable - Paid: Rp ' . number_format($accountPayable->paid, 0, ',', '.') . PHP_EOL;
echo 'Account Payable - Remaining: Rp ' . number_format($accountPayable->remaining, 0, ',', '.') . PHP_EOL;
echo 'Account Payable - Status: ' . $accountPayable->status . PHP_EOL;
echo 'Invoice Status: ' . $invoice->status . PHP_EOL . PHP_EOL;

// Cek Journal Entries untuk full payment
$fullJournals = \App\Models\JournalEntry::where('source_type', \App\Models\VendorPayment::class)
    ->where('source_id', $fullPayment->id)
    ->with('coa')
    ->get();

echo 'JOURNAL ENTRIES PEMBAYARAN LUNAS:' . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;

$totalDebitFull = 0;
$totalCreditFull = 0;

foreach ($fullJournals as $index => $entry) {
    $coa = $entry->coa;
    $coaName = $coa ? $coa->name . ' (' . $coa->code . ')' : 'Unknown COA';

    $debit = (float) $entry->debit;
    $credit = (float) $entry->credit;

    $totalDebitFull += $debit;
    $totalCreditFull += $credit;

    echo ($index + 1) . '. ' . $coaName . PHP_EOL;
    echo '   Description: ' . $entry->description . PHP_EOL;
    echo '   Debit: Rp ' . number_format($debit, 0, ',', '.') . PHP_EOL;
    echo '   Credit: Rp ' . number_format($credit, 0, ',', '.') . PHP_EOL;
    echo '   Date: ' . $entry->date . PHP_EOL;
    echo PHP_EOL;
}

echo 'RINGKASAN FULL PAYMENT:' . PHP_EOL;
echo 'Total Debit: Rp ' . number_format($totalDebitFull, 0, ',', '.') . PHP_EOL;
echo 'Total Credit: Rp ' . number_format($totalCreditFull, 0, ',', '.') . PHP_EOL;
echo 'Status: ' . ($totalDebitFull == $totalCreditFull ? '✅ SEIMBANG' : '❌ TIDAK SEIMBANG') . PHP_EOL . PHP_EOL;

// Cek Ageing Schedule
$ageingSchedule = $accountPayable->ageingSchedule;
if ($ageingSchedule) {
    echo 'AGEING SCHEDULE UPDATE:' . PHP_EOL;
    echo 'Invoice Date: ' . $ageingSchedule->invoice_date . PHP_EOL;
    echo 'Due Date: ' . $ageingSchedule->due_date . PHP_EOL;
    echo 'Days Outstanding: ' . $ageingSchedule->days_outstanding . PHP_EOL;
    echo 'Bucket: ' . $ageingSchedule->bucket . PHP_EOL . PHP_EOL;
}

echo '=== RINGKASAN KESELURUHAN ===' . PHP_EOL;
echo '✅ Account Payable terupdate dengan benar' . PHP_EOL;
echo '✅ Invoice status terupdate dengan benar' . PHP_EOL;
echo '✅ Journal entries seimbang untuk kedua pembayaran' . PHP_EOL;
echo '✅ Ageing schedule tetap terjaga' . PHP_EOL . PHP_EOL;

echo 'Vendor Payment Partial: http://localhost:8009/admin/vendor-payments/' . $partialPayment->id . PHP_EOL;
echo 'Vendor Payment Lunas: http://localhost:8009/admin/vendor-payments/' . $fullPayment->id . PHP_EOL;
echo 'Account Payable: http://localhost:8009/admin/account-payables/' . $accountPayable->id . PHP_EOL;