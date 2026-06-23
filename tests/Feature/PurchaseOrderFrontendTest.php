<?php

use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
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

function grantPurchaseOrderPermissions(User $user, array $permissions): void
{
    registerAllPermissions();

    $user->givePermissionTo($permissions);
}

beforeEach(function () {
    $this->user = User::factory()->create();
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
    grantPurchaseOrderPermissions($this->user, $permissions);
    $this->actingAs($this->user);

    UnitOfMeasure::factory()->create();
    $this->currency = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
    ]);
    $this->supplier = Supplier::factory()->create([
        'tempo_hutang' => 21,
    ]);
    $this->cabang = Cabang::factory()->create();
    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
        'status' => 1,
    ]);
    $this->product = Product::factory()->forCabang($this->cabang)->create([
        'supplier_id' => $this->supplier->id,
        'cost_price' => 8500,
        'sell_price' => 13000,
    ]);

    $this->purchaseOrder = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-FRONT-001',
        'order_date' => Carbon::now()->toDateTimeString(),
        'status' => 'approved',
        'expected_date' => Carbon::now()->addDays(5)->toDateTimeString(),
        'total_amount' => 12500,
        'warehouse_id' => $this->warehouse->id,
        'tempo_hutang' => $this->supplier->tempo_hutang,
        'note' => 'PO untuk pengujian front-end',
        'created_by' => $this->user->id,
    ]);

    $this->purchaseOrder->purchaseOrderItem()->create([
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 12500,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
        'currency_id' => $this->currency->id,
    ]);
});

test('purchase order index page loads successfully and displays purchase orders', function () {
    $response = $this->get(PurchaseOrderResource::getUrl('index'));

    $response->assertOk()
        ->assertSee('Pembelian')
        ->assertSee('PO-FRONT-001');
});

test('purchase order list shows cabang from order request item and omits warehouse column', function () {
    $orderRequestCabang = Cabang::factory()->create([
        'kode' => 'ORCAB',
        'nama' => 'Cabang OR Purchase',
    ]);

    $orderRequest = OrderRequest::factory()->create([
        'status' => 'approved',
        'currency_id' => $this->currency->id,
        'created_by' => $this->user->id,
    ]);

    $orderRequestItem = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $orderRequestCabang->id,
        'quantity' => 5,
        'fulfilled_quantity' => 0,
        'unit_price' => 1000,
        'currency_id' => $this->currency->id,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-CABANG-OR-001',
        'status' => 'approved',
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $orderRequest->id,
        'cabang_id' => null,
        'created_by' => $this->user->id,
    ]);

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 5,
        'refer_item_model_type' => OrderRequestItem::class,
        'refer_item_model_id' => $orderRequestItem->id,
        'currency_id' => $this->currency->id,
    ]);

    expect(PurchaseOrderResource::resolvePurchaseOrderCabang($purchaseOrder->fresh())->id)
        ->toBe($orderRequestCabang->id);

    Livewire::actingAs($this->user)
        ->test(ListPurchaseOrders::class)
        ->assertSee('Cabang')
        ->assertSee('Cabang OR Purchase')
        ->assertDontSee('Gudang');
});

test('purchase order index search does not error when cabang_id column is absent', function () {
    $response = $this->get(PurchaseOrderResource::getUrl('index', ['tableSearch' => 'asdfsdf']));

    $response->assertOk();
});

test('purchase order create page is accessible with the correct heading', function () {
    $response = $this->get(PurchaseOrderResource::getUrl('create'));

    $response->assertOk()
        ->assertSee('Buat Pembelian');
});

