<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderRequestQuantityLockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Supplier $supplier;
    protected Product $product;
    protected OrderRequest $orderRequest;
    protected OrderRequestItem $orderRequestItem;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::factory()->create(['code' => 'IDR']);
        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create();

        $this->orderRequest = OrderRequest::factory()->create([
            'status' => 'approved',
            'currency_id' => $currency->id,
            'created_by' => $this->user->id,
        ]);

        $this->orderRequestItem = OrderRequestItem::factory()->create([
            'order_request_id' => $this->orderRequest->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'quantity' => 10,
            'fulfilled_quantity' => 0,
            'currency_id' => $currency->id,
        ]);

        $this->actingAs($this->user);
    }

    #[Test]
    public function approved_po_items_lock_order_request_quantity_on_backend(): void
    {
        $this->createPoWithItem('PO-LOCK-001', 'approved', 10);
        $draftPo = $this->createPoWithItem('PO-LOCK-002', 'draft', 1);

        $this->expectException(\InvalidArgumentException::class);

        app(PurchaseOrderService::class)->approvePo($draftPo, $this->user->id);
    }

    #[Test]
    public function purchase_receipt_for_order_request_cannot_exceed_order_request_quantity(): void
    {
        $po = $this->createPoWithItem('PO-LOCK-003', 'approved', 10);
        $poItem = $po->purchaseOrderItem()->first();

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'status' => 'completed',
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 1,
            'qty_accepted' => 1,
            'qty_rejected' => 0,
        ]);
    }

    #[Test]
    public function qc_from_order_request_po_item_cannot_create_receipt_over_order_request_quantity(): void
    {
        $po = $this->createPoWithItem('PO-LOCK-004', 'approved', 10);
        $poItem = $po->purchaseOrderItem()->first();

        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'status' => 'completed',
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
        ]);

        $this->expectException(\Exception::class);

        app(QualityControlService::class)->createQCFromPurchaseOrderItem($poItem, [
            'passed_quantity' => 1,
            'rejected_quantity' => 0,
        ]);
    }

    private function createPoWithItem(string $number, string $status, float $quantity): PurchaseOrder
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => $number,
            'status' => $status,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $this->orderRequest->id,
            'created_by' => $this->user->id,
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'refer_item_model_type' => OrderRequestItem::class,
            'refer_item_model_id' => $this->orderRequestItem->id,
        ]);

        return $po->fresh('purchaseOrderItem');
    }
}
