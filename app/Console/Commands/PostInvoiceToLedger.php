<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\VendorPayment;
use App\Services\LedgerPostingService;

class PostInvoiceToLedger extends Command
{
    protected $signature = 'ledger:post-invoice {invoice_number} {--post-payment}';
    protected $description = 'Post an invoice (and optional related vendor payment) to the general ledger';

    public function handle()
    {
        $invoiceNumber = $this->argument('invoice_number');
        $postPayment = $this->option('post-payment');

        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();
        if (!$invoice) {
            $this->error('Invoice not found: ' . $invoiceNumber);
            return 1;
        }

        $service = new LedgerPostingService();

        $this->info('Posting Invoice: ' . $invoiceNumber);
        $res = $service->postInvoice($invoice);
        $this->info('Result: ' . json_encode(['status' => $res['status']]));

        if ($postPayment) {
            $this->info('Looking for vendor payment linked to invoice...');
            $payment = VendorPayment::where('invoice_id', $invoice->id)->first();
            if (!$payment) {
                $this->warn('No VendorPayment found for this invoice');
            } else {
                $this->info('Posting VendorPayment ID: ' . $payment->id);
                $r2 = $service->postVendorPayment($payment);
                $this->info('Payment result: ' . json_encode(['status' => $r2['status']]));
            }
        }

        $this->info('Done');
        return 0;
    }
}
