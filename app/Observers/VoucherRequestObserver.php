<?php

namespace App\Observers;

use App\Models\VoucherRequest;
use App\Services\VoucherRequestService;
use Illuminate\Support\Facades\Auth;

class VoucherRequestObserver
{
    protected $voucherRequestService;

    public function __construct(VoucherRequestService $voucherRequestService)
    {
        $this->voucherRequestService = $voucherRequestService;
    }

    /**
     * Handle the VoucherRequest "creating" event.
     */
    public function creating(VoucherRequest $voucherRequest): void
    {
        // Auto-generate voucher number if not provided
        if (empty($voucherRequest->voucher_number)) {
            $voucherRequest->voucher_number = $this->voucherRequestService->generateVoucherNumber();
        }

        // Set created_by if not set
        if (empty($voucherRequest->created_by) && Auth::check()) {
            $voucherRequest->created_by = Auth::id();
        }

        // Ensure status is draft for new records
        if (empty($voucherRequest->status)) {
            $voucherRequest->status = 'draft';
        }
    }

    /**
     * Handle the VoucherRequest "created" event.
     */
    public function created(VoucherRequest $voucherRequest): void
    {
        //
    }

    /**
     * Handle the VoucherRequest "updating" event.
     */
    public function updating(VoucherRequest $voucherRequest): void
    {
        // Prevent editing if not in editable state
        if ($voucherRequest->isDirty() && !$voucherRequest->canBeEdited()) {
            // Allow updates to status, approved_by, approved_at, approval_notes
            $allowedFields = ['status', 'approved_by', 'approved_at', 'approval_notes', 'cash_bank_transaction_id'];
            $dirtyFields = array_keys($voucherRequest->getDirty());
            
            foreach ($dirtyFields as $field) {
                if (!in_array($field, $allowedFields) && $field !== 'updated_at') {
                    throw new \Exception("Voucher tidak dapat diubah karena sudah diajukan/diproses. Status: {$voucherRequest->getStatusLabel()}");
                }
            }
        }
    }

    /**
     * Handle the VoucherRequest "updated" event.
     */
    public function updated(VoucherRequest $voucherRequest): void
    {
        //
    }

    /**
     * Handle the VoucherRequest "deleted" event.
     */
    public function deleted(VoucherRequest $voucherRequest): void
    {
        //
    }

    /**
     * Handle the VoucherRequest "restored" event.
     */
    public function restored(VoucherRequest $voucherRequest): void
    {
        //
    }

    /**
     * Handle the VoucherRequest "force deleted" event.
     */
    public function forceDeleted(VoucherRequest $voucherRequest): void
    {
        //
    }
}
