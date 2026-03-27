<?php

use App\Filament\Resources\AssetTransferResource\Pages\CreateAssetTransfer;
use App\Filament\Resources\DeliveryOrderResource\Pages\CreateDeliveryOrder;
use App\Filament\Resources\ManufacturingOrderResource\Pages\CreateManufacturingOrder;
use App\Filament\Resources\PurchaseInvoiceResource\Pages\CreatePurchaseInvoice;
use App\Filament\Resources\SaleOrderResource\Pages\CreateSaleOrder;
use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Filament\Resources\SuratJalanResource\Pages\CreateSuratJalan;
use App\Models\Asset;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\ProductionPlan;
use App\Models\PurchaseOrder;
use App\Models\OrderRequest;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function invokeMutateBeforeCreate(object $page, array $data): array
{
    $reflection = new ReflectionClass($page);
    $method = $reflection->getMethod('mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    /** @var array $result */
    $result = $method->invoke($page, $data);
    return $result;
}

test('sale order inherits cabang from quotation source', function () {
    $cabangSource = Cabang::factory()->create();
    $cabangWrong = Cabang::factory()->create();
    $customer = Customer::factory()->create();

    $quotation = Quotation::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id' => $cabangSource->id,
    ]);

    $page = new CreateSaleOrder();

    $result = invokeMutateBeforeCreate($page, [
        'quotation_id' => $quotation->id,
        'cabang_id' => $cabangWrong->id,
    ]);

    expect((int) $result['cabang_id'])->toBe((int) $cabangSource->id);
});

test('sales invoice inherits cabang from sale order source', function () {
    $cabangSource = Cabang::factory()->create();
    $cabangWrong = Cabang::factory()->create();

    $saleOrder = SaleOrder::factory()->create([
        'cabang_id' => $cabangSource->id,
    ]);

    $page = new CreateSalesInvoice();

    $result = invokeMutateBeforeCreate($page, [
        'selected_sale_order' => $saleOrder->id,
        'cabang_id' => $cabangWrong->id,
    ]);

    expect((int) $result['cabang_id'])->toBe((int) $cabangSource->id);
});

test('purchase invoice inherits cabang from selected purchase order source', function () {
    $cabangSource = Cabang::factory()->create();
    $cabangWrong = Cabang::factory()->create();
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create(['cabang_id' => $cabangSource->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabangSource->id]);
    $orderRequest = OrderRequest::factory()->create([
        'cabang_id' => $cabangSource->id,
        'status' => 'approved',
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'cabang_id' => $cabangSource->id,
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $orderRequest->id,
    ]);

    $page = new CreatePurchaseInvoice();

    $result = invokeMutateBeforeCreate($page, [
        'selected_order_request' => $orderRequest->id,
        'selected_purchase_orders' => [$purchaseOrder->id],
        'subtotal' => 100000,
        'ppn_rate' => 11,
        'tax' => 0,
        'cabang_id' => $cabangWrong->id,
    ]);

    expect((int) $result['cabang_id'])->toBe((int) $cabangSource->id);
});

test('manufacturing order inherits cabang from production plan sale order source', function () {
    $cabangSource = Cabang::factory()->create();
    $cabangWarehouse = Cabang::factory()->create();

    $saleOrder = SaleOrder::factory()->create([
        'cabang_id' => $cabangSource->id,
    ]);

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabangWarehouse->id,
    ]);

    $productionPlan = ProductionPlan::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $page = new CreateManufacturingOrder();

    $result = invokeMutateBeforeCreate($page, [
        'production_plan_id' => $productionPlan->id,
    ]);

    expect((int) $result['cabang_id'])->toBe((int) $cabangSource->id);
});

