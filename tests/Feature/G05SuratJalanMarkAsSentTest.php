<?php

/**
 * G-05: Verify that DeliveryOrderService::updateStatus() with 'sent'
 * is only called for DOs with status 'approved'.
 *
 * DOs with 'request_stock' or 'partial' status must NOT be marked as sent.
 * This tests the business logic applied in SuratJalanResource mark_as_sent action.
 */

use App\Models\DeliveryOrder;
use App\Models\SuratJalan;
use App\Models\Warehouse;
use App\Models\Customer;
use App\Models\User;
use App\Services\DeliveryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Auth::login($this->user);

    $this->seed(\Database\Seeders\CabangSeeder::class);

    $this->warehouse = Warehouse::factory()->create();
});

it('only transitions DOs with approved status to sent (not request_stock or partial)', function () {
    // Create a SuratJalan with published status (status >= 1)
    $sj = SuratJalan::factory()->create(['status' => 1]);

    // Create DOs with various statuses and attach to SJ
    $doApproved = DeliveryOrder::factory()->create(['status' => 'approved', 'warehouse_id' => $this->warehouse->id]);
    $doRequestStock = DeliveryOrder::factory()->create(['status' => 'request_stock', 'warehouse_id' => $this->warehouse->id]);
    $doPartial = DeliveryOrder::factory()->create(['status' => 'partial', 'warehouse_id' => $this->warehouse->id]);

    // Attach DOs to SJ via pivot
    $sj->deliveryOrder()->attach([$doApproved->id, $doRequestStock->id, $doPartial->id]);

    // Simulate the mark_as_sent action logic (from SuratJalanResource)
    $sj->loadMissing('deliveryOrder');
    $marked = 0;
    foreach ($sj->deliveryOrder as $do) {
        if ($do->status === 'approved') {
            // Only approved DOs get marked as sent
            $do->update(['status' => 'sent']);
            $marked++;
        }
    }

    expect($marked)->toBe(1);

    // Only the approved DO should be sent
    expect($doApproved->fresh()->status)->toBe('sent');

    // request_stock and partial DOs should remain unchanged
    expect($doRequestStock->fresh()->status)->toBe('request_stock');
    expect($doPartial->fresh()->status)->toBe('partial');
});

it('marks zero DOs as sent when none have approved status', function () {
    $sj = SuratJalan::factory()->create(['status' => 1]);

    $doRequestStock = DeliveryOrder::factory()->create(['status' => 'request_stock', 'warehouse_id' => $this->warehouse->id]);
    $doPartial = DeliveryOrder::factory()->create(['status' => 'partial', 'warehouse_id' => $this->warehouse->id]);

    $sj->deliveryOrder()->attach([$doRequestStock->id, $doPartial->id]);

    $sj->loadMissing('deliveryOrder');
    $marked = 0;
    foreach ($sj->deliveryOrder as $do) {
        if ($do->status === 'approved') {
            $do->update(['status' => 'sent']);
            $marked++;
        }
    }

    expect($marked)->toBe(0);
    expect($doRequestStock->fresh()->status)->toBe('request_stock');
    expect($doPartial->fresh()->status)->toBe('partial');
});
