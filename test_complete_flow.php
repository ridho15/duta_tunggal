<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$payment = new App\Models\VendorPayment();
$payment->supplier_id = 1;
$payment->total_payment = 2500;
$payment->payment_method = 'Cash';
$payment->payment_date = now();
$payment->coa_id = 1;
$payment->selected_invoices = [13];
$payment->invoice_receipts = [
    [
        'invoice_id' => 13,
        'balance_amount' => '50000.00',
        'payment_amount' => 2500,
        'adjustment_amount' => 0,
        'adjustment_description' => ''
    ]
];
$payment->save();

echo 'Created Vendor Payment ID: ' . $payment->id . PHP_EOL;
echo 'Status before afterCreate: ' . ($payment->status ?? 'null') . PHP_EOL;

// Simulate afterCreate logic
$record = $payment;
$totalPaymentFromUser = $record->total_payment - ($record->payment_adjustment ?? 0);
$invoiceReceipts = $record->invoice_receipts ?? [];

if (!empty($invoiceReceipts) && is_array($invoiceReceipts)) {
    foreach ($invoiceReceipts as $receipt) {
        if (isset($receipt['invoice_id'])) {
            $accountPayable = App\Models\AccountPayable::where('invoice_id', $receipt['invoice_id'])->first();
            if ($accountPayable && $accountPayable->remaining > 0) {
                $proportion = $accountPayable->remaining / $accountPayable->remaining; // simplified for single invoice
                $paymentForThisInvoice = $totalPaymentFromUser * $proportion;
                $actualPayment = min($paymentForThisInvoice, $accountPayable->remaining);

                if ($actualPayment > 0) {
                    $record->vendorPaymentDetail()->create([
                        'invoice_id' => $receipt['invoice_id'],
                        'amount' => $actualPayment,
                        'notes' => 'Proportional payment from total: ' . number_format($record->total_payment),
                        'method' => $record->payment_method ?? 'Cash',
                        'coa_id' => $record->coa_id,
                        'payment_date' => $record->payment_date,
                    ]);
                    echo 'Created payment detail for invoice ' . $receipt['invoice_id'] . PHP_EOL;
                }
            }
        }
    }
}

// Set status to Paid
$record->status = 'Paid';
$record->save();

echo 'Status after afterCreate: ' . $payment->status . PHP_EOL;
echo 'Payment Details Count: ' . $payment->vendorPaymentDetail()->count() . PHP_EOL;

// Test ledger posting
try {
    $ledgerService = new App\Services\LedgerPostingService();
    $ledgerService->postVendorPayment($payment);
    echo 'Ledger posting successful' . PHP_EOL;
} catch (Exception $e) {
    echo 'Ledger posting failed: ' . $e->getMessage() . PHP_EOL;
}