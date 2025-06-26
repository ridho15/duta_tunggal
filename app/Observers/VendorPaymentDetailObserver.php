<?php

namespace App\Observers;

use App\Http\Controllers\HelperController;
use App\Models\AccountPayable;
use App\Models\Deposit;
use App\Models\VendorPaymentDetail;

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
        }
        // Update vendor payment
        if ($accountPayable->paid > 0 && $accountPayable->total > $accountPayable->remaining) {
            $vendorPayment->update([
                'status' => 'Partial'
            ]);
        } elseif ($accountPayable->remaining == 0) {
            $vendorPayment->update([
                'status' => 'Paid'
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
