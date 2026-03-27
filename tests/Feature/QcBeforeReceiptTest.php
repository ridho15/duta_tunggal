<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderBiaya;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReceiptBiaya;
use App\Models\PurchaseReceiptPhoto;
use App\Models\PurchaseReceiptItemPhoto;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcBeforeReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountSeeder::class);

        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create();
        $this->currency = Currency::factory()->create();

        // configure product COA
        $inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
        $unbilledPurchaseCoa = ChartOfAccount::where('code', '2100.10')->first();
        $temporaryProcurementCoa = ChartOfAccount::where('code', '1400.01')->first();

        if ($inventoryCoa) $this->product->inventory_coa_id = $inventoryCoa->id;
        if ($unbilledPurchaseCoa) $this->product->unbilled_purchase_coa_id = $unbilledPurchaseCoa->id;
        if ($temporaryProcurementCoa) $this->product->temporary_procurement_coa_id = $temporaryProcurementCoa->id;
        $this->product->save();

        $this->actingAs($this->user);
    }

    /** @test */
    public function qc_from_po_item_creates_receipt_and_posts_journals()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 10000,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertNotNull($qc);
        $this->assertDatabaseHas('quality_controls', [
            'id' => $qc->id,
            'from_model_type' => \App\Models\PurchaseOrderItem::class,
        ]);

        $qcService->completeQualityControl($qc, []);

        $this->assertDatabaseHas('purchase_receipts', [
            'purchase_order_id' => $po->id,
        ]);

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'qty_accepted' => 5,
        ]);

        $this->assertTrue(
            \App\Models\JournalEntry::where('journal_type', 'inventory')
                ->where('description', 'like', '%QC Inventory - Debit inventory for QC passed items%')
                ->exists()
        );
        $this->assertTrue(
            \App\Models\JournalEntry::where('journal_type', 'inventory')
                ->where('description', 'like', '%QC Inventory - Credit temporary procurement for QC passed items%')
                ->exists()
        );

        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($receipt);

        $this->assertEquals('posted', $result['status']);
    }

    /** @test */
    public function qc_from_po_item_copies_purchase_order_biaya_to_receipt()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'unit_price' => 12500,
        ]);

        $poBiaya = PurchaseOrderBiaya::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $this->currency->id,
            'coa_id' => ChartOfAccount::where('code', '6100.02')->first()?->id,
            'nama_biaya' => 'Biaya Handling',
            'total' => 25000,
            'untuk_pembelian' => 0,
            'masuk_invoice' => 1,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 4,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $qcService->completeQualityControl($qc, []);

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        $this->assertDatabaseHas('purchase_receipt_biayas', [
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_biaya_id' => $poBiaya->id,
            'nama_biaya' => 'Biaya Handling',
            'total' => 25000,
        ]);

        $receiptBiaya = PurchaseReceiptBiaya::where('purchase_receipt_id', $receipt->id)
            ->where('purchase_order_biaya_id', $poBiaya->id)
            ->first();

        $this->assertNotNull($receiptBiaya);
        $this->assertSame('Biaya Handling', $receiptBiaya->nama_biaya);
        $this->assertEquals(25000, (float) $receiptBiaya->total);
        $this->assertEquals(1, (int) $receiptBiaya->masuk_invoice);
    }

    /** @test */
    public function qc_handles_rejected_quantities_correctly()
    {
        // create approved purchase order and item
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
        ]);

        // create QC with some rejected items
        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 7,  // 7 passed
            'rejected_quantity' => 3, // 3 rejected
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertNotNull($qc);
        $this->assertEquals(7, $qc->passed_quantity);
        $this->assertEquals(3, $qc->rejected_quantity);

        // complete QC
        $qcService->completeQualityControl($qc, [
            'item_condition' => 'damage',
            'notes' => '3 items damaged during transport'
        ]);

        // assert receipt was created with correct quantities
        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        $receiptItem = PurchaseReceiptItem::where('purchase_receipt_id', $receipt->id)->first();
        $this->assertNotNull($receiptItem);
        $this->assertEquals(10, $receiptItem->qty_received); // total received
        $this->assertEquals(7, $receiptItem->qty_accepted);  // only accepted items
        $this->assertEquals(3, $receiptItem->qty_rejected);  // rejected items

        // post receipt
        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($receipt);
        $this->assertEquals('posted', $result['status']);

        // verify inventory only reflects accepted quantity
        $inventoryStock = \App\Models\InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($inventoryStock);
        $this->assertEquals(7.0, (float) $inventoryStock->qty_available); // only accepted qty in inventory
    }

    /** @test */
    public function qc_approval_updates_inventory_stock()
    {
        // create approved purchase order and item
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 20000,
        ]);

        // create QC with all items passed
        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // complete QC with approval
        $qcService->completeQualityControl($qc, [
            'item_condition' => 'good',
            'notes' => 'All items passed QC'
        ]);

        // get the created receipt
        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        // post receipt to update inventory
        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($receipt);
        $this->assertEquals('posted', $result['status']);

        // verify inventory stock was updated
        $inventoryStock = \App\Models\InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($inventoryStock);
        $this->assertEquals(5.0, (float) $inventoryStock->qty_available);
        $this->assertEquals(5.0, (float) $inventoryStock->qty_on_hand);

        // verify stock movement was recorded
        $stockMovement = \App\Models\StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('type', 'purchase_in')
            ->first();

        $this->assertNotNull($stockMovement);
        $this->assertEquals(5.0, (float) $stockMovement->quantity);
        $this->assertEquals('purchase_in', $stockMovement->type);
    }

    /** @test */
    public function qc_rejection_prevents_inventory_update()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 15000,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 0,
            'rejected_quantity' => 10,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $qcService->completeQualityControl($qc, [
            'item_condition' => 'damage',
            'notes' => 'All items rejected due to quality issues'
        ]);

        $this->assertDatabaseMissing('purchase_receipts', [
            'purchase_order_id' => $po->id,
        ]);

        $inventoryStock = \App\Models\InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        if ($inventoryStock) {
            $this->assertEquals(0.0, (float) $inventoryStock->qty_available);
        }

        $this->assertDatabaseMissing('purchase_receipt_items', [
            'purchase_order_item_id' => $poItem->id,
        ]);
    }

    /**
     * @test
     * QC completion should not crash when the product has no COA accounts
     * configured (relations return default models with null ids).  In that
     * circumstance the service skips journal creation entirely.
     */
    public function qc_with_missing_coa_accounts_skips_journals()
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_price' => 5000,
        ]);

        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 3,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // should not throw
        $qcService->completeQualityControl($qc, []);

        $this->assertDatabaseHas('quality_controls', ['id' => $qc->id]);
        $this->assertFalse(JournalEntry::where('source_type', QualityControl::class)
            ->where('source_id', $qc->id)
            ->exists(), 'No QC-related journals should be created');
    }
}