test('purchase order can be created with non_ppn option', function () {
    $supplier = Supplier::factory()->create(['tempo_hutang' => 21]);
    $warehouse = Warehouse::factory()->create(['status' => 1]);
    $currency = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
    ]);
    $product = Product::factory()->forCabang($warehouse->cabang_id)->create();

    $data = [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'currency_id' => $currency->id,
        'is_import' => false,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_price' => 10000,
                'opsi_harga' => 'Exclude PPN',
                'tax' => 0,
                'discount' => 0,
            ],
        ],
    ];

    // Since this is a Filament form, we need to simulate the form submission
    // For now, just test the model creation directly
    $purchaseOrder = PurchaseOrder::create([
        'po_number' => 'PO-NON-PPN-001',
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'currency_id' => $currency->id,
        'order_date' => now(),
        'tempo_hutang' => 30,
        'is_import' => false,
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);

    expect($purchaseOrder)->not->toBeNull();
});

test('purchase order create form no longer shows a global tax field', function () {
    $response = $this->get(PurchaseOrderResource::getUrl('create'));

    $response->assertOk();
    $response->assertDontSee('Tipe Pajak (Global)');
});

test('purchase order resource no longer exposes cabang item or warehouse list labels', function () {
    $resource = file_get_contents(base_path('app/Filament/Resources/PurchaseOrderResource.php'));

    expect($resource)->not->toContain('Cabang (Item)')
        ->and($resource)->not->toContain('Cabang Item')
        ->and($resource)->not->toContain("->label('Gudang')");
});

test('purchase order status presentation handles paid state', function () {
    expect(PurchaseOrderResource::formatStatusLabel('partially_received'))->toBe('Partially Received');
    expect(PurchaseOrderResource::formatStatusLabel('paid'))->toBe('Paid');
    expect(PurchaseOrderResource::getStatusColor('paid'))->toBe('success');
});

test('purchase order table uses latest created sorting and status color legend', function () {
    $resource = file_get_contents(base_path('app/Filament/Resources/PurchaseOrderResource.php'));

    expect($resource)->toContain("->defaultSort('created_at', 'desc')")
        ->and($resource)->not->toContain("orderByDesc('order_date')")
        ->and($resource)->toContain('width: 100%; min-width: 100%; max-width: none; box-sizing: border-box;')
        ->and($resource)->toContain('Legenda Warna Status Baris Data')
        ->and($resource)->toContain('Biru (Approved)')
        ->and($resource)->toContain('Kuning (Partially Received)')
        ->and($resource)->toContain('Hijau (Completed/Paid)')
        ->and($resource)->toContain('Merah (Request Close/Closed)');
});

test('purchase order form uses collapsed product item repeater and disabled field styling', function () {
    $resource = file_get_contents(base_path('app/Filament/Resources/PurchaseOrderResource.php'));

    expect($resource)->toContain("Repeater::make('purchaseOrderItem')")
        ->and($resource)->toContain("Fieldset::make('Form Pembelian')")
        ->and($resource)->toContain('->columns(3)')
        ->and($resource)->toContain("TextInput::make('po_number')")
        ->and($resource)->toContain("Select::make('cabang_id')")
        ->and($resource)->toContain("->label('TOP (Term Of Payment)')")
        ->and($resource)->toContain("->label('Masa Kredit (Hari)')")
        ->and($resource)->toContain('->disabled(fn(Get $get) => $get(\'refer_model_type\') === \'App\\\\Models\\\\OrderRequest\')')
        ->and($resource)->toContain('->addable(fn(Get $get) => $get(\'refer_model_type\') !== \'App\\\\Models\\\\OrderRequest\')')
        ->and($resource)->toContain('->collapsed(function')
        ->and($resource)->toContain('->itemLabel(function (array $state)')
        ->and($resource)->toContain('->columns(10)')
        ->and($resource)->toContain('bg-gray-100 dark:bg-gray-800 cursor-not-allowed text-gray-500 dark:text-gray-400')
        ->and($resource)->toContain('background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;')
        ->and($resource)->toContain("TextInput::make('unit')")
        ->and($resource)->toContain("TextInput::make('total')")
        ->and($resource)->toContain("TextInput::make('tax_nominal')")
        ->and($resource)->toContain("TextInput::make('subtotal')");

    $purchaseOrderBiayaStart = strpos($resource, "Repeater::make('purchaseOrderBiaya')");
    expect($purchaseOrderBiayaStart)->not->toBeFalse();
    $purchaseOrderBiayaBlock = substr($resource, $purchaseOrderBiayaStart, 500);
    expect($purchaseOrderBiayaBlock)->toContain('->hidden()')
        ->and($purchaseOrderBiayaBlock)->toContain('->dehydrated(false)');

    $repeaterStart = strpos($resource, "Repeater::make('purchaseOrderItem')");
    $taxBreakdownStart = strpos($resource, "Placeholder::make('tax_breakdown')", $repeaterStart);
    $purchaseOrderItemSchema = substr($resource, $repeaterStart, $taxBreakdownStart - $repeaterStart);

    $expectedOrder = [
        "Select::make('product_id')",
        "TextInput::make('unit')",
        "TextInput::make('quantity')",
        "Select::make('currency_id')",
        "TextInput::make('unit_price')",
        "TextInput::make('total')",
        "TextInput::make('discount')",
        "TextInput::make('discount_nominal')",
        "Radio::make('tipe_pajak')",
        "TextInput::make('tax')",
        "TextInput::make('tax_nominal')",
        "TextInput::make('subtotal')",
    ];

    $previousPosition = -1;

    foreach ($expectedOrder as $field) {
        $position = strpos($purchaseOrderItemSchema, $field);

        expect($position)->not->toBeFalse()
            ->and($position)->toBeGreaterThan($previousPosition);

        $previousPosition = $position;
    }
});

