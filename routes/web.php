<?php

use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Route;

Route::get('testing', function () {
    return UnitOfMeasure::inRandomOrder()->first();
});
