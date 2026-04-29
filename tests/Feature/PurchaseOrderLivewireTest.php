<?php

use App\Filament\Resources\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrderResource\Pages\ViewPurchaseOrder;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Currency;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\TaxSetting;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

if (! function_exists('registerAllPermissions')) {
    function registerAllPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (HelperController::listPermission() as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => sprintf('%s %s', $action, $resource),
                    'guard_name' => 'web',
                ]);
            }
        }

        foreach ([
            'request purchase order',
            'response purchase order',
        ] as $additionalPermission) {
            Permission::firstOrCreate([
                'name' => $additionalPermission,
                'guard_name' => 'web',
            ]);
        }
    }
}

function grantLivewirePurchaseOrderPermissions(User $user): void
{
    $permissions = [
        'view any purchase order',
        'view purchase order',
        'create purchase order',
        'update purchase order',
        'delete purchase order',
        'request purchase order',
        'response purchase order',
        'view any supplier',
        'view any warehouse',
        'view any product',
        'view any currency',
        'view any account payable',
        'view account payable',
        'create account payable',
        'update account payable',
        'delete account payable',
        'restore account payable',
        'force-delete account payable',
        'view any account receivable',
        'view account receivable',
        'create account receivable',
        'update account receivable',
        'delete account receivable',
        'restore account receivable',
        'force-delete account receivable',
        'view any ageing schedule',
    ];

    registerAllPermissions();

    $user->givePermissionTo($permissions);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    grantLivewirePurchaseOrderPermissions($this->user);
    $this->actingAs($this->user);

    UnitOfMeasure::factory()->create();
    $this->currency = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);
    $this->supplier = Supplier::factory()->create([
        'tempo_hutang' => 30,
    ]);
    $this->cabang = Cabang::factory()->create();
    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'status' => 1,
    ]);
    $this->product = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'cost_price' => 12500,
        'sell_price' => 19000,
    ]);
    // Attach supplier ke product via pivot agar supplier_price resolve dengan benar
    $this->product->suppliers()->attach($this->supplier->id, ['supplier_price' => 12500]);
});

test('purchase order livewire form auto-fills tempo hutang from supplier selection', function () {
    Livewire::actingAs($this->user)
        ->test(CreatePurchaseOrder::class)
        ->set('data.supplier_id', $this->supplier->id)
        ->assertSet('data.tempo_hutang', $this->supplier->tempo_hutang);
});

