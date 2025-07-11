<?php

use App\Models\PurchaseReceiptItem;
use Illuminate\Support\Facades\Route;


Route::get('testing', function () {
    $purchaseReceiptItem = PurchaseReceiptItem::find(23);
    return $purchaseReceiptItem->qualityControl;
});
