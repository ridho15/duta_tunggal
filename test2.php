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
    $supplier = Supplier::first();
    $product = Product::first();
    $product->supplier_id = $supplier->id;
    $product->save();
}

echo "Testing Product ID: " . $product->id . " Name: " . $product->name . PHP_EOL;
echo "Product supplier_id: " . $product->supplier_id . PHP_EOL;
echo "Recommended supplier ID: " . OrderRequestResource::resolveProductSupplierId($product->id) . PHP_EOL;
echo "Options:" . PHP_EOL;
print_r(OrderRequestResource::resolveSupplierOptions($product->id));

