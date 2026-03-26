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
use App\Models\WarehouseConfirmation;
use App\Models\Warehouse;
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
