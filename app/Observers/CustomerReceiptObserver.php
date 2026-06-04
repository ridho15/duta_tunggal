<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\AccountReceivable;
use App\Models\CustomerReceipt;
use App\Services\LedgerPostingService;
use Illuminate\Support\Facades\DB;
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

    public function creating(CustomerReceipt $receipt): void
    {
        $receipt->exchange_rate = (float) ($receipt->exchange_rate ?? 1) ?: 1;
        $receipt->total_payment_idr = (float) ($receipt->total_payment_idr ?: $receipt->total_payment);
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
            DB::transaction(function () use ($receipt) {
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
            });
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

            $this->updateAccountReceivables($receipt->fresh());
            self::markArUpdatedInCreate($receipt->id);
        }
    }

    private function updateAccountReceivables(CustomerReceipt $receipt)
    {
        $items = $receipt->customerReceiptItem;

        if ($items->isEmpty()) {
            $this->updateAccountReceivablesFromReceiptHeader($receipt);
            return;
        }

        foreach ($items as $item) {
            // If selected_invoices exists, update AR for each invoice
            if (!empty($item->selected_invoices)) {
                $selectedInvoiceIds = array_values(array_unique(array_filter(array_map(
                    'intval',
                    is_array($item->selected_invoices) ? $item->selected_invoices : (json_decode($item->selected_invoices, true) ?? [])
                ))));

                foreach ($selectedInvoiceIds as $invoiceId) {
                    $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
                    if ($accountReceivable) {
                        $amountIdr = (float) ($item->amount_idr ?: $item->amount);
                        $exchangeRate = (float) ($accountReceivable->exchange_rate ?? $item->exchange_rate ?? 1);
                        $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;
                        $accountReceivable->paid      = $accountReceivable->paid + $amountIdr;
                        $accountReceivable->remaining = $accountReceivable->remaining - $amountIdr;
                        $accountReceivable->paid_original = round((float) $accountReceivable->paid / $exchangeRate, 4);
                        $accountReceivable->remaining_original = round(max(0, (float) $accountReceivable->remaining) / $exchangeRate, 4);
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
                    $amountIdr = (float) ($item->amount_idr ?: $item->amount);
                    $exchangeRate = (float) ($accountReceivable->exchange_rate ?? $item->exchange_rate ?? 1);
                    $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;
                    $accountReceivable->paid      = $accountReceivable->paid + $amountIdr;
                    $accountReceivable->remaining = $accountReceivable->remaining - $amountIdr;
                    $accountReceivable->paid_original = round((float) $accountReceivable->paid / $exchangeRate, 4);
                    $accountReceivable->remaining_original = round(max(0, (float) $accountReceivable->remaining) / $exchangeRate, 4);
                    $accountReceivable->save();

                    $this->syncArStatus($accountReceivable);
                }
            }
        }
    }

    private function updateAccountReceivablesFromReceiptHeader(CustomerReceipt $receipt): void
    {
        $invoiceIds = collect($receipt->selected_invoices ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($invoiceIds->isEmpty() && $receipt->invoice_id) {
            $invoiceIds = collect([(int) $receipt->invoice_id]);
        }

        if ($invoiceIds->isEmpty()) {
            return;
        }

        $remainingPayment = (float) ($receipt->total_payment_idr ?: $receipt->total_payment);

        foreach ($invoiceIds as $invoiceId) {
            if ($remainingPayment <= 0) {
                break;
            }

            $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
            if (!$accountReceivable) {
                continue;
            }

            $amountIdr = min($remainingPayment, max(0, (float) $accountReceivable->remaining));
            if ($amountIdr <= 0) {
                continue;
            }

            $exchangeRate = (float) ($accountReceivable->exchange_rate ?? $receipt->exchange_rate ?? 1);
            $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;

            $accountReceivable->paid = (float) $accountReceivable->paid + $amountIdr;
            $accountReceivable->remaining = (float) $accountReceivable->remaining - $amountIdr;
            $accountReceivable->paid_original = round((float) $accountReceivable->paid / $exchangeRate, 4);
            $accountReceivable->remaining_original = round(max(0, (float) $accountReceivable->remaining) / $exchangeRate, 4);
            $accountReceivable->save();

            $this->syncArStatus($accountReceivable);
            $remainingPayment -= $amountIdr;
        }
    }

    private function syncArStatus(AccountReceivable $ar): void
    {
        if ($ar->remaining <= 0) {
            $ar->invoice?->update(['status' => 'paid']);
            $ar->update(['status' => PaymentStatus::PAID->value]);
            if ($ar->ageingSchedule) {
                $ar->ageingSchedule->delete();
            }
        } elseif ($ar->paid > 0) {
            $ar->invoice?->update(['status' => 'partially_paid']);
        }
    }
}
