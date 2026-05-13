<?php

use Tests\TestCase;
use App\Models\Cabang;
use App\Models\Supplier;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Product;
use App\Filament\Resources\PurchaseOrderResource;

it('returns cabang ids filtered by supplier from order request', function () {
    $c1 = Cabang::factory()->create();
    $c2 = Cabang::factory()->create();

    $sA = Supplier::factory()->create();
    $sB = Supplier::factory()->create();

    // Ensure a user and warehouse exist for the order request factory
    User::factory()->create(['cabang_id' => $c1->id]);
    Warehouse::factory()->create(['cabang_id' => $c1->id]);
    // Ensure products exist for order request items
    Product::factory()->count(3)->create();
    $or = OrderRequest::factory()->create(['status' => 'approved']);

    // Create OR items across suppliers and cabangs
    OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'supplier_id' => $sA->id,
        'cabang_id' => $c1->id,
        'quantity' => 5,
    ]);

    OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'supplier_id' => $sA->id,
        'cabang_id' => $c2->id,
        'quantity' => 3,
    ]);

    OrderRequestItem::factory()->create([
        'order_request_id' => $or->id,
        'supplier_id' => $sB->id,
        'cabang_id' => $c1->id,
        'quantity' => 2,
    ]);

    $all = PurchaseOrderResource::getAvailableOrderRequestCabangIds($or, null);
    expect(collect($all)->sort()->values()->all())->toBe([$c1->id, $c2->id]);

    $forA = PurchaseOrderResource::getAvailableOrderRequestCabangIds($or, $sA->id);
    expect(collect($forA)->sort()->values()->all())->toBe([$c1->id, $c2->id]);

    $forB = PurchaseOrderResource::getAvailableOrderRequestCabangIds($or, $sB->id);
    expect(collect($forB)->sort()->values()->all())->toBe([$c1->id]);
});
