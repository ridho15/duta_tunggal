<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class QuotationService
{
    public function updateTotalAmount($quotation)
    {
        $total = 0;
        foreach ($quotation->quotationItem as $item) {
            $total += $item->total_price;
        }

        $quotation->update([
            'total_amount' => $total
        ]);
    }

    public function requestApprove($quotation)
    {
        return $quotation->update([
            'status' => 'request_approve',
            'request_approve_by' => Auth::user()->id,
            'request_approve_at' => Carbon::now()
        ]);
    }

    public function approve($quotation)
    {
        return $quotation->update([
            'status' => 'approve',
            'approve_by' => Auth::user()->id,
            'approve_at' => Carbon::now()
        ]);
    }

    public function reject($quotation)
    {
        return $quotation->update([
            'status' => 'reject',
            'reject_by' => Auth::user()->id,
            'reject_at' => Carbon::now()
        ]);
    }
}
