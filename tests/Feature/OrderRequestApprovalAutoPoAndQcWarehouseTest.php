<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderRequestResource\Pages\ViewOrderRequest;
use App\Filament\Resources\QualityControlPurchaseResource\Pages\CreateQualityControlPurchase;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QualityControl;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->creator = User::factory()->create();
    $this->actingAs($this->user);

    $role = Role::firstOrCreate(['name' => 'Purchasing Manager', 'guard_name' => 'web']);
    $this->user->assignRole($role);

    $needed = [
        'view any order request',
        'view order request',
        'approve order request',
        'create quality control',
        'view any quality control',
    ];

    foreach ($needed as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->user->givePermissionTo($needed);

    $this->currency = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    $this->uom = UnitOfMeasure::factory()->create(['name' => 'pcs']);
    $this->cabang = Cabang::factory()->create(['kode' => 'CBG-001', 'nama' => 'Pusat']);
    $this->warehouse1 = Warehouse::factory()->create(['name' => 'Gudang Utama', 'kode' => 'GDG-01', 'status' => 1]);
    $this->warehouse2 = Warehouse::factory()->create(['name' => 'Gudang Cadangan', 'kode' => 'GDG-02', 'status' => 1]);

    $this->supplier = Supplier::factory()->create(['tempo_hutang' => 30]);
    $this->product = Product::factory()->create([
        'cost_price' => 10000,
        'sell_price' => 15000,
        'uom_id' => $this->uom->id,
    ]);
});

it('approves an order request with auto-PO and assigns the selected destination warehouse', function () {
    $or = OrderRequest::factory()->create([
        'created_by' => $this->creator->id,
        'status' => 'request_approve',
        'currency_id' => $this->currency->id,
    ]);

    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'quantity' => 5,
        'fulfilled_quantity' => 0,
        'unit_price' => 10000,
        'original_price' => 10000,
        'currency_id' => $this->currency->id,
        'status' => OrderRequestItem::STATUS_DRAFT,
    ]);

    Livewire::actingAs($this->user)
        ->test(ViewOrderRequest::class, ['record' => $or->getKey()])
        ->callAction('approve', data: [
            'create_purchase_order' => true,
            'multi_supplier' => false,
            'warehouse_id' => $this->warehouse2->id,
            'order_date' => now()->toDateString(),
            'selected_items' => [[
                'item_id' => $item->id,
                'item_supplier_id' => $this->supplier->id,
                'item_cabang_id' => $this->cabang->id,
                'currency_id' => $this->currency->id,
                'quantity' => 5,
                'original_price' => 10000,
                'unit_price' => 10000,
                'approval_status' => OrderRequestItem::STATUS_APPROVED,
                'include' => true,
            ]],
        ])
        ->assertHasNoActionErrors();

    $or->refresh();
    expect($or->status)->toBe('approved');

    $po = $or->purchaseOrders()->first();
    expect($po)->not->toBeNull();
    expect($po->warehouse_id)->toBe($this->warehouse2->id);
    expect($po->status)->toBe('approved');
});

it('creates purchase order from secondary action on ViewOrderRequest with selected warehouse', function () {
    $or = OrderRequest::factory()->create([
        'created_by' => $this->creator->id,
        'status' => 'approved',
        'currency_id' => $this->currency->id,
    ]);

    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'quantity' => 10,
        'fulfilled_quantity' => 0,
        'unit_price' => 10000,
        'original_price' => 10000,
        'currency_id' => $this->currency->id,
        'status' => OrderRequestItem::STATUS_APPROVED,
    ]);

    Livewire::actingAs($this->user)
        ->test(ViewOrderRequest::class, ['record' => $or->getKey()])
        ->callAction('create_purchase_order', data: [
            'order_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse1->id,
            'selected_items' => [[
                'item_id' => $item->id,
                'item_supplier_id' => $this->supplier->id,
                'item_cabang_id' => $this->cabang->id,
                'currency_id' => $this->currency->id,
                'quantity' => 10,
                'original_price' => 10000,
                'unit_price' => 10000,
                'approval_status' => OrderRequestItem::STATUS_APPROVED,
                'include' => true,
            ]],
        ])
        ->assertHasNoActionErrors();

    $po = $or->purchaseOrders()->latest('id')->first();
    expect($po)->not->toBeNull();
    expect($po->warehouse_id)->toBe($this->warehouse1->id);
});

it('allows creating QC for PO without warehouse by choosing warehouse manually', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'warehouse_id' => null,
        'status' => 'approved',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 8,
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(CreateQualityControlPurchase::class)
        ->fillForm([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse2->id,
            'inspected_by' => $this->user->id,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'remaining_allowed' => 8,
                    'quantity_received' => 8,
                    'passed_quantity' => 8,
                    'rejected_quantity' => 0,
                    'failed_qc_action' => 'wait_next_delivery',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $qc = QualityControl::where('purchase_order_id', $po->id)->first();
    expect($qc)->not->toBeNull();
    expect($qc->warehouse_id)->toBe($this->warehouse2->id);
    expect($qc->inspected_by)->toBe($this->user->id);
});

it('locks QC warehouse when PO already specifies a destination warehouse', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'warehouse_id' => $this->warehouse1->id,
        'status' => 'approved',
    ]);

    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'quantity' => 5,
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(CreateQualityControlPurchase::class)
        ->fillForm([
            'purchase_order_id' => $po->id,
            'warehouse_id' => $this->warehouse1->id,
            'inspected_by' => $this->user->id,
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $this->product->id,
                    'remaining_allowed' => 5,
                    'quantity_received' => 5,
                    'passed_quantity' => 5,
                    'rejected_quantity' => 0,
                    'failed_qc_action' => 'wait_next_delivery',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $qc = QualityControl::where('purchase_order_id', $po->id)->first();
    expect($qc)->not->toBeNull();
    expect($qc->warehouse_id)->toBe($this->warehouse1->id);
});
