<?php

use App\Filament\Resources\OrderRequestResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\QuotationResource;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (HelperController::listPermission() as $resource => $actions) {
        foreach ($actions as $action) {
            Permission::firstOrCreate([
                'name' => sprintf('%s %s', $action, $resource),
                'guard_name' => 'web',
            ]);
        }
    }

    $this->user = User::factory()->create([
        'email' => 'row-style-test@example.com',
        'manage_type' => 'all',
    ]);
    $this->user->givePermissionTo(Permission::all());
    $this->actingAs($this->user);

    $this->cabang = Cabang::factory()->create();
    $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 1]);
    $this->uom = UnitOfMeasure::factory()->create();
    $this->product = Product::factory()->create([
        'cabang_id' => $this->cabang->id,
        'supplier_id' => $this->supplier->id,
        'uom_id' => $this->uom->id,
    ]);
});

test('quotation index renders status row classes', function () {
    $quotation = Quotation::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'approve',
    ]);

    $response = $this->get(QuotationResource::getUrl('index'));

    $response->assertOk();
    $response->assertSee($quotation->quotation_number);
    $response->assertSee('bg-blue-100');
});

test('quotation resource includes a status color legend', function () {
    $resource = file_get_contents(base_path('app/Filament/Resources/QuotationResource.php'));

    expect($resource)->toContain('Legenda Warna Status Baris Data')
        ->and($resource)->toContain('Putih (Draft)')
        ->and($resource)->toContain('Abu-abu (Request Approve)')
        ->and($resource)->toContain('Biru (Approved)')
        ->and($resource)->toContain('Merah (Reject)');
});

test('order request index renders status row classes', function () {
    $orderRequest = OrderRequest::factory()->create([
        'cabang_id' => $this->cabang->id,
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
        'status' => 'approved',
    ]);

    $response = $this->get(OrderRequestResource::getUrl('index'));

    $response->assertOk();
    $response->assertSee($orderRequest->request_number);
    $response->assertSee('bg-blue-100');
});

test('purchase order index renders status row classes', function () {
    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'completed',
    ]);

    $response = $this->get(PurchaseOrderResource::getUrl('index'));

    $response->assertOk();
    $response->assertSee($purchaseOrder->po_number);
    $response->assertSee('bg-green-100');
});