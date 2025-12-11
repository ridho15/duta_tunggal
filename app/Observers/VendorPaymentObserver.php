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
        // Handle amount changes - reverse old journals and post new ones
        if ($payment->wasChanged('total_payment') && $payment->journalEntries()->exists()) {
            $this->reverseJournalEntries($payment);
            // Re-post with new amount if payment is still active
            if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
                $this->ledger->postVendorPayment($payment);
            }
        }
        
        // Post journal for both partial and full payments (only if no journals exist)
        if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
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
        // Validate payment amount against remaining balances before creating details
        if (!empty($payment->selected_invoices)) {
            $this->validatePaymentAmount($payment);
        }

        // Create VendorPaymentDetail from selected_invoices if none exist
        if ($payment->vendorPaymentDetail()->count() == 0 && !empty($payment->selected_invoices)) {
            $this->createPaymentDetailsFromSelectedInvoices($payment);
        }

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

    public function deleted(VendorPayment $payment)
    {
        // Reverse account payable updates when payment is deleted
        $this->reverseAccountPayableAndInvoiceStatus($payment);

        // Reverse journal entries when payment is deleted
        $this->reverseJournalEntries($payment);

        // Soft delete related vendor payment details
        $payment->vendorPaymentDetail()->delete();
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
            $totalPaidForInvoice = \App\Models\VendorPaymentDetail::where('invoice_id', $invoiceId)
                ->whereHas('vendorPayment', function($query) {
                    $query->whereIn('status', ['partial', 'paid']);
                })
                ->sum('amount');

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

    public function reverseAccountPayableAndInvoiceStatus(VendorPayment $payment)
    {
        // Get all invoices from payment details (including soft deleted ones)
        $paymentDetails = $payment->vendorPaymentDetail()->withTrashed()->get();

        foreach ($paymentDetails as $detail) {
            $invoiceId = $detail->invoice_id;
            $paidAmount = $detail->amount;

            // Update Account Payable - subtract the payment amount
            $accountPayable = \App\Models\AccountPayable::where('invoice_id', $invoiceId)->first();
            if (!$accountPayable) {
                continue; // Skip if AP not found
            }

            // Subtract the payment amount directly from paid and add to remaining
            $newPaid = max(0, $accountPayable->paid - $paidAmount);
            $newRemaining = $accountPayable->total - $newPaid;

            $accountPayable->paid = $newPaid;
            $accountPayable->remaining = $newRemaining;
            $accountPayable->status = $newRemaining <= 0.01 ? 'Lunas' : 'Belum Lunas';
            $accountPayable->save();

            // Sync invoice status with AP
            if ($accountPayable->invoice) {
                $accountPayable->invoice->status = $newRemaining <= 0.01 ? 'paid' : ($newPaid > 0 ? 'partially_paid' : 'unpaid');
                $accountPayable->invoice->save();
            }
        }
    }

    protected function reverseJournalEntries(VendorPayment $payment)
    {
        // Delete existing journal entries to prepare for re-posting
        $payment->journalEntries()->delete();
    }

    protected function validatePaymentAmount(VendorPayment $payment)
    {
        $selectedInvoices = $payment->selected_invoices;
        if (!$selectedInvoices) {
            return;
        }

        if (!is_array($selectedInvoices)) {
            $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
        }

        if (empty($selectedInvoices)) {
            return;
        }

        // Extract invoice IDs
        $invoiceIds = [];
        foreach ($selectedInvoices as $item) {
            if (is_numeric($item)) {
                $invoiceIds[] = (int) $item;
            } elseif (is_array($item) && isset($item['invoice_id'])) {
                $invoiceIds[] = (int) $item['invoice_id'];
            } elseif (is_object($item) && isset($item->invoice_id)) {
                $invoiceIds[] = (int) $item->invoice_id;
            }
        }

        $invoiceIds = array_unique($invoiceIds);

        if (empty($invoiceIds)) {
            return;
        }

        // Calculate total remaining balance
        $totalRemaining = \App\Models\Invoice::whereIn('id', $invoiceIds)
            ->with('accountPayable')
            ->get()
            ->sum(function ($invoice) {
                return $invoice->accountPayable->remaining ?? $invoice->total;
            });

        // Check if payment amount exceeds total remaining balance
        if ($payment->total_payment > $totalRemaining) {
            throw new \Exception("Payment amount ({$payment->total_payment}) exceeds total remaining balance ({$totalRemaining}). Overpayment is not allowed.");
        }
    }

    protected function createPaymentDetailsFromSelectedInvoices(VendorPayment $payment)
    {
        $selectedInvoices = $payment->selected_invoices;
        if (!$selectedInvoices) {
            return;
        }

        if (!is_array($selectedInvoices)) {
            $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
        }

        if (empty($selectedInvoices)) {
            return;
        }

        // Handle different data formats:
        // 1. Array of invoice IDs: [1, 2, 3]
        // 2. Array of objects: [['invoice_id' => 1, 'amount' => 1000], ...]
        $invoiceIds = [];
        $hasPaymentDetails = false;

        foreach ($selectedInvoices as $item) {
            if (is_numeric($item)) {
                // Format 1: direct invoice ID
                $invoiceIds[] = (int) $item;
            } elseif (is_array($item) && isset($item['invoice_id'])) {
                // Format 2: object with invoice_id
                $invoiceIds[] = (int) $item['invoice_id'];
                $hasPaymentDetails = true;
            } elseif (is_object($item) && isset($item->invoice_id)) {
                // Format 2: object with invoice_id
                $invoiceIds[] = (int) $item->invoice_id;
                $hasPaymentDetails = true;
            }
        }

        $invoiceIds = array_unique($invoiceIds);

        if (empty($invoiceIds)) {
            return;
        }

        $invoices = \App\Models\Invoice::whereIn('id', $invoiceIds)
            ->with('accountPayable')
            ->get();

        // If we already have payment details format, don't create additional details
        if ($hasPaymentDetails) {
            return;
        }

        $totalPayment = $payment->total_payment ?? 0;
        $remainingPayment = $totalPayment;

        foreach ($invoices as $invoice) {
            if ($remainingPayment <= 0) break;

            $remainingAmount = $invoice->accountPayable->remaining ?? $invoice->total;
            $paymentAmount = min($remainingAmount, $remainingPayment);

            \App\Models\VendorPaymentDetail::create([
                'vendor_payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $paymentAmount,
                'method' => $payment->payment_method ?? 'Cash',
                'payment_date' => $payment->payment_date,
                'coa_id' => $payment->coa_id,
            ]);

            $remainingPayment -= $paymentAmount;
        }
    }
}
