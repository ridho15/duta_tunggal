<?php

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderBiaya;
use App\Models\PurchaseOrderCurrency;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Filament\Resources\PurchaseOrderResource;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PurchaseOrderService::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    UnitOfMeasure::factory()->create();

    $this->currency = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    \App\Models\ChartOfAccount::create([
        'code' => '2110',
        'name' => 'Hutang Dagang',
        'type' => 'liability',
        'is_active' => 1,
    ]);

    $this->supplier = Supplier::factory()->create([
        'tempo_hutang' => 14,
    ]);

    $this->cabang = Cabang::factory()->create();
    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'status' => true,
    ]);

    $this->productA = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'cost_price' => 10000,
        'sell_price' => 15000,
    ]);

    $this->productB = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'cost_price' => 5000,
        'sell_price' => 9000,
    ]);

    $this->purchaseOrder = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-UNIT-001',
        'order_date' => Carbon::now()->toDateTimeString(),
        'status' => 'draft',
        'expected_date' => Carbon::now()->addDays(7)->toDateTimeString(),
        'total_amount' => 0,
        'warehouse_id' => $this->warehouse->id,
        'tempo_hutang' => $this->supplier->tempo_hutang,
        'note' => 'Pengujian layanan pembelian',
        'created_by' => $this->user->id,
    ]);

    $this->purchaseOrder->purchaseOrderItem()->create([
        'product_id' => $this->productA->id,
        'quantity' => 2,
        'unit_price' => 10000,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
        'currency_id' => $this->currency->id,
    ]);

    $this->purchaseOrder->purchaseOrderItem()->create([
        'product_id' => $this->productB->id,
        'quantity' => 3,
        'unit_price' => 5000,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
        'currency_id' => $this->currency->id,
    ]);
});

test('updateTotalAmount recalculates purchase order total accurately', function () {
    $updated = $this->service->updateTotalAmount($this->purchaseOrder->fresh('purchaseOrderItem'));

    expect((float) $updated->total_amount)->toBe(35000.0)
        ->and((float) $updated->fresh()->total_amount)->toBe(35000.0);
});

test('updateTotalAmount calculates USD item total using purchase order currency rate', function () {
    $usd = Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => 'USD',
        'to_rupiah' => 16000,
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-UNIT-USD-001',
        'order_date' => Carbon::now()->toDateTimeString(),
        'status' => 'approved',
        'expected_date' => Carbon::now()->addDays(7)->toDateTimeString(),
        'total_amount' => 0,
        'warehouse_id' => $this->warehouse->id,
        'tempo_hutang' => $this->supplier->tempo_hutang,
        'created_by' => $this->user->id,
    ]);

    PurchaseOrderCurrency::create([
        'purchase_order_id' => $purchaseOrder->id,
        'currency_id' => $usd->id,
        'nominal' => 15000,
    ]);

    $purchaseOrder->purchaseOrderItem()->create([
        'product_id' => $this->productA->id,
        'quantity' => 10,
        'unit_price' => 53.33,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'Eklusif',
        'currency_id' => $usd->id,
    ]);

    $updated = $this->service->updateTotalAmount($purchaseOrder->fresh(['purchaseOrderItem', 'purchaseOrderCurrency']));

    expect((float) $updated->fresh()->total_amount)->toBe(8879400.0);
});

test('calculateTotalAmount uses purchase order rate before global currency rate', function () {
    $usd = Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => 'USD',
        'to_rupiah' => 16000,
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-UNIT-RATE-001',
        'order_date' => Carbon::now()->toDateTimeString(),
        'status' => 'draft',
        'expected_date' => Carbon::now()->addDays(7)->toDateTimeString(),
        'total_amount' => 0,
        'warehouse_id' => $this->warehouse->id,
        'tempo_hutang' => $this->supplier->tempo_hutang,
        'created_by' => $this->user->id,
    ]);

    PurchaseOrderCurrency::create([
        'purchase_order_id' => $purchaseOrder->id,
        'currency_id' => $usd->id,
        'nominal' => 15000,
    ]);

    $purchaseOrder->purchaseOrderItem()->create([
        'product_id' => $this->productA->id,
        'quantity' => 1,
        'unit_price' => 1,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
        'currency_id' => $usd->id,
    ]);

    expect($this->service->calculateTotalAmount($purchaseOrder->fresh(['purchaseOrderItem', 'purchaseOrderCurrency'])))->toBe(15000.0);
});

