<?php

use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use Illuminate\Support\Collection;

function makeOrderRequestMock(string $status, array $items, ?array $expectedUpdate = null): OrderRequest
{
    $orderRequest = Mockery::mock(OrderRequest::class)->makePartial();
    $orderRequest->status = $status;

    $relation = Mockery::mock();
    $relation->shouldReceive('withoutTrashed')->andReturnSelf();
    $relation->shouldReceive('get')->andReturn(new Collection($items));

    $orderRequest->shouldReceive('orderRequestItem')->andReturn($relation);

    if ($expectedUpdate !== null) {
            $orderRequest->shouldReceive('update')
                ->once()
                ->with($expectedUpdate)
                ->andReturnUsing(function (array $attributes) use ($orderRequest) {
                    $orderRequest->status = $attributes['status'];

                    return true;
                });
    } else {
        $orderRequest->shouldReceive('update')->never();
    }

    return $orderRequest;
}

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
        $or = makeOrderRequestMock('approved', [
            new OrderRequestItem(['quantity' => 10, 'fulfilled_quantity' => 10]),
        ], ['status' => 'complete']);

        $or->syncFulfillmentStatus();

            expect($or->status)->toBe('complete');
    });

    it('transitions approved → partial when some items partially fulfilled', function () {
        $or = makeOrderRequestMock('approved', [
            new OrderRequestItem(['quantity' => 10, 'fulfilled_quantity' => 5]),
            new OrderRequestItem(['quantity' => 20, 'fulfilled_quantity' => 0]),
        ], ['status' => 'partial']);

        $or->syncFulfillmentStatus();

        expect($or->status)->toBe('partial');
    });

    it('stays approved when nothing is fulfilled', function () {
        $or = makeOrderRequestMock('approved', [
            new OrderRequestItem(['quantity' => 10, 'fulfilled_quantity' => 0]),
        ]);

        $or->syncFulfillmentStatus();

        expect($or->status)->toBe('approved');
    });

    it('does NOT transition a draft order', function () {
        $or = makeOrderRequestMock('draft', [
            new OrderRequestItem(['quantity' => 5, 'fulfilled_quantity' => 5]),
        ]);

        $or->syncFulfillmentStatus();

        expect($or->status)->toBe('draft');
    });

    it('does NOT transition a closed order', function () {
        $or = makeOrderRequestMock('closed', [
            new OrderRequestItem(['quantity' => 5, 'fulfilled_quantity' => 5]),
        ]);

        $or->syncFulfillmentStatus();

        expect($or->status)->toBe('closed');
    });

    it('transitions partial → complete once last item is fulfilled', function () {
        $or = makeOrderRequestMock('partial', [
            new OrderRequestItem(['quantity' => 10, 'fulfilled_quantity' => 10]),
            new OrderRequestItem(['quantity' => 20, 'fulfilled_quantity' => 20]),
        ], ['status' => 'complete']);

        $or->syncFulfillmentStatus();

            expect($or->status)->toBe('complete');
    });

    it('remaining_quantity accessor returns correct value', function () {
        $item = new OrderRequestItem([
            'quantity'           => 100,
            'fulfilled_quantity' => 60,
        ]);

        expect($item->remaining_quantity)->toBe(40);
    });

    it('remaining_quantity is never negative', function () {
        $item = new OrderRequestItem([
            'quantity'           => 10,
            'fulfilled_quantity' => 20, // over-fulfilled edge case
        ]);

        expect($item->remaining_quantity)->toBe(0);
    });
});
