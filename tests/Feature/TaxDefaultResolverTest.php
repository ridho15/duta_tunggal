<?php

use App\Models\Product;
use App\Models\TaxSetting;
use App\Support\TaxDefaultResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tax resolver prioritizes active setting over product tax', function () {
    TaxSetting::factory()->create([
        'type' => 'PPN',
        'rate' => 11,
        'status' => true,
        'effective_date' => now()->subDay(),
    ]);

    $product = Product::factory()->create([
        'pajak' => 7,
    ]);

    $rate = TaxDefaultResolver::resolveForProductId($product->id, 'PPN Excluded');

    expect($rate)->toBe(11.0);
});

test('tax resolver falls back to product tax when no active setting exists', function () {
    $product = Product::factory()->create([
        'pajak' => 9,
    ]);

    $rate = TaxDefaultResolver::resolveForProductId($product->id, 'PPN Excluded');

    expect($rate)->toBe(9.0);
});

test('tax resolver returns zero for non tax type', function () {
    TaxSetting::factory()->create([
        'type' => 'PPN',
        'rate' => 12,
        'status' => true,
        'effective_date' => now()->subDay(),
    ]);

    $product = Product::factory()->create([
        'pajak' => 8,
    ]);

    $rate = TaxDefaultResolver::resolveForProductId($product->id, 'None');

    expect($rate)->toBe(0.0);
});
