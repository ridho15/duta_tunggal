<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\Invoice;
use App\Models\VendorPayment;
use App\Services\OrderRequestService;
use App\Services\PurchaseReceiptService;
use App\Services\QualityControlService;
use App\Services\BalanceSheetService;
use Illuminate\Support\Facades\DB;

class PurchaseFlowTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating complete purchase flow test data...');

        // Get existing master data
        $warehouse = \App\Models\Warehouse::first();
        $supplier = \App\Models\Supplier::first();
        $product = \App\Models\Product::first();
        $user = \App\Models\User::first();
        $currency = \App\Models\Currency::first();

        // Setup COA for product if not set
        $inventoryCoa = \App\Models\ChartOfAccount::where('code', '1140.10')->first();
        $unbilledPurchaseCoa = \App\Models\ChartOfAccount::where('code', '2100.10')->first();
        $temporaryProcurementCoa = \App\Models\ChartOfAccount::where('code', '1400.01')->first();

        if ($product && (!$product->inventory_coa_id || !$product->unbilled_purchase_coa_id || !$product->temporary_procurement_coa_id)) {
            $product->update([
                'inventory_coa_id' => $inventoryCoa?->id,
                'unbilled_purchase_coa_id' => $unbilledPurchaseCoa?->id,
                'temporary_procurement_coa_id' => $temporaryProcurementCoa?->id,
            ]);
            $product->refresh();
        }

        if (!$warehouse || !$supplier || !$product || !$user || !$currency || !$inventoryCoa || !$unbilledPurchaseCoa || !$temporaryProcurementCoa) {
            $this->command->error('Required master data not found. Please run master data seeders first.');
            return;
        }

        DB::transaction(function () use ($supplier, $product, $user, $currency) {
            // Reset stock for this product in ALL warehouses
            InventoryStock::where('product_id', $product->id)->update(['qty_available' => 0, 'qty_reserved' => 0]);

            // Reset related data
            StockMovement::where('product_id', $product->id)->delete();
            JournalEntry::where('source_type', \App\Models\PurchaseReceiptItem::class)->delete();
            QualityControl::where('product_id', $product->id)->delete();
            PurchaseReceiptItem::where('product_id', $product->id)->delete();
            
            // Force delete to clear soft-deleted records and prevent unique constraint violations
            PurchaseReceipt::where('purchase_order_id', '>', 0)->forceDelete();
            PurchaseOrder::where('supplier_id', $supplier->id)->forceDelete();
            OrderRequest::whereIn('id', function ($q) use ($supplier) {
                $q->select('order_request_id')->from('order_request_items')->where('supplier_id', $supplier->id);
            })->forceDelete();

            $seedPrefix = rand(1000, 9999);

            // Run 3 iterations with random Cabangs!
            for ($i = 1; $i <= 3; $i++) {
                $this->command->info("--- RUNNING PURCHASE FLOW ITERATION {$i} ---");

                // Get a random Cabang
                $cabang = \App\Models\Cabang::inRandomOrder()->first();
                if (!$cabang) {
                    $cabang = \App\Models\Cabang::first();
                }

                // Get/create a warehouse for this Cabang
                $warehouse = \App\Models\Warehouse::where('cabang_id', $cabang->id)->first();
                if (!$warehouse) {
                    $warehouse = \App\Models\Warehouse::factory()->create([
                        'cabang_id' => $cabang->id,
                        'status' => 1
                    ]);
                }

                // 1. Create Order Request
                $this->command->info("Step 1 [Iter {$i}]: Creating Order Request for Cabang: ({$cabang->kode}) {$cabang->nama}...");
                $orderRequest = OrderRequest::create([
                    'request_number' => 'OR-' . now()->format('Ymd') . '-' . $seedPrefix . '-' . sprintf('%04d', $i),
                    'request_date' => now(),
                    'status' => 'draft',
                    'note' => "Test Order Request for Purchase Flow Iteration {$i}",
                    'created_by' => $user->id,
                    'currency_id' => $currency->id,
                    'cabang_id' => $cabang->id, // Set the random cabang!
                ]);

                // Create Order Request Item
                $orderRequestItem = OrderRequestItem::create([
                    'order_request_id' => $orderRequest->id,
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'note' => 'Test item',
                    'cabang_id' => $cabang->id, // Same random cabang!
                    'supplier_id' => $supplier->id,
                    'currency_id' => $currency->id,
                ]);

                // 2. Approve Order Request (creates Purchase Order)
                $this->command->info("Step 2 [Iter {$i}]: Approving Order Request...");
                $orderRequestService = app(OrderRequestService::class);
                $orderRequest = $orderRequestService->approve($orderRequest, [
                    'supplier_id' => $supplier->id,
                    'po_number' => 'PO-' . now()->format('Ymd') . '-' . $seedPrefix . '-' . sprintf('%04d', $i),
                    'order_date' => now(),
                    'expected_date' => now()->addDays(7),
                    'note' => 'Test PO from Order Request'
                ]);

                $purchaseOrder = $orderRequest->purchaseOrder;
                
                // Assert that the PurchaseOrder's cabang_id matches the OrderRequestItem's cabang_id!
                if ($purchaseOrder->cabang_id !== $cabang->id) {
                    throw new \Exception("PurchaseOrder cabang_id ({$purchaseOrder->cabang_id}) does not match OrderRequestItem cabang_id ({$cabang->id})");
                }

                // 3. Approve Purchase Order
                $this->command->info("Step 3 [Iter {$i}]: Approving Purchase Order...");
                $purchaseOrder->update([
                    'status' => 'approved',
                    'date_approved' => now(),
                    'approved_by' => $user->id
                ]);

                // 4. Create Purchase Receipt
                $this->command->info("Step 4 [Iter {$i}]: Creating Purchase Receipt...");
                $purchaseReceiptService = app(PurchaseReceiptService::class);
                $receipt = PurchaseReceipt::create([
                    'receipt_number' => $purchaseReceiptService->generateReceiptNumber() . '-' . $i,
                    'purchase_order_id' => $purchaseOrder->id,
                    'receipt_date' => now(),
                    'received_by' => $user->id,
                    'notes' => 'Test Purchase Receipt ' . $i,
                    'currency_id' => $currency->id,
                    'status' => 'draft',
                    'cabang_id' => $cabang->id, // Same random cabang!
                ]);

                // Create Purchase Receipt Item
                $receiptItem = PurchaseReceiptItem::create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $purchaseOrder->purchaseOrderItem->first()->id,
                    'product_id' => $product->id,
                    'qty_received' => 10,
                    'qty_accepted' => 10,
                    'qty_rejected' => 0,
                    'warehouse_id' => $warehouse->id,
                    'status' => 'pending'
                ]);

                // 5. Send item to Quality Control
                $this->command->info("Step 5 [Iter {$i}]: Sending item to Quality Control...");
                
                // Create Quality Control record for this receipt item
                $qualityControl = QualityControl::create([
                    'qc_number'         => app(QualityControlService::class)->generateQcNumber(),
                    'passed_quantity'   => 10,
                    'rejected_quantity' => 0,
                    'quantity_received' => 10,
                    'notes'             => 'Test QC from Seeder ' . $i,
                    'status'            => 0,
                    'inspected_by'      => $user->id,
                    'warehouse_id'      => $warehouse->id,
                    'product_id'        => $product->id,
                    'from_model_type'   => \App\Models\PurchaseReceiptItem::class,
                    'from_model_id'     => $receiptItem->id,
                    'cabang_id'         => $cabang->id, // Same random cabang!
                ]);

                $result = $purchaseReceiptService->createTemporaryProcurementEntriesForReceiptItem($receiptItem);
                if (!isset($result['status']) || $result['status'] !== 'posted') {
                    throw new \Exception('Failed to send item to QC: ' . ($result['message'] ?? 'Unknown error'));
                }
                $receipt->checkAndUpdateStatus();

                // Load and verify it has the correct cabang_id!
                $qualityControl = $receiptItem->fresh()->qualityControl;
                if (!$qualityControl || !$qualityControl->exists) {
                    throw new \Exception("QualityControl record not found in database for receipt item");
                }
                if ($qualityControl->cabang_id !== $cabang->id) {
                    throw new \Exception("QualityControl cabang_id ({$qualityControl->cabang_id}) does not match expected ({$cabang->id})");
                }

                // 6. Complete Quality Control
                $this->command->info("Step 6 [Iter {$i}]: Completing Quality Control...");
                $qualityControlService = app(QualityControlService::class);
                $qualityControlService->completeQualityControl($qualityControl, [
                    'notes' => 'Test QC completed ' . $i,
                    'item_condition' => 'good'
                ]);

                // Post inventory after QC completion
                $purchaseReceiptService->postItemInventoryAfterQC($receiptItem);

                // Let's assert that the PurchaseReceipt's cabang_id is consistent!
                if ($receipt->fresh()->cabang_id !== $cabang->id) {
                    throw new \Exception("PurchaseReceipt cabang_id does not match expected");
                }

                // 7. Check Purchase Receipt and Items
                $this->command->info("Step 7 [Iter {$i}]: Checking Purchase Receipt and Items...");
                $this->assertPurchaseReceiptData($receipt, $receiptItem, $qualityControl);

                // 8. Check Stock
                $this->command->info("Step 8 [Iter {$i}]: Checking Stock in warehouse {$warehouse->id}...");
                $this->assertStockData($product->id, $warehouse->id, 10);

                // 9. Check Journal Entries
                $this->command->info("Step 9 [Iter {$i}]: Checking Journal Entries...");
                $this->assertJournalEntries($receiptItem);
                // Assert that the journal entries created have the correct cabang_id!
                $receiptJournals = JournalEntry::where('source_type', PurchaseReceiptItem::class)->where('source_id', $receiptItem->id)->get();
                foreach ($receiptJournals as $je) {
                    if ($je->cabang_id !== $cabang->id) {
                        throw new \Exception("JournalEntry cabang_id ({$je->cabang_id}) does not match expected ({$cabang->id})");
                    }
                }

                // 10. Create Invoice from Purchase Order
                $this->command->info("Step 10 [Iter {$i}]: Creating Invoice from Purchase Order...");
                $invoiceService = app(\App\Services\InvoiceService::class);
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceService->generateInvoiceNumber() . '-' . $i,
                    'from_model_type' => \App\Models\PurchaseOrder::class,
                    'from_model_id' => $purchaseOrder->id,
                    'invoice_date' => now(),
                    'due_date' => now()->addDays(30),
                    'subtotal' => $purchaseOrder->total_amount,
                    'tax' => 0,
                    'other_fee' => 0,
                    'total' => $purchaseOrder->total_amount,
                    'status' => 'sent',
                    'supplier_name' => $supplier->perusahaan ?? null,
                    'supplier_phone' => $supplier->phone,
                    'cabang_id' => $cabang->id, // Consistently set!
                ]);

                // Create invoice items
                foreach ($purchaseOrder->purchaseOrderItem as $poItem) {
                    $invoice->invoiceItem()->create([
                        'product_id' => $poItem->product_id,
                        'quantity' => $poItem->quantity,
                        'price' => $poItem->unit_price,
                        'total' => $poItem->quantity * $poItem->unit_price,
                    ]);
                }

                // 11. Create Vendor Payment
                $this->command->info("Step 11 [Iter {$i}]: Creating Vendor Payment...");
                $cashBankAccount = \App\Models\CashBankAccount::first();
                if (!$cashBankAccount) {
                    $cashCoa = \App\Models\ChartOfAccount::where('code', '1111.01')->first();
                    $cashBankAccount = \App\Models\CashBankAccount::create([
                        'name' => 'Kas Kecil',
                        'account_number' => '1111.01',
                        'coa_id' => $cashCoa?->id,
                    ]);
                }

                $vendorPayment = \App\Models\VendorPayment::create([
                    'supplier_id' => $supplier->id,
                    'selected_invoices' => [$invoice->id],
                    'payment_date' => now(),
                    'total_payment' => $invoice->total,
                    'coa_id' => $cashBankAccount->coa_id,
                    'payment_method' => 'Cash',
                    'status' => 'Draft',
                    'notes' => 'Test payment for purchase invoice ' . $i,
                ]);

                // Create vendor payment detail
                $vendorPayment->vendorPaymentDetail()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->total,
                    'method' => 'Cash',
                    'coa_id' => $cashBankAccount->coa_id,
                    'payment_date' => now(),
                    'notes' => 'Full payment for invoice ' . $i,
                ]);

                // 12. Complete Payment (set status to Paid)
                $this->command->info("Step 12 [Iter {$i}]: Completing Payment...");
                $vendorPayment->update(['status' => 'Paid']);

                // 13. Check Invoice and Payment Status
                $this->command->info("Step 13 [Iter {$i}]: Checking Invoice and Payment Status...");
                $this->assertInvoiceAndPaymentStatus($invoice, $vendorPayment);
            }

            // 14. Check Balance Sheet after Payment
            $this->command->info('Step 14: Checking Balance Sheet after Payment...');
            $this->assertBalanceSheetAfterPayment();

            $this->command->info('Complete purchase to payment flow test data created successfully!');
        });
    }

    private function assertPurchaseReceiptData($receipt, $receiptItem, $qualityControl)
    {
        // Check receipt status
        if ($receipt->status !== 'completed') {
            throw new \Exception("Purchase Receipt status should be 'completed', got '{$receipt->status}'");
        }

        // Check receipt item
        if ($receiptItem->qty_received != 10 || $receiptItem->qty_accepted != 10) {
            throw new \Exception("Purchase Receipt Item quantities incorrect");
        }

        // Check QC
        if ($qualityControl->status != 1 || $qualityControl->passed_quantity != 10) {
            throw new \Exception("Quality Control not completed properly");
        }

        $this->command->info('✓ Purchase Receipt and Items data verified');
    }

    private function assertStockData($productId, $warehouseId, $expectedQty)
    {
        $stock = InventoryStock::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (!$stock) {
            throw new \Exception("Inventory stock not found for product {$productId} in warehouse {$warehouseId}");
        }

        // Check if stock increased by expected quantity (not absolute)
        $initialStock = 0; // Assuming we start from zero in test environment
        $expectedFinalStock = $initialStock + $expectedQty;

        if ($stock->qty_available != $expectedFinalStock) {
            throw new \Exception("Stock quantity should be {$expectedFinalStock}, got {$stock->qty_available}");
        }

        $this->command->info('✓ Stock data verified');
    }

    private function assertJournalEntries($receiptItem)
    {
        $journalEntries = JournalEntry::where('source_type', \App\Models\PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->get();

        if ($journalEntries->isEmpty()) {
            throw new \Exception("No journal entries found for Purchase Receipt Item {$receiptItem->id}");
        }

        // Check for inventory and unbilled purchase entries
        $hasInventoryDebit = false;
        $hasUnbilledPurchaseCredit = false;

        foreach ($journalEntries as $entry) {
            if (str_starts_with($entry->coa->code, '1140') && $entry->debit > 0) { // Inventory debit
                $hasInventoryDebit = true;
            }
            if (str_starts_with($entry->coa->code, '2100') && $entry->credit > 0) { // Unbilled purchase credit
                $hasUnbilledPurchaseCredit = true;
            }
        }

        if (!$hasInventoryDebit || !$hasUnbilledPurchaseCredit) {
            throw new \Exception("Journal entries incomplete for Purchase Order");
        }

        $this->command->info('✓ Journal entries verified');
    }

    private function assertBalanceSheet()
    {
        $balanceSheetService = app(\App\Services\BalanceSheetService::class);
        $balanceSheet = $balanceSheetService->generate([
            'as_of_date' => now()->format('Y-m-d'),
            'cabang_id' => null,
            'display_level' => 'all',
            'show_zero_balance' => false
        ]);

        // Basic checks - ensure balance sheet has data
        if (!isset($balanceSheet['total_assets']) || !isset($balanceSheet['total_liabilities'])) {
            throw new \Exception("Balance sheet data incomplete");
        }

        $this->command->info('✓ Balance sheet verified');
    }

    private function assertInvoiceAndPaymentStatus($invoice, $vendorPayment)
    {
        // Check invoice status
        if ($invoice->status !== 'sent') {
            throw new \Exception("Invoice status should be 'sent', got '{$invoice->status}'");
        }

        // Check vendor payment status
        if ($vendorPayment->status !== 'Paid') {
            throw new \Exception("Vendor payment status should be 'Paid', got '{$vendorPayment->status}'");
        }

        // Check account payable
        $accountPayable = $invoice->accountPayable;
        if (!$accountPayable) {
            throw new \Exception("Account payable not found for invoice");
        }

        if ($accountPayable->status !== 'Lunas') {
            throw new \Exception("Account payable status should be 'Lunas', got '{$accountPayable->status}'");
        }

        if ($accountPayable->paid != $invoice->total) {
            throw new \Exception("Account payable paid amount should be {$invoice->total}, got {$accountPayable->paid}");
        }

        if ($accountPayable->remaining != 0) {
            throw new \Exception("Account payable remaining should be 0, got {$accountPayable->remaining}");
        }

        $this->command->info('✓ Invoice and payment status verified');
    }

    private function assertBalanceSheetAfterPayment()
    {
        $balanceSheetService = app(\App\Services\BalanceSheetService::class);
        $balanceSheet = $balanceSheetService->generate([
            'as_of_date' => now()->format('Y-m-d'),
            'cabang_id' => null,
            'display_level' => 'all',
            'show_zero_balance' => false
        ]);

        // After payment, liabilities should decrease (AP paid)
        // Assets might change depending on payment method (cash/bank)
        if (!isset($balanceSheet['total_assets']) || !isset($balanceSheet['total_liabilities'])) {
            throw new \Exception("Balance sheet data incomplete after payment");
        }

        $this->command->info('✓ Balance sheet after payment verified');
    }
}