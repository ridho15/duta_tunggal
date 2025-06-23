<?php

use App\Models\DeliveryOrder;
use App\Models\PurchaseOrder;
use App\Models\QualityControl;
use App\Models\ReturnProduct;
use App\Services\ReturnProductService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


Route::get('testing', function () {
    $returnProduct = ReturnProduct::find(26);
    return $returnProduct->fromModel;
});
