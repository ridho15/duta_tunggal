<?php

namespace App\Console\Commands;

use App\Models\CustomerReceipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixCustomerReceiptData extends Command
{
    protected $signature = 'customer-receipt:fix-data {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix inconsistent data in customer receipts';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('=== CUSTOMER RECEIPT DATA FIX ===');
        $this->info($isDryRun ? 'DRY RUN MODE - No changes will be made' : 'FIXING MODE - Changes will be saved');
        
        $receipts = CustomerReceipt::with('customerReceiptItem')->get();
        $fixedCount = 0;
        $issuesFound = 0;
        
        foreach ($receipts as $receipt) {
            $issues = $this->analyzeReceipt($receipt);
            
            if (!empty($issues)) {
                $issuesFound++;
                $this->warn("Receipt ID {$receipt->id} has issues:");
                
                foreach ($issues as $issue) {
                    $this->line("  - {$issue}");
                }
                
                if (!$isDryRun) {
                    $fixed = $this->fixReceipt($receipt);
                    if ($fixed) {
                        $fixedCount++;
                        $this->info("  ✓ Fixed Receipt ID {$receipt->id}");
                    } else {
                        $this->error("  ✗ Failed to fix Receipt ID {$receipt->id}");
                    }
                }
            }
        }
        
        $this->info("\n=== SUMMARY ===");
        $this->info("Total receipts checked: {$receipts->count()}");
        $this->info("Receipts with issues: {$issuesFound}");
        
        if ($isDryRun) {
            $this->info("Run without --dry-run to apply fixes");
        } else {
            $this->info("Receipts fixed: {$fixedCount}");
        }
    }
    
    private function analyzeReceipt(CustomerReceipt $receipt): array
    {
        $issues = [];
        
        // Parse invoice_receipts
        $invoiceReceipts = $receipt->invoice_receipts;
        if (is_string($invoiceReceipts)) {
            $invoiceReceipts = json_decode($invoiceReceipts, true) ?? [];
        }
        
        $totalFromReceipts = array_sum($invoiceReceipts ?? []);
        $totalFromItems = $receipt->customerReceiptItem->sum('amount');
        
        // Check total payment vs invoice receipts
        if (abs($receipt->total_payment - $totalFromReceipts) > 0.01) {
            $issues[] = "Total payment ({$receipt->total_payment}) != Invoice receipts total ({$totalFromReceipts})";
        }
        
        // Check invoice receipts vs receipt items
        if (abs($totalFromReceipts - $totalFromItems) > 0.01) {
            $issues[] = "Invoice receipts total ({$totalFromReceipts}) != Receipt items total ({$totalFromItems})";
        }
        
        // Check if invoice_receipts is empty but items exist
        if (empty($invoiceReceipts) && $receipt->customerReceiptItem->count() > 0) {
            $issues[] = "Empty invoice_receipts but receipt items exist";
        }
        
        // Check if invoice_id is empty but selected_invoices exists
        if (empty($receipt->invoice_id) && !empty($receipt->selected_invoices)) {
            $issues[] = "Empty invoice_id but selected_invoices exists";
        }
        
        return $issues;
    }
    
    private function fixReceipt(CustomerReceipt $receipt): bool
    {
        try {
            $changes = [];
            
            // Parse invoice_receipts
            $invoiceReceipts = $receipt->invoice_receipts;
            if (is_string($invoiceReceipts)) {
                $invoiceReceipts = json_decode($invoiceReceipts, true) ?? [];
            }
            
            $totalFromReceipts = array_sum($invoiceReceipts ?? []);
            $totalFromItems = $receipt->customerReceiptItem->sum('amount');
            
            // Fix strategy: Use receipt items as source of truth
            if ($totalFromItems > 0) {
                // Rebuild invoice_receipts from actual receipt items
                $newInvoiceReceipts = [];
                foreach ($receipt->customerReceiptItem as $item) {
                    $invoiceId = $item->invoice_id;
                    if (!isset($newInvoiceReceipts[$invoiceId])) {
                        $newInvoiceReceipts[$invoiceId] = 0;
                    }
                    $newInvoiceReceipts[$invoiceId] += $item->amount;
                }
                
                $receipt->invoice_receipts = $newInvoiceReceipts;
                $receipt->total_payment = $totalFromItems;
                $changes[] = "Updated invoice_receipts and total_payment from receipt items";
                
                // Update selected_invoices if needed
                $invoiceIds = array_keys($newInvoiceReceipts);
                if (empty($receipt->selected_invoices) || $receipt->selected_invoices != $invoiceIds) {
                    $receipt->selected_invoices = $invoiceIds;
                    $changes[] = "Updated selected_invoices";
                }
                
                // Fix invoice_id if empty
                if (empty($receipt->invoice_id) && !empty($invoiceIds)) {
                    $receipt->invoice_id = $invoiceIds[0];
                    $changes[] = "Set invoice_id to first selected invoice";
                }
                
            } else {
                // No items exist, fix based on total_payment and selected_invoices
                if (!empty($receipt->selected_invoices) && $receipt->total_payment > 0) {
                    $selectedInvoices = $receipt->selected_invoices;
                    if (count($selectedInvoices) === 1) {
                        $receipt->invoice_receipts = [$selectedInvoices[0] => $receipt->total_payment];
                        $changes[] = "Created invoice_receipts from selected_invoices and total_payment";
                    }
                }
            }
            
            if (!empty($changes)) {
                $receipt->save();
                
                Log::info("Fixed Customer Receipt {$receipt->id}", [
                    'changes' => $changes,
                    'new_total_payment' => $receipt->total_payment,
                    'new_invoice_receipts' => $receipt->invoice_receipts,
                ]);
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error("Failed to fix Customer Receipt {$receipt->id}: {$e->getMessage()}");
            return false;
        }
    }
}
