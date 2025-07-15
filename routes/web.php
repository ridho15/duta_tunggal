<?php

use App\Models\Invoice;
use App\Models\PurchaseReceiptItem;
use Illuminate\Support\Facades\Route;


Route::get('testing', function () {
    $invoice = Invoice::find(4);
    return $invoice->fromModel->customer_id;
});
