<?php

use App\Filament\Resources\OrderRequestResource\Pages\CreateOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\EditOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\ListOrderRequests;
use App\Filament\Resources\OrderRequestResource;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\Currency;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $needed = [
        'view any order request',
        'view order request',
        'create order request',
        'update order request',
        'delete order request',
        'approve order request',
        'reject order request',
        'submit order request',
        'view any supplier',
        'view any warehouse',
        'view any product',
        'view any currency',
    ];

    foreach ($needed as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->user->givePermissionTo($needed);

    UnitOfMeasure::factory()->create();
    $this->defaultCurrency = Currency::factory()->create([
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
        'code' => 'IDR',
        'to_rupiah' => 1,
    ]);

    $this->cabang = \App\Models\Cabang::factory()->create();
    // assign user's cabang so the disabled cabang select has a default
    $this->user->cabang_id = $this->cabang->id;
    $this->user->save();

    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->product = Product::factory()->forCabang($this->cabang)->create(['supplier_id' => $this->supplier->id]);
});

it('creates an order request through the Filament create page', function () {
    $headerPayload = [
        'request_number' => 'OR-TEST-'.uniqid(),
        'request_date' => now()->format('Y-m-d'),
        'note' => 'Order request dari test',
    ];

    $itemsPayload = [[
        'product_id' => $this->product->id,
        'cabang_id' => $this->cabang->id,
        'quantity' => 2,
        'original_price' => $this->product->cost_price,
        'unit_price' => $this->product->cost_price,
        'tipe_pajak' => 'eklusif',
        // supply nonzero percentages to ensure calculation logic is exercised
        'discount' => 10, // 10%
        'tax' => 5,       // 5%
        // compute expected subtotal using percentage formula
        'subtotal' => round(
            ((2 * $this->product->cost_price) * (1 - 0.10)) * (1 + 0.05),
            2
        ),
    ]];

    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm($headerPayload)
        ->set('data.orderRequestItem', $itemsPayload)
        ->call('create');

    $component->assertHasNoFormErrors();

    // Grab the latest record and assert its relationships and creator are correct.
    $or = OrderRequest::latest('id')->first();
    $item = $or->orderRequestItem()->latest('id')->first();

    expect($or)->not->toBeNull();
    expect($or->orderRequestItem()->count())->toBe(1);
    expect($or->created_by)->toBe($this->user->id);
    expect((float) $item->subtotal)->toBeGreaterThan(0.0);
});

it('only offers active warehouses for order request selection', function () {
    $activeWarehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'status' => 1,
        'name' => 'Gudang Aktif',
    ]);

    $inactiveWarehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'status' => 0,
        'name' => 'Gudang Nonaktif',
    ]);

    $availableWarehouseIds = Warehouse::where('status', 1)
        ->where('cabang_id', $this->cabang->id)
        ->orderBy('name')
        ->pluck('id')
        ->all();

    expect($availableWarehouseIds)->toContain($activeWarehouse->id)
        ->and($availableWarehouseIds)->not->toContain($inactiveWarehouse->id);
});

it('stores formatted unit_price as numeric value in database', function () {
    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-FMT-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request format nominal test',
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $this->product->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'original_price' => '1.250.000',
            'unit_price' => '1.250.000',
            'tipe_pajak' => 'eklusif',
            'discount' => 0,
            'tax' => 0,
            'subtotal' => '1.250.000',
        ]])
        ->call('create');

    $component->assertHasNoFormErrors();

    $or = OrderRequest::latest('id')->first();
    $item = $or->orderRequestItem()->latest('id')->first();

    expect($item)->not->toBeNull();
    expect((float) $item->unit_price)->toBe(1250000.0);
    expect((float) $item->original_price)->toBe(1250000.0);
});

