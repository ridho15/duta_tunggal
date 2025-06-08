<?php

use App\Http\Controllers\PurchaseOrderController;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Route;

Route::get('cetak-pdf/{id}', [PurchaseOrderController::class, 'cetakPdf'])->name('purchase-order.cetak');
