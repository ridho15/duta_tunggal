<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class QuotationService
{
    public function updateTotalAmount($quotation)
    {
        $total = 0;
        foreach ($quotation->quotationItem as $item) {
            $total += HelperController::hitungSubtotal($item->quantity, $item->unit_price, $item->discount, $item->tax);
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

    public function generateCode()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = Quotation::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->quotation_number, -4));
            $number = $lastNumber + 1;
        }

        return 'QO-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
