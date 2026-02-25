<?php

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(OrderRequestService::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->currency = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
    ]);

    UnitOfMeasure::factory()->create();
    $this->cabang = Cabang::factory()->create();
    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
    ]);
    $this->supplier = Supplier::factory()->create([
        'tempo_hutang' => 30,
    ]);

    $this->productA = Product::factory()->create([
        'cost_price' => 15000,
        'sell_price' => 25000,
    ]);

    $this->productB = Product::factory()->create([
        'cost_price' => 27500,
        'sell_price' => 35000,
    ]);

    $this->orderRequest = OrderRequest::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
        'status' => 'draft',
        'request_date' => Carbon::now()->toDateString(),
        'note' => 'Pengadaan material uji',
    ]);

    $this->itemA = OrderRequestItem::factory()->create([
        'order_request_id' => $this->orderRequest->id,
        'product_id' => $this->productA->id,
        'quantity' => 5,
        'note' => 'Untuk batch produksi 01',
    ]);

    $this->itemB = OrderRequestItem::factory()->create([
        'order_request_id' => $this->orderRequest->id,
        'product_id' => $this->productB->id,
        'quantity' => 3,
        'note' => 'Safety stock',
    ]);
});

test('order request approval generates purchase order and items', function () {
    $orderDate = Carbon::now()->startOfDay();
    $expectedDate = Carbon::now()->addDays(7)->startOfDay();

    $payload = [
        'po_number' => 'PO-20251031-0001',
        'supplier_id' => $this->supplier->id,
        'order_date' => $orderDate->toDateTimeString(),
        'note' => 'Auto generated from order request',
        'expected_date' => $expectedDate->toDateTimeString(),
    ];

    $orderRequest = $this->orderRequest->fresh(['orderRequestItem.product']);

    $result = $this->service->approve($orderRequest, $payload);

    $fresh = $result->fresh(['purchaseOrder.purchaseOrderItem']);

    expect($fresh->status)->toBe('approved');
    expect(PurchaseOrder::count())->toBe(1);

    $purchaseOrder = $fresh->purchaseOrder;

    expect($purchaseOrder)->not->toBeNull()
        ->and($purchaseOrder->po_number)->toBe($payload['po_number'])
        ->and($purchaseOrder->supplier_id)->toBe($payload['supplier_id'])
        ->and($purchaseOrder->order_date->toDateTimeString())->toBe($payload['order_date'])
        ->and($purchaseOrder->expected_date->toDateTimeString())->toBe($payload['expected_date'])
        ->and($purchaseOrder->note)->toBe($payload['note'])
        ->and($purchaseOrder->refer_model_type)->toBe(OrderRequest::class)
        ->and($purchaseOrder->refer_model_id)->toBe($fresh->id)
        ->and($purchaseOrder->status)->toBe('approved')
        ->and($purchaseOrder->warehouse_id)->toBe($this->warehouse->id)
        ->and($purchaseOrder->tempo_hutang)->toBe(30)
        ->and($purchaseOrder->created_by)->toBe($this->user->id);

    expect($purchaseOrder->purchaseOrderItem)->toHaveCount(2);

    $poItemA = $purchaseOrder->purchaseOrderItem->firstWhere('product_id', $this->productA->id);
    $poItemB = $purchaseOrder->purchaseOrderItem->firstWhere('product_id', $this->productB->id);

    expect($poItemA)->not->toBeNull()
        ->and((float) $poItemA->quantity)->toBe(5.0)
        ->and((float) $poItemA->unit_price)->toBe((float) $this->productA->cost_price)
        ->and((float) $poItemA->discount)->toBe(0.0)
        ->and((float) $poItemA->tax)->toBe(0.0)
        ->and($poItemA->tipe_pajak)->toBe('Non Pajak')
        ->and($poItemA->currency_id)->toBe($this->currency->id)
        ->and($poItemA->refer_item_model_type)->toBe(OrderRequestItem::class)
        ->and($poItemA->refer_item_model_id)->toBe($this->itemA->id);

    expect($poItemB)->not->toBeNull()
        ->and((float) $poItemB->quantity)->toBe(3.0)
        ->and((float) $poItemB->unit_price)->toBe((float) $this->productB->cost_price)
        ->and((float) $poItemB->discount)->toBe(0.0)
        ->and((float) $poItemB->tax)->toBe(0.0)
        ->and($poItemB->tipe_pajak)->toBe('Non Pajak')
        ->and($poItemB->currency_id)->toBe($this->currency->id)
        ->and($poItemB->refer_item_model_type)->toBe(OrderRequestItem::class)
        ->and($poItemB->refer_item_model_id)->toBe($this->itemB->id);
});

test('order request rejection updates status without creating purchase order', function () {
    $this->service->reject($this->orderRequest);

    expect($this->orderRequest->fresh()->status)->toBe('rejected');
    expect(PurchaseOrder::count())->toBe(0);
    expect(PurchaseOrderItem::count())->toBe(0);
});
