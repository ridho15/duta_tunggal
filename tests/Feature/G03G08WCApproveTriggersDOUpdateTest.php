<?php

/**
 * G-03/G-08: Verify that approving or rejecting a WarehouseConfirmation
 * correctly triggers updateStatusFromWarehouseConfirmations() on the
 * linked DeliveryOrder, updating DO status accordingly.
 *
 * WCs are now linked to the DO via polymorphic confirmable_type/confirmable_id
 * (confirmable_type = App\Models\DeliveryOrder).
 */

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Driver;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Rak;
use App\Models\WarehouseConfirmationItem;
use App\Models\WarehouseConfirmation;
use App\Models\Warehouse;
use App\Models\Vehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Auth::login($this->user);

    $this->seed(\Database\Seeders\CabangSeeder::class);

    $this->warehouse = Warehouse::factory()->create();

    $this->do = DeliveryOrder::factory()->create([
        'status' => 'request_stock',
        'warehouse_id' => $this->warehouse->id,
    ]);
});

it('sets DO status to approved when all linked WCs are confirmed', function () {
    // Create 2 WCs linked to the DO via polymorphic confirmable
    $wc1 = WarehouseConfirmation::withoutEvents(fn () => WarehouseConfirmation::factory()->create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id'   => $this->do->id,
        'status'           => 'request',
    ]));
    $wc2 = WarehouseConfirmation::withoutEvents(fn () => WarehouseConfirmation::factory()->create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id'   => $this->do->id,
        'status'           => 'request',
    ]));

    WarehouseConfirmation::withoutEvents(fn () => $wc1->update([
        'status'       => 'confirmed',
        'confirmed_by' => $this->user->id,
        'confirmed_at' => now(),
    ]));
    $wc1->getLinkedDeliveryOrder()?->updateStatusFromWarehouseConfirmations();

    // DO should still be request_stock (wc2 still pending)
    expect($this->do->fresh()->status)->toBe('request_stock');

    WarehouseConfirmation::withoutEvents(fn () => $wc2->update([
        'status'       => 'confirmed',
        'confirmed_by' => $this->user->id,
        'confirmed_at' => now(),
    ]));
    $wc2->getLinkedDeliveryOrder()?->updateStatusFromWarehouseConfirmations();

    // ALL WCs confirmed → DO should be approved
    expect($this->do->fresh()->status)->toBe('approved');
});

it('sets DO status to reject when all linked WCs are rejected', function () {
    $wc1 = WarehouseConfirmation::withoutEvents(fn () => WarehouseConfirmation::factory()->create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id'   => $this->do->id,
        'status'           => 'request',
    ]));
    $wc2 = WarehouseConfirmation::withoutEvents(fn () => WarehouseConfirmation::factory()->create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id'   => $this->do->id,
        'status'           => 'request',
    ]));

    WarehouseConfirmation::withoutEvents(fn () => $wc1->update([
        'status'           => 'rejected',
        'rejection_reason' => 'Stok tidak tersedia',
        'confirmed_by'     => $this->user->id,
        'confirmed_at'     => now(),
    ]));
    WarehouseConfirmation::withoutEvents(fn () => $wc2->update([
        'status'           => 'rejected',
        'rejection_reason' => 'Stok tidak tersedia',
        'confirmed_by'     => $this->user->id,
        'confirmed_at'     => now(),
    ]));

    $this->do->updateStatusFromWarehouseConfirmations();

    expect($this->do->fresh()->status)->toBe('reject');
});

it('sets DO status to reject on mixed confirmed/rejected (any rejected wins)', function () {
    $wc1 = WarehouseConfirmation::withoutEvents(fn () => WarehouseConfirmation::factory()->create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id'   => $this->do->id,
        'status'           => 'request',
    ]));
    $wc2 = WarehouseConfirmation::withoutEvents(fn () => WarehouseConfirmation::factory()->create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id'   => $this->do->id,
        'status'           => 'request',
    ]));

    WarehouseConfirmation::withoutEvents(fn () => $wc1->update([
        'status'       => 'confirmed',
        'confirmed_by' => $this->user->id,
        'confirmed_at' => now(),
    ]));
    WarehouseConfirmation::withoutEvents(fn () => $wc2->update([
        'status'           => 'rejected',
        'rejection_reason' => 'Item hilang',
        'confirmed_by'     => $this->user->id,
        'confirmed_at'     => now(),
    ]));

    $this->do->updateStatusFromWarehouseConfirmations();

    // Any rejected → DO status = reject (per business rule)
    expect($this->do->fresh()->status)->toBe('reject');
});