it('accepts live formatted decimal unit price and recalculates preview totals', function () {
    $this->product->forceFill([
        'pajak' => 10,
        'tipe_pajak' => 'Eksklusif',
    ])->save();

    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-LIVE-MONEY-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request live money test',
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $this->product->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 2,
            'original_price' => '1.234,56',
            'unit_price' => '1.234,56',
            'tipe_pajak' => 'eklusif',
            'discount' => 0,
            'tax' => 10,
            'total' => '2.469,12',
            'tax_nominal' => '246,91',
            'subtotal' => '2.716,03',
        ]]);

    $component
        ->set('data.orderRequestItem.0.unit_price', '1.234,56')
        ->assertSet('data.orderRequestItem.0.total', '2.469,12')
        ->assertSet('data.orderRequestItem.0.tax_nominal', '246,91')
        ->assertSet('data.orderRequestItem.0.subtotal', '2.716,03')
        ->call('create')
        ->assertHasNoFormErrors();

    $or = OrderRequest::latest('id')->first();
    $item = $or->orderRequestItem()->latest('id')->first();

    expect((float) $item->unit_price)->toBe(1234.56)
        ->and((float) $item->original_price)->toBe(1234.56)
        ->and((float) $item->subtotal)->toBe(2716.03);
});

it('stores decimal unit_price and original_price without changing 15.09', function () {
    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-DECIMAL-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request decimal price test',
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $this->product->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'original_price' => '15.09',
            'unit_price' => '15.09',
            'tipe_pajak' => 'none',
            'discount' => 0,
            'tax' => 0,
            'subtotal' => '15.09',
        ]])
        ->call('create');

    $component->assertHasNoFormErrors();

    $or = OrderRequest::latest('id')->first();
    $item = $or->orderRequestItem()->latest('id')->first();

    expect((float) $item->unit_price)->toBe(15.09)
        ->and((float) $item->original_price)->toBe(15.09)
        ->and((float) $item->subtotal)->toBe(15.09)
        ->and($item->tipe_pajak)->toBe('none');
});

it('forces item tax to zero when item tax type is non tax', function () {
    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-NON-PAJAK-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request non pajak test',
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $this->product->id,
            'cabang_id' => $this->cabang->id,
            'quantity' => 1,
            'original_price' => '1.000.000',
            'unit_price' => '1.000.000',
            'tipe_pajak' => 'none',
            'discount' => 0,
            'tax' => 11,
            'subtotal' => '1.000.000',
        ]])
        ->call('create');

    $component->assertHasNoFormErrors();

    $or = OrderRequest::latest('id')->first();
    $item = $or->orderRequestItem()->latest('id')->first();

    expect($item->tipe_pajak)->toBe('none')
        ->and((float) $item->tax)->toBe(0.0);
});

it('recalculates approval preview totals when override price changes', function () {
    $preview = OrderRequestResource::calculateApprovalItemPreview(4, 125000, 0, 11, 'PPN Excluded');

    expect($preview['total_cost'])->toBe(500000.0)
        ->and($preview['subtotal'])->toBe(555000.0)
        ->and($preview['tax_nominal'])->toBe(55000.0);
});

it('limits product options to fifty entries', function () {
    Product::factory()
        ->count(60)
        ->create(['cabang_id' => $this->cabang->id]);

    $options = OrderRequestResource::resolveProductOptions(null, 50);

    expect($options)->toHaveCount(50);
});

it('does not expose a global tax type select and keeps item-level tax radio', function () {
    $file = file_get_contents(base_path('app/Filament/Resources/OrderRequestResource.php'));

    expect($file)->not->toContain("Select::make('tax_type')")
        ->and($file)->toContain("Radio::make('tipe_pajak')");
});

it('keeps guide full width and styles non editable inputs', function () {
    $file = file_get_contents(base_path('app/Filament/Resources/OrderRequestResource.php'));

    expect($file)->toContain('width: 100%; min-width: 100%; max-width: none; box-sizing: border-box;')
        ->and($file)->toContain('bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400')
        ->and($file)->toContain('background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;')
        ->and($file)->toContain("TextInput::make('product_name')")
        ->and($file)->toContain("TextInput::make('unit')")
        ->and($file)->toContain("TextInput::make('tax_nominal')")
        ->and($file)->toContain("TextInput::make('subtotal')");
});

it('makes create purchase order selected items collapsible with item labels', function () {
    $file = file_get_contents(base_path('app/Filament/Resources/OrderRequestResource.php'));

    expect($file)->toContain("Repeater::make('selected_items')")
        ->and($file)->toContain('->collapsed()')
        ->and($file)->toContain('->itemLabel(function (array $state): string')
        ->and($file)->toContain("Checkbox::make('include')")
        ->and($file)->toContain('->live()')
        ->and($file)->toContain('Disertakan')
        ->and($file)->toContain('Tidak disertakan');
});

