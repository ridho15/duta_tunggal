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

        $currency = Currency::factory()->create([
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'code' => 'IDR',
            'to_rupiah' => 1,
        ]);
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

    #[Test]
    public function fully_poed_items_are_excluded_from_create_po_form(): void
    {
        $needed = [
            'view any order request',
            'view order request',
            'approve order request',
        ];
        foreach ($needed as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo($needed);

        $secondProduct = Product::factory()->create();
        $secondItem = OrderRequestItem::factory()->create([
            'order_request_id' => $this->orderRequest->id,
            'product_id' => $secondProduct->id,
            'supplier_id' => $this->supplier->id,
            'quantity' => 5,
            'fulfilled_quantity' => 0,
            'currency_id' => $this->orderRequestItem->currency_id,
        ]);

        // Fully PO the first item (qty 10)
        $this->createPoWithItem('PO-LOCK-005', 'approved', 10);

        $component = \Livewire\Livewire::test(\App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest::class, [
            'record' => $this->orderRequest->getKey(),
        ])
        ->assertActionVisible('create_purchase_order')
        ->mountAction('create_purchase_order');

        $mountedData = $component->get('mountedActionsData');
        $selectedItemsRaw = end($mountedData)['selected_items'] ?? [];
        $selectedItems = array_values($selectedItemsRaw);

        $this->assertCount(1, $selectedItems);
        $this->assertEquals($secondItem->id, $selectedItems[0]['item_id']);
    }

    #[Test]
    public function create_po_form_contains_all_cost_and_subtotal_fields(): void
    {
        $needed = [
            'view any order request',
            'view order request',
            'approve order request',
        ];
        foreach ($needed as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo($needed);

        // 1. Verify ViewOrderRequest page action
        $componentPage = \Livewire\Livewire::test(\App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest::class, [
            'record' => $this->orderRequest->getKey(),
        ])
        ->assertActionVisible('create_purchase_order')
        ->mountAction('create_purchase_order');

        $mountedPageData = $componentPage->get('mountedActionsData');
        $selectedItemsPage = array_values(end($mountedPageData)['selected_items'] ?? []);

        $this->assertCount(1, $selectedItemsPage);
        $this->assertNotNull($selectedItemsPage[0]['original_price'] ?? null);
        $this->assertNotNull($selectedItemsPage[0]['total_cost'] ?? null);
        $this->assertNotNull($selectedItemsPage[0]['subtotal'] ?? null);

        // 2. Verify OrderRequestResource table action
        $componentTable = \Livewire\Livewire::test(\App\Filament\Resources\OrderRequestResource\Pages\ListOrderRequests::class)
        ->assertTableActionVisible('create_purchase_order', $this->orderRequest)
        ->mountTableAction('create_purchase_order', $this->orderRequest);

        $mountedTableData = $componentTable->get('mountedTableActionsData');
        $selectedItemsTable = array_values(end($mountedTableData)['selected_items'] ?? []);

        $this->assertCount(1, $selectedItemsTable);
        $this->assertNotNull($selectedItemsTable[0]['original_price'] ?? null);
        $this->assertNotNull($selectedItemsTable[0]['total_cost'] ?? null);
        $this->assertNotNull($selectedItemsTable[0]['subtotal'] ?? null);
    }

    #[Test]
    public function creating_multiple_partial_pos_via_filament_action_works(): void
    {
        $needed = [
            'view any order request',
            'view order request',
            'approve order request',
        ];
        foreach ($needed as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo($needed);

        // 1. Submit first PO with quantity 5
        $component = \Livewire\Livewire::test(\App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest::class, [
            'record' => $this->orderRequest->getKey(),
        ])
        ->assertActionVisible('create_purchase_order')
        ->mountAction('create_purchase_order');

        $mountedData = $component->get('mountedActionsData');
        $selectedItemsRaw = end($mountedData)['selected_items'] ?? [];
        $firstKey = array_key_first($selectedItemsRaw);

        // Modify quantity of item to 5 while preserving associative keys
        $selectedItemsRaw[$firstKey]['quantity'] = 5;
        $selectedItemsRaw[$firstKey]['include'] = true;

        $component->setActionData([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-PARTIAL-ACTION-1',
            'order_date' => now()->format('Y-m-d'),
            'selected_items' => $selectedItemsRaw,
        ])
        ->callMountedAction(); // Submit first PO

        // Check first PO is created
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-PARTIAL-ACTION-1']);
        $po1 = PurchaseOrder::where('po_number', 'PO-PARTIAL-ACTION-1')->first();
        $this->assertEquals(5, $po1->purchaseOrderItem()->first()->quantity);

        // Do NOT approve the first PO, leave it as draft

        // 2. Try to submit second PO with quantity 5
        $component2 = \Livewire\Livewire::test(\App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest::class, [
            'record' => $this->orderRequest->getKey(),
        ])
        ->assertActionVisible('create_purchase_order')
        ->mountAction('create_purchase_order');

        $mountedData2 = $component2->get('mountedActionsData');
        $selectedItemsRaw2 = end($mountedData2)['selected_items'] ?? [];
        $firstKey2 = array_key_first($selectedItemsRaw2);

        // Verify remaining qty for PO is indeed 10 (since draft PO is excluded from limits)
        $this->assertEquals(10, $selectedItemsRaw2[$firstKey2]['quantity']);

        // Modify quantity of item to 5 and set include = true
        $selectedItemsRaw2[$firstKey2]['quantity'] = 5;
        $selectedItemsRaw2[$firstKey2]['include'] = true;
        
        $component2->setActionData([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-PARTIAL-ACTION-2',
            'order_date' => now()->format('Y-m-d'),
            'selected_items' => $selectedItemsRaw2,
        ])
        ->callMountedAction(); // Submit second PO

        // Check second PO is created
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-PARTIAL-ACTION-2']);
    }

    #[Test]
    public function creating_multiple_partial_pos_with_receiving_and_status_changes_works(): void
    {
        $needed = [
            'view any order request',
            'view order request',
            'approve order request',
        ];
        foreach ($needed as $p) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo($needed);

        // 1. Submit first PO with quantity 5
        $component = \Livewire\Livewire::test(\App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest::class, [
            'record' => $this->orderRequest->getKey(),
        ])
        ->assertActionVisible('create_purchase_order')
        ->mountAction('create_purchase_order');

        $mountedData = $component->get('mountedActionsData');
        $selectedItemsRaw = end($mountedData)['selected_items'] ?? [];
        $firstKey = array_key_first($selectedItemsRaw);

        // Modify quantity of item to 5 and set include = true
        $selectedItemsRaw[$firstKey]['quantity'] = 5;
        $selectedItemsRaw[$firstKey]['include'] = true;

        $component->setActionData([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-PARTIAL-TEST-1',
            'order_date' => now()->format('Y-m-d'),
            'selected_items' => $selectedItemsRaw,
        ])
        ->callMountedAction(); // Submit first PO

        // Check first PO is created in draft state
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-PARTIAL-TEST-1', 'status' => 'draft']);
        $po1 = PurchaseOrder::where('po_number', 'PO-PARTIAL-TEST-1')->first();
        $po1Item = $po1->purchaseOrderItem()->first();
        $this->assertEquals(5, $po1Item->quantity);

        // 2. Approve the first PO (which locks the quantity backend-side)
        $po1->update(['status' => 'approved']);

        // 3. Perform partial receipt (QC and Purchase Receipt) for 5 items
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po1->id,
            'status' => 'completed',
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $po1Item->id,
            'product_id' => $this->product->id,
            'qty_received' => 5,
            'qty_accepted' => 5,
            'qty_rejected' => 0,
        ]);

        // 4. Verify that OrderRequest status transitioned to 'partial'
        $this->orderRequest->refresh();
        $this->assertEquals('partial', $this->orderRequest->status);

        // 5. Verify that 'create_purchase_order' action is STILL visible on the OrderRequest even when status is 'partial'
        $component2 = \Livewire\Livewire::test(\App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest::class, [
            'record' => $this->orderRequest->getKey(),
        ])
        ->assertActionVisible('create_purchase_order')
        ->mountAction('create_purchase_order');

        // 6. Verify remaining qty to pre-fill is indeed 5 (remaining from 10 total)
        $mountedData2 = $component2->get('mountedActionsData');
        $selectedItemsRaw2 = end($mountedData2)['selected_items'] ?? [];
        $firstKey2 = array_key_first($selectedItemsRaw2);
        $this->assertEquals(5, $selectedItemsRaw2[$firstKey2]['quantity']);

        // 7. Submit second partial PO with the remaining quantity of 5
        $selectedItemsRaw2[$firstKey2]['quantity'] = 5;
        $selectedItemsRaw2[$firstKey2]['include'] = true;

        $component2->setActionData([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-PARTIAL-TEST-2',
            'order_date' => now()->format('Y-m-d'),
            'selected_items' => $selectedItemsRaw2,
        ])
        ->callMountedAction();

        // 8. Verify the second PO is created successfully
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-PARTIAL-TEST-2', 'status' => 'draft']);
        $po2 = PurchaseOrder::where('po_number', 'PO-PARTIAL-TEST-2')->first();
        $this->assertEquals(5, $po2->purchaseOrderItem()->first()->quantity);
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
