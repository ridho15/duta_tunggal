<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VendorPayment;
use App\Models\JournalEntry;

class ShowJournalEntriesForPayment extends Command
{
    protected $signature = 'ledger:show-journal-payment {payment_id}';
    protected $description = 'Show journal entries created for a vendor payment id';

    public function handle()
    {
        $paymentId = $this->argument('payment_id');
        $payment = VendorPayment::find($paymentId);
        if (!$payment) {
            $this->error('VendorPayment not found: ' . $paymentId);
            return 1;
        }

        $entries = JournalEntry::where('source_type', VendorPayment::class)
            ->where('source_id', $payment->id)
            ->orderBy('id')
            ->get(['id','coa_id','date','reference','description','debit','credit','journal_type']);

        if ($entries->isEmpty()) {
            $this->info('No journal entries found for payment ' . $paymentId);
            return 0;
        }

        $this->table(
            ['ID','COA ID','Date','Reference','Description','Debit','Credit','Type'],
            $entries->map(function($e){
                return [
                    $e->id, $e->coa_id, $e->date, $e->reference, $e->description, number_format($e->debit,2), number_format($e->credit,2), $e->journal_type
                ];
            })->toArray()
        );

        return 0;
    }
}
