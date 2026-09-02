<?php

use App\Http\Controllers\Api\OrderRequestApiController;
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
});
