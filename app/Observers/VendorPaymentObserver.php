<?php

namespace App\Observers;

use App\Models\VendorPayment;
use App\Services\LedgerPostingService;
use Illuminate\Support\Facades\Log;

class VendorPaymentObserver
{
    protected $ledger;

    public function __construct()
    {
        $this->ledger = new LedgerPostingService();
    }

    public function updated(VendorPayment $payment)
    {
        // Post journal for both partial and full payments
        if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
            // Avoid double posting journals: only post if none exist yet
            if (!$payment->journalEntries()->exists()) {
                $this->ledger->postVendorPayment($payment);
            }
        }
        
        // Update AP status for both partial and paid
        if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
            $this->updateAccountPayableAndInvoiceStatus($payment);
        }
    }

    public function created(VendorPayment $payment)
    {
        // Post journal for both partial and full payments
        if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
            // Avoid double posting journals: only post if none exist yet
            if (!$payment->journalEntries()->exists()) {
                $this->ledger->postVendorPayment($payment);
            }
        }
        
        // Update AP status for both partial and paid
        if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
            $this->updateAccountPayableAndInvoiceStatus($payment);
        }
    }

    public function updateAccountPayableAndInvoiceStatus(VendorPayment $payment)
    {
        // Get all invoices from payment details
        $paymentDetails = $payment->vendorPaymentDetail()->get();

        foreach ($paymentDetails as $detail) {
            $invoiceId = $detail->invoice_id;
            $paidAmount = $detail->amount;

            // Update Account Payable
            $accountPayable = \App\Models\AccountPayable::where('invoice_id', $invoiceId)->first();
            if (!$accountPayable) {
                throw new \Exception("Account payable not found for invoice {$invoiceId}");
            }
            
            // Recalculate paid and remaining based on all payment details for this invoice
            $totalPaidForInvoice = \App\Models\VendorPaymentDetail::where('invoice_id', $invoiceId)->sum('amount');
            $newPaid = min($totalPaidForInvoice, $accountPayable->total);
            $newRemaining = max(0, $accountPayable->total - $newPaid);

            $accountPayable->paid = $newPaid;
            $accountPayable->remaining = $newRemaining;
            $accountPayable->status = $newRemaining <= 0.01 ? 'Lunas' : 'Belum Lunas';
            $accountPayable->save();

            // Sync invoice status with AP
            if ($accountPayable->invoice) {
                $accountPayable->invoice->status = $newRemaining <= 0.01 ? 'paid' : ($newPaid > 0 ? 'partially_paid' : $accountPayable->invoice->status);
                $accountPayable->invoice->save();
            }
        }
    }
}
