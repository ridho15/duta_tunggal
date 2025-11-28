<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$payment = App\Models\VendorPayment::find(8);
echo 'Processing afterCreate logic for payment ID: ' . $payment->id . PHP_EOL;

$record = $payment;
$totalPaymentFromUser = $record->total_payment - ($record->payment_adjustment ?? 0);
echo 'Total Payment From User: ' . $totalPaymentFromUser . PHP_EOL;

$invoiceReceipts = $record->invoice_receipts ?? [];
if (!empty($invoiceReceipts) && is_array($invoiceReceipts)) {
    echo 'Processing ' . count($invoiceReceipts) . ' invoice receipts...' . PHP_EOL;

    $totalInvoiceRemaining = 0;
    $invoiceData = [];

    foreach ($invoiceReceipts as $receipt) {
        if (isset($receipt['invoice_id'])) {
            $accountPayable = App\Models\AccountPayable::where('invoice_id', $receipt['invoice_id'])->first();
            if ($accountPayable && $accountPayable->remaining > 0) {
                $invoiceData[] = [
                    'invoice_id' => $receipt['invoice_id'],
                    'remaining' => $accountPayable->remaining,
                    'account_payable' => $accountPayable
                ];
                $totalInvoiceRemaining += $accountPayable->remaining;
                echo 'Added invoice ' . $receipt['invoice_id'] . ' with remaining ' . $accountPayable->remaining . PHP_EOL;
            } else {
                echo 'Skipped invoice ' . $receipt['invoice_id'] . ' - no account payable or zero remaining' . PHP_EOL;
            }
        }
    }

    echo 'Total invoice remaining: ' . $totalInvoiceRemaining . PHP_EOL;
    echo 'Invoice data count: ' . count($invoiceData) . PHP_EOL;

    foreach ($invoiceData as $invoice) {
        if ($totalInvoiceRemaining > 0) {
            $proportion = $invoice['remaining'] / $totalInvoiceRemaining;
            $paymentForThisInvoice = $totalPaymentFromUser * $proportion;
            $actualPayment = min($paymentForThisInvoice, $invoice['remaining']);

            echo 'Invoice ' . $invoice['invoice_id'] . ': proportion=' . $proportion . ', calculated=' . $paymentForThisInvoice . ', actual=' . $actualPayment . PHP_EOL;

            if ($actualPayment > 0) {
                // Create vendor payment detail record
                $detail = $record->vendorPaymentDetail()->create([
                    'invoice_id' => $invoice['invoice_id'],
                    'amount' => $actualPayment,
                    'notes' => 'Proportional payment from total: ' . number_format($record->total_payment),
                    'method' => $record->payment_method ?? 'Cash',
                    'coa_id' => $record->coa_id,
                    'payment_date' => $record->payment_date,
                ]);

                echo 'Created payment detail ID: ' . $detail->id . ' for invoice ' . $invoice['invoice_id'] . PHP_EOL;
            }
        }
    }
} else {
    echo 'No invoice receipts found' . PHP_EOL;
}

echo 'Final payment details count: ' . $payment->vendorPaymentDetail()->count() . PHP_EOL;