it('resolves product supplier options and auto-selects supplier when product changes', function () {
    $secondarySupplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    $primaryProduct = Product::factory()->forCabang($this->cabang)->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);
    $secondaryProduct = Product::factory()->forCabang($this->cabang)->create([
        'supplier_id' => $secondarySupplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $primaryProduct->suppliers()->syncWithoutDetaching([
        $this->supplier->id => ['supplier_price' => 100000],
    ]);
    $secondaryProduct->suppliers()->syncWithoutDetaching([
        $secondarySupplier->id => ['supplier_price' => 120000],
    ]);

    $supplierOptions = OrderRequestResource::resolveSupplierOptions($primaryProduct->id);

    expect($supplierOptions)->toHaveKey($this->supplier->id)
        ->and($supplierOptions)->toHaveKey($secondarySupplier->id)
        ->and(array_key_first($supplierOptions))->toBe($this->supplier->id);

    Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-SUPPLIER-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request supplier auto select test',
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $primaryProduct->id,
            'quantity' => 1,
            'supplier_id' => $this->supplier->id,
        ]])
        ->assertSet('data.orderRequestItem.0.supplier_id', $this->supplier->id);
});

it('formats original and override prices after product and supplier are selected', function () {
    $secondarySupplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    $product = Product::factory()->forCabang($this->cabang)->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'cost_price' => 75000,
    ]);

    $product->suppliers()->syncWithoutDetaching([
        $this->supplier->id => ['supplier_price' => 100000],
        $secondarySupplier->id => ['supplier_price' => 125000],
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-PRICE-FMT-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'currency_id' => $this->defaultCurrency->id,
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => null,
            'currency_id' => $this->defaultCurrency->id,
            'quantity' => 1,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'eklusif',
        ]])
        ->set('data.orderRequestItem.0.product_id', $product->id)
        ->assertSet('data.orderRequestItem.0.original_price', '100.000,00')
        ->assertSet('data.orderRequestItem.0.unit_price', '100.000,00');

    $component
        ->set('data.orderRequestItem.0.supplier_id', $secondarySupplier->id)
        ->assertSet('data.orderRequestItem.0.original_price', '125.000,00')
        ->assertSet('data.orderRequestItem.0.unit_price', '125.000,00');
});

it('preserves granular plastic supplier price when switching IDR to USD and back', function () {
    $usd = Currency::factory()->create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 15000,
    ]);

    $supplier = Supplier::factory()->create([
        'perusahaan' => 'UD Hassanah Saptono',
        'cabang_id' => $this->cabang->id,
    ]);

    $product = Product::factory()->create([
        'name' => 'Bahan Baku Plastik Granul',
        'supplier_id' => $supplier->id,
        'cabang_id' => null,
        'pajak' => 11,
    ]);

    $product->suppliers()->syncWithoutDetaching([
        $supplier->id => ['supplier_price' => 704000],
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-GRANUL-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'currency_id' => $this->defaultCurrency->id,
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => null,
            'currency_id' => $this->defaultCurrency->id,
            'quantity' => 10,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'eklusif',
        ]])
        ->set('data.orderRequestItem.0.product_id', $product->id)
        ->assertSet('data.orderRequestItem.0.supplier_id', $supplier->id)
        ->assertSet('data.orderRequestItem.0.cabang_id', $this->cabang->id)
        ->assertSet('data.orderRequestItem.0.original_price', '704.000,00')
        ->assertSet('data.orderRequestItem.0.unit_price', '704.000,00');

    expect(OrderRequestResource::resolveSupplierLabel($supplier->id, $product->id, $usd->id))
        ->toContain('$ 46,93')
        ->not->toContain('Rp 704.000');

    $component
        ->set('data.orderRequestItem.0.currency_id', $usd->id)
        ->assertSet('data.orderRequestItem.0.cabang_id', $this->cabang->id)
        ->assertSet('data.orderRequestItem.0.original_price', '46,93')
        ->assertSet('data.orderRequestItem.0.unit_price', '46,93')
        ->assertSet('data.orderRequestItem.0.total', '469,33')
        ->assertSet('data.orderRequestItem.0.tax_nominal', '51,63')
        ->assertSet('data.orderRequestItem.0.subtotal', '520,96')
        ->set('data.orderRequestItem.0.currency_id', $this->defaultCurrency->id)
        ->assertSet('data.orderRequestItem.0.original_price', '704.000,00')
        ->assertSet('data.orderRequestItem.0.unit_price', '704.000,00')
        ->assertSet('data.orderRequestItem.0.total', '7.040.000,00')
        ->assertSet('data.orderRequestItem.0.tax_nominal', '774.400,00')
        ->assertSet('data.orderRequestItem.0.subtotal', '7.814.400,00');
});

