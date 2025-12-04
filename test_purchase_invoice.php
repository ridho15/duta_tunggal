<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo '=== MEMBUAT PURCHASE INVOICE MELALUI RESOURCE FORM ===' . PHP_EOL . PHP_EOL;

// Cari supplier dan PO yang ada
$supplier = \App\Models\Supplier::first();
if (!$supplier) {
    echo 'Tidak ada Supplier' . PHP_EOL;
    exit;
}

$po = \App\Models\PurchaseOrder::where('supplier_id', $supplier->id)
    ->where('status', 'completed')
    ->whereHas('purchaseReceipt', function ($query) {
        $query->where('status', 'completed');
    })
    ->first();

if (!$po) {
    echo 'Tidak ada Purchase Order yang sesuai' . PHP_EOL;
    exit;
}

$receipts = $po->purchaseReceipt()->where('status', 'completed')->get();
if ($receipts->isEmpty()) {
    echo 'Tidak ada Purchase Receipts yang completed' . PHP_EOL;
    exit;
}

echo 'Menggunakan:' . PHP_EOL;
echo 'Supplier: ' . $supplier->name . ' (' . $supplier->code . ')' . PHP_EOL;
echo 'PO: ' . $po->po_number . PHP_EOL;
echo 'Receipts: ' . $receipts->pluck('receipt_number')->join(', ') . PHP_EOL . PHP_EOL;

// Siapkan data form seperti di PurchaseInvoiceResource
$formData = [
    'selected_supplier' => $supplier->id,
    'selected_purchase_order' => $po->id,
    'selected_purchase_receipts' => $receipts->pluck('id')->toArray(),
    'invoice_number' => 'INV-' . now()->format('Ymd-His'),
    'invoice_date' => now()->toDateString(),
    'due_date' => now()->addDays(30)->toDateString(),
    'subtotal' => 2500000,
    'tax' => 2,
    'ppn_rate' => 11,
    'other_fees' => [
        ['name' => 'Biaya Admin', 'amount' => 50000],
        ['name' => 'Biaya Pengiriman', 'amount' => 75000]
    ],
    'receiptBiayaItems' => [
        ['nama_biaya' => 'Biaya Transport Receipt', 'total' => 100000],
        ['nama_biaya' => 'Biaya Asuransi Receipt', 'total' => 25000]
    ],
    'from_model_type' => 'App\\Models\\PurchaseOrder',
    'from_model_id' => $po->id,
    'supplier_name' => $supplier->name,
    'supplier_phone' => $supplier->phone ?? '',
    'purchase_receipts' => $receipts->pluck('id')->toArray(),
    'accounts_payable_coa_id' => 1,
    'ppn_masukan_coa_id' => 2,
    'inventory_coa_id' => 3,
    'expense_coa_id' => 4,
    'status' => 'draft'
];

echo 'Data Form:' . PHP_EOL;
echo '- Subtotal: Rp ' . number_format($formData['subtotal'], 0, ',', '.') . PHP_EOL;
echo '- Tax: ' . $formData['tax'] . '%' . PHP_EOL;
echo '- PPN: ' . $formData['ppn_rate'] . '%' . PHP_EOL;
echo '- Other Fees Manual: ' . count($formData['other_fees']) . ' items' . PHP_EOL;
echo '- Receipt Biaya: ' . count($formData['receiptBiayaItems']) . ' items' . PHP_EOL . PHP_EOL;

// Proses data menggunakan logic yang sama dengan prepareInvoiceData
function prepareInvoiceData($data) {
    // Remove form-specific fields and prepare data for database
    unset($data['selected_supplier'], $data['selected_purchase_order'], $data['selected_purchase_receipts']);

    // Ensure other_fee is properly formatted - combine manual fees and receipt fees
    $otherFees = [];
    if (isset($data['other_fees']) && is_array($data['other_fees'])) {
        $otherFees = array_merge($otherFees, $data['other_fees']);
    }
    if (isset($data['receiptBiayaItems']) && is_array($data['receiptBiayaItems'])) {
        $otherFees = array_merge($otherFees, $data['receiptBiayaItems']);
    }
    $data['other_fee'] = collect($otherFees)->map(function ($fee) {
        return [
            'name' => $fee['nama_biaya'] ?? $fee['name'] ?? 'Biaya Lain',
            'amount' => (float) ($fee['total'] ?? $fee['amount'] ?? 0),
        ];
    })->toArray();

    // Remove temporary fields
    unset($data['other_fees'], $data['receiptBiayaItems']);

    // Calculate totals if not set
    if (!isset($data['total']) || $data['total'] == 0) {
        $subtotal = $data['subtotal'] ?? 0;
        $otherFeeTotal = collect($data['other_fee'] ?? [])->sum('amount');
        $tax = $data['tax'] ?? 0;
        $ppnRate = $data['ppn_rate'] ?? 0;
        $data['total'] = $subtotal + $otherFeeTotal + ($subtotal * $tax / 100) + ($subtotal * $ppnRate / 100);
    }

    return $data;
}

$processedData = prepareInvoiceData($formData);

echo 'Data Setelah Diproses:' . PHP_EOL;
echo '- Subtotal: Rp ' . number_format($processedData['subtotal'], 0, ',', '.') . PHP_EOL;
echo '- Other Fee Items: ' . count($processedData['other_fee']) . ' items' . PHP_EOL;
foreach ($processedData['other_fee'] as $fee) {
    echo '  - ' . $fee['name'] . ': Rp ' . number_format($fee['amount'], 0, ',', '.') . PHP_EOL;
}
echo '- Total: Rp ' . number_format($processedData['total'], 0, ',', '.') . PHP_EOL . PHP_EOL;

// Buat invoice
$invoice = \App\Models\Invoice::create($processedData);
echo 'Invoice berhasil dibuat!' . PHP_EOL;
echo 'Invoice Number: ' . $invoice->invoice_number . PHP_EOL;
echo 'ID: ' . $invoice->id . PHP_EOL;
echo 'Total: Rp ' . number_format($invoice->total, 0, ',', '.') . PHP_EOL . PHP_EOL;

// Tunggu sebentar untuk observer memproses
sleep(2);

// Cek journal entries
$journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\Invoice::class)
    ->where('source_id', $invoice->id)
    ->with('coa')
    ->orderBy('created_at', 'desc')
    ->get();

echo 'JOURNAL ENTRIES YANG DIBUAT OLEH OBSERVER:' . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;

$totalDebit = 0;
$totalCredit = 0;

foreach ($journalEntries as $index => $entry) {
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
    echo '   Reference: ' . $entry->reference . PHP_EOL;
    echo PHP_EOL;
}

echo 'RINGKASAN AKUNTANSI:' . PHP_EOL;
echo 'Total Debit: Rp ' . number_format($totalDebit, 0, ',', '.') . PHP_EOL;
echo 'Total Credit: Rp ' . number_format($totalCredit, 0, ',', '.') . PHP_EOL;
echo 'Status: ' . ($totalDebit == $totalCredit ? 'SEIMBANG' : 'TIDAK SEIMBANG') . PHP_EOL . PHP_EOL;

echo 'Invoice tersimpan di: http://localhost:8009/admin/purchase-invoices/' . $invoice->id . PHP_EOL;
echo 'Journal entries tersimpan di: http://localhost:8009/admin/journal-entries' . PHP_EOL;