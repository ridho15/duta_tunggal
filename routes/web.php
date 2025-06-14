<?php

use App\Http\Controllers\PurchaseOrderController;
use App\Models\DeliveryOrder;
use App\Models\SaleOrderItem;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Route;

Route::get('testing', function () {
    $deliveryOrder = DeliveryOrder::has('deliverySalesOrder')->inRandomOrder()->first();
    $saleOrderItem = SaleOrderItem::whereHas('saleOrder', function ($query) use ($deliveryOrder) {
        $query->whereHas('deliverySalesOrder', function ($query) use ($deliveryOrder) {
            $query->where('delivery_order_id', $deliveryOrder->id);
        });
    })->inRandomOrder()->first();

    return $saleOrderItem;
});