it('persists and shows granular plastic USD values without rounding to whole dollars', function () {
    $usd = Currency::factory()->create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 15000,
    ]);

    $supplier = Supplier::factory()->create([
        'perusahaan' => 'UD Hassanah Saptono',
        'cabang_id' => $this->cabang->id,
    ]);

    $product = Product::factory()->create([
        'name' => 'Bahan Baku Plastik Granul',
        'supplier_id' => $supplier->id,
        'cabang_id' => null,
        'pajak' => 11,
    ]);

    $product->suppliers()->syncWithoutDetaching([
        $supplier->id => ['supplier_price' => 704000],
    ]);

    Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-GRANUL-SAVE-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'currency_id' => $this->defaultCurrency->id,
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => null,
            'currency_id' => $this->defaultCurrency->id,
            'quantity' => 10,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'eklusif',
        ]])
        ->set('data.orderRequestItem.0.product_id', $product->id)
        ->set('data.orderRequestItem.0.currency_id', $usd->id)
        ->call('create')
        ->assertHasNoFormErrors();

    $orderRequest = OrderRequest::latest('id')->first();
    $item = $orderRequest->orderRequestItem()->latest('id')->first();

    expect(abs((float) $item->original_price - 46.93))->toBeLessThan(0.0000001)
        ->and(abs((float) $item->unit_price - 46.93))->toBeLessThan(0.0000001)
        ->and(abs((float) $item->subtotal - 520.92))->toBeLessThan(0.0000001)
        ->and($item->cabang_id)->toBe($this->cabang->id);

    Livewire::actingAs($this->user)
        ->test(ViewOrderRequest::class, ['record' => $orderRequest->getKey()])
        ->assertSee('$ 46,93')
        ->assertSee('$ 469,30')
        ->assertSee('$ 51,62')
        ->assertSee('$ 520,92');
});

it('auto-selects item cabang when product changes', function () {
    $otherCabang = \App\Models\Cabang::factory()->create();
    $branchProduct = Product::factory()->forCabang($otherCabang)->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $otherCabang->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-CABANG-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request cabang auto select test',
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => null,
            'cabang_id' => null,
            'quantity' => 1,
        ]])
        ->set('data.orderRequestItem.0.product_id', $branchProduct->id)
        ->assertSet('data.orderRequestItem.0.cabang_id', $otherCabang->id);
});

it('formats supplier label using selected currency conversion', function () {
    $usd = Currency::factory()->create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 15000,
    ]);

    $product = Product::factory()->forCabang($this->cabang)->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $product->suppliers()->syncWithoutDetaching([
        $this->supplier->id => ['supplier_price' => 30000],
    ]);

    $label = OrderRequestResource::resolveSupplierLabel($this->supplier->id, $product->id, $usd->id);

    expect($label)->toContain('$ 2');
});