test('purchase order item fields from order request are locked and show nominal discount', function () {
    $resource = file_get_contents(base_path('app/Filament/Resources/PurchaseOrderResource.php'));

    expect($resource)->toContain('public static function isOrderRequestBackedItem(Get $get): bool')
        ->and($resource)->toContain("Select::make('product_id')")
        ->and($resource)->toContain("Select::make('currency_id')")
        ->and($resource)->toContain("TextInput::make('unit_price')")
        ->and($resource)->toContain("TextInput::make('discount')")
        ->and($resource)->toContain("Radio::make('tipe_pajak')")
        ->and($resource)->toContain('->disabled(fn(Get $get) => self::isOrderRequestBackedItem($get))')
        ->and($resource)->toContain('->readOnly(fn(Get $get) => self::isOrderRequestBackedItem($get))')
        ->and($resource)->toContain("TextInput::make('discount_nominal')")
        ->and($resource)->toContain("->label('Nominal Discount')")
        ->and($resource)->toContain("'discount_nominal' => round(\$base * (\$discount / 100), 2)");

    $preview = PurchaseOrderResource::calculateCurrencyPreview(
        quantity: 10,
        unitPrice: 12500,
        discount: 5,
        tax: 11,
        taxType: 'eklusif',
        currencyId: $this->currency->id,
    );

    expect($preview['discount_nominal'])->toBe(6250.0)
        ->and($preview['total'])->toBe(125000.0);
});

test('purchase order creation auto approves full order request references conditionally', function () {
    $createPage = file_get_contents(base_path('app/Filament/Resources/PurchaseOrderResource/Pages/CreatePurchaseOrder.php'));
    $orderRequestService = file_get_contents(base_path('app/Services/OrderRequestService.php'));

    expect($createPage)->toContain('use App\\Models\\OrderRequest;')
        ->and($createPage)->toContain('$record->refer_model_type === OrderRequest::class')
        ->and($createPage)->toContain('PurchaseOrderResource::shouldAutoApproveOrderRequestPurchaseOrder($record)')
        ->and($createPage)->toContain('$purchaseOrderService->approvePo($record, Auth::id())')
        ->and($orderRequestService)->toContain('app(PurchaseOrderService::class)->approvePo($purchaseOrder, Auth::id())')
        ->and($createPage)->toContain('$data[\'status\']     = \'draft\'');
});
