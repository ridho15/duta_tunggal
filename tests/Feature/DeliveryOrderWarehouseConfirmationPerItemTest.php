<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\Driver;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Services\DeliveryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
});

test('delivery order warehouse confirmations are created per request item and source warehouse', function () {
    $branch = Cabang::factory()->create();
    $warehouseA = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $warehouseB = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rakA = Rak::factory()->create(['warehouse_id' => $warehouseA->id]);
    $rakB = Rak::factory()->create(['warehouse_id' => $warehouseB->id]);
    $driver = Driver::factory()->create();
    $vehicle = Vehicle::factory()->create();
    $customer = Customer::factory()->create();
    $category = ProductCategory::factory()->create();
    $uom = UnitOfMeasure::factory()->create();

    $productA = Product::factory()->create([
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'name' => 'DO Confirmation Product A',
    ]);

    $productB = Product::factory()->create([
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'name' => 'DO Confirmation Product B',
    ]);

    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id' => $branch->id,
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
    ]);

    $saleOrderItemA = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $productA->id,
        'quantity' => 10,
        'warehouse_id' => $warehouseA->id,
        'rak_id' => $rakA->id,
    ]);

    $saleOrderItemB = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $productB->id,
        'quantity' => 7,
        'warehouse_id' => $warehouseA->id,
        'rak_id' => $rakA->id,
    ]);

    $deliveryOrder = DeliveryOrder::create([
        'do_number' => 'DO-WC-PER-ITEM-001',
        'delivery_date' => now()->toDateString(),
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'warehouse_id' => null,
        'status' => 'draft',
        'notes' => 'Per-item WC test',
        'cabang_id' => $branch->id,
    ]);

    $deliveryItemA = DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sale_order_item_id' => $saleOrderItemA->id,
        'product_id' => $productA->id,
        'quantity' => 10,
        'reason' => 'Request product A',
    ]);

    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $deliveryItemA->id,
        'warehouse_id' => $warehouseA->id,
        'rak_id' => $rakA->id,
        'quantity' => 10,
    ]);

    $deliveryItemB = DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sale_order_item_id' => $saleOrderItemB->id,
        'product_id' => $productB->id,
        'quantity' => 7,
        'reason' => 'Request product B',
    ]);

    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $deliveryItemB->id,
        'warehouse_id' => $warehouseA->id,
        'rak_id' => $rakA->id,
        'quantity' => 4,
    ]);

    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $deliveryItemB->id,
        'warehouse_id' => $warehouseB->id,
        'rak_id' => $rakB->id,
        'quantity' => 3,
    ]);

    $confirmations = app(DeliveryOrderService::class)->createWarehouseConfirmationsForDeliveryOrder($deliveryOrder);

    expect($confirmations)->toHaveCount(3);

    $storedConfirmations = WarehouseConfirmation::query()
        ->where('confirmable_type', DeliveryOrder::class)
        ->where('confirmable_id', $deliveryOrder->id)
        ->with('warehouseConfirmationItems')
        ->orderBy('id')
        ->get();

    expect($storedConfirmations)->toHaveCount(3);

    $requestKeys = $storedConfirmations
        ->map(function (WarehouseConfirmation $confirmation) {
            $item = $confirmation->warehouseConfirmationItems->sole();

            expect($confirmation->status)->toBe('request')
                ->and($confirmation->confirmation_type)->toBe('delivery_order')
                ->and($confirmation->warehouseConfirmationItems)->toHaveCount(1);

            return implode(':', [
                $item->sale_order_item_id,
                $item->warehouse_id,
                (float) $item->requested_qty,
            ]);
        })
        ->sort()
        ->values()
        ->all();

    expect($requestKeys)->toBe([
        $saleOrderItemA->id . ':' . $warehouseA->id . ':10',
        $saleOrderItemB->id . ':' . $warehouseA->id . ':4',
        $saleOrderItemB->id . ':' . $warehouseB->id . ':3',
    ]);
});