test('purchase order view total summary falls back to computed item total when header is zero', function () {
    $usd = Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => 'USD',
        'to_rupiah' => 16000,
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-UNIT-VIEW-001',
        'order_date' => Carbon::now()->toDateTimeString(),
        'status' => 'approved',
        'expected_date' => Carbon::now()->addDays(7)->toDateTimeString(),
        'total_amount' => 0,
        'warehouse_id' => $this->warehouse->id,
        'tempo_hutang' => $this->supplier->tempo_hutang,
        'created_by' => $this->user->id,
    ]);

    PurchaseOrderCurrency::create([
        'purchase_order_id' => $purchaseOrder->id,
        'currency_id' => $usd->id,
        'nominal' => 15000,
    ]);

    $purchaseOrder->purchaseOrderItem()->create([
        'product_id' => $this->productA->id,
        'quantity' => 10,
        'unit_price' => 53.33,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'Eklusif',
        'currency_id' => $usd->id,
    ]);

    PurchaseOrder::withoutEvents(fn() => $purchaseOrder->update(['total_amount' => 0]));

    $fresh = $purchaseOrder->fresh(['purchaseOrderItem.currency', 'purchaseOrderCurrency.currency']);
    $totalHtml = PurchaseOrderResource::renderPurchaseOrderTotalAmountSummary($fresh);
    $itemsHtml = PurchaseOrderResource::renderPurchaseOrderItemsTotalSummary($fresh);

    expect($totalHtml)->toContain('Rp 8.879.400,00')
        ->and($totalHtml)->toContain('Perlu sync')
        ->and($itemsHtml)->toContain('USD 591,96')
        ->and($itemsHtml)->toContain('Rp 8.879.400,00')
        ->and($itemsHtml)->toContain('-&gt;');
});

test('generateInvoice creates invoice with correct totals and items', function () {
    PurchaseOrderBiaya::create([
        'purchase_order_id' => $this->purchaseOrder->id,
        'currency_id' => $this->currency->id,
        'nama_biaya' => 'Biaya Pengiriman',
        'total' => 2000,
        'masuk_invoice' => 1,
    ]);

    $data = [
        'invoice_number' => 'INV-UNIT-001',
        'invoice_date' => Carbon::now()->toDateString(),
        'tax' => 5000,
        'other_fee' => 2000,
        'due_date' => Carbon::now()->addDays(14)->toDateString(),
    ];

    $result = $this->service->generateInvoice($this->purchaseOrder->fresh('purchaseOrderItem'), $data);

    expect($result)->toBeTrue();

    $invoice = $this->purchaseOrder->invoice()->with('invoiceItem')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->invoice_number)->toBe('INV-UNIT-001')
        ->and((float) $invoice->subtotal)->toBe(35000.0)
        ->and((float) $invoice->tax)->toBe(5000.0)
        ->and((float) $invoice->total)->toBe(42000.0)
        ->and($invoice->status)->toBe('draft');

    expect($invoice->other_fee)->toBeArray()
        ->and($invoice->other_fee)->toHaveCount(1)
        ->and($invoice->other_fee[0]['name'])->toBe('Biaya Pengiriman')
        ->and((float) $invoice->other_fee[0]['amount'])->toBe(2000.0)
        ->and($invoice->other_fee_total)->toBe(2000);

    expect($invoice->invoiceItem)->toHaveCount(2);

    $lineTotals = $invoice->invoiceItem->pluck('total');
    expect((float) $lineTotals->sum())->toBe(35000.0);

    $firstLine = $invoice->invoiceItem->firstWhere('product_id', $this->productA->id);
    expect($firstLine)->not->toBeNull()
        ->and((int) $firstLine->quantity)->toBe(2)
        ->and((float) $firstLine->price)->toBe(10000.0);
});

test('order request approval clamps PO qty to remaining order request qty', function () {
    // Setup an order request with a single item (qty 10)
    $orderRequest = \App\Models\OrderRequest::create([
        'request_number' => 'OR-TEST-CLAMP',
        'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'request_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);

    $orderRequestItem = \App\Models\OrderRequestItem::create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->productA->id,
        'quantity' => 10,
    ]);

    $service = app(\App\Services\OrderRequestService::class);

    $orderRequest = $service->approve($orderRequest, [
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-OR-CLAMP-001',
        'order_date' => now()->toDateString(),
        'expected_date' => now()->addDays(7)->toDateString(),
        'note' => 'Testing clamp',
        'selected_items' => [
            [
                'item_id' => $orderRequestItem->id,
                'include' => true,
                'quantity' => 11,
                'unit_price' => 10000,
            ],
        ],
    ]);

    // Find the created PO and verify item quantity is clamped
    $purchaseOrder = $orderRequest->purchaseOrders()->first();
    expect($purchaseOrder)->not->toBeNull();

    $poItem = $purchaseOrder->purchaseOrderItem->first();
    expect((float) $poItem->quantity)->toBe(10.0);

    // OR should be marked approved by the service
    expect($orderRequest->fresh()->status)->toBe('approved');
});
