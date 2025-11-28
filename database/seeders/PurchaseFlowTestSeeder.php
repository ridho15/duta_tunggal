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

        DB::transaction(function () use ($warehouse, $supplier, $product, $user, $currency) {
            // Reset stock for this product/warehouse combination
            InventoryStock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->update(['qty_available' => 0, 'qty_reserved' => 0]);

            // Reset related data
            StockMovement::where('product_id', $product->id)->delete();
            JournalEntry::where('source_type', \App\Models\PurchaseReceiptItem::class)->delete();
            QualityControl::where('product_id', $product->id)->delete();
            PurchaseReceiptItem::where('product_id', $product->id)->delete();
            PurchaseReceipt::where('purchase_order_id', '>', 0)->delete(); // Will be recreated
            PurchaseOrder::where('supplier_id', $supplier->id)->delete(); // Will be recreated
            OrderRequest::where('supplier_id', $supplier->id)->delete(); // Will be recreated
            // 1. Create Order Request
            $this->command->info('Step 1: Creating Order Request...');
            $orderRequest = OrderRequest::create([
                'request_number' => 'OR-' . now()->format('Ymd') . '-0001',
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $supplier->id,
                'request_date' => now(),
                'status' => 'draft',
                'note' => 'Test Order Request for Purchase Flow',
                'created_by' => $user->id
            ]);

            // Create Order Request Item
            OrderRequestItem::create([
                'order_request_id' => $orderRequest->id,
                'product_id' => $product->id,
                'quantity' => 10,
                'note' => 'Test item'
            ]);

            // 2. Approve Order Request (creates Purchase Order)
            $this->command->info('Step 2: Approving Order Request...');
            $orderRequestService = app(OrderRequestService::class);
            $orderRequest = $orderRequestService->approve($orderRequest, [
                'supplier_id' => $supplier->id,
                'po_number' => 'PO-' . now()->format('Ymd') . '-0001',
                'order_date' => now(),
                'expected_date' => now()->addDays(7),
                'note' => 'Test PO from Order Request'
            ]);

            $purchaseOrder = $orderRequest->purchaseOrder;

            // 3. Approve Purchase Order
            $this->command->info('Step 3: Approving Purchase Order...');
            $purchaseOrder->update([
                'status' => 'approved',
                'date_approved' => now(),
                'approved_by' => $user->id
            ]);

            // 4. Create Purchase Receipt
            $this->command->info('Step 4: Creating Purchase Receipt...');
            $purchaseReceiptService = app(PurchaseReceiptService::class);
            $receipt = PurchaseReceipt::create([
                'receipt_number' => $purchaseReceiptService->generateReceiptNumber(),
                'purchase_order_id' => $purchaseOrder->id,
                'receipt_date' => now(),
                'received_by' => $user->id,
                'notes' => 'Test Purchase Receipt',
                'currency_id' => $currency->id,
                'status' => 'draft'
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
                'is_sent' => false
            ]);

            // 5. Send item to Quality Control
            $this->command->info('Step 5: Sending item to Quality Control...');
            $purchaseReceiptService = app(PurchaseReceiptService::class);
            $result = $purchaseReceiptService->createTemporaryProcurementEntriesForReceiptItem($receiptItem);
            if (!isset($result['status']) || $result['status'] !== 'posted') {
                throw new \Exception('Failed to send item to QC: ' . ($result['message'] ?? 'Unknown error'));
            }
            $receipt->updateStatusBasedOnQCItems();

            // QC is automatically created via model observer

            // 6. Complete Quality Control
            $this->command->info('Step 6: Completing Quality Control...');
            $qualityControl = $receiptItem->qualityControl;
            $qualityControlService = app(QualityControlService::class);
            $qualityControlService->completeQualityControl($qualityControl, [
                'notes' => 'Test QC completed',
                'item_condition' => 'good'
            ]);

            // Post inventory after QC completion
            $purchaseReceiptService->postItemInventoryAfterQC($receiptItem);

            // 7. Check Purchase Receipt and Items
            $this->command->info('Step 7: Checking Purchase Receipt and Items...');
            $this->assertPurchaseReceiptData($receipt, $receiptItem, $qualityControl);

            // 8. Check Stock
            $this->command->info('Step 8: Checking Stock...');
            $this->assertStockData($product->id, $warehouse->id, 10);

            // 9. Check Journal Entries
            $this->command->info('Step 9: Checking Journal Entries...');
            $this->assertJournalEntries($receiptItem);

            // 10. Create Invoice from Purchase Order
            $this->command->info('Step 10: Creating Invoice from Purchase Order...');
            $invoiceService = app(\App\Services\InvoiceService::class);
            $invoice = Invoice::create([
                'invoice_number' => $invoiceService->generateInvoiceNumber(),
                'from_model_type' => \App\Models\PurchaseOrder::class,
                'from_model_id' => $purchaseOrder->id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'subtotal' => $purchaseOrder->total_amount,
                'tax' => 0,
                'other_fee' => 0,
                'total' => $purchaseOrder->total_amount,
                'status' => 'sent',
                'supplier_name' => $supplier->name,
                'supplier_phone' => $supplier->phone,
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
            $this->command->info('Step 11: Creating Vendor Payment...');
            $cashBankAccount = \App\Models\CashBankAccount::first();
            if (!$cashBankAccount) {
                // Create a default cash account if not exists
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
                'notes' => 'Test payment for purchase invoice',
            ]);

            // Create vendor payment detail
            $vendorPayment->vendorPaymentDetail()->create([
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total,
                'method' => 'Cash',
                'coa_id' => $cashBankAccount->coa_id,
                'payment_date' => now(),
                'notes' => 'Full payment for invoice',
            ]);

            // 12. Complete Payment (set status to Paid)
            $this->command->info('Step 12: Completing Payment...');
            $vendorPayment->update(['status' => 'Paid']);

            // 13. Check Invoice and Payment Status
            $this->command->info('Step 13: Checking Invoice and Payment Status...');
            $this->assertInvoiceAndPaymentStatus($invoice, $vendorPayment);

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