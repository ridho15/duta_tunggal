<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\PurchaseInvoiceAccountingService;
use Illuminate\Console\Command;

class AuditPurchaseInvoices extends Command
{
    protected $signature = 'procurement:audit-purchase-invoices
        {--invoice= : Audit a single purchase invoice ID}
        {--repair : Repair mismatched invoices}
        {--include-paid : Allow repair for paid or partially paid invoices}';

    protected $description = 'Audit and optionally repair purchase invoice totals, branches, account payables, and journals.';

    public function handle(PurchaseInvoiceAccountingService $service): int
    {
        $query = Invoice::withoutGlobalScopes()
            ->where('from_model_type', \App\Models\PurchaseOrder::class)
            ->with('accountPayable', 'invoiceItem');

        if ($this->option('invoice')) {
            $query->whereKey((int) $this->option('invoice'));
        }

        $invoices = $query->orderBy('id')->get();
        if ($invoices->isEmpty()) {
            $this->warn('Tidak ada purchase invoice ditemukan untuk kriteria ini.');
            return self::SUCCESS;
        }

        $repair = (bool) $this->option('repair');
        $rows = [];

        foreach ($invoices as $invoice) {
            $audit = $service->auditInvoice($invoice);
            $status = $audit['is_mismatched'] ? 'MISMATCH' : 'OK';

            if ($repair && $audit['is_mismatched']) {
                $invoiceStatus = strtolower((string) $invoice->status);
                if (! $this->option('include-paid') && in_array($invoiceStatus, ['paid', 'partially_paid'], true)) {
                    $status = 'SKIPPED_PAID';
                } else {
                    $service->repairInvoice($invoice);
                    $audit = $service->auditInvoice($invoice->fresh(['accountPayable', 'invoiceItem']));
                    $status = $audit['is_mismatched'] ? 'REPAIR_FAILED' : 'REPAIRED';
                }
            }

            $rows[] = [
                'id' => $audit['invoice_id'],
                'number' => $audit['invoice_number'],
                'status' => $status,
                'current_total' => number_format((float) $audit['current']['total'], 2, '.', ''),
                'expected_total' => number_format((float) $audit['expected']['total'], 2, '.', ''),
                'current_cabang' => $audit['current']['cabang_id'] ?? '-',
                'expected_cabang' => $audit['expected']['cabang_id'] ?? '-',
            ];
        }

        $this->table(
            ['ID', 'Invoice', 'Status', 'Current Total', 'Expected Total', 'Current Cabang', 'Expected Cabang'],
            $rows
        );

        if (! $repair) {
            $this->info('Dry-run only. Tambahkan --repair untuk memperbaiki invoice yang mismatch.');
        }

        return self::SUCCESS;
    }
}
