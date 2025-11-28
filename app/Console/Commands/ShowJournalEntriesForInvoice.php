<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\JournalEntry;

class ShowJournalEntriesForInvoice extends Command
{
    protected $signature = 'ledger:show-journal {invoice_number}';
    protected $description = 'Show journal entries created for a given invoice number';

    public function handle()
    {
        $invoiceNumber = $this->argument('invoice_number');
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();
        if (!$invoice) {
            $this->error('Invoice not found: ' . $invoiceNumber);
            return 1;
        }

        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->orderBy('id')
            ->get(['id','coa_id','date','reference','description','debit','credit','journal_type']);

        if ($entries->isEmpty()) {
            $this->info('No journal entries found for invoice ' . $invoiceNumber);
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