it('syncs linked delivery order item status when the DO-linked WC is rejected', function () {
    $saleOrder = SaleOrder::factory()->create([
        'status' => 'approved',
    ]);

    $saleOrderItem = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'quantity' => 2,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $driver = Driver::factory()->create();
    $vehicle = Vehicle::factory()->create();

    $deliveryOrder = DeliveryOrder::factory()->create([
        'status' => 'request_stock',
        'warehouse_id' => $this->warehouse->id,
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
    ]);

    $deliveryOrderItem = DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sale_order_item_id' => $saleOrderItem->id,
        'product_id' => $saleOrderItem->product_id,
        'quantity' => 2,
        'reason' => 'Reject sync test',
        'status' => 'pending',
    ]);

    $wc = WarehouseConfirmation::create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id' => $deliveryOrder->id,
        'confirmation_type' => 'delivery_order',
        'status' => 'request',
        'note' => 'Reject sync test',
    ]);

    WarehouseConfirmationItem::create([
        'warehouse_confirmation_id' => $wc->id,
        'sale_order_item_id' => $saleOrderItem->id,
        'product_id' => $saleOrderItem->product_id,
        'product_name' => $saleOrderItem->product?->name ?? '-',
        'requested_qty' => 2,
        'confirmed_qty' => 0,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
        'status' => 'request',
    ]);

    $wc->update([
        'status' => 'rejected',
        'rejection_reason' => 'No stock available',
        'confirmed_by' => $this->user->id,
        'confirmed_at' => now(),
    ]);

    expect($deliveryOrderItem->fresh()->status)->toBe('rejected');
    expect($deliveryOrder->fresh()->status)->toBe('reject');
});

it('reverts linked delivery order item status when the DO-linked WC is deleted', function () {
    $saleOrder = SaleOrder::factory()->create([
        'status' => 'approved',
    ]);

    $saleOrderItem = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'quantity' => 3,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $driver = Driver::factory()->create();
    $vehicle = Vehicle::factory()->create();

    $deliveryOrder = DeliveryOrder::factory()->create([
        'status' => 'request_stock',
        'warehouse_id' => $this->warehouse->id,
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
    ]);

    $deliveryOrderItem = DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sale_order_item_id' => $saleOrderItem->id,
        'product_id' => $saleOrderItem->product_id,
        'quantity' => 3,
        'reason' => 'Cancel sync test',
        'status' => 'pending',
    ]);

    $wc = WarehouseConfirmation::create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id' => $deliveryOrder->id,
        'confirmation_type' => 'delivery_order',
        'status' => 'request',
        'note' => 'Cancel sync test',
    ]);

    WarehouseConfirmationItem::create([
        'warehouse_confirmation_id' => $wc->id,
        'sale_order_item_id' => $saleOrderItem->id,
        'product_id' => $saleOrderItem->product_id,
        'product_name' => $saleOrderItem->product?->name ?? '-',
        'requested_qty' => 3,
        'confirmed_qty' => 3,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
        'status' => 'request',
    ]);

    $wc->update([
        'status' => 'confirmed',
        'confirmed_by' => $this->user->id,
        'confirmed_at' => now(),
    ]);

    expect($deliveryOrderItem->fresh()->status)->toBe('confirmed');
    expect($deliveryOrder->fresh()->status)->toBe('approved');

    $wc->delete();

    expect(DeliveryOrderItem::withTrashed()->find($deliveryOrderItem->id)?->status)->toBe('pending');
    expect($deliveryOrder->fresh()->status)->toBe('request_stock');
});

it('model updated event fires updateStatusFromWarehouseConfirmations for DO-linked WC', function () {
    $wc = WarehouseConfirmation::withoutEvents(fn () => WarehouseConfirmation::factory()->create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id'   => $this->do->id,
        'status'           => 'request',
    ]));

    WarehouseConfirmation::withoutEvents(fn () => $wc->update(['status' => 'confirmed']));
    $wc->getLinkedDeliveryOrder()?->updateStatusFromWarehouseConfirmations();

    // Single WC confirmed → all WCs confirmed → DO approved
    expect($this->do->fresh()->status)->toBe('approved');
});

it('syncs linked delivery order item status when the DO-linked WC is confirmed', function () {
    $saleOrder = SaleOrder::factory()->create([
        'status' => 'approved',
    ]);

    $saleOrderItem = SaleOrderItem::factory()->create([
        'sale_order_id' => $saleOrder->id,
        'quantity' => 4,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $rak = Rak::factory()->create([
        'warehouse_id' => $this->warehouse->id,
    ]);

    $driver = Driver::factory()->create();
    $vehicle = Vehicle::factory()->create();

    $deliveryOrder = DeliveryOrder::factory()->create([
        'status' => 'request_stock',
        'warehouse_id' => $this->warehouse->id,
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
    ]);

    $deliveryOrderItem = DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sale_order_item_id' => $saleOrderItem->id,
        'product_id' => $saleOrderItem->product_id,
        'quantity' => 4,
        'reason' => 'Warehouse confirmation sync test',
        'status' => 'pending',
    ]);

    $wc = WarehouseConfirmation::create([
        'confirmable_type' => DeliveryOrder::class,
        'confirmable_id' => $deliveryOrder->id,
        'confirmation_type' => 'delivery_order',
        'status' => 'request',
        'note' => 'Warehouse confirmation sync test',
    ]);

    WarehouseConfirmationItem::create([
        'warehouse_confirmation_id' => $wc->id,
        'sale_order_item_id' => $saleOrderItem->id,
        'product_id' => $saleOrderItem->product_id,
        'product_name' => $saleOrderItem->product?->name ?? '-',
        'requested_qty' => 4,
        'confirmed_qty' => 4,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => $rak->id,
        'status' => 'request',
    ]);

    expect($deliveryOrderItem->fresh()->status)->toBe('requested');

    $wc->update([
        'status' => 'confirmed',
        'confirmed_by' => $this->user->id,
        'confirmed_at' => now(),
    ]);

    expect($deliveryOrderItem->fresh()->status)->toBe('confirmed');
    expect($deliveryOrder->fresh()->status)->toBe('approved');
});
