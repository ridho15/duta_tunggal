<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductService;
use App\Services\PurchaseReceiptService;
use App\Services\QualityControlService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementFlowFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected ChartOfAccount $inventoryCoa;
    protected ChartOfAccount $unbilledPurchaseCoa;
    protected ChartOfAccount $temporaryProcurementCoa;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountSeeder::class);

        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create([
            'cost_price' => 10000,
            'is_active' => true,
        ]);
        $this->currency = Currency::factory()->create(['code' => 'IDR']);

        // Set up product COA relationships for testing
        $inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
        $unbilledPurchaseCoa = ChartOfAccount::where('code', '2190.10')->first(); // Use liability account for unbilled purchases
        $temporaryProcurementCoa = ChartOfAccount::where('code', '1400.01')->first();

        $this->inventoryCoa = $inventoryCoa;
        $this->unbilledPurchaseCoa = $unbilledPurchaseCoa;
        $this->temporaryProcurementCoa = $temporaryProcurementCoa;

        $this->product->update([
            'inventory_coa_id' => $inventoryCoa?->id,
            'unbilled_purchase_coa_id' => $unbilledPurchaseCoa?->id,
            'temporary_procurement_coa_id' => $temporaryProcurementCoa?->id,
        ]);

        $this->product->refresh();

        $this->actingAs($this->user);
    }

    /** @test */
    public function complete_procurement_flow_via_filament_forms()
    {
        // ==========================================
        // STEP 1: CREATE ORDER REQUEST (Filament Form Simulation)
        // ==========================================

        $orderRequest = OrderRequest::factory()->create([
            'request_number' => 'OR-20251107-0001',
            'request_date' => now(),
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $orderRequestItem = OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'note' => 'Test procurement item',
        ]);

        $this->assertDatabaseHas('order_requests', [
            'id' => $orderRequest->id,
            'status' => 'approved',
            'request_number' => 'OR-20251107-0001'
        ]);

        $this->assertDatabaseHas('order_request_items', [
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        // ==========================================
        // STEP 2: CREATE PURCHASE ORDER (Filament Form Simulation)
        // ==========================================

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20251107-0001',
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);

                $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Eklusif',
            'refer_item_model_id' => $orderRequestItem->id,
            'refer_item_model_type' => OrderRequestItem::class,
            'currency_id' => $this->currency->id,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'approved',
            'po_number' => 'PO-20251107-0001',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
        ]);

        // ==========================================
        // STEP 3: CREATE PURCHASE RECEIPT (Filament Form Simulation)
        // ==========================================

        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-20251107-0001',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
            'other_cost' => 0,
        ]);

        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'is_sent' => false,
        ]);

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $purchaseReceipt->id,
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_number' => 'RN-20251107-0001',
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $purchaseReceipt->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'is_sent' => false,
        ]);

        // ==========================================
        // STEP 4: SEND TO QUALITY CONTROL (Filament Action Simulation)
        // ==========================================

        // Debug: Check product COA setup
        echo "Product COA IDs:\n";
        echo "inventory_coa_id: " . $this->product->inventory_coa_id . "\n";
        echo "unbilled_purchase_coa_id: " . $this->product->unbilled_purchase_coa_id . "\n";
        echo "temporary_procurement_coa_id: " . $this->product->temporary_procurement_coa_id . "\n";

        $purchaseReceiptService = app(PurchaseReceiptService::class);
        $qcResult = $purchaseReceiptService->createTemporaryProcurementEntriesForReceiptItem($receiptItem);

        echo "QC Send Result: " . json_encode($qcResult) . "\n";

        $this->assertEquals('posted', $qcResult['status']);
        $this->assertCount(2, $qcResult['entries']);

        // Verify receipt item is marked as sent
        $receiptItem->refresh();
        $this->assertEquals(1, $receiptItem->is_sent);

        // ==========================================
        // STEP 5: CREATE QUALITY CONTROL (Filament Form Simulation)
        // ==========================================

        $qualityControl = QualityControl::factory()->create([
            'qc_number' => 'QC-20251107-0001',
            'from_model_type' => PurchaseReceiptItem::class,
            'from_model_id' => $receiptItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'passed_quantity' => 10,
            'rejected_quantity' => 0,
            'status' => 0, // Not completed yet
            'inspected_by' => $this->user->id,
            'notes' => 'All items passed QC',
        ]);

        $this->assertDatabaseHas('quality_controls', [
            'id' => $qualityControl->id,
            'qc_number' => 'QC-20251107-0001',
            'from_model_type' => PurchaseReceiptItem::class,
            'from_model_id' => $receiptItem->id,
            'passed_quantity' => 10,
            'rejected_quantity' => 0,
            'status' => 0,
        ]);

        // ==========================================
        // STEP 6: COMPLETE QUALITY CONTROL (Filament Action Simulation)
        // ==========================================

        $qualityControlService = app(QualityControlService::class);
        $completeResult = $qualityControlService->completeQualityControl($qualityControl, []);

        // Verify QC is completed
        $qualityControl->refresh();
        $this->assertEquals(1, $qualityControl->status);
        $this->assertNotNull($qualityControl->date_send_stock);

        // ==========================================
        // STEP 7: POST INVENTORY AFTER QC COMPLETION
        // ==========================================

        $inventoryResult = $purchaseReceiptService->postItemInventoryAfterQC($receiptItem);
        $this->assertEquals('posted', $inventoryResult['status']);
        $this->assertCount(2, $inventoryResult['entries']);

        // ==========================================
        // STEP 8: VERIFY INVENTORY STOCK UPDATED
        // ==========================================

        $inventoryStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($inventoryStock);
        $this->assertEquals(10, $inventoryStock->qty_available);
        $this->assertEquals(0, $inventoryStock->qty_reserved);

        // Verify stock movement was created
        $stockMovement = StockMovement::where('product_id', $this->product->id)
            ->where('type', 'purchase_in')
            ->where('quantity', 10)
            ->first();

        $this->assertNotNull($stockMovement);
        $this->assertEquals($this->warehouse->id, $stockMovement->warehouse_id);
        $this->assertEquals(100000, $stockMovement->value); // 10 * 10000

        // ==========================================
        // VERIFICATION: JOURNAL ENTRIES
        // ==========================================

        $journalEntries = JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->get();

        $this->assertCount(4, $journalEntries); // 2 from QC send + 2 from inventory post

        // Check temporary procurement entries (from QC send)
        $tempProcurementDebit = $journalEntries->where('debit', 100000)->where('credit', 0)->first();
        $this->assertNotNull($tempProcurementDebit);
        $this->assertEquals($this->product->temporary_procurement_coa_id, $tempProcurementDebit->coa_id);
        $this->assertTrue(strpos($tempProcurementDebit->description, 'Temporary Procurement') !== false);

        $unbilledPurchaseCredit = $journalEntries->where('debit', 0)->where('credit', 100000)->where('journal_type', 'procurement')->first();
        $this->assertNotNull($unbilledPurchaseCredit);
        $this->assertEquals($this->product->unbilled_purchase_coa_id, $unbilledPurchaseCredit->coa_id);

        // Check inventory entries (from QC completion)
        $inventoryDebit = $journalEntries->where('debit', 100000)->where('journal_type', 'inventory')->first();
        $this->assertNotNull($inventoryDebit);
        $this->assertEquals($this->product->inventory_coa_id, $inventoryDebit->coa_id);
        $this->assertTrue(strpos($inventoryDebit->description, 'Inventory Stock') !== false);

        $tempProcurementCloseCredit = $journalEntries->where('debit', 0)->where('credit', 100000)->where('journal_type', 'inventory')->first();
        $this->assertNotNull($tempProcurementCloseCredit);
        $this->assertEquals($this->product->temporary_procurement_coa_id, $tempProcurementCloseCredit->coa_id);
        $this->assertTrue(strpos($tempProcurementCloseCredit->description, 'Close Temporary Procurement') !== false);

        // ==========================================
        // VERIFICATION: GENERAL LEDGER
        // ==========================================

        // Check temporary procurement COA balance (should be zero after closing)
        $tempProcurementCoa = ChartOfAccount::find($this->product->temporary_procurement_coa_id);
        $tempProcurementCoa->load('journalEntries');
        $this->assertEquals(0, $tempProcurementCoa->calculateEndingBalance());

        // Check unbilled purchase COA balance
        $unbilledPurchaseCoa = ChartOfAccount::find($this->product->unbilled_purchase_coa_id);
        $unbilledPurchaseCoa->load('journalEntries');
        $this->assertEquals(100000, $unbilledPurchaseCoa->calculateEndingBalance()); // Still open liability

        // Check inventory COA balance
        $inventoryCoa = ChartOfAccount::find($this->product->inventory_coa_id);
        $inventoryCoa->load('journalEntries');
        $this->assertEquals(100000, $inventoryCoa->calculateEndingBalance()); // Inventory asset

        // ==========================================
        // VERIFICATION: BALANCE SHEET
        // ==========================================

        // Calculate total assets (inventory) - check the specific inventory COA used by the product
        $inventoryCoa = ChartOfAccount::find($this->product->inventory_coa_id);
        $inventoryCoa->load('journalEntries');
        $totalInventoryAssets = $inventoryCoa->calculateEndingBalance();
        $this->assertEquals(100000, $totalInventoryAssets);

        // Calculate total liabilities (unbilled purchases) - check the specific liability COA used by the product
        $liabilityCoa = ChartOfAccount::find($this->product->unbilled_purchase_coa_id);
        $liabilityCoa->load('journalEntries');
        $totalUnbilledLiabilities = $liabilityCoa->calculateEndingBalance();
        $this->assertEquals(100000, $totalUnbilledLiabilities);

        // Balance sheet should balance: Assets = Liabilities + Equity
        // Here: 100000 (inventory) = 100000 (unbilled purchases) + 0 (equity)
        $this->assertEquals($totalInventoryAssets, $totalUnbilledLiabilities);

        // ==========================================
        // VERIFICATION: CASH FLOW STATEMENT
        // ==========================================

        // At this point, no cash transactions have occurred yet
        // The procurement creates accruals but no cash flow impact
        $cashFlowEntries = JournalEntry::where('journal_type', 'cash_flow')->count();
        $this->assertEquals(0, $cashFlowEntries);

        // Verify no cash accounts are affected
        $cashCoa = ChartOfAccount::where('code', 'like', '110%')->first();
        if ($cashCoa) {
            $cashCoa->load('journalEntries');
            $this->assertEquals(0, $cashCoa->balance);
        }

        // ==========================================
        // FINAL VERIFICATION: COMPLETE FLOW
        // ==========================================

        // Verify order request is linked to completed purchase order
        $orderRequest->refresh();
        $this->assertEquals('approved', $orderRequest->status);

        // Verify purchase order status
        $purchaseOrder->refresh();
        $this->assertEquals('approved', $purchaseOrder->status); // Would be 'completed' if all items received

        // Verify purchase receipt is completed
        $purchaseReceipt->refresh();
        $this->assertEquals('completed', $purchaseReceipt->status);

        // Verify QC is completed
        $this->assertEquals(1, $qualityControl->status);

        // Verify inventory is updated
        $this->assertEquals(10, $inventoryStock->qty_available);

        // Verify all journal entries are balanced
        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(200000, $totalDebit); // 100000 temp procurement + 100000 inventory
        $this->assertEquals(200000, $totalCredit); // 100000 unbilled purchase + 100000 close temp procurement
    }
}