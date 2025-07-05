<?php

namespace App\Observers;

use App\Models\Deposit;
use App\Models\DepositLog;
use Illuminate\Support\Facades\Auth;

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