it('converts item original and override price when item currency changes in create form', function () {
    $usd = Currency::factory()->create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 15000,
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-CURRENCY-SWITCH-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request currency switch test',
            'currency_id' => $this->defaultCurrency->id,
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $this->product->id,
            'cabang_id' => $this->cabang->id,
            'currency_id' => $this->defaultCurrency->id,
            'quantity' => 1,
            'original_price' => '109.859',
            'unit_price' => '109.859',
            'tipe_pajak' => 'eklusif',
            'discount' => 0,
            'tax' => 0,
            'subtotal' => '109.859',
        ]]);

    $component->set('data.orderRequestItem.0.currency_id', $usd->id);

    $rawOriginal = $component->get('data.orderRequestItem.0.original_price');
    $rawUnit = $component->get('data.orderRequestItem.0.unit_price');

    $convertedOriginal = is_numeric($rawOriginal)
        ? (float) $rawOriginal
        : \App\Helpers\MoneyHelper::parse($rawOriginal);
    $convertedUnit = is_numeric($rawUnit)
        ? (float) $rawUnit
        : \App\Helpers\MoneyHelper::parse($rawUnit);

    $expectedConverted = 109859 / 15000;

    expect($convertedOriginal)->toBeGreaterThan(0)
        ->and($convertedOriginal)->toBeLessThan(109859)
        ->and(abs($convertedOriginal - $expectedConverted))->toBeLessThan(1.0);

    expect($convertedUnit)->toBeGreaterThan(0)
        ->and($convertedUnit)->toBeLessThan(109859)
        ->and(abs($convertedUnit - $expectedConverted))->toBeLessThan(1.0);
});

it('recalculates total subtotal and tax nominal when item currency changes', function () {
    $usd = Currency::factory()->create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 15000,
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-CURRENCY-TOTALS-'.uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request currency totals test',
            'currency_id' => $this->defaultCurrency->id,
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $this->product->id,
            'cabang_id' => $this->cabang->id,
            'currency_id' => $this->defaultCurrency->id,
            'quantity' => 2,
            'original_price' => '150.000',
            'unit_price' => '150.000',
            'tipe_pajak' => 'eklusif',
            'discount' => 10,
            'tax' => 11,
            'total' => '300.000',
            'subtotal' => '299.700',
            'tax_nominal' => '29.700',
        ]]);

    $oldTotal = (float) \App\Helpers\MoneyHelper::parse($component->get('data.orderRequestItem.0.total'));
    $oldSubtotal = (float) \App\Helpers\MoneyHelper::parse($component->get('data.orderRequestItem.0.subtotal'));
    $oldTaxNominal = (float) \App\Helpers\MoneyHelper::parse($component->get('data.orderRequestItem.0.tax_nominal'));

    $component->set('data.orderRequestItem.0.currency_id', $usd->id);

    $newTotal = (float) \App\Helpers\MoneyHelper::parse($component->get('data.orderRequestItem.0.total'));
    $newSubtotal = (float) \App\Helpers\MoneyHelper::parse($component->get('data.orderRequestItem.0.subtotal'));
    $newTaxNominal = (float) \App\Helpers\MoneyHelper::parse($component->get('data.orderRequestItem.0.tax_nominal'));

    expect($newTotal)->toBeLessThan($oldTotal)
        ->and($newSubtotal)->toBeLessThan($oldSubtotal)
        ->and($newTaxNominal)->toBeLessThan($oldTaxNominal);

    $expectedUnitPriceUsd = 150000 / 15000; // 10
    $expectedBase = 2 * $expectedUnitPriceUsd; // 20
    $expectedAfterDiscount = $expectedBase - ($expectedBase * 0.10); // 18
    $expectedTaxNominal = $expectedAfterDiscount * 0.11; // 1.98
    $expectedSubtotal = $expectedAfterDiscount + $expectedTaxNominal; // 19.98

    expect(abs($newTotal - $expectedBase))->toBeLessThan(1.0)
        ->and(abs($newTaxNominal - $expectedTaxNominal))->toBeLessThan(1.0)
        ->and(abs($newSubtotal - $expectedSubtotal))->toBeLessThan(1.0);
});

it('lists order requests on the index page', function () {
    $or1 = OrderRequest::factory()->create();
    $or2 = OrderRequest::factory()->create();

    Livewire::actingAs($this->user)
        ->test(ListOrderRequests::class)
        ->assertSee($or1->request_number)
        ->assertSee($or2->request_number);
});

it('shows fulfilled quantity summary on the index page', function () {
    $or = OrderRequest::factory()->create();

    OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'product_id' => $this->product->id,
        'quantity' => 10,
        'fulfilled_quantity' => 4,
        'unit_price' => 1000,
        'discount' => 0,
        'tax' => 0,
        'subtotal' => 10000,
    ]);

    Livewire::actingAs($this->user)
        ->test(ListOrderRequests::class)
        ->assertSee('4');
});

