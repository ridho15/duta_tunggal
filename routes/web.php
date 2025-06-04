<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Route;

Route::get('testing', function(){
    return UnitOfMeasure::inRandomOrder()->first();
});
