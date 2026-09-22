<?php

use App\Filament\Resources\OrderRequestResource\Pages\CreateOrderRequest;
use App\Filament\Resources\OrderRequestResource\Pages\EditOrderRequest;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $permissions = [
        'view any order request',
        'view order request',
        'create order request',
        'update order request',
        'view any product',
        'view any supplier',
        'view any currency',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->user->givePermissionTo($permissions);

    UnitOfMeasure::factory()->create();
    Currency::factory()->create([
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
        'code' => 'IDR',
        'to_rupiah' => 1,
    ]);

    $this->cabang = \App\Models\Cabang::factory()->create();
    $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->product = Product::factory()->forCabang($this->cabang)->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);
});

function orderRequestItemRepeater($component): Repeater
{
    return collect($component->instance()->getForm('form')->getFlatComponents(withHidden: true))
        ->first(fn ($field) => $field instanceof Repeater && $field->getName() === 'orderRequestItem');
}

it('keeps only the newest order request item expanded on create', function () {
    $component = Livewire::actingAs($this->user)
        ->test(CreateOrderRequest::class)
        ->callFormComponentAction('orderRequestItem', 'add')
        ->callFormComponentAction('orderRequestItem', 'add');

    $repeater = orderRequestItemRepeater($component);
    $items = array_values($repeater->getChildComponentContainers());

    expect($items)->toHaveCount(3)
        ->and($repeater->isCollapsed($items[0]))->toBeTrue()
        ->and($repeater->isCollapsed($items[1]))->toBeTrue()
        ->and($repeater->isCollapsed($items[2]))->toBeFalse();
});

it('collapses order request items on edit', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
    ]);

    $orderRequest->orderRequestItem()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'quantity' => 1,
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(EditOrderRequest::class, ['record' => $orderRequest->getKey()]);

    $repeater = orderRequestItemRepeater($component);
    $items = array_values($repeater->getChildComponentContainers());

    expect($items)->toHaveCount(1)
        ->and($repeater->isCollapsed($items[0]))->toBeTrue();
});

it('adds a new order request item from the edit repeater action', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'status' => 'request_approve',
    ]);

    $orderRequest->orderRequestItem()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'quantity' => 1,
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(EditOrderRequest::class, ['record' => $orderRequest->getKey()])
        ->callFormComponentAction('orderRequestItem', 'add');

    $repeater = orderRequestItemRepeater($component);
    $items = array_values($repeater->getChildComponentContainers());

    expect($items)->toHaveCount(2)
        ->and($repeater->isCollapsed($items[0]))->toBeTrue()
        ->and($repeater->isCollapsed($items[1]))->toBeFalse();
});