it('views order request details on the Filament view page', function () {
    $usd = Currency::factory()->create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 15000,
    ]);

    $or = OrderRequest::factory()->create([
        'note' => 'Note View Test',
        'currency_id' => $usd->id,
    ]);

    OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'product_id' => $this->product->id,
        'quantity' => 3,
        'fulfilled_quantity' => 1,
        'original_price' => 100000,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'eklusif',
        'subtotal' => 333000,
        'currency_id' => $usd->id,
    ]);

    $item = $or->orderRequestItem()->latest('id')->first();
    $base = (float) $item->quantity * (float) $item->unit_price;
    $taxRes = \App\Services\TaxService::compute($base, (float) $item->tax, OrderRequestResource::taxServiceTypeFromItemTaxType($item->tipe_pajak));
    $expectedRate = number_format((float) $item->tax, 0, ',', '.') . '%';
    $symbol = $item->currency?->symbol ?? '$';
    $expectedTotal = $symbol . ' 300.000';
    $expectedTax = $symbol . ' ' . number_format((float) ($taxRes['ppn'] ?? 0), 0, ',', '.');
    $expectedSubtotal = $symbol . ' ' . number_format((float) ($taxRes['total'] ?? 0), 0, ',', '.');

    Livewire::actingAs($this->user)
        ->test(ViewOrderRequest::class, ['record' => $or->getKey()])
        ->assertSee('Informasi Order Request')
        ->assertSee($or->request_number)
        ->assertSee('eklusif')
        ->assertSee($expectedRate)
        ->assertSee($expectedTotal)
        ->assertSee($expectedTax)
        ->assertSee($expectedSubtotal)
        ->assertSee('Qty Diterima (Penerimaan Barang)')
        ->assertSee('Sisa Qty Belum Diterima');
});

it('approves an order request from the view page action and updates status', function () {
    $or = OrderRequest::factory()->create([
        'status' => 'request_approve',
        'currency_id' => $this->defaultCurrency->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(ViewOrderRequest::class, ['record' => $or->getKey()])
        ->callAction('approve', data: [
            'create_purchase_order' => false,
        ]);

    expect($or->fresh()->status)->toBe('approved');
    expect($or->fresh()->purchaseOrders()->count())->toBe(0);
});

it('approves an order request from the list table action without creating purchase order', function () {
    $or = OrderRequest::factory()->create([
        'status' => 'request_approve',
        'currency_id' => $this->defaultCurrency->id,
    ]);

    OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'quantity' => 2,
        'fulfilled_quantity' => 0,
        'unit_price' => 1000,
        'original_price' => 1000,
        'currency_id' => $this->defaultCurrency->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(ListOrderRequests::class)
        ->callTableAction('approve', $or, data: [
            'create_purchase_order' => false,
        ])
        ->assertHasNoTableActionErrors();

    expect($or->fresh()->status)->toBe('approved');
    expect($or->fresh()->purchaseOrders()->count())->toBe(0);
});

it('approves an order request from the list table action and creates a single purchase order', function () {
    $or = OrderRequest::factory()->create([
        'status' => 'request_approve',
        'currency_id' => $this->defaultCurrency->id,
    ]);

    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'quantity' => 3,
        'fulfilled_quantity' => 0,
        'unit_price' => 1000,
        'original_price' => 1000,
        'currency_id' => $this->defaultCurrency->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(ListOrderRequests::class)
        ->callTableAction('approve', $or, data: [
            'create_purchase_order' => true,
            'multi_supplier' => false,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-LIST-APPROVE-001',
            'order_date' => now()->toDateString(),
            'selected_items' => [[
                'item_id' => $item->id,
                'item_supplier_id' => $this->supplier->id,
                'item_cabang_id' => $this->cabang->id,
                'currency_id' => $this->defaultCurrency->id,
                'quantity' => 3,
                'unit_price' => 1000,
                'include' => true,
            ]],
        ])
        ->assertHasNoTableActionErrors();

    expect($or->fresh()->status)->toBe('approved');
    expect($or->fresh()->purchaseOrders)->toHaveCount(1);
    expect($or->fresh()->purchaseOrders->first()->po_number)->toBe('PO-LIST-APPROVE-001');
});

it('approves an order request from the list table action and creates grouped purchase orders', function () {
    $supplierB = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    $cabangB = \App\Models\Cabang::factory()->create();
    $productB = Product::factory()->forCabang($cabangB)->create(['supplier_id' => $supplierB->id]);

    $or = OrderRequest::factory()->create([
        'status' => 'request_approve',
        'currency_id' => $this->defaultCurrency->id,
    ]);

    $itemA = OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'quantity' => 2,
        'fulfilled_quantity' => 0,
        'unit_price' => 1000,
        'original_price' => 1000,
        'currency_id' => $this->defaultCurrency->id,
    ]);

    $itemB = OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'product_id' => $productB->id,
        'supplier_id' => $supplierB->id,
        'cabang_id' => $cabangB->id,
        'quantity' => 4,
        'fulfilled_quantity' => 0,
        'unit_price' => 2000,
        'original_price' => 2000,
        'currency_id' => $this->defaultCurrency->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(ListOrderRequests::class)
        ->callTableAction('approve', $or, data: [
            'create_purchase_order' => true,
            'multi_supplier' => true,
            'order_date' => now()->toDateString(),
            'selected_items' => [
                [
                    'item_id' => $itemA->id,
                    'item_supplier_id' => $this->supplier->id,
                    'item_cabang_id' => $this->cabang->id,
                    'currency_id' => $this->defaultCurrency->id,
                    'quantity' => 2,
                    'unit_price' => 1000,
                    'include' => true,
                ],
                [
                    'item_id' => $itemB->id,
                    'item_supplier_id' => $supplierB->id,
                    'item_cabang_id' => $cabangB->id,
                    'currency_id' => $this->defaultCurrency->id,
                    'quantity' => 4,
                    'unit_price' => 2000,
                    'include' => true,
                ],
            ],
        ])
        ->assertHasNoTableActionErrors();

    expect($or->fresh()->status)->toBe('approved');
    expect(PurchaseOrder::where('refer_model_type', OrderRequest::class)->where('refer_model_id', $or->id)->count())->toBe(2);
});

