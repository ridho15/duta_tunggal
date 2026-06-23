<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\PurchaseInvoiceAccountingService;
use Illuminate\Console\Command;

class AuditRepairPurchaseInvoice extends Command
{
    protected $signature = 'procurement:audit-repair-purchase-invoice
        {--invoice=1 : Purchase invoice ID to audit or repair.}
        {--apply : Apply the repair. Without this option the command only reports.}';

    protected $description = 'Audit and optionally repair a receipt-backed purchase invoice from its purchase receipts.';

    public function handle(PurchaseInvoiceAccountingService $service): int
    {
        $invoiceId = $this->positiveIntegerOption('invoice');
        if ($invoiceId === null) {
            return self::FAILURE;
        }

        $invoice = Invoice::withoutGlobalScopes()
            ->with(['invoiceItem.product', 'accountPayable'])
            ->find($invoiceId);

        if (! $invoice) {
            $this->error("Purchase invoice {$invoiceId} was not found.");
            return self::FAILURE;
        }

        if ($invoice->from_model_type !== \App\Models\PurchaseOrder::class) {
            $this->error("Invoice {$invoiceId} is not a purchase-order-backed purchase invoice.");
            return self::FAILURE;
        }

        $audit = $service->auditReceiptBackedInvoice($invoice);
        $this->renderAudit($audit, $invoice);

        if (! $audit['receipt_backed']) {
            $this->warn('Invoice ini tidak memiliki purchase_receipts, jadi command ini tidak bisa membangun ulang item dari receipt.');
            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn('Dry-run only. Re-run with --apply to rebuild invoice items, AP, and invoice journals.');
            return $audit['is_mismatched'] ? self::FAILURE : self::SUCCESS;
        }

        $repaired = $service->repairReceiptBackedInvoice($invoice);
        $after = $service->auditReceiptBackedInvoice($repaired->fresh(['invoiceItem.product', 'accountPayable']));

        $this->newLine();
        $this->info('After repair:');
        $this->renderAudit($after, $repaired->fresh());

        if ($after['is_mismatched']) {
            $this->error('Repair completed, but invoice is still mismatched.');
            return self::FAILURE;
        }

        $this->info("Purchase invoice {$invoiceId} repaired successfully.");
        return self::SUCCESS;
    }

    protected function renderAudit(array $audit, Invoice $invoice): void
    {
        $status = $audit['is_mismatched'] ? 'MISMATCH' : 'OK';

        $this->info("Invoice: {$audit['invoice_number']} (#{$audit['invoice_id']})");
        $this->line("Status: {$status}");

        $this->table(
            ['Field', 'Current', 'Expected'],
            [
                ['Subtotal/DPP', $this->money($audit['current']['subtotal'] ?? 0), $this->money($audit['expected']['subtotal'] ?? 0)],
                ['PPN Rate', $this->rate($audit['current']['ppn_rate'] ?? 0), $this->rate($audit['expected']['ppn_rate'] ?? 0)],
                ['PPN Amount', $this->money($audit['current']['ppn_amount'] ?? 0), $this->money($audit['expected']['ppn_amount'] ?? 0)],
                ['Total', $this->money($audit['current']['total'] ?? 0), $this->money($audit['expected']['total'] ?? 0)],
                ['AP Total', $this->money($audit['current']['account_payable_total'] ?? 0), $this->money($audit['expected']['total'] ?? 0)],
                ['AP Remaining', $this->money($audit['current']['account_payable_remaining'] ?? 0), $this->money($audit['expected']['total'] ?? 0)],
            ]
        );

        $this->line('Invoice items:');
        $currentItems = collect($audit['current_items'] ?? []);
        $expectedItems = collect($audit['expected_items'] ?? []);
        $max = max($currentItems->count(), $expectedItems->count());
        $rows = [];
        for ($i = 0; $i < $max; $i++) {
            $current = $currentItems->get($i, []);
            $expected = $expectedItems->get($i, []);
            $rows[] = [
                $i + 1,
                $current['product'] ?? ($current['product_id'] ?? '-'),
                $this->itemSummary($current),
                $this->itemSummary($expected),
            ];
        }
        $this->table(['#', 'Product', 'Current', 'Expected'], $rows);

        $journalEntries = JournalEntry::withoutGlobalScopes()
            ->with('coa')
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('is_reversal', false)
            ->whereNull('reversal_of_transaction_id')
            ->orderBy('id')
            ->get();

        $exchangeRate = (float) ($invoice->exchange_rate ?? 1);
        if ($exchangeRate <= 0) {
            $exchangeRate = 1;
        }

        $expectedSubtotalIdr = round((float) ($audit['expected']['subtotal'] ?? 0) * $exchangeRate, 2);
        $expectedPpnIdr = round((float) ($audit['expected']['ppn_amount'] ?? 0) * $exchangeRate, 2);
        $expectedTotalIdr = round((float) ($audit['expected']['total'] ?? 0) * $exchangeRate, 2);

        $this->line('Open invoice journals:');
        $this->table(
            ['ID', 'COA', 'Debit', 'Credit', 'Original'],
            $journalEntries->map(fn (JournalEntry $entry) => [
                $entry->id,
                trim(($entry->coa?->code ?? '') . ' ' . ($entry->coa?->name ?? '')),
                $this->money($entry->debit),
                $this->money($entry->credit),
                $this->money($entry->amount_original_currency),
            ])->all()
        );

        $this->table(
            ['Expected Journal Role', 'Amount IDR'],
            [
                ['Debit Unbilled Purchase', $this->money($expectedSubtotalIdr)],
                ['Debit PPN Masukan', $this->money($expectedPpnIdr)],
                ['Credit Hutang Dagang', $this->money($expectedTotalIdr)],
            ]
        );
    }

    protected function itemSummary(array $item): string
    {
        if (empty($item)) {
            return '-';
        }

        return sprintf(
            'qty %s x %s = %s, tax %s (%s)',
            $this->decimal($item['quantity'] ?? 0),
            $this->money($item['price'] ?? 0),
            $this->money($item['total'] ?? 0),
            $this->rate($item['tax_rate'] ?? 0),
            $this->money($item['tax_amount'] ?? 0)
        );
    }

    protected function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    protected function rate(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.') . '%';
    }

    protected function decimal(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }

    protected function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if (! is_numeric($value) || (int) $value <= 0) {
            $this->error("Option --{$name} must be a positive numeric ID.");
            return null;
        }

        return (int) $value;
    }
}
