<?php

use App\Models\Product;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChartOfAccountSeeder::class);
});

it('seeds products with the canonical inventory and temporary procurement coa defaults', function () {
    $this->seed(ProductSeeder::class);

    $product = Product::query()->orderBy('id')->first();

    expect($product)->not->toBeNull()
        ->and($product->inventoryCoa?->code)->toBe('1140.01')
        ->and($product->temporaryProcurementCoa?->code)->toBe('1400.01');
});