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
            $total += HelperController::hitungSubtotal($item->quantity, $item->unit_price, $item->discount, $item->tax, $item->tax_type ?? 'Exclusive');
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
        $prefix = 'QO-' . $date . '-';

        // Use sequential numbering (consistent with SO/Invoice generators) to
        // guarantee monotonic, audit-friendly document numbers.
        $max = Quotation::withoutGlobalScopes()
            ->where('quotation_number', 'like', $prefix . '%')
            ->max('quotation_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        // Guard against concurrent inserts
        do {
            $candidate = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
            $exists = Quotation::withoutGlobalScopes()
                ->where('quotation_number', $candidate)
                ->exists();
            if ($exists) {
                $next++;
            }
        } while ($exists);

        return $candidate;
    }
}
