<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MaterialFulfillment;
use App\Models\TestModel;

var_dump('Trait exists:', trait_exists('Illuminate\Database\Eloquent\SoftDeletes'));

var_dump('MaterialFulfillment traits:', class_uses(MaterialFulfillment::class));
var_dump('TestModel traits:', class_uses(TestModel::class));

var_dump('MaterialFulfillment withTrashed:', method_exists(MaterialFulfillment::class, 'withTrashed'));
var_dump('TestModel withTrashed:', method_exists(TestModel::class, 'withTrashed'));

var_dump('MaterialFulfillment methods:', get_class_methods(MaterialFulfillment::class));