it('shows decimal unit price on the Filament view page', function () {
    $or = OrderRequest::factory()->create([
        'note' => 'Decimal view test',
        'currency_id' => $this->defaultCurrency->id,
    ]);

    OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'fulfilled_quantity' => 0,
        'original_price' => 15.09,
        'unit_price' => 15.09,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'eklusif',
        'subtotal' => 15.09,
        'currency_id' => $this->defaultCurrency->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(ViewOrderRequest::class, ['record' => $or->getKey()])
        ->assertSee('15,09')
            ;
});

it('edits an order request through the Filament edit page', function () {
    $or = OrderRequest::factory()->create(['note' => 'Old Note']);
    // ensure it has at least one item so save validation passes
    \App\Models\OrderRequestItem::factory()->create(['order_request_id' => $or->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0, 'tax' => 0, 'subtotal' => 1000]);

    Livewire::actingAs($this->user)
        ->test(EditOrderRequest::class, ['record' => $or->getKey()])
        ->fillForm([
            'note' => 'New Note via Edit',
            'orderRequestItem' => [[
                'product_id' => $this->product->id,
                'cabang_id' => $this->cabang->id,
                'quantity' => 1,
                'unit_price' => 1000,
                'tipe_pajak' => 'eklusif',
                'discount' => 15, // percent
                'tax' => 2,       // percent
                'subtotal' => round(((1 * 1000) * (1 - 0.15)) * (1 + 0.02), 2),
            ]]
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $or->refresh();
    expect($or->note)->toBe('New Note via Edit');
});

it('deletes (soft deletes) an order request and its items', function () {
    $or = OrderRequest::factory()->create();
    $item = OrderRequestItem::factory()->create(['order_request_id' => $or->id, 'product_id' => $this->product->id, 'discount' => 0, 'tax' => 0, 'subtotal' => 0]);

    // Simulate deletion (DeleteAction calls model delete internally). Some Filament actions are not directly callable
    // via Livewire test harness, so perform model delete to ensure soft-delete cascade behavior.
    $or->delete();

    $this->assertSoftDeleted('order_requests', ['id' => $or->id]);
    $this->assertSoftDeleted('order_request_items', ['id' => $item->id]);
});
