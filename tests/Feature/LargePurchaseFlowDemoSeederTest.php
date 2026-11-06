<?php

use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use Database\Seeders\LargePurchaseFlowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('large purchase flow demo seeder creates one OR with two large PO records', function () {
    $this->seed(LargePurchaseFlowDemoSeeder::class);

    $orderRequest = OrderRequest::where('request_number', 'OR-LARGE-DEMO-001')->first();

    expect($orderRequest)->not->toBeNull();
    expect($orderRequest->orderRequestItem()->count())->toBe(120);

    $purchaseOrders = PurchaseOrder::whereIn('po_number', [
        'PO-LARGE-DEMO-A',
        'PO-LARGE-DEMO-B',
    ])->with('purchaseOrderItem')->get()->keyBy('po_number');

    expect($purchaseOrders)->toHaveCount(2);
    expect($purchaseOrders['PO-LARGE-DEMO-A']->refer_model_type)->toBe(OrderRequest::class);
    expect((int) $purchaseOrders['PO-LARGE-DEMO-A']->refer_model_id)->toBe((int) $orderRequest->id);
    expect($purchaseOrders['PO-LARGE-DEMO-A']->purchaseOrderItem)->toHaveCount(60);
    expect($purchaseOrders['PO-LARGE-DEMO-B']->purchaseOrderItem)->toHaveCount(60);

    $purchaseOrders->flatMap->purchaseOrderItem->each(function ($purchaseOrderItem) {
        expect($purchaseOrderItem->refer_item_model_type)->toBe(OrderRequestItem::class);
        expect($purchaseOrderItem->refer_item_model_id)->not->toBeNull();

        $sourceItem = OrderRequestItem::find($purchaseOrderItem->refer_item_model_id);

        expect($sourceItem)->not->toBeNull();
        expect($purchaseOrderItem->product_id)->toBe($sourceItem->product_id);
        expect($purchaseOrderItem->tipe_pajak)->toBe($sourceItem->tipe_pajak);
    });
});

