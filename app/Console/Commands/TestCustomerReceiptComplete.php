<?php

namespace App\Console\Commands;

use App\Models\CustomerReceipt;
use App\Models\Customer;
use App\Models\SaleOrder;
use App\Models\Invoice;
use Illuminate\Console\Command;

class TestCustomerReceiptComplete extends Command
{
    protected $signature = 'test:customer-receipt-complete';
    protected $description = 'Test complete customer receipt create process';

    public function handle()
    {
        $this->info('=== TESTING COMPLETE CUSTOMER RECEIPT PROCESS ===');
        
        // Find test data
        $customer = Customer::first();
        $saleOrder = SaleOrder::where('customer_id', $customer->id)->first();
        
        if (!$saleOrder) {
            $this->error('No sale order found for customer');
            return;
        }
        
        $invoice = Invoice::where('from_model_type', 'App\Models\SaleOrder')
                         ->where('from_model_id', $saleOrder->id)
                         ->first();
                         
        if (!$invoice) {
            $this->error('No invoice found for sale order');
            return;
        }
        
        $this->info("Testing with Customer: {$customer->name}");
        $this->info("Invoice: {$invoice->invoice_number}");
        
        // Test the complete process as it would happen in the form
        $formData = [
            'customer_id' => $customer->id,
            'selected_invoices' => json_encode([$invoice->id]), // From hidden field
            'invoice_receipts' => json_encode([$invoice->id => 45000]), // From hidden field
            'total_payment' => 45000,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'Cash',
            'status' => 'Draft',
            'notes' => 'Test complete process',
            'ntpn' => 'TEST123'
        ];
        
        $this->info('Form data before processing:');
        $this->line("- selected_invoices: {$formData['selected_invoices']}");
        $this->line("- invoice_receipts: {$formData['invoice_receipts']}");
        $this->line("- total_payment: {$formData['total_payment']}");
        
        // Simulate mutateFormDataBeforeCreate
        if (is_string($formData['selected_invoices'])) {
            $formData['selected_invoices'] = json_decode($formData['selected_invoices'], true) ?? [];
        }
        
        if (is_string($formData['invoice_receipts'])) {
            $formData['invoice_receipts'] = json_decode($formData['invoice_receipts'], true) ?? [];
        }
        
        $this->info('After parsing:');
        $this->line('- selected_invoices: ' . json_encode($formData['selected_invoices']));
        $this->line('- invoice_receipts: ' . json_encode($formData['invoice_receipts']));
        
        // Create the record
        $receipt = CustomerReceipt::create($formData);
        $this->info("Created CustomerReceipt ID: {$receipt->id}");
        
        // Verify data was saved correctly
        $savedReceipt = CustomerReceipt::find($receipt->id);
        $this->info('Saved data verification:');
        $this->line('- selected_invoices type: ' . gettype($savedReceipt->selected_invoices));
        $this->line('- selected_invoices value: ' . json_encode($savedReceipt->selected_invoices));
        $this->line('- invoice_receipts type: ' . gettype($savedReceipt->invoice_receipts));
        $this->line('- invoice_receipts value: ' . json_encode($savedReceipt->invoice_receipts));
        
        // Simulate afterCreate
        $invoiceReceipts = $savedReceipt->invoice_receipts;
        
        if (!empty($invoiceReceipts) && is_array($invoiceReceipts)) {
            $this->info('Creating CustomerReceiptItems...');
            
            foreach ($invoiceReceipts as $invoiceId => $receiptAmount) {
                if ($receiptAmount > 0) {
                    $item = $savedReceipt->customerReceiptItem()->create([
                        'invoice_id' => $invoiceId,
                        'method' => $savedReceipt->payment_method ?? 'Cash',
                        'amount' => $receiptAmount,
                        'payment_date' => $savedReceipt->payment_date ?? now(),
                    ]);
                    $this->info("Created CustomerReceiptItem ID: {$item->id}, Amount: {$receiptAmount}");
                }
            }
        } else {
            $this->error('Failed to create items - invoice_receipts data issue');
            return;
        }
        
        // Final verification
        $savedReceipt->refresh();
        $itemsCount = $savedReceipt->customerReceiptItem()->count();
        $totalFromItems = $savedReceipt->customerReceiptItem->sum('amount');
        
        $this->info('=== FINAL VERIFICATION ===');
        $this->line("CustomerReceiptItems created: {$itemsCount}");
        $this->line("Total from items: " . number_format($totalFromItems, 0));
        $this->line("Receipt total: " . number_format($savedReceipt->total_payment, 0));
        
        $consistent = $totalFromItems == $savedReceipt->total_payment;
        $this->line("Data consistent: " . ($consistent ? 'YES ✓' : 'NO ✗'));
        
        if ($consistent) {
            $this->info('✓ TEST PASSED - Customer Receipt process working correctly!');
        } else {
            $this->error('✗ TEST FAILED - Data inconsistency detected');
        }
    }
}