test('purchase order edit and view pages show supplier code and name', function () {
    $supplier = Supplier::factory()->create([
        'code' => 'SUP-PO-001',
        'perusahaan' => 'PT Supplier Purchase Order',
        'tempo_hutang' => 30,
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-SUP-001',
        'order_date' => Carbon::now()->toDateString(),
        'status' => 'draft',
        'expected_date' => Carbon::now()->addDays(3)->toDateString(),
        'total_amount' => 0,
        'warehouse_id' => $this->warehouse->id,
        'tempo_hutang' => $supplier->tempo_hutang,
        'note' => 'PO supplier label test',
        'created_by' => $this->user->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(EditPurchaseOrder::class, ['record' => $purchaseOrder->id])
        ->assertFormExists()
        ->assertSee('(SUP-PO-001) PT Supplier Purchase Order');

    Livewire::actingAs($this->user)
        ->test(ViewPurchaseOrder::class, ['record' => $purchaseOrder->id])
        ->assertSee('(SUP-PO-001) PT Supplier Purchase Order');
});

test('purchase order can be created through livewire create page', function () {
    $orderDate = Carbon::now()->toDateString();
    $expectedDate = Carbon::now()->addDays(3)->toDateString();

    Livewire::actingAs($this->user)
        ->test(CreatePurchaseOrder::class)
        ->set('data.po_number', 'PO-LIVE-001')
        ->set('data.supplier_id', $this->supplier->id)
        ->set('data.order_date', $orderDate)
        ->set('data.expected_date', $expectedDate)
        ->set('data.warehouse_id', $this->warehouse->id)
        ->set('data.status', 'draft')
        ->set('data.is_asset', false)
        ->set('data.note', 'Pengujian Livewire Form')
        ->set('data.purchaseOrderItem', [
            [
                'product_id' => $this->product->id,
                'currency_id' => $this->currency->id,
                'quantity' => 2,
                'unit_price' => 12500,
                'discount' => 0,
                'tax' => 0,
                'subtotal' => 25000.0,
                'tipe_pajak' => 'Non Pajak',
            ],
        ])
        ->set('data.purchaseOrderCurrency', [
            [
                'currency_id' => $this->currency->id,
                'nominal' => 1.0, // IDR kurs terhadap IDR = 1
            ],
        ])
        ->set('data.purchaseOrderBiaya', [])
        ->set('data.total_amount', 25000.0) // 2 qty × 12500 unit price × 1 (IDR kurs)
        ->assertSet('data.tempo_hutang', $this->supplier->tempo_hutang)
        ->call('create')
        ->assertHasNoFormErrors();

    $created = PurchaseOrder::where('po_number', 'PO-LIVE-001')->with('purchaseOrderItem')->first();

    expect($created)->not->toBeNull()
        ->and($created->supplier_id)->toBe($this->supplier->id)
        ->and($created->warehouse_id)->toBe($this->warehouse->id)
        ->and((int) $created->tempo_hutang)->toBe($this->supplier->tempo_hutang)
        ->and((float) $created->total_amount)->toBe(25000.0)
        ->and($created->created_by)->toBe($this->user->id)
        ->and($created->status)->toBe('draft');

    expect($created->purchaseOrderItem)->toHaveCount(1);

    $line = $created->purchaseOrderItem->first();
    expect($line->product_id)->toBe($this->product->id)
        ->and((int) $line->quantity)->toBe(2)
        ->and((float) $line->unit_price)->toBe(12500.0)
        ->and($line->currency_id)->toBe($this->currency->id);
});

test('purchase order subtotal and total amount stay formatted after reactive updates and hydration', function () {
    $orderDate = Carbon::now()->toDateString();
    $expectedDate = Carbon::now()->addDays(3)->toDateString();

    $createComponent = Livewire::actingAs($this->user)
        ->test(CreatePurchaseOrder::class)
        ->set('data.po_number', 'PO-LIVE-FORMAT-001')
        ->set('data.supplier_id', $this->supplier->id)
        ->set('data.order_date', $orderDate)
        ->set('data.expected_date', $expectedDate)
        ->set('data.warehouse_id', $this->warehouse->id)
        ->set('data.status', 'draft')
        ->set('data.is_asset', false)
        ->set('data.purchaseOrderItem', [
            [
                'product_id' => $this->product->id,
                'currency_id' => $this->currency->id,
                'quantity' => 2,
                'unit_price' => 12500,
                'discount' => 0,
                'tax' => 0,
                'subtotal' => 25000,
                'tipe_pajak' => 'Non Pajak',
            ],
        ])
        ->set('data.purchaseOrderCurrency', [
            [
                'currency_id' => $this->currency->id,
                'nominal' => 1.0,
            ],
        ])
        ->set('data.purchaseOrderBiaya', [])
        ->set('data.purchaseOrderItem.0.quantity', 3);

    $createComponent
        ->set('data.total_amount', '37.500')
        ->call('create')
        ->assertHasNoFormErrors();

    $purchaseOrder = PurchaseOrder::where('po_number', 'PO-LIVE-FORMAT-001')->with(['purchaseOrderItem', 'purchaseOrderCurrency'])->first();

    expect($purchaseOrder)->not->toBeNull();

    Livewire::actingAs($this->user)
        ->test(EditPurchaseOrder::class, ['record' => $purchaseOrder->id])
        ->assertFormExists()
        ->assertFormSet([
            'total_amount' => '37.500',
        ]);
});

test('purchase order total amount includes formatted other fee values correctly', function () {
    $orderDate = Carbon::now()->toDateString();
    $expectedDate = Carbon::now()->addDays(3)->toDateString();
    $expenseCoa = ChartOfAccount::factory()->create([
        'code' => '6000.99',
        'name' => 'Biaya Pengiriman Test',
        'type' => 'Expense',
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(CreatePurchaseOrder::class)
        ->set('data.po_number', 'PO-LIVE-FEE-001')
        ->set('data.supplier_id', $this->supplier->id)
        ->set('data.order_date', $orderDate)
        ->set('data.expected_date', $expectedDate)
        ->set('data.warehouse_id', $this->warehouse->id)
        ->set('data.status', 'draft')
        ->set('data.is_asset', false)
        ->set('data.purchaseOrderItem', [
            [
                'product_id' => $this->product->id,
                'currency_id' => $this->currency->id,
                'quantity' => 2,
                'unit_price' => 12500,
                'discount' => 0,
                'tax' => 0,
                'subtotal' => '25.000',
                'tipe_pajak' => 'Non Pajak',
            ],
        ])
        ->set('data.purchaseOrderCurrency', [
            [
                'currency_id' => $this->currency->id,
                'nominal' => 1.0,
            ],
        ])
        ->set('data.purchaseOrderBiaya', [[
            'nama_biaya' => 'Biaya Pengiriman',
            'currency_id' => $this->currency->id,
            'coa_id' => $expenseCoa->id,
            'total' => '0',
            'masuk_invoice' => false,
            'untuk_pembelian' => 0,
        ]])
        ->set('data.purchaseOrderBiaya.0.total', '100.000');

    $component
        ->assertSet('data.total_amount', '125.000')
        ->call('create')
        ->assertHasNoFormErrors();

    $purchaseOrder = PurchaseOrder::where('po_number', 'PO-LIVE-FEE-001')->first();

    expect($purchaseOrder)->not->toBeNull()
        ->and((float) $purchaseOrder->total_amount)->toBe(125000.0);
});

test('purchase order item tax auto-fills from active setting when tipe pajak changes', function () {
    TaxSetting::factory()->ppn()->create([
        'effective_date' => now()->subDay()->toDateString(),
        'status' => true,
    ]);

    Livewire::actingAs($this->user)
        ->test(CreatePurchaseOrder::class)
        ->set('data.supplier_id', $this->supplier->id)
        ->set('data.order_date', Carbon::now()->toDateString())
        ->set('data.expected_date', Carbon::now()->addDays(3)->toDateString())
        ->set('data.warehouse_id', $this->warehouse->id)
        ->set('data.status', 'draft')
        ->set('data.is_asset', false)
        ->set('data.purchaseOrderItem', [
            [
                'product_id' => $this->product->id,
                'currency_id' => $this->currency->id,
                'quantity' => 1,
                'unit_price' => 12500,
                'discount' => 0,
                'tax' => 0,
                'tipe_pajak' => 'Inklusif',
            ],
        ])
        ->set('data.purchaseOrderItem.0.tipe_pajak', 'Eklusif')
        ->assertSet('data.purchaseOrderItem.0.tax', 11)
        ->set('data.purchaseOrderItem.0.tax', 7)
        ->assertSet('data.purchaseOrderItem.0.tax', 7)
        ->set('data.purchaseOrderItem.0.tipe_pajak', 'Non Pajak')
        ->assertSet('data.purchaseOrderItem.0.tax', 0)
        ->set('data.purchaseOrderItem.0.tipe_pajak', 'Inklusif')
        ->assertSet('data.purchaseOrderItem.0.tax', 11);
});

test('scenario 1: supplier non-linked tetap tersedia pada opsi supplier untuk produk terpilih', function () {
    $nonLinkedSupplier = Supplier::factory()->create([
        'code' => 'SUP-NL-01',
        'perusahaan' => 'PT Non Linked',
    ]);

    $options = PurchaseOrderResource::resolveSupplierSearchOptions([$this->product->id], '', null, 50);

    expect($options)->toHaveKey($this->supplier->id)
        ->and($options)->toHaveKey($nonLinkedSupplier->id);
});

test('scenario 2: unit_price otomatis 0 saat supplier tidak terhubung ke product', function () {
    $nonLinkedSupplier = Supplier::factory()->create([
        'tempo_hutang' => 21,
    ]);

    Livewire::actingAs($this->user)
        ->test(CreatePurchaseOrder::class)
        ->set('data.supplier_id', $nonLinkedSupplier->id)
        ->set('data.purchaseOrderItem', [[
            'currency_id' => $this->currency->id,
            'quantity' => 1,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Non Pajak',
        ]])
        ->set('data.purchaseOrderItem.0.product_id', $this->product->id)
        ->assertSet('data.purchaseOrderItem.0.unit_price', fn ($value) => (float) \App\Helpers\MoneyHelper::parse($value) === 0.0);
});

test('scenario 3: supplier linked muncul paling atas dan label memuat harga', function () {
    $nonLinkedSupplier = Supplier::factory()->create([
        'code' => 'SUP-NL-02',
        'perusahaan' => 'ZZ Non Linked',
    ]);

    $options = PurchaseOrderResource::resolveSupplierSearchOptions([$this->product->id], '', null, 50);

    $orderedSupplierIds = array_keys($options);
    $linkedLabel = $options[$this->supplier->id] ?? '';
    $nonLinkedLabel = $options[$nonLinkedSupplier->id] ?? '';

    expect($orderedSupplierIds[0] ?? null)->toBe($this->supplier->id)
        ->and($linkedLabel)->toContain('Rp 12.500')
        ->and($nonLinkedLabel)->not->toContain('Rp ');
});
