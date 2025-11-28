<?php

use App\Filament\Resources\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderBiaya;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create test user
    $this->user = User::factory()->create();

    // Create test data
    $this->cabang = Cabang::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->currency = Currency::factory()->create(['code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $this->category = ProductCategory::factory()->create(['cabang_id' => $this->cabang->id]);

    // Create product with COA
    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'supplier_id' => $this->supplier->id,
        'product_category_id' => $this->category->id,
        'inventory_coa_id' => ChartOfAccount::factory()->create(['code' => '1140.01'])->id,
        'sales_coa_id' => ChartOfAccount::factory()->create(['code' => '4100.01'])->id,
        'temporary_procurement_coa_id' => ChartOfAccount::factory()->create(['code' => '1400.01'])->id,
        'unbilled_purchase_coa_id' => ChartOfAccount::factory()->create(['code' => '2190.10'])->id,
    ]);

    // Create COA for biaya
    $this->expenseCoa = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6000.01']);
});

it('validates purchase order creation data structure', function () {
    $this->actingAs($this->user);

    $formData = [
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-TEST-' . now()->format('YmdHis'),
        'order_date' => now()->format('Y-m-d'),
        'expected_date' => now()->addDays(7)->format('Y-m-d'),
        'warehouse_id' => $this->warehouse->id,
        'tempo_hutang' => 30,
        'note' => 'Test purchase order from unit test',
        'is_asset' => false,
        'purchaseOrderItem' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 10,
                'unit_price' => 15000,
                'currency_id' => $this->currency->id,
                'discount' => 0,
                'tax' => 0,
                'tipe_pajak' => 'Inklusif',
                'subtotal' => 150000,
            ],
        ],
        'purchaseOrderCurrency' => [
            [
                'currency_id' => $this->currency->id,
                'nominal' => 150000,
            ],
        ],
        'purchaseOrderBiaya' => [
            [
                'nama_biaya' => 'Biaya Pengiriman',
                'currency_id' => $this->currency->id,
                'total' => 50000,
                'coa_id' => $this->expenseCoa->id,
            ],
        ],
    ];

    // Test that data structure is valid by creating purchase order
    $purchaseOrderData = $formData;
    unset($purchaseOrderData['purchaseOrderItem'], $purchaseOrderData['purchaseOrderCurrency'], $purchaseOrderData['purchaseOrderBiaya']);

    $purchaseOrderData['created_by'] = $this->user->id;
    $purchaseOrderData['status'] = 'draft';

    $purchaseOrder = PurchaseOrder::create($purchaseOrderData);

    expect($purchaseOrder)
        ->toHaveKey('supplier_id', $this->supplier->id)
        ->toHaveKey('po_number', $formData['po_number'])
        ->toHaveKey('created_by', $this->user->id)
        ->toHaveKey('status', 'draft');
});

it('creates purchase order with complete data through service', function () {
    $this->actingAs($this->user);

    $purchaseOrderData = [
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-SERVICE-TEST-' . now()->format('YmdHis'),
        'order_date' => now()->format('Y-m-d'),
        'expected_date' => now()->addDays(7)->format('Y-m-d'),
        'warehouse_id' => $this->warehouse->id,
        'tempo_hutang' => 30,
        'note' => 'Test purchase order through service',
        'is_asset' => false,
        'created_by' => $this->user->id,
        'status' => 'draft',
    ];

    $itemData = [
        [
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 20000,
            'currency_id' => $this->currency->id,
            'discount' => 5, // 5% discount instead of 1000
            'tax' => 10,
            'tipe_pajak' => 'Eklusif',
        ],
    ];

    $currencyData = [
        [
            'currency_id' => $this->currency->id,
            'nominal' => 95000,
        ],
    ];

    $biayaData = [
        [
            'nama_biaya' => 'Biaya Transport',
            'currency_id' => $this->currency->id,
            'total' => 25000,
            'coa_id' => $this->expenseCoa->id,
        ],
    ];

    // Create purchase order
    $purchaseOrder = PurchaseOrder::create($purchaseOrderData);

    // Create related data
    foreach ($itemData as $item) {
        $purchaseOrder->purchaseOrderItem()->create($item);
    }

    foreach ($currencyData as $currency) {
        $purchaseOrder->purchaseOrderCurrency()->create($currency);
    }

    foreach ($biayaData as $biaya) {
        $purchaseOrder->purchaseOrderBiaya()->create($biaya);
    }

    // Refresh and load relationships
    $purchaseOrder->refresh();
    $purchaseOrder->load('purchaseOrderItem', 'purchaseOrderCurrency', 'purchaseOrderBiaya');

    // Test that data was created correctly
    expect($purchaseOrder)
        ->not->toBeNull()
        ->and($purchaseOrder->supplier_id)->toBe($this->supplier->id)
        ->and($purchaseOrder->po_number)->toBe($purchaseOrderData['po_number']);

    expect($purchaseOrder->purchaseOrderItem)->toHaveCount(1);
    expect($purchaseOrder->purchaseOrderCurrency)->toHaveCount(1);
    expect($purchaseOrder->purchaseOrderBiaya)->toHaveCount(1);

    // Test total amount calculation
    $service = app(PurchaseOrderService::class);
    $service->updateTotalAmount($purchaseOrder);
    $purchaseOrder->refresh();

    // Expected total: item subtotal (104500) + biaya (25000) = 129500
    expect($purchaseOrder->total_amount)->toBe('129500.00');
});

