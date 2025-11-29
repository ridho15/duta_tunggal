<?php

namespace App\Observers;

use App\Models\CustomerReceipt;
use App\Services\LedgerPostingService;
use Illuminate\Support\Facades\Log;

class CustomerReceiptObserver
{
    protected $ledger;

    public function __construct()
    {
        $this->ledger = new LedgerPostingService();
    }

    public function updated(CustomerReceipt $receipt)
    {
        // Post journal for both partial and full receipts
        if (in_array(strtolower($receipt->status ?? ''), ['partial', 'paid'])) {
            // Avoid double posting journals: only post if none exist yet
            if (!$receipt->journalEntries()->exists()) {
                $this->ledger->postCustomerReceipt($receipt);
            }
        }
    }

    public function created(CustomerReceipt $receipt)
    {
        // Post journal for both partial and full receipts
        if (in_array(strtolower($receipt->status ?? ''), ['partial', 'paid'])) {
            // Avoid double posting journals: only post if none exist yet
            if (!$receipt->journalEntries()->exists()) {
                $this->ledger->postCustomerReceipt($receipt);
            }
        }
    }
}