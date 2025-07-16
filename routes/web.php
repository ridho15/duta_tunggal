<?php

use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\PurchaseReceiptItem;
use App\Models\VendorPaymentDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;


Route::get('testing', function () {
    $objects = [
        new App\Models\User(),
        new App\Models\Invoice(),
        new App\Models\User(),
        new App\Models\Customer(),
        new App\Models\Invoice(),
    ];

    // Ambil nama class dari setiap object
    $classNames = array_map(fn($obj) => get_class($obj), $objects);

    // Hitung kemunculan setiap class
    $classCounts = array_count_values($classNames);

    // Ambil class yang muncul lebih dari 1 kali (duplikat)
    $duplicates = array_filter($classCounts, fn($count) => $count > 1);

    // Hasil
    print_r(array_keys($duplicates));
});
