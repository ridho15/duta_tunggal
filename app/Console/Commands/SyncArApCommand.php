<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\VendorPaymentDetail;
use Illuminate\Console\Command;

class SyncArApCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ar-ap:sync
                            {--force : Force update existing AR/AP records}
                            {--invoice-id= : Restrict sync to a specific invoice ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Account Receivable and Account Payable from unpaid invoices automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting AR & AP Synchronization...');
        $this->newLine();

        $force = $this->option('force');
        $invoiceId = $this->option('invoice-id');
        
        // Sync Account Receivables from Customer Invoices
        $this->syncAccountReceivables($force, $invoiceId);
        
        // Sync Account Payables from Supplier Invoices
        $this->syncAccountPayables($force, $invoiceId);
        
        $this->newLine();
        $this->info('✅ AR & AP Synchronization completed successfully!');
    }

    private function syncAccountReceivables($force = false, $invoiceId = null)
    {
        $this->info('📊 Syncing Account Receivables from Customer Invoices...');
        
        // Get all customer invoices (from Sale Orders)
        $customerInvoices = Invoice::where('from_model_type', 'App\Models\SaleOrder')
            ->when($invoiceId, fn ($query) => $query->where('id', $invoiceId))
            ->with(['fromModel.customer', 'accountReceivable'])
            ->get();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($customerInvoices as $invoice) {
            if (!$invoice->fromModel || !$invoice->fromModel->customer_id) {
                $this->warn("⚠️  Skipping invoice {$invoice->invoice_number} - No customer found");
                $skipped++;
                continue;
            }

            $existingAR = AccountReceivable::where('invoice_id', $invoice->id)->first();

            if ($existingAR && !$force) {
                $skipped++;
                continue;
            }

            // Calculate remaining amount
            $totalPaid = (float) CustomerReceiptItem::query()
                ->where('invoice_id', $invoice->id)
                ->sum('amount');
            
            $remaining = max(0, $invoice->total - $totalPaid);
            $status = $remaining > 0 ? PaymentStatus::UNPAID->value : PaymentStatus::PAID->value;

            $arData = [
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->fromModel->customer_id,
                'total' => $invoice->total,
                'paid' => $totalPaid,
                'remaining' => $remaining,
                'status' => $status
            ];

            if ($existingAR) {
                $existingAR->update($arData);
                $updated++;
                $this->line("🔄 Updated AR for invoice: {$invoice->invoice_number}");
            } else {
                AccountReceivable::create($arData);
                $created++;
                $this->line("✅ Created AR for invoice: {$invoice->invoice_number}");
            }
        }

        $this->info("📈 Account Receivables: {$created} created, {$updated} updated, {$skipped} skipped");
    }

    private function syncAccountPayables($force = false, $invoiceId = null)
    {
        $this->info('📊 Syncing Account Payables from Supplier Invoices...');
        
        // Get all supplier invoices (from Purchase Orders)
        $supplierInvoices = Invoice::where('from_model_type', 'App\Models\PurchaseOrder')
            ->when($invoiceId, fn ($query) => $query->where('id', $invoiceId))
            ->with(['fromModel.supplier', 'accountPayable'])
            ->get();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($supplierInvoices as $invoice) {
            if (!$invoice->fromModel || !$invoice->fromModel->supplier_id) {
                $this->warn("⚠️  Skipping invoice {$invoice->invoice_number} - No supplier found");
                $skipped++;
                continue;
            }

            $existingAP = AccountPayable::where('invoice_id', $invoice->id)->first();

            if ($existingAP && !$force) {
                $skipped++;
                continue;
            }

            // Calculate remaining amount
            $totalPaid = (float) VendorPaymentDetail::query()
                ->where('invoice_id', $invoice->id)
                ->sum('amount');

            $totalAdjustments = (float) VendorPaymentDetail::query()
                ->where('invoice_id', $invoice->id)
                ->sum('adjustment_amount');

            $remaining = max(0, $invoice->total - ($totalPaid + $totalAdjustments));
            $status = $remaining > 0 ? PaymentStatus::UNPAID->value : PaymentStatus::PAID->value;

            $apData = [
                'invoice_id' => $invoice->id,
                'supplier_id' => $invoice->fromModel->supplier_id,
                'total' => $invoice->total,
                'paid' => $totalPaid,
                'remaining' => $remaining,
                'status' => $status
            ];

            if ($existingAP) {
                $existingAP->update($apData);
                $updated++;
                $this->line("🔄 Updated AP for invoice: {$invoice->invoice_number}");
            } else {
                AccountPayable::create($apData);
                $created++;
                $this->line("✅ Created AP for invoice: {$invoice->invoice_number}");
            }
        }

        $this->info("📈 Account Payables: {$created} created, {$updated} updated, {$skipped} skipped");
    }
}
