<?php

namespace App\Observers;

use App\Models\AccountReceivable;
use App\Models\CustomerReceipt;
use App\Services\LedgerPostingService;
use Illuminate\Support\Facades\Log;

class CustomerReceiptObserver
{
    protected $ledger;

    /**
     * Track receipt IDs for which AR was already updated in afterCreate().
     * Prevents double-counting when the observer fires on the status update
     * triggered by CustomerReceiptItemObserver.
     */
    protected static array $arUpdatedInCreate = [];

    public function __construct()
    {
        $this->ledger = new LedgerPostingService();
    }

    /**
     * Called by CreateCustomerReceipt::afterCreate() once AR has been updated
     * in the page handler. Prevents the observer from double-counting.
     */
    public static function markArUpdatedInCreate(int $receiptId): void
    {
        self::$arUpdatedInCreate[$receiptId] = true;
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

            // Update AR only if afterCreate() did NOT already handle it.
            // This prevents double-counting when status is set by CustomerReceiptItemObserver
            // right after items are created in the same request.
            if (!isset(self::$arUpdatedInCreate[$receipt->id])) {
                $this->updateAccountReceivables($receipt);
            }
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
        }
    }

    private function updateAccountReceivables(CustomerReceipt $receipt)
    {
        foreach ($receipt->customerReceiptItem as $item) {
            // If selected_invoices exists, update AR for each invoice
            if (!empty($item->selected_invoices)) {
                foreach ($item->selected_invoices as $invoiceId) {
                    $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
                    if ($accountReceivable) {
                        $accountReceivable->paid      = $accountReceivable->paid + $item->amount;
                        $accountReceivable->remaining = $accountReceivable->remaining - $item->amount;
                        $accountReceivable->save();

                        // Update invoice and AR status
                        $this->syncArStatus($accountReceivable);
                    }
                }
            } else {
                // Fallback: use item's own invoice_id or receipt's invoice_id
                $invoiceId = $item->invoice_id ?? $receipt->invoice_id;
                $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();

                if ($accountReceivable) {
                    $accountReceivable->paid      = $accountReceivable->paid + $item->amount;
                    $accountReceivable->remaining = $accountReceivable->remaining - $item->amount;
                    $accountReceivable->save();

                    $this->syncArStatus($accountReceivable);
                }
            }
        }
    }

    private function syncArStatus(AccountReceivable $ar): void
    {
        if ($ar->remaining <= 0) {
            $ar->invoice?->update(['status' => 'paid']);
            $ar->update(['status' => 'Lunas']);
            if ($ar->ageingSchedule) {
                $ar->ageingSchedule->delete();
            }
        } elseif ($ar->paid > 0) {
            $ar->invoice?->update(['status' => 'partially_paid']);
        }
    }
}