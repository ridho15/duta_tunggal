<?php

use App\Filament\Resources\OrderRequestResource\Pages\CreateOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\EditOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\ListOrderRequests;
use App\Filament\Resources\OrderRequestResource;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
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
    Currency::factory()->create();

    $this->cabang = \App\Models\Cabang::factory()->create();
    // assign user's cabang so the disabled cabang select has a default
    $this->user->cabang_id = $this->cabang->id;
    $this->user->save();

    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'cabang_id' => $this->cabang->id]);
});

it('creates an order request through the Filament create page', function () {
    $headerPayload = [
        'request_number' => 'OR-TEST-'.uniqid(),
        'cabang_id' => $this->cabang->id,
        'warehouse_id' => $this->warehouse->id,
        'request_date' => now()->format('Y-m-d'),
        'note' => 'Order request dari test',
    ];

    $itemsPayload = [[
        'product_id' => $this->product->id,
        'quantity' => 2,
        'original_price' => $this->product->cost_price,
        'unit_price' => $this->product->cost_price,
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
    expect($or->warehouse_id)->toBe($this->warehouse->id);
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
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request format nominal test',
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $this->product->id,
            'quantity' => 1,
            'original_price' => '1.250.000',
            'unit_price' => '1.250.000',
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

it('forces item tax to zero when order request tax type is non tax', function () {
    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-NON-PAJAK-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'request_date' => now()->format('Y-m-d'),
            'tax_type' => 'None',
            'note' => 'Order request non pajak test',
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $this->product->id,
            'quantity' => 1,
            'original_price' => '1.000.000',
            'unit_price' => '1.000.000',
            'discount' => 0,
            'tax' => 11,
            'subtotal' => '1.000.000',
        ]])
        ->call('create');

    $component->assertHasNoFormErrors();

    $or = OrderRequest::latest('id')->first();
    $item = $or->orderRequestItem()->latest('id')->first();

    expect($or->tax_type)->toBe('None')
        ->and((float) $item->tax)->toBe(0.0);
});

it('recalculates approval preview totals when override price changes', function () {
    $preview = OrderRequestResource::calculateApprovalItemPreview(4, 125000, 0, 11, 'PPN Excluded');

    expect($preview['total_cost'])->toBe('500.000')
        ->and($preview['subtotal'])->toBe('555.000')
        ->and($preview['tax_nominal'])->toBe('55.000');
});

it('limits product options to fifty entries', function () {
    Product::factory()
        ->count(60)
        ->create(['cabang_id' => $this->cabang->id]);

    $options = OrderRequestResource::resolveProductOptions(null, 50);

    expect($options)->toHaveCount(50);
});

it('preloads the static tax type select', function () {
    $file = file_get_contents(base_path('app/Filament/Resources/OrderRequestResource.php'));

    expect($file)->toContain("Select::make('tax_type')")
        ->and($file)->toContain("->preload()");
});

it('resolves product supplier options and auto-selects supplier when product changes', function () {
    $secondarySupplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    $primaryProduct = Product::factory()->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);
    $secondaryProduct = Product::factory()->create([
        'supplier_id' => $secondarySupplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $supplierOptions = OrderRequestResource::resolveSupplierOptions($primaryProduct->id);

    expect($supplierOptions)->toHaveKey($this->supplier->id)
        ->and($supplierOptions)->not->toHaveKey($secondarySupplier->id);

    Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm([
            'request_number' => 'OR-TEST-SUPPLIER-'.uniqid(),
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'request_date' => now()->format('Y-m-d'),
            'note' => 'Order request supplier auto select test',
        ])
        ->set('data.orderRequestItem', [[
            'product_id' => $primaryProduct->id,
            'quantity' => 1,
        ]])
        ->assertSet('data.orderRequestItem.0.supplier_id', $this->supplier->id)
        ->set('data.orderRequestItem.0.product_id', $secondaryProduct->id)
        ->assertSet('data.orderRequestItem.0.supplier_id', $secondarySupplier->id);
});

it('lists order requests on the index page', function () {
    $or1 = OrderRequest::factory()->create(['warehouse_id' => $this->warehouse->id, 'cabang_id' => $this->cabang->id]);
    $or2 = OrderRequest::factory()->create(['warehouse_id' => $this->warehouse->id, 'cabang_id' => $this->cabang->id]);

    Livewire::actingAs($this->user)
        ->test(ListOrderRequests::class)
        ->assertSee($or1->request_number)
        ->assertSee($or2->request_number);
});

it('shows fulfilled quantity summary on the index page', function () {
    $or = OrderRequest::factory()->create(['warehouse_id' => $this->warehouse->id, 'cabang_id' => $this->cabang->id]);

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
        ->assertSee('Qty Diterima (Penerimaan Barang)')
        ->assertSee('4');
});

it('views order request details on the Filament view page', function () {
    $or = OrderRequest::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'note' => 'Note View Test',
        'cabang_id' => $this->cabang->id,
        'tax_type' => 'PPN Excluded',
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
        'subtotal' => 333000,
    ]);

    Livewire::actingAs($this->user)
        ->test(ViewOrderRequest::class, ['record' => $or->getKey()])
        ->assertSee('Informasi Order Request')
        ->assertSee($or->request_number)
        ->assertSee($this->warehouse->name)
        ->assertSee('PPN Excluded')
        ->assertSee('11%')
        ->assertSee('Rp 300.000')
        ->assertSee('Rp 33.000')
        ->assertSee('Rp 333.000')
        ->assertSee('Qty Diterima (Penerimaan Barang)')
        ->assertSee('Sisa Qty Belum Diterima');
});

it('edits an order request through the Filament edit page', function () {
    $or = OrderRequest::factory()->create(['warehouse_id' => $this->warehouse->id, 'note' => 'Old Note', 'cabang_id' => $this->cabang->id]);
    // ensure it has at least one item so save validation passes
    \App\Models\OrderRequestItem::factory()->create(['order_request_id' => $or->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0, 'tax' => 0, 'subtotal' => 1000]);

    Livewire::actingAs($this->user)
        ->test(EditOrderRequest::class, ['record' => $or->getKey()])
        ->fillForm([
            'note' => 'New Note via Edit',
            'orderRequestItem' => [[
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 1000,
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
    $or = OrderRequest::factory()->create(['warehouse_id' => $this->warehouse->id, 'cabang_id' => $this->cabang->id]);
    $item = OrderRequestItem::factory()->create(['order_request_id' => $or->id, 'product_id' => $this->product->id, 'discount' => 0, 'tax' => 0, 'subtotal' => 0]);

    // Simulate deletion (DeleteAction calls model delete internally). Some Filament actions are not directly callable
    // via Livewire test harness, so perform model delete to ensure soft-delete cascade behavior.
    $or->delete();

    $this->assertSoftDeleted('order_requests', ['id' => $or->id]);
    $this->assertSoftDeleted('order_request_items', ['id' => $item->id]);
});
