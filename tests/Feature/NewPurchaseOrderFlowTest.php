<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\UnitOfMeasure;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create permissions
    $permissions = [
        'view any purchase order',
        'view purchase order',
        'create purchase order',
        'update purchase order',
        'delete purchase order',
        'approve purchase order',
        'view any quality control',
        'create quality control',
        'update quality control',
        'complete quality control',
        'view any purchase receipt',
    ];

    foreach ($permissions as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->user->givePermissionTo($permissions);

    // Create basic data
    $this->cabang = Cabang::factory()->create();
    $this->user->cabang_id = $this->cabang->id;
    $this->user->save();

    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->currency = Currency::factory()->create();
    $this->uom = UnitOfMeasure::factory()->create();
    $this->product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'cost_price' => 100000,
    ]);

    // Create required COA accounts
    $this->inventoryCoa = ChartOfAccount::factory()->create([
        'code' => '1140.10',
        'name' => 'Persediaan Barang',
        'type' => 'asset',
        'is_active' => true,
    ]);

    $this->tempProcurementCoa = ChartOfAccount::factory()->create([
        'code' => '1180.01',
        'name' => 'Barang Dalam Pengiriman',
        'type' => 'asset',
        'is_active' => true,
    ]);

    $this->unbilledPurchaseCoa = ChartOfAccount::factory()->create([
        'code' => '2100.10',
        'name' => 'Hutang Yang Belum Ditagih',
        'type' => 'liability',
        'is_active' => true,
    ]);

    // Assign COA to product
    $this->product->inventory_coa_id = $this->inventoryCoa->id;
    $this->product->temporary_procurement_coa_id = $this->tempProcurementCoa->id;
    $this->product->unbilled_purchase_coa_id = $this->unbilledPurchaseCoa->id;
    $this->product->save();

    $this->qcService = app(QualityControlService::class);
});

it('executes new flow: PO → QC → Auto-Receipt → Stock → Complete', function () {
    // Step 1: Create Purchase Order
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
        'po_number' => 'PO-TEST-' . uniqid(),
    ]);

    // Add currency to PO
    $po->purchaseOrderCurrency()->create([
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'currency_id' => $this->currency->id,
    ]);

    // Step 2: Create QC from PO Item (NEW FLOW)
    $qc = $this->qcService->createQCFromPurchaseOrderItem($poItem, [
        'passed_quantity' => 95,
        'rejected_quantity' => 5,
        'inspected_by' => $this->user->id,
        'warehouse_id' => $this->warehouse->id,
    ]);

    expect($qc)->not->toBeNull();
    expect($qc->from_model_type)->toBe('App\Models\PurchaseOrderItem');
    expect($qc->from_model_id)->toBe($poItem->id);
    expect($qc->passed_quantity)->toBe(95);
    expect($qc->rejected_quantity)->toBe(5);
    expect($qc->status)->toBe(0); // Not completed yet

    // Step 3: Complete QC (should auto-create receipt)
    $this->qcService->completeQualityControl($qc->fresh(), [
        'received_by' => $this->user->id,
    ]);

    // Step 4: Assert Receipt Auto-Created
    $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)
        ->where('notes', 'like', '%' . $qc->qc_number . '%')
        ->first();

    expect($receipt)->not->toBeNull();
    expect($receipt->status)->toBe('completed');

    $receiptItem = PurchaseReceiptItem::where('purchase_receipt_id', $receipt->id)
        ->where('purchase_order_item_id', $poItem->id)
        ->first();

    expect($receiptItem)->not->toBeNull();
    expect((int)$receiptItem->qty_received)->toBe(100); // 95 + 5
    expect((int)$receiptItem->qty_accepted)->toBe(95);
    expect((int)$receiptItem->qty_rejected)->toBe(5);
    expect($receiptItem->is_sent)->toBe(1); // Already sent to QC

    // Step 5: Journal entries are now created via receipt posting (not QC directly)
    // The new flow creates journal entries through autoCreatePurchaseReceiptFromQC
    
    // Extra: Ensure the posting helper reports status
    $receiptService = app(\App\Services\PurchaseReceiptService::class);
    $postResult = $receiptService->postItemInventoryAfterQC($receiptItem);
    expect(in_array($postResult['status'], ['posted','skipped']))->toBeTrue();

    // Step 6: Assert Stock Movement Created
    // Accept stock movement that may be linked to the QC or to the generated receipt item.
    $stockMovement = StockMovement::where(function ($q) use ($qc) {
            $q->where('from_model_type', QualityControl::class)
              ->where('from_model_id', $qc->id);
        })->orWhere(function ($q) use ($receiptItem) {
            $q->where('from_model_type', PurchaseReceiptItem::class)
              ->where('from_model_id', $receiptItem->id);
        })->where('product_id', $this->product->id)
        ->first();

    expect($stockMovement)->not->toBeNull();
    expect((int)$stockMovement->quantity)->toBe(95); // Only passed quantity
    expect($stockMovement->type)->toBe('purchase_in');

    // Step 7: Assert PO Auto-Completed (since all items received)
    $po->refresh();
    expect($po->status)->toBe('completed');
    expect($po->completed_by)->not->toBeNull();
    expect($po->completed_at)->not->toBeNull();
});

