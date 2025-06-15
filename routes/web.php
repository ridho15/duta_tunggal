<?php

use App\Http\Controllers\PurchaseOrderController;
use App\Models\DeliveryOrder;
use App\Models\ReturnProduct;
use App\Models\SaleOrderItem;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Route;

Route::get('testing', function () {
    $returnProduct = ReturnProduct::has('warehouse.rak')->inRandomOrder()->first();
    $deliveryOrderItem = $returnProduct->fromModel->deliveryOrderItem()->inRandomOrder()->first();
    $rakId = $returnProduct->warehouse->rak()->inRandomOrder()->get();

    return $rakId;
});
