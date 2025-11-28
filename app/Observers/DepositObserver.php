<?php

namespace App\Observers;

use App\Models\Deposit;
use App\Models\DepositLog;
use Illuminate\Support\Facades\Auth;
use App\Services\LedgerPostingService;

class DepositObserver
{
    /**
     * Handle the Deposit "created" event.
     */
    public function created(Deposit $deposit): void
    {
        $deposit->depositLog()->create([
            'type' => 'create',
            'amount' => $deposit->amount,
            'created_by' => Auth::user()->id
        ]);

        // Post deposit to ledger to ensure journal entries exist for all creation paths
        // DISABLED: Journal entries are now handled by CreateDeposit::afterCreate()
        // to avoid duplication
        /*
        try {
            $ledger = new LedgerPostingService();
            $ledger->postDeposit($deposit);
        } catch (\Throwable $e) {
            // Log but don't break the request flow
            \Illuminate\Support\Facades\Log::error('Failed posting deposit to ledger: ' . $e->getMessage(), ['deposit_id' => $deposit->id]);
        }
        */
    }

    /**
     * Handle the Deposit "updated" event.
     */
    public function updated(Deposit $deposit): void {}

    /**
     * Handle the Deposit "deleted" event.
     */
    public function deleted(Deposit $deposit): void
    {
        //
    }

    /**
     * Handle the Deposit "restored" event.
     */
    public function restored(Deposit $deposit): void
    {
        //
    }

    /**
     * Handle the Deposit "force deleted" event.
     */
    public function forceDeleted(Deposit $deposit): void
    {
        //
    }
}
