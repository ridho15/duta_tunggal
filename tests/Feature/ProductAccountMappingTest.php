<?php

use App\Models\Product;
use App\Models\ChartOfAccount;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChartOfAccountSeeder::class);

    ChartOfAccount::firstOrCreate(
        ['code' => '2100.10'],
        ['name' => 'Pembelian Belum Tertagih', 'type' => 'Liability', 'is_active' => true]
    );
});

it('sets default chart of accounts on newly created products', function () {
    $product = Product::factory()->create();

    expect($product->inventoryCoa?->code)->toBe('1140.01')
        ->and($product->salesCoa?->code)->toBe('4100.10')
        ->and($product->salesReturnCoa?->code)->toBe('4120.10')
        ->and($product->salesDiscountCoa?->code)->toBe('4110.10')
    ->and($product->goodsDeliveryCoa?->code)->toBe('1140.20')
        ->and($product->cogsCoa?->code)->toBe('5100.10')
        ->and($product->purchaseReturnCoa?->code)->toBe('5120.10')
        ->and($product->unbilledPurchaseCoa?->code)->toBe('2100.10');
});
