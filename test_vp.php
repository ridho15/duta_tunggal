<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$vp = App\Models\VendorPayment::find(6);
if ($vp) {
    echo 'Found VP ID: ' . $vp->id . PHP_EOL;
    echo 'invoice_receipts: ' . json_encode($vp->invoice_receipts) . PHP_EOL;

    $receipts = $vp->invoice_receipts ?? [];
    echo 'is_array: ' . (is_array($receipts) ? 'true' : 'false') . PHP_EOL;
    echo 'count: ' . count($receipts) . PHP_EOL;

    if (!empty($receipts) && is_array($receipts)) {
        echo 'Should process receipts' . PHP_EOL;

        // Simulate afterCreate logic
        $totalPaymentFromUser = $vp->total_payment - ($vp->payment_adjustment ?? 0);
        echo 'totalPaymentFromUser: ' . $totalPaymentFromUser . PHP_EOL;

        $totalInvoiceRemaining = 0;
        $invoiceData = [];

        foreach ($receipts as $receipt) {
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
                }
            }
        }

        echo 'Total invoice remaining: ' . $totalInvoiceRemaining . PHP_EOL;

        foreach ($invoiceData as $invoice) {
            if ($totalInvoiceRemaining > 0) {
                $proportion = $invoice['remaining'] / $totalInvoiceRemaining;
                $paymentForThisInvoice = $totalPaymentFromUser * $proportion;
                $actualPayment = min($paymentForThisInvoice, $invoice['remaining']);

                echo 'Creating payment for invoice ' . $invoice['invoice_id'] . ': ' . $actualPayment . PHP_EOL;

                if ($actualPayment > 0) {
                    $vp->vendorPaymentDetail()->create([
                        'invoice_id' => $invoice['invoice_id'],
                        'amount' => $actualPayment,
                        'notes' => 'Manual test payment',
                        'method' => $vp->payment_method ?? 'Cash',
                        'coa_id' => $vp->coa_id,
                        'payment_date' => $vp->payment_date,
                    ]);
                    echo 'Payment detail created!' . PHP_EOL;
                }
            }
        }
    } else {
        echo 'No receipts to process' . PHP_EOL;
    }

    $details = $vp->vendorPaymentDetail()->count();
    echo 'Final details count: ' . $details . PHP_EOL;
} else {
    echo 'VP not found' . PHP_EOL;
}