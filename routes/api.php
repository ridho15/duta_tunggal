<?php

use App\Http\Controllers\Api\OrderRequestApiController;
use App\Http\Controllers\Api\PurchaseOrderApiController;
use App\Http\Controllers\Api\QuotationApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::prefix('v1')->group(function () {
    // Order Requests
    Route::prefix('order-requests')->group(function () {
        Route::get('/dependencies', [OrderRequestApiController::class, 'dependencies'])->name('api.order-requests.dependencies');
        Route::get('/generate-number', [OrderRequestApiController::class, 'generateNumber'])->name('api.order-requests.generate-number');
        Route::post('/', [OrderRequestApiController::class, 'store'])->name('api.order-requests.store');
        Route::get('/{id}', [OrderRequestApiController::class, 'show'])->name('api.order-requests.show');
        Route::put('/{id}', [OrderRequestApiController::class, 'update'])->name('api.order-requests.update');
    });

    // Purchase Orders
    Route::prefix('purchase-orders')->group(function () {
        Route::get('/dependencies', [PurchaseOrderApiController::class, 'dependencies'])->name('api.purchase-orders.dependencies');
        Route::get('/reference-items', [PurchaseOrderApiController::class, 'referenceItems'])->name('api.purchase-orders.reference-items');
        Route::get('/generate-number', [PurchaseOrderApiController::class, 'generateNumber'])->name('api.purchase-orders.generate-number');
        Route::post('/', [PurchaseOrderApiController::class, 'store'])->name('api.purchase-orders.store');
        Route::get('/{id}', [PurchaseOrderApiController::class, 'show'])->name('api.purchase-orders.show');
        Route::put('/{id}', [PurchaseOrderApiController::class, 'update'])->name('api.purchase-orders.update');
    });

    // Quotations
    Route::prefix('quotations')->group(function () {
        Route::get('/dependencies', [QuotationApiController::class, 'dependencies'])->name('api.quotations.dependencies');
        Route::get('/generate-number', [QuotationApiController::class, 'generateNumber'])->name('api.quotations.generate-number');
        Route::post('/', [QuotationApiController::class, 'store'])->name('api.quotations.store');
        Route::get('/{id}', [QuotationApiController::class, 'show'])->name('api.quotations.show');
        Route::put('/{id}', [QuotationApiController::class, 'update'])->name('api.quotations.update');
    });

    // Sales Orders
    Route::prefix('sales-orders')->group(function () {
        Route::get('/dependencies', [\App\Http\Controllers\Api\SaleOrderApiController::class, 'dependencies'])->name('api.sales-orders.dependencies');
        Route::get('/generate-number', [\App\Http\Controllers\Api\SaleOrderApiController::class, 'generateNumber'])->name('api.sales-orders.generate-number');
        Route::get('/quotation/{id}', [\App\Http\Controllers\Api\SaleOrderApiController::class, 'getQuotation'])->name('api.sales-orders.quotation');
        Route::get('/customer-credit/{id}', [\App\Http\Controllers\Api\SaleOrderApiController::class, 'getCustomerCredit'])->name('api.sales-orders.customer-credit');
        Route::post('/', [\App\Http\Controllers\Api\SaleOrderApiController::class, 'store'])->name('api.sales-orders.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\SaleOrderApiController::class, 'show'])->name('api.sales-orders.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\SaleOrderApiController::class, 'update'])->name('api.sales-orders.update');
    });
});
