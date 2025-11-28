<?php

namespace App\Listeners;

use App\Events\TransferPosted;
use App\Models\BankReconciliation;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class AutoCreateBankReconciliation
{
    public function handle(TransferPosted $event)
    {
        $transfer = $event->transfer;

        // Get bank accounts involved (assuming code starts with 1111 or 1112)
        $bankCoas = [];
        if (str_starts_with($transfer->fromCoa->code, '1111') || str_starts_with($transfer->fromCoa->code, '1112')) {
            $bankCoas[] = $transfer->fromCoa;
        }
        if (str_starts_with($transfer->toCoa->code, '1111') || str_starts_with($transfer->toCoa->code, '1112')) {
            $bankCoas[] = $transfer->toCoa;
        }

        foreach ($bankCoas as $coa) {
            // Determine period: start of month to end of month
            $periodStart = $transfer->date->startOfMonth();
            $periodEnd = $transfer->date->endOfMonth();

            // Check if reconciliation already exists for this coa and period
            $existing = BankReconciliation::where('coa_id', $coa->id)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->first();

            if (!$existing) {
                // Calculate book balance: sum of debits - credits for the period
                $entries = JournalEntry::where('coa_id', $coa->id)
                    ->whereBetween('date', [$periodStart, $periodEnd])
                    ->get();

                $debit = $entries->sum('debit');
                $credit = $entries->sum('credit');
                $bookBalance = $debit - $credit;

                // Create reconciliation
                $reconciliation = BankReconciliation::create([
                    'coa_id' => $coa->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'book_balance' => $bookBalance,
                    'status' => 'open',
                    'notes' => 'Auto-created from transfer ' . $transfer->number,
                ]);

                // Auto-reconcile entries from this transfer
                JournalEntry::where('source_type', get_class($transfer))
                    ->where('source_id', $transfer->id)
                    ->where('coa_id', $coa->id)
                    ->update([
                        'bank_recon_id' => $reconciliation->id,
                        'bank_recon_status' => 'cleared',
                        'bank_recon_date' => now()->toDateString(),
                    ]);
            } else {
                // If exists, just reconcile the new entries
                JournalEntry::where('source_type', get_class($transfer))
                    ->where('source_id', $transfer->id)
                    ->where('coa_id', $coa->id)
                    ->whereNull('bank_recon_id')
                    ->update([
                        'bank_recon_id' => $existing->id,
                        'bank_recon_status' => 'cleared',
                        'bank_recon_date' => now()->toDateString(),
                    ]);

                // Recalculate book balance
                $entries = JournalEntry::where('coa_id', $coa->id)
                    ->whereBetween('date', [$periodStart, $periodEnd])
                    ->get();
                $debit = $entries->sum('debit');
                $credit = $entries->sum('credit');
                $existing->update(['book_balance' => $debit - $credit]);
            }
        }
    }
}