it('validates required fields for purchase order', function () {
    $this->actingAs($this->user);

    // Test missing required fields
    try {
        PurchaseOrder::create([]);
        $this->fail('Expected validation exception for missing required fields');
    } catch (\Illuminate\Database\QueryException $e) {
        expect($e->getMessage())->toContain('supplier_id');
    }
});

it('handles currency relationships correctly', function () {
    $this->actingAs($this->user);

    // Create purchase order with currencies
    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
    ]);

    // Create currencies
    $purchaseOrder->purchaseOrderCurrency()->create([
        'currency_id' => $this->currency->id,
        'nominal' => 100000,
    ]);

    $purchaseOrder->load('purchaseOrderCurrency.currency');

    expect($purchaseOrder->purchaseOrderCurrency)->toHaveCount(1);
    expect($purchaseOrder->purchaseOrderCurrency->first()->currency->code)->toBe('IDR');
    expect($purchaseOrder->purchaseOrderCurrency->first()->nominal)->toBe('100000.00');
});

it('handles biaya relationships correctly', function () {
    $this->actingAs($this->user);

    // Create purchase order with biaya
    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
    ]);

    // Create biaya
    $purchaseOrder->purchaseOrderBiaya()->create([
        'nama_biaya' => 'Biaya Testing',
        'currency_id' => $this->currency->id,
        'total' => 75000,
        'coa_id' => $this->expenseCoa->id,
    ]);

    $purchaseOrder->load('purchaseOrderBiaya.currency', 'purchaseOrderBiaya.coa');

    expect($purchaseOrder->purchaseOrderBiaya)->toHaveCount(1);

    $biaya = $purchaseOrder->purchaseOrderBiaya->first();
    expect($biaya->nama_biaya)->toBe('Biaya Testing')
        ->and($biaya->total)->toBe('75000.00')
        ->and($biaya->currency->code)->toBe('IDR')
        ->and($biaya->coa->code)->toBe('6000.01');
});

it('calculates total amount correctly with items and biaya', function () {
    $this->actingAs($this->user);

    // Create purchase order without triggering observer
    $purchaseOrder = PurchaseOrder::withoutEvents(function () {
        return PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-CALC-TEST-' . now()->format('YmdHis'),
            'order_date' => now()->format('Y-m-d'),
            'expected_date' => now()->addDays(7)->format('Y-m-d'),
            'warehouse_id' => $this->warehouse->id,
            'tempo_hutang' => 30,
            'note' => 'Test calculation',
            'is_asset' => false,
            'created_by' => $this->user->id,
            'status' => 'draft',
            'total_amount' => 0,
        ]);
    });

    // Create item: 5 * 20000 - 5% discount + 10% tax (eklusif) = 104500
    $purchaseOrder->purchaseOrderItem()->create([
        'product_id' => $this->product->id,
        'quantity' => 5,
        'unit_price' => 20000,
        'currency_id' => $this->currency->id,
        'discount' => 5, // 5% discount
        'tax' => 10,
        'tipe_pajak' => 'Eklusif',
    ]);

    // Create biaya: 25000
    $purchaseOrder->purchaseOrderBiaya()->create([
        'nama_biaya' => 'Biaya Transport',
        'currency_id' => $this->currency->id,
        'total' => 25000,
        'coa_id' => $this->expenseCoa->id,
    ]);

    // Load relationships
    $purchaseOrder->load('purchaseOrderItem', 'purchaseOrderBiaya');

    // Test calculation
    $service = app(PurchaseOrderService::class);
    $service->updateTotalAmount($purchaseOrder);
    $purchaseOrder->refresh();

    // Expected: 104500 (item after 5% discount and 10% tax) + 25000 (biaya) = 129500
    expect($purchaseOrder->total_amount)->toBe('129500.00');
});