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
            'created_by' => Auth::user()->id ?? $deposit->created_by
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
    public function updated(Deposit $deposit): void
    {
        // Check if amount has changed
        if ($deposit->wasChanged('amount')) {
            $oldAmount = $deposit->getOriginal('amount');
            $newAmount = $deposit->amount;

            // Update all journal entries created during deposit creation
            // These have reference pattern 'DEP-{id}' and are not soft deleted
            if ($deposit->from_model_type === 'App\Models\Supplier') {
                // For supplier deposit: 
                // - Debit entry (uang muka): debit = new amount, credit = 0
                // - Credit entry (kas/bank): debit = 0, credit = new amount
                $deposit->journalEntry()
                    ->where('reference', 'DEP-' . $deposit->id)
                    ->whereNull('deleted_at')
                    ->where('debit', '>', 0)
                    ->update(['debit' => $newAmount, 'credit' => 0]);

                $deposit->journalEntry()
                    ->where('reference', 'DEP-' . $deposit->id)
                    ->whereNull('deleted_at')
                    ->where('credit', '>', 0)
                    ->update(['debit' => 0, 'credit' => $newAmount]);

            } elseif ($deposit->from_model_type === 'App\Models\Customer') {
                // For customer deposit:
                // - Debit entry (kas/bank): debit = new amount, credit = 0
                // - Credit entry (hutang titipan): debit = 0, credit = new amount
                $deposit->journalEntry()
                    ->where('reference', 'DEP-' . $deposit->id)
                    ->whereNull('deleted_at')
                    ->where('debit', '>', 0)
                    ->update(['debit' => $newAmount, 'credit' => 0]);

                $deposit->journalEntry()
                    ->where('reference', 'DEP-' . $deposit->id)
                    ->whereNull('deleted_at')
                    ->where('credit', '>', 0)
                    ->update(['debit' => 0, 'credit' => $newAmount]);
            }

            // Log the amount change
            $deposit->depositLog()->create([
                'type' => 'add',
                'amount' => $newAmount - $oldAmount, // Difference
                'note' => "Amount changed from " . number_format($oldAmount, 0, ',', '.') . " to " . number_format($newAmount, 0, ',', '.'),
                'created_by' => Auth::user()->id ?? null
            ]);
        }
    }

    /**
     * Handle the Deposit "deleted" event.
     */
    public function deleted(Deposit $deposit): void
    {
        // Soft delete all related journal entries when deposit is deleted
        $deposit->journalEntry()->delete();
    }

    /**
     * Handle the Deposit "restored" event.
     */
    public function restored(Deposit $deposit): void
    {
        // Restore all related journal entries when deposit is restored
        $deposit->journalEntry()->restore();
    }

    /**
     * Handle the Deposit "force deleted" event.
     */
    public function forceDeleted(Deposit $deposit): void
    {
        //
    }
}
