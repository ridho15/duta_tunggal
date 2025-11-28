<?php

use App\Filament\Resources\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
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
});

test('purchase order livewire form auto-fills tempo hutang from supplier selection', function () {
    Livewire::actingAs($this->user)
        ->test(CreatePurchaseOrder::class)
        ->set('data.supplier_id', $this->supplier->id)
        ->assertSet('data.tempo_hutang', $this->supplier->tempo_hutang);
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
                'nominal' => 25000.0,
            ],
        ])
        ->set('data.purchaseOrderBiaya', [])
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