test('asset transfer inherits source cabang from selected asset', function () {
    $cabangSource = Cabang::factory()->create();
    $assetCoa = ChartOfAccount::factory()->create(['type' => 'Asset']);
    $accumulatedDepCoa = ChartOfAccount::factory()->create(['type' => 'Contra Asset']);
    $depExpenseCoa = ChartOfAccount::factory()->create(['type' => 'Expense']);

    $asset = Asset::factory()->create([
        'cabang_id' => $cabangSource->id,
        'asset_coa_id' => $assetCoa->id,
        'accumulated_depreciation_coa_id' => $accumulatedDepCoa->id,
        'depreciation_expense_coa_id' => $depExpenseCoa->id,
    ]);

    $page = new CreateAssetTransfer();

    $result = invokeMutateBeforeCreate($page, [
        'asset_id' => $asset->id,
    ]);

    expect((int) $result['from_cabang_id'])->toBe((int) $cabangSource->id);
});

test('delivery order inherits cabang from selected sales order source', function () {
    $cabangSource = Cabang::factory()->create();
    $cabangWrong = Cabang::factory()->create();

    $user = User::factory()->create(['cabang_id' => $cabangSource->id]);
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'approved',
        'cabang_id' => $cabangSource->id,
    ]);

    Supplier::factory()->create(['cabang_id' => $cabangSource->id]);
    UnitOfMeasure::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabangSource->id]);
    $rak = \App\Models\Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $product = Product::factory()->create();

    $saleOrderItem = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    $page = new CreateDeliveryOrder();

    $result = invokeMutateBeforeCreate($page, [
        'salesOrders' => [$saleOrder->id],
        'cabang_id' => $cabangWrong->id,
        'deliveryOrderItem' => [
            [
                'sale_order_item_id' => $saleOrderItem->id,
                'product_id' => $saleOrderItem->product_id,
                'quantity' => 2,
            ],
        ],
    ]);

    expect((int) $result['cabang_id'])->toBe((int) $cabangSource->id);
});

test('surat jalan inherits cabang from selected delivery order source', function () {
    $cabangSource = Cabang::factory()->create();
    $cabangWrong = Cabang::factory()->create();

    $user = User::factory()->create(['cabang_id' => $cabangSource->id]);
    $this->actingAs($user);

    $deliveryOrder = DeliveryOrder::factory()->create([
        'status' => 'approved',
        'cabang_id' => $cabangSource->id,
    ]);

    $page = new CreateSuratJalan();

    $result = invokeMutateBeforeCreate($page, [
        'deliveryOrder' => [$deliveryOrder->id],
        'cabang_id' => $cabangWrong->id,
    ]);

    expect((int) $result['cabang_id'])->toBe((int) $cabangSource->id);
});

test('delivery order rejects mixed-branch sales orders', function () {
    $cabangA = Cabang::factory()->create();
    $cabangB = Cabang::factory()->create();

    $user = User::factory()->create([
        'cabang_id' => null,
    ]);
    $this->actingAs($user);

    $customer = Customer::factory()->create();

    $soA = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'approved',
        'cabang_id' => $cabangA->id,
    ]);
    $soB = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'approved',
        'cabang_id' => $cabangB->id,
    ]);

    $itemA = SaleOrderItem::factory()->create([
        'sale_order_id' => $soA->id,
        'quantity' => 5,
    ]);

    $page = new CreateDeliveryOrder();

    expect(fn () => invokeMutateBeforeCreate($page, [
        'salesOrders' => [$soA->id, $soB->id],
        'deliveryOrderItem' => [
            [
                'sale_order_item_id' => $itemA->id,
                'product_id' => $itemA->product_id,
                'quantity' => 2,
            ],
        ],
    ]))->toThrow(ValidationException::class);
});

test('surat jalan rejects mixed-branch delivery orders', function () {
    $cabangA = Cabang::factory()->create();
    $cabangB = Cabang::factory()->create();

    $user = User::factory()->create([
        'cabang_id' => null,
    ]);
    $this->actingAs($user);

    $doA = DeliveryOrder::factory()->create([
        'status' => 'approved',
        'cabang_id' => $cabangA->id,
    ]);
    $doB = DeliveryOrder::factory()->create([
        'status' => 'approved',
        'cabang_id' => $cabangB->id,
    ]);

    $page = new CreateSuratJalan();

    expect(fn () => invokeMutateBeforeCreate($page, [
        'deliveryOrder' => [$doA->id, $doB->id],
    ]))->toThrow(ValidationException::class);
});
