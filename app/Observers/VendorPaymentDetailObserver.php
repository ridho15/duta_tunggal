<?php

namespace App\Observers;

use App\Http\Controllers\HelperController;
use App\Models\AccountPayable;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\VendorPaymentDetail;
use Illuminate\Support\Facades\Auth;

class VendorPaymentDetailObserver
{
    /**
     * Handle the VendorPaymentDetail "created" event.
     */
    public function created(VendorPaymentDetail $vendorPaymentDetail): void
    {
        $accountPayable = AccountPayable::where('invoice_id', $vendorPaymentDetail->vendorPayment->invoice_id)->first();
        $vendorPayment = $vendorPaymentDetail->vendorPayment;
        // Update account payable
        if ($accountPayable) {
            $accountPayable->paid = $accountPayable->paid + $vendorPaymentDetail->amount;
            $accountPayable->remaining = $accountPayable->remaining - $vendorPaymentDetail->amount;
            $accountPayable->save();
        }
        // Update vendor payment
        if ($accountPayable->remaining == 0) {
            $vendorPayment->update([
                'status' => 'Paid'
            ]);

            $accountPayable->invoice->update([
                'status' => 'paid'
            ]);

            $accountPayable->update([
                'status' => 'Lunas'
            ]);

            $accountPayable->ageingSchedule->delete();
        } elseif ($accountPayable->paid > 0 && $accountPayable->total > $accountPayable->remaining) {
            $vendorPayment->update([
                'status' => 'Partial'
            ]);

            $accountPayable->invoice->update([
                'status' => 'partially_paid'
            ]);
        }

        $deposit = Deposit::where('from_model_type', 'App\Models\Supplier')
            ->where('from_model_id', $vendorPayment->supplier_id)->where('status', 'active')->first();
        if ($deposit) {
            $deposit->remaining_amount = $deposit->remaining_amount - $vendorPaymentDetail->amount;
            $deposit->used_amount = $deposit->used_amount + $vendorPaymentDetail->amount;
            $deposit->save();

            $vendorPaymentDetail->depositLog()->create([
                'deposit_id' => $deposit->id,
                'amount' => $vendorPaymentDetail->amount,
                'type' => 'use',
                'created_by' => Auth::user()->id
            ]);
        }
    }

    /**
     * Handle the VendorPaymentDetail "updated" event.
     */
    public function updated(VendorPaymentDetail $vendorPaymentDetail): void
    {
        //
    }

    /**
     * Handle the VendorPaymentDetail "deleted" event.
     */
    public function deleted(VendorPaymentDetail $vendorPaymentDetail): void
    {
        //
    }

    /**
     * Handle the VendorPaymentDetail "restored" event.
     */
    public function restored(VendorPaymentDetail $vendorPaymentDetail): void
    {
        //
    }

    /**
     * Handle the VendorPaymentDetail "force deleted" event.
     */
    public function forceDeleted(VendorPaymentDetail $vendorPaymentDetail): void
    {
        //
    }
}
