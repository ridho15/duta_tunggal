<?php

namespace App\Services;

use App\Models\OtherSale;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OtherSaleService
{
    /**
     * Post journal entries for other sales transaction
     *
     * @param OtherSale $otherSale
     * @return bool
     */
    public function postJournalEntries(OtherSale $otherSale): bool
    {
        try {
            DB::beginTransaction();

            // Check if already posted
            if ($otherSale->hasPostedJournals()) {
                throw new \Exception('Journal entries already posted for this transaction');
            }

            // Get the revenue COA
            $revenueCoa = $otherSale->coa;

            // Determine the contra account (Cash/Bank or Accounts Receivable)
            $contraCoa = null;
            if ($otherSale->cash_bank_account_id) {
                // If cash/bank account is specified, use it
                $contraCoa = $otherSale->cashBankAccount->coa;
            } else {
                // Otherwise, use Accounts Receivable
                $contraCoa = ChartOfAccount::where('code', '1120')->first(); // PIUTANG DAGANG
                if (!$contraCoa) {
                    throw new \Exception('Accounts Receivable COA not found');
                }
            }

            // Create journal entries
            $journalEntries = [];

            // Debit: Cash/Bank or Accounts Receivable
            $journalEntries[] = [
                'coa_id' => $contraCoa->id,
                'date' => $otherSale->transaction_date,
                'reference' => $otherSale->reference_number,
                'description' => $otherSale->description,
                'debit' => $otherSale->amount,
                'credit' => 0,
                'journal_type' => 'other_sales',
                'cabang_id' => $otherSale->cabang_id,
                'source_type' => OtherSale::class,
                'source_id' => $otherSale->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Credit: Revenue account
            $journalEntries[] = [
                'coa_id' => $revenueCoa->id,
                'date' => $otherSale->transaction_date,
                'reference' => $otherSale->reference_number,
                'description' => $otherSale->description,
                'debit' => 0,
                'credit' => $otherSale->amount,
                'journal_type' => 'other_sales',
                'cabang_id' => $otherSale->cabang_id,
                'source_type' => OtherSale::class,
                'source_id' => $otherSale->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert journal entries
            JournalEntry::insert($journalEntries);

            // Update status
            $otherSale->update(['status' => 'posted']);

            DB::commit();

            Log::info('Journal entries posted for other sale', [
                'other_sale_id' => $otherSale->id,
                'reference' => $otherSale->reference_number,
                'amount' => $otherSale->amount
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post journal entries for other sale', [
                'other_sale_id' => $otherSale->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Reverse journal entries for other sales transaction
     *
     * @param OtherSale $otherSale
     * @return bool
     */
    public function reverseJournalEntries(OtherSale $otherSale): bool
    {
        try {
            DB::beginTransaction();

            // Delete existing journal entries
            $otherSale->journalEntries()->delete();

            // Update status back to draft
            $otherSale->update(['status' => 'draft']);

            DB::commit();

            Log::info('Journal entries reversed for other sale', [
                'other_sale_id' => $otherSale->id,
                'reference' => $otherSale->reference_number
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reverse journal entries for other sale', [
                'other_sale_id' => $otherSale->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}