<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\Supplier;
use App\Filament\Resources\OrderRequestResource;

$product = Product::whereNotNull('supplier_id')->first();
if (!$product) {
    echo "No product found with a supplier_id." . PHP_EOL;
    // let's create a fake one
    $supplier = Supplier::first();
    $product = Product::first();
    $product->supplier_id = $supplier->id;
    $product->save();
}

echo "Testing Product ID: " . $product->id . " Name: " . $product->name . PHP_EOL;
echo "Product supplier_id: " . $product->supplier_id . PHP_EOL;
echo "Pivot suppliers count: " . $product->suppliers()->count() . PHP_EOL;

echo "Recommended supplier ID (resolveSupplierId): " . OrderRequestResource::resolveSupplierId($product->id) . PHP_EOL;
echo "Options (resolveSupplierOptions):" . PHP_EOL;
$options = OrderRequestResource::resolveSupplierOptions($product->id);
print_r($options);
