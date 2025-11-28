<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TestModel;

var_dump('Trait exists:', trait_exists('Illuminate\Database\Eloquent\SoftDeletes'));

var_dump('TestModel traits:', class_uses(TestModel::class));

var_dump('TestModel withTrashed:', method_exists(TestModel::class, 'withTrashed'));

var_dump('TestModel methods:', get_class_methods(TestModel::class));