it('supports partial QC and completion', function () {
    // Create PO with 2 items
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
    ]);

    $product2 = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'inventory_coa_id' => $this->inventoryCoa->id,
        'temporary_procurement_coa_id' => $this->tempProcurementCoa->id,
        'unbilled_purchase_coa_id' => $this->unbilledPurchaseCoa->id,
    ]);

    $poItem1 = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'unit_price' => 100000,
        'currency_id' => $this->currency->id,
    ]);

    $poItem2 = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product2->id,
        'quantity' => 50,
        'unit_price' => 50000,
        'currency_id' => $this->currency->id,
    ]);

    // QC only first item
    $qc1 = $this->qcService->createQCFromPurchaseOrderItem($poItem1, [
        'passed_quantity' => 100,
        'rejected_quantity' => 0,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $this->qcService->completeQualityControl($qc1->fresh(), []);

    // PO should NOT be completed yet (only 1 of 2 items)
    $po->refresh();
    expect($po->status)->not->toBe('completed');

    // QC second item
    $qc2 = $this->qcService->createQCFromPurchaseOrderItem($poItem2, [
        'passed_quantity' => 50,
        'rejected_quantity' => 0,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $this->qcService->completeQualityControl($qc2->fresh(), []);

    // Now PO should be completed (all items received)
    $po->refresh();
    expect($po->status)->toBe('completed');
});

it('allows manual completion of purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
    ]);

    // Create PO item and receipt so it can be completed
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'unit_price' => 100000,
        'currency_id' => $this->currency->id,
    ]);

    // Create receipt for the item (simulating received goods)
    $receipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => 'completed',
    ]);

    PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $this->product->id,
        'qty_received' => 100,
        'qty_accepted' => 100,
    ]);

    $po->refresh();
    expect($po->canBeCompleted())->toBeTrue();

    // Manual complete
    $po->manualComplete($this->user->id);

    expect($po->status)->toBe('completed');
    expect($po->completed_by)->toBe($this->user->id);
    expect($po->completed_at)->not->toBeNull();
});

it('prevents completion of already completed PO', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'completed',
        'completed_by' => $this->user->id,
        'completed_at' => now(),
    ]);

    expect($po->canBeCompleted())->toBeFalse();

    // Should throw exception
    $this->expectException(\Exception::class);
    $po->manualComplete($this->user->id);
});

it('creates return product for rejected items', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'unit_price' => 100000,
        'currency_id' => $this->currency->id,
    ]);

    $qc = $this->qcService->createQCFromPurchaseOrderItem($poItem, [
        'passed_quantity' => 80,
        'rejected_quantity' => 20,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $this->qcService->completeQualityControl($qc->fresh(), []);

    // Assert return product created
    $returnProduct = $qc->returnProduct;
    expect($returnProduct)->not->toBeNull();
    expect($returnProduct->status)->toBe('draft');

    $returnItem = $qc->returnProductItem->first();
    expect($returnItem)->not->toBeNull();
    expect((int)$returnItem->quantity)->toBe(20);
    expect($returnItem->product_id)->toBe($this->product->id);
});

it('handles zero rejected quantity correctly', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'unit_price' => 100000,
        'currency_id' => $this->currency->id,
    ]);

    $qc = $this->qcService->createQCFromPurchaseOrderItem($poItem, [
        'passed_quantity' => 100,
        'rejected_quantity' => 0,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $this->qcService->completeQualityControl($qc->fresh(), []);

    // Assert NO return product created
    $returnProduct = $qc->returnProduct;
    expect($returnProduct->exists)->toBeFalse();

    // Receipt should have no rejected qty
    $receipt = PurchaseReceipt::where('purchase_order_id', $po->id)->first();
    $receiptItem = $receipt->purchaseReceiptItem->first();

    expect((int)$receiptItem->qty_rejected)->toBe(0);
    expect((int)$receiptItem->qty_accepted)->toBe(100);
});

it('validates QC passed quantity cannot exceed ordered quantity', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 100,
        'unit_price' => 100000,
        'currency_id' => $this->currency->id,
    ]);

    // Should throw exception: passed_quantity > ordered quantity
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('cannot exceed ordered quantity');

    $this->qcService->createQCFromPurchaseOrderItem($poItem, [
        'passed_quantity' => 150, // More than ordered (100)
        'rejected_quantity' => 0,
        'warehouse_id' => $this->warehouse->id,
    ]);
});
