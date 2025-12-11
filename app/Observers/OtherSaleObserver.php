<?php

namespace App\Observers;

use App\Models\OtherSale;
use App\Services\OtherSaleService;
use Illuminate\Support\Facades\Log;

class OtherSaleObserver
{
    protected $otherSaleService;

    public function __construct()
    {
        $this->otherSaleService = new OtherSaleService();
    }

    public function updated(OtherSale $otherSale)
    {
        // Handle amount or transaction_date changes - reverse old journals and post new ones
        if ($otherSale->wasChanged(['amount', 'transaction_date', 'coa_id', 'cash_bank_account_id']) && $otherSale->hasPostedJournals()) {
            Log::info('OtherSale updated, reversing and re-posting journal entries', [
                'other_sale_id' => $otherSale->id,
                'changes' => $otherSale->getChanges()
            ]);

            // Reverse existing journal entries
            $this->otherSaleService->reverseJournalEntries($otherSale);

            // Re-post with updated data (status will be set back to 'posted' by postJournalEntries)
            $this->otherSaleService->postJournalEntries($otherSale);
        }
    }
}