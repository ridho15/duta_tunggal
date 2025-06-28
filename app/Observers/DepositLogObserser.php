<?php

namespace App\Observers;

use App\Models\DepositLog;

class DepositLogObserser
{
    /**
     * Handle the DepositLog "created" event.
     */
    public function created(DepositLog $depositLog): void
    {
        
    }

    /**
     * Handle the DepositLog "updated" event.
     */
    public function updated(DepositLog $depositLog): void
    {
        //
    }

    /**
     * Handle the DepositLog "deleted" event.
     */
    public function deleted(DepositLog $depositLog): void
    {
        //
    }

    /**
     * Handle the DepositLog "restored" event.
     */
    public function restored(DepositLog $depositLog): void
    {
        //
    }

    /**
     * Handle the DepositLog "force deleted" event.
     */
    public function forceDeleted(DepositLog $depositLog): void
    {
        //
    }
}
