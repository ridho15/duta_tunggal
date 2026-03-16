<?php

use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tests for OrderRequest::syncFulfillmentStatus()
 *
 * Verifies that:
 *  - When all items are fully fulfilled → status becomes 'complete'
 *  - When some items are partially fulfilled → status becomes 'partial'
 *  - When nothing is fulfilled → status stays at 'approved'
 *  - Draft/closed/rejected are never auto-transitioned
 */
describe('OrderRequest::syncFulfillmentStatus()', function () {

    it('transitions approved → complete when all items fully fulfilled', function () {
        $or = OrderRequest::factory()->create(['status' => 'approved']);
        $item = OrderRequestItem::factory()->create([
            'order_request_id'    => $or->id,
            'quantity'            => 10,
            'fulfilled_quantity'  => 10,
        ]);

        $or->syncFulfillmentStatus();
        $or->refresh();

        expect($or->status)->toBe('complete');
    });

    it('transitions approved → partial when some items partially fulfilled', function () {
        $or = OrderRequest::factory()->create(['status' => 'approved']);
        OrderRequestItem::factory()->create([
            'order_request_id'   => $or->id,
            'quantity'           => 10,
            'fulfilled_quantity' => 5,
        ]);
        OrderRequestItem::factory()->create([
            'order_request_id'   => $or->id,
            'quantity'           => 20,
            'fulfilled_quantity' => 0,
        ]);

        $or->syncFulfillmentStatus();
        $or->refresh();

        expect($or->status)->toBe('partial');
    });

    it('stays approved when nothing is fulfilled', function () {
        $or = OrderRequest::factory()->create(['status' => 'approved']);
        OrderRequestItem::factory()->create([
            'order_request_id'   => $or->id,
            'quantity'           => 10,
            'fulfilled_quantity' => 0,
        ]);

        $or->syncFulfillmentStatus();
        $or->refresh();

        expect($or->status)->toBe('approved');
    });

    it('does NOT transition a draft order', function () {
        $or = OrderRequest::factory()->create(['status' => 'draft']);
        OrderRequestItem::factory()->create([
            'order_request_id'   => $or->id,
            'quantity'           => 5,
            'fulfilled_quantity' => 5,
        ]);

        $or->syncFulfillmentStatus();
        $or->refresh();

        expect($or->status)->toBe('draft');
    });

    it('does NOT transition a closed order', function () {
        $or = OrderRequest::factory()->create(['status' => 'closed']);
        OrderRequestItem::factory()->create([
            'order_request_id'   => $or->id,
            'quantity'           => 5,
            'fulfilled_quantity' => 5,
        ]);

        $or->syncFulfillmentStatus();
        $or->refresh();

        expect($or->status)->toBe('closed');
    });

    it('transitions partial → complete once last item is fulfilled', function () {
        $or = OrderRequest::factory()->create(['status' => 'partial']);
        OrderRequestItem::factory()->create([
            'order_request_id'   => $or->id,
            'quantity'           => 10,
            'fulfilled_quantity' => 10,
        ]);
        OrderRequestItem::factory()->create([
            'order_request_id'   => $or->id,
            'quantity'           => 20,
            'fulfilled_quantity' => 20,
        ]);

        $or->syncFulfillmentStatus();
        $or->refresh();

        expect($or->status)->toBe('complete');
    });

    it('remaining_quantity accessor returns correct value', function () {
        $item = OrderRequestItem::factory()->make([
            'quantity'           => 100,
            'fulfilled_quantity' => 60,
        ]);

        expect($item->remaining_quantity)->toBe(40);
    });

    it('remaining_quantity is never negative', function () {
        $item = OrderRequestItem::factory()->make([
            'quantity'           => 10,
            'fulfilled_quantity' => 20, // over-fulfilled edge case
        ]);

        expect($item->remaining_quantity)->toBe(0);
    });
});
