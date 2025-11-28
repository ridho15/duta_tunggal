<?php

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
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

test('generateInvoice creates invoice with correct totals and items', function () {
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

    expect($invoice->invoiceItem)->toHaveCount(2);

    $lineTotals = $invoice->invoiceItem->pluck('total');
    expect((float) $lineTotals->sum())->toBe(35000.0);

    $firstLine = $invoice->invoiceItem->firstWhere('product_id', $this->productA->id);
    expect($firstLine)->not->toBeNull()
        ->and((int) $firstLine->quantity)->toBe(2)
        ->and((float) $firstLine->price)->toBe(10000.0);
});
