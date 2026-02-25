<?php

use App\Filament\Resources\OrderRequestResource\Pages\CreateOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\EditOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\ListOrderRequests;
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
    $payload = [
        'request_number' => 'OR-TEST-'.uniqid(),
        'cabang_id' => $this->cabang->id,
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'request_date' => now()->format('Y-m-d'),
        'note' => 'Order request dari test',
        'orderRequestItem' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'unit_price' => $this->product->cost_price,
                'discount' => 0,
                'tax' => 0,
                'subtotal' => 2 * $this->product->cost_price,
            ],
        ],
    ];

    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->fillForm($payload)
        ->call('create');

    $component->assertHasNoFormErrors();

    // Grab the latest record and assert its relationships and creator are correct.
    $or = OrderRequest::latest('id')->first();

    expect($or)->not->toBeNull();
    expect($or->supplier_id)->toBe($this->supplier->id);
    expect($or->warehouse_id)->toBe($this->warehouse->id);
    expect($or->orderRequestItem()->count())->toBe(1);
    expect($or->created_by)->toBe($this->user->id);
});

it('lists order requests on the index page', function () {
    $or1 = OrderRequest::factory()->create(['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'cabang_id' => $this->cabang->id]);
    $or2 = OrderRequest::factory()->create(['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'cabang_id' => $this->cabang->id]);

    Livewire::actingAs($this->user)
        ->test(ListOrderRequests::class)
        ->assertSee($or1->request_number)
        ->assertSee($or2->request_number);
});

it('views order request details on the Filament view page', function () {
    $or = OrderRequest::factory()->create(['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'note' => 'Note View Test', 'cabang_id' => $this->cabang->id]);

    Livewire::actingAs($this->user)
        ->test(ViewOrderRequest::class, ['record' => $or->getKey()])
        ->assertFormExists()
        ->assertFormSet([
            'note' => 'Note View Test',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
});

it('edits an order request through the Filament edit page', function () {
    $or = OrderRequest::factory()->create(['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'note' => 'Old Note', 'cabang_id' => $this->cabang->id]);
    // ensure it has at least one item so save validation passes
    \App\Models\OrderRequestItem::factory()->create(['order_request_id' => $or->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0, 'tax' => 0, 'subtotal' => 1000]);

    Livewire::actingAs($this->user)
        ->test(EditOrderRequest::class, ['record' => $or->getKey()])
        ->fillForm(['note' => 'New Note via Edit', 'orderRequestItem' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0, 'tax' => 0, 'subtotal' => 1000]]])
        ->call('save')
        ->assertHasNoFormErrors();

    $or->refresh();
    expect($or->note)->toBe('New Note via Edit');
});

it('deletes (soft deletes) an order request and its items', function () {
    $or = OrderRequest::factory()->create(['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'cabang_id' => $this->cabang->id]);
    $item = OrderRequestItem::factory()->create(['order_request_id' => $or->id, 'product_id' => $this->product->id, 'discount' => 0, 'tax' => 0, 'subtotal' => 0]);

    // Simulate deletion (DeleteAction calls model delete internally). Some Filament actions are not directly callable
    // via Livewire test harness, so perform model delete to ensure soft-delete cascade behavior.
    $or->delete();

    $this->assertSoftDeleted('order_requests', ['id' => $or->id]);
    $this->assertSoftDeleted('order_request_items', ['id' => $item->id]);
});
