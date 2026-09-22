<?php

use App\Filament\Resources\MaterialIssueResource;
use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('material issue warehouse resolver only returns stock-backed warehouses', function () {
    $branch = Cabang::factory()->create();
    $uom = UnitOfMeasure::factory()->create();
    $category = ProductCategory::factory()->create();

    $warehouseWithoutStock = Warehouse::factory()->create([
        'cabang_id' => $branch->id,
        'kode' => 'WH-NO-STOCK',
        'name' => 'Warehouse Without Stock',
    ]);

    $warehouseWithStock = Warehouse::factory()->create([
        'cabang_id' => $branch->id,
        'kode' => 'WH-HAS-STOCK',
        'name' => 'Warehouse With Stock',
    ]);

    $product = Product::factory()->create([
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 12500,
    ]);

    InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouseWithStock->id,
        'qty_available' => 14,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $options = MaterialIssueResource::resolveWarehouseOptionsForProduct($product->id);

    expect($options)
        ->toHaveKey($warehouseWithStock->id)
        ->not->toHaveKey($warehouseWithoutStock->id);

    expect(MaterialIssueResource::resolveWarehouseIdForProduct($product->id, $warehouseWithoutStock->id))
        ->toBe($warehouseWithStock->id);
});
