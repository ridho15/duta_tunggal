<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\LedgerPostingService;
use Illuminate\Console\Command;

class ReversePurchaseInvoiceJournal extends Command
{
    protected $signature = 'ledger:reverse-invoice-journal
                            {invoice_number : Invoice number to reverse}
                            {--date= : Reversal date in YYYY-MM-DD format}
                            {--dry-run : Show what would be reversed without making changes}
                            {--yes : Execute without confirmation}';

    protected $description = 'Reverse all journal entries linked to a purchase invoice';

    public function handle(): int
    {
        $invoiceNumber = $this->argument('invoice_number');
        $reversalDate = $this->option('date');
        $dryRun = (bool) $this->option('dry-run');
        $autoYes = (bool) $this->option('yes');

        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();
        if (! $invoice) {
            $this->error('Invoice not found: ' . $invoiceNumber);
            return self::FAILURE;
        }

        $originalEntries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('is_reversal', false)
            ->get();

        if ($originalEntries->isEmpty()) {
            $this->warn('No original journal entries found for invoice ' . $invoiceNumber);
            return self::SUCCESS;
        }

        $alreadyReversed = $originalEntries->contains(fn (JournalEntry $entry) => ! empty($entry->reversal_of_transaction_id));
        if ($alreadyReversed) {
            $this->warn('Invoice ' . $invoiceNumber . ' already has reversal markers on original journal entries.');
            return self::SUCCESS;
        }

        $this->info('Invoice: ' . $invoiceNumber);
        $this->info('Original journal entries: ' . $originalEntries->count());
        $this->table(
            ['ID', 'COA', 'Debit', 'Credit', 'Description'],
            $originalEntries->map(fn (JournalEntry $entry) => [
                $entry->id,
                $entry->coa?->code,
                number_format((float) $entry->debit, 2),
                number_format((float) $entry->credit, 2),
                $entry->description,
            ])->all()
        );

        if ($dryRun) {
            $this->info('Dry-run mode enabled. No changes were made.');
            return self::SUCCESS;
        }

        if (! $autoYes && ! $this->confirm('Proceed to reverse these journal entries?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $service = app(LedgerPostingService::class);
        $reversals = $service->reverseInvoiceJournalEntries($invoice, $reversalDate);

        $this->info('Created reversal entries: ' . $reversals->count());
        $this->table(
            ['ID', 'COA', 'Debit', 'Credit', 'Description'],
            $reversals->map(fn (JournalEntry $entry) => [
                $entry->id,
                $entry->coa?->code,
                number_format((float) $entry->debit, 2),
                number_format((float) $entry->credit, 2),
                $entry->description,
            ])->all()
        );

        $this->info('Done.');
        return self::SUCCESS;
    }
}