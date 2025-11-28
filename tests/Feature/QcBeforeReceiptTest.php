<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\PurchaseOrder;
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
            'unit_price' => 10000,
        ]);

        // create QC from PO item (simulate inspector created record)
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

    // complete QC -> this should create receipt + receipt item and create temporary procurement journal
    // but inventory posting is deferred until the purchase receipt is posted
    $qcService->completeQualityControl($qc, []);

        // assert a purchase receipt was created
        $this->assertDatabaseHas('purchase_receipts', [
            'purchase_order_id' => $po->id,
        ]);

        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        // assert receipt item created
        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'qty_accepted' => 5,
        ]);

        // temporary procurement journal should be created (temporary entry)
        $this->assertTrue(\App\Models\JournalEntry::where('journal_type', 'procurement')
            ->where('description', 'like', '%Temporary Procurement%')
            ->exists());

        // Inventory journal should NOT yet be created (deferred to receipt posting)
        $this->assertFalse(\App\Models\JournalEntry::where('journal_type', 'inventory')
            ->where('description', 'like', '%Inventory Stock%')
            ->exists());

        // Now post the receipt (this should post inventory and close temporary procurement)
        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($receipt);

        $this->assertEquals('posted', $result['status']);

        // inventory journal should be created (inventory posting)
        $this->assertTrue(\App\Models\JournalEntry::where('journal_type', 'inventory')
            ->where('description', 'like', '%Inventory Stock%')
            ->exists());
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
            'unit_price' => 15000,
        ]);

        // create QC with all items rejected
        $qcService = app(QualityControlService::class);
        $qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 0,
            'rejected_quantity' => 10,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // complete QC with rejection
        $qcService->completeQualityControl($qc, [
            'item_condition' => 'damage',
            'notes' => 'All items rejected due to quality issues'
        ]);

        // get the created receipt
        $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($receipt);

        // post receipt (should be skipped since nothing was accepted)
        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($receipt);
        $this->assertEquals('skipped', $result['status']);

        // verify NO inventory stock was updated (all rejected)
        $inventoryStock = \App\Models\InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        // Should be null or zero since nothing was accepted
        if ($inventoryStock) {
            $this->assertEquals(0.0, (float) $inventoryStock->qty_available);
        }

        // verify receipt item shows all rejected
        $receiptItem = PurchaseReceiptItem::where('purchase_receipt_id', $receipt->id)->first();
        $this->assertNotNull($receiptItem);
        $this->assertEquals(10, $receiptItem->qty_received);
        $this->assertEquals(0, $receiptItem->qty_accepted);
        $this->assertEquals(10, $receiptItem->qty_rejected);
    }
}
