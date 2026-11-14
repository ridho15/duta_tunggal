<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use App\Support\OrderRequestQuantityLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalAuditStage2Test extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Supplier $supplier;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(
            ['id' => 1],
            ['code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1.0, 'is_default' => 1]
        );

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Purchasing', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Purchasing Manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Kepala Gudang', 'guard_name' => 'web']);

        $this->cabang = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 1]);

        $this->superAdmin = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->supplier = Supplier::factory()->create();
        $uom = UnitOfMeasure::firstOrCreate(['name' => 'Pcs'], ['abbreviation' => 'pcs']);
        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'uom_id' => $uom->id,
            'cost_price' => 50000,
        ]);
    }

    #[Test]
    public function it_resyncs_and_restores_order_request_quota_when_draft_po_is_deleted(): void
    {
        $orderRequest = OrderRequest::create([
            'request_number' => 'OR-TEST-001',
            'cabang_id' => $this->cabang->id,
            'request_date' => now(),
            'status' => 'approved',
            'created_by' => $this->superAdmin->id,
        ]);

        $orderRequestItem = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'fulfilled_quantity' => 0,
            'unit_price' => 50000,
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-DRAFT-001',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now(),
            'status' => 'draft',
            'created_by' => $this->superAdmin->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'unit_price' => 50000,
            'refer_item_model_type' => OrderRequestItem::class,
            'refer_item_model_id' => $orderRequestItem->id,
        ]);

        $orderRequestItem->addFulfilledQuantity(100);
        $orderRequest->syncFulfillmentStatus();

        $this->assertEquals('complete', $orderRequest->fresh()->status);
        $limitBeforeDelete = OrderRequestQuantityLock::orderRequestItemLimit($orderRequestItem->id);
        $this->assertEquals(0.0, $limitBeforeDelete['remaining_for_po']);

        $response = $this->actingAs($this->superAdmin)->delete(route('pembelian.po.destroy', $po));

        $response->assertRedirect(route('pembelian.po.index'));
        $response->assertSessionHas('success');

        $this->assertEquals(0.0, (float) $orderRequestItem->fresh()->fulfilled_quantity);
        $this->assertEquals('approved', $orderRequest->fresh()->status);

        $limitAfterDelete = OrderRequestQuantityLock::orderRequestItemLimit($orderRequestItem->id);
        $this->assertEquals(100.0, $limitAfterDelete['remaining_for_po']);
    }

    #[Test]
    public function it_allows_super_admin_to_cancel_approved_po_and_liberates_order_request_quota(): void
    {
        $orderRequest = OrderRequest::create([
            'request_number' => 'OR-TEST-002',
            'cabang_id' => $this->cabang->id,
            'request_date' => now(),
            'status' => 'approved',
            'created_by' => $this->superAdmin->id,
        ]);

        $orderRequestItem = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'fulfilled_quantity' => 0,
            'unit_price' => 50000,
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-APPR-002',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now(),
            'status' => 'approved',
            'created_by' => $this->superAdmin->id,
            'approved_by' => $this->superAdmin->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 50000,
            'refer_item_model_type' => OrderRequestItem::class,
            'refer_item_model_id' => $orderRequestItem->id,
        ]);

        $orderRequestItem->addFulfilledQuantity(50);
        $orderRequest->syncFulfillmentStatus();

        $response = $this->actingAs($this->superAdmin)->post(route('pembelian.po.cancel', $po), [
            'reason' => 'Supplier out of stock and delivery cancelled by management.',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('cancelled', $po->fresh()->status);
        $this->assertStringContainsString('Supplier out of stock', $po->fresh()->close_reason);

        $this->assertEquals(0.0, (float) $orderRequestItem->fresh()->fulfilled_quantity);
        $this->assertEquals('approved', $orderRequest->fresh()->status);
        $this->assertEquals(50.0, OrderRequestQuantityLock::orderRequestItemLimit($orderRequestItem->id)['remaining_for_po']);
    }

    #[Test]
    public function it_blocks_po_cancellation_when_active_receipts_exist(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-RECEIPT-003',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now(),
            'status' => 'approved',
            'created_by' => $this->superAdmin->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_price' => 50000,
        ]);

        PurchaseReceipt::create([
            'receipt_number' => 'GRN-TEST-003',
            'purchase_order_id' => $po->id,
            'currency_id' => 1,
            'receipt_date' => now(),
            'received_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('pembelian.po.cancel', $po), [
            'reason' => 'Mau membatalkan PO yang sudah ada barang datang.',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('approved', $po->fresh()->status);
    }

    #[Test]
    public function it_allows_super_admin_to_void_completed_grn_and_restores_po_status(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-VOID-004',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now(),
            'status' => 'approved',
            'created_by' => $this->superAdmin->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 50000,
        ]);

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-VOID-004',
            'purchase_order_id' => $po->id,
            'currency_id' => 1,
            'receipt_date' => now(),
            'received_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        app(PurchaseReceiptService::class)->postPurchaseReceipt($receipt);
        $po->syncReceiptFulfillmentStatus();
        $this->assertEquals('completed', $po->fresh()->status);

        $response = $this->actingAs($this->superAdmin)->post(route('gudang.penerimaan.void', $receipt), [
            'reason' => 'Salah input kuantitas fisik dan salah nomor PO.',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('cancelled', $receipt->fresh()->status);
        $this->assertEquals('approved', $po->fresh()->status);
    }

    #[Test]
    public function it_releases_unfulfilled_order_request_quota_when_partially_received_po_is_closed(): void
    {
        $orderRequest = OrderRequest::create([
            'request_number' => 'OR-TEST-005',
            'cabang_id' => $this->cabang->id,
            'request_date' => now(),
            'status' => 'approved',
            'created_by' => $this->superAdmin->id,
        ]);

        $orderRequestItem = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'fulfilled_quantity' => 0,
            'unit_price' => 50000,
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-PARTIAL-005',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now(),
            'status' => 'partially_received',
            'created_by' => $this->superAdmin->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'unit_price' => 50000,
            'refer_item_model_type' => OrderRequestItem::class,
            'refer_item_model_id' => $orderRequestItem->id,
        ]);

        $orderRequestItem->addFulfilledQuantity(100);
        $orderRequest->syncFulfillmentStatus();

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-PARTIAL-005',
            'purchase_order_id' => $po->id,
            'currency_id' => 1,
            'receipt_date' => now(),
            'received_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 70,
            'qty_accepted' => 70,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->superAdmin)->post(route('pembelian.po.close', $po), [
            'reason' => 'Sisa 30 unit dibatalkan oleh supplier karena discontinue.',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('closed', $po->fresh()->status);

        $this->assertEquals(70.0, (float) $orderRequestItem->fresh()->fulfilled_quantity);
        $limit = OrderRequestQuantityLock::orderRequestItemLimit($orderRequestItem->id);
        $this->assertEquals(30.0, $limit['remaining_for_po']);
    }
}
