<?php

namespace App\Observers;

use App\Models\AccountReceivable;
use App\Models\CustomerReceipt;
use App\Services\LedgerPostingService;
use Illuminate\Support\Facades\Log;

class CustomerReceiptObserver
{
    protected $ledger;

    public function __construct()
    {
        $this->ledger = new LedgerPostingService();
    }

    public function updated(CustomerReceipt $receipt)
    {
        // Post journal for both partial and full receipts
        if (in_array(strtolower($receipt->status ?? ''), ['partial', 'paid'])) {
            $currentTotal = $receipt->getCalculatedTotalAttribute();
            $journalTotal = $receipt->journalEntries()->where('credit', '>', 0)->sum('credit');

            // If journals exist and total has changed, delete old journals and create new ones
            if ($receipt->journalEntries()->exists() && $currentTotal != $journalTotal) {
                $receipt->journalEntries()->delete();
                $this->ledger->postCustomerReceipt($receipt);
            } elseif (!$receipt->journalEntries()->exists()) {
                // If no journals exist, create new ones
                $this->ledger->postCustomerReceipt($receipt);
            }

            // Update AR and invoice status when receipt status changes to Paid or Partial
            $this->updateAccountReceivables($receipt);
        }
    }

    public function created(CustomerReceipt $receipt)
    {
        // Post journal for both partial and full receipts
        if (in_array(strtolower($receipt->status ?? ''), ['partial', 'paid'])) {
            // Avoid double posting journals: only post if none exist yet
            if (!$receipt->journalEntries()->exists()) {
                $this->ledger->postCustomerReceipt($receipt);
            }

            // Note: AR updates are handled in updated() method when status changes
        }
    }

    private function updateAccountReceivables(CustomerReceipt $receipt)
    {
        foreach ($receipt->customerReceiptItem as $item) {
            Log::info('Processing item ID: ' . $item->id . ', selected_invoices: ' . json_encode($item->selected_invoices));
            // If selected_invoices exists, update AR for each invoice
            if (!empty($item->selected_invoices)) {
                foreach ($item->selected_invoices as $invoiceId) {
                    Log::info('Updating AR for invoice ID: ' . $invoiceId);
                    $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
                    if ($accountReceivable) {
                        Log::info('Found AR, current paid: ' . $accountReceivable->paid . ', remaining: ' . $accountReceivable->remaining);
                        // Only update if not already updated (avoid double updates)
                        $accountReceivable->paid = $accountReceivable->paid + $item->amount;
                        $accountReceivable->remaining = $accountReceivable->remaining - $item->amount;
                        $accountReceivable->save();
                        Log::info('Updated AR, new paid: ' . $accountReceivable->paid . ', remaining: ' . $accountReceivable->remaining);

                        // Update status based on remaining amount
                        if ($accountReceivable->remaining == 0) {
                            Log::info('Updating invoice status to paid for invoice ID: ' . $accountReceivable->invoice_id);
                            $accountReceivable->invoice->update(['status' => 'paid']);
                            $accountReceivable->update(['status' => 'Lunas']);
                            if ($accountReceivable->ageingSchedule) {
                                $accountReceivable->ageingSchedule->delete();
                            }
                        } elseif ($accountReceivable->paid > 0 && $accountReceivable->total > $accountReceivable->remaining) {
                            Log::info('Updating invoice status to partially_paid for invoice ID: ' . $accountReceivable->invoice_id);
                            $accountReceivable->invoice->update(['status' => 'partially_paid']);
                        }
                    } else {
                        Log::info('AR not found for invoice ID: ' . $invoiceId);
                    }
                }
            } else {
                Log::info('No selected_invoices for item ID: ' . $item->id);
                // Fallback to old logic
                $invoiceId = $item->invoice_id ?? $receipt->invoice_id;
                Log::info('Fallback to invoice ID: ' . $invoiceId);
                $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();

                if ($accountReceivable) {
                    Log::info('Found AR with fallback, current paid: ' . $accountReceivable->paid . ', remaining: ' . $accountReceivable->remaining);
                    $accountReceivable->paid = $accountReceivable->paid + $item->amount;
                    $accountReceivable->remaining = $accountReceivable->remaining - $item->amount;
                    $accountReceivable->save();
                    Log::info('Updated AR with fallback, new paid: ' . $accountReceivable->paid . ', remaining: ' . $accountReceivable->remaining);

                    // Update status based on remaining amount
                    if ($accountReceivable->remaining == 0) {
                        $accountReceivable->invoice->update(['status' => 'paid']);
                        $accountReceivable->update(['status' => 'Lunas']);
                        if ($accountReceivable->ageingSchedule) {
                            $accountReceivable->ageingSchedule->delete();
                        }
                    } elseif ($accountReceivable->paid > 0 && $accountReceivable->total > $accountReceivable->remaining) {
                        $accountReceivable->invoice->update(['status' => 'partially_paid']);
                    }
                } else {
                    Log::info('AR not found with fallback for invoice ID: ' . $invoiceId);
                }
            }
        }
    }
}