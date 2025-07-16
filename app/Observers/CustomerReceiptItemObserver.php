<?php

namespace App\Observers;

use App\Models\AccountReceivable;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class CustomerReceiptItemObserver
{
    public function created(CustomerReceiptItem $customerReceiptItem): void
    {
        $accountReceivable = AccountReceivable::where('invoice_id', $customerReceiptItem->customerReceipt->invoice_id)->first();
        $customerReceipt = $customerReceiptItem->customerReceipt;
        // Update account payable
        if ($accountReceivable) {
            $accountReceivable->paid = $accountReceivable->paid + $customerReceiptItem->amount;
            $accountReceivable->remaining = $accountReceivable->remaining - $customerReceiptItem->amount;
            $accountReceivable->save();
        }
        // Update vendor payment
        if ($accountReceivable->remaining == 0) {
            $customerReceipt->update([
                'status' => 'Paid'
            ]);

            $accountReceivable->invoice->update([
                'status' => 'paid'
            ]);

            $accountReceivable->update([
                'status' => 'Lunas'
            ]);

            $accountReceivable->ageingSchedule->delete();
        } elseif ($accountReceivable->paid > 0 && $accountReceivable->total > $accountReceivable->remaining) {
            $customerReceipt->update([
                'status' => 'Partial'
            ]);

            $accountReceivable->invoice->update([
                'status' => 'partially_paid'
            ]);
        }

        $deposit = Deposit::where('from_model_type', 'App\Models\Customer')
            ->where('from_model_id', $customerReceipt->customer_id)->where('status', 'active')->first();
        if ($deposit) {
            $deposit->remaining_amount = $deposit->remaining_amount - $customerReceiptItem->amount;
            $deposit->used_amount = $deposit->used_amount + $customerReceiptItem->amount;
            $deposit->save();

            $customerReceiptItem->depositLog()->create([
                'deposit_id' => $deposit->id,
                'amount' => $customerReceiptItem->amount,
                'type' => 'use',
                'created_by' => Auth::user()->id
            ]);
        }

        if ($customerReceiptItem->coa_id) {
            $customerReceiptItem->journalEntry()->create([
                'coa_id' => $customerReceiptItem->coa_id,
                'date' => Carbon::now(),
                'description' => 'Customer receipt item',
                'credit' => $customerReceiptItem->amount,
                'journal_type' => 'Sales',
            ]);
        }
    }
}
