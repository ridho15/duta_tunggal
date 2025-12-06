<?php

namespace App\Observers;

use App\Models\CashBankTransfer;
use App\Models\JournalEntry;
use App\Services\CashBankService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CashBankTransferObserver
{
    public function deleting(CashBankTransfer $transfer): void
    {
        // Log the deletion attempt
        Log::info('CashBankTransfer deletion initiated', [
            'transfer_id' => $transfer->id,
            'transfer_number' => $transfer->number,
            'status' => $transfer->status,
            'amount' => $transfer->amount,
        ]);

        // Store original status for restoration
        $cacheKey = 'cash_bank_transfer_status_' . $transfer->id;
        Cache::put($cacheKey, $transfer->status, 300); // 5 minutes

        // If transfer is posted, prevent hard deletion and force soft delete
        if ($transfer->status === 'posted' || $transfer->status === 'reconciled') {
            Log::warning('Attempted to delete posted/reconciled transfer - forcing soft delete', [
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->number,
                'status' => $transfer->status,
            ]);
        }
    }

    public function deleted(CashBankTransfer $transfer): void
    {
        // Clean up journal entries when transfer is deleted (soft delete)
        if ($transfer->trashed()) {
            // Delete associated journal entries
            $journalEntries = \App\Models\JournalEntry::where('source_type', CashBankTransfer::class)
                ->where('source_id', $transfer->id)
                ->where('journal_type', 'transfer')
                ->get();

            foreach ($journalEntries as $entry) {
                $entry->delete(); // This will trigger JournalEntryObserver
            }

            Log::info('CashBankTransfer soft deleted - journal entries cleaned up', [
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->number,
                'journal_entries_deleted' => $journalEntries->count(),
            ]);
        }
    }

    public function restoring(CashBankTransfer $transfer): void
    {
        Log::info('CashBankTransfer restoration initiated', [
            'transfer_id' => $transfer->id,
            'transfer_number' => $transfer->number,
            'status' => $transfer->status,
        ]);
    }

    public function restored(CashBankTransfer $transfer): void
    {
        try {
            // Get original status from cache
            $cacheKey = 'cash_bank_transfer_status_' . $transfer->id;
            $originalStatus = Cache::get($cacheKey);

            Log::info('CashBankTransfer restoration initiated', [
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->number,
                'current_status' => $transfer->status,
                'original_status' => $originalStatus,
            ]);

            // Re-post journal entries if transfer was posted/reconciled before deletion
            if ($originalStatus === 'posted' || $originalStatus === 'reconciled') {
                $cashBankService = app(CashBankService::class);
                $cashBankService->postTransfer($transfer);

                Log::info('CashBankTransfer restored - journal entries re-posted', [
                    'transfer_id' => $transfer->id,
                    'transfer_number' => $transfer->number,
                    'original_status' => $originalStatus,
                ]);
            }

            // Clean up cache
            Cache::forget($cacheKey);

        } catch (\Exception $e) {
            Log::error('Failed to re-post journal entries after transfer restoration', [
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->number,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function updating(CashBankTransfer $transfer): void
    {
        // Store original values in cache for comparison
        $cacheKey = 'cash_bank_transfer_original_' . $transfer->id;
        Cache::put($cacheKey, [
            'status' => $transfer->getOriginal('status'),
            'amount' => $transfer->getOriginal('amount'),
            'from_coa_id' => $transfer->getOriginal('from_coa_id'),
            'to_coa_id' => $transfer->getOriginal('to_coa_id'),
            'other_costs' => $transfer->getOriginal('other_costs'),
        ], 300); // 5 minutes
    }

    public function updated(CashBankTransfer $transfer): void
    {
        try {
            $cacheKey = 'cash_bank_transfer_original_' . $transfer->id;
            $original = Cache::get($cacheKey);

            if (!$original) {
                Log::warning('CashBankTransferObserver: No original data found in cache for transfer ID: ' . $transfer->id);
                return;
            }

            $changes = $transfer->getChanges();

            // Check if any critical fields changed that require journal re-posting
            $criticalFieldsChanged = isset($changes['amount']) ||
                                   isset($changes['from_coa_id']) ||
                                   isset($changes['to_coa_id']) ||
                                   isset($changes['other_costs']) ||
                                   isset($changes['other_costs_coa_id']);

            // If posted transfer is modified with critical changes, reverse old and create new journal entries
            if ($original['status'] === 'posted' && $criticalFieldsChanged) {
                // Reverse old journal entries
                $this->reverseJournalEntries($transfer, $original);

                // Create new journal entries
                $this->createJournalEntries($transfer);

                Log::info('CashBankTransfer updated - journal entries reversed and re-posted', [
                    'transfer_id' => $transfer->id,
                    'transfer_number' => $transfer->number,
                    'changes' => array_keys($changes),
                ]);
            }

            // Log significant changes
            if (!empty($changes)) {
                Log::info('CashBankTransfer updated', [
                    'transfer_id' => $transfer->id,
                    'transfer_number' => $transfer->number,
                    'changes' => $changes,
                ]);
            }

            // Clean up cache
            Cache::forget($cacheKey);

        } catch (\Exception $e) {
            Log::error('CashBankTransferObserver updated error: ' . $e->getMessage(), [
                'transfer_id' => $transfer->id,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function reverseJournalEntries(CashBankTransfer $transfer, array $original): void
    {
        try {
            // Find and delete existing journal entries for this transfer
            $journalEntries = JournalEntry::where('source_type', 'App\\Models\\CashBankTransfer')
                                        ->where('source_id', $transfer->id)
                                        ->get();

            foreach ($journalEntries as $entry) {
                $entry->delete();
                Log::info('Reversed journal entry', [
                    'journal_entry_id' => $entry->id,
                    'transfer_id' => $transfer->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to reverse journal entries', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function createJournalEntries(CashBankTransfer $transfer): void
    {
        try {
            $cashBankService = app(CashBankService::class);
            $cashBankService->postTransfer($transfer);

            Log::info('Created new journal entries for updated transfer', [
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->number,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create journal entries', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}