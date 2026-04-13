<?php

use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChartOfAccountSeeder::class);
});

function createProductWithoutObserver(array $attributes = []): Product
{
    $category = ProductCategory::factory()->create();
    $uom = UnitOfMeasure::factory()->create();

    return Product::withoutEvents(function () use ($attributes, $category, $uom) {
        return Product::factory()->create(array_merge([
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'inventory_coa_id' => null,
            'sales_coa_id' => null,
            'sales_return_coa_id' => null,
            'sales_discount_coa_id' => null,
            'goods_delivery_coa_id' => null,
            'cogs_coa_id' => null,
            'purchase_return_coa_id' => null,
            'unbilled_purchase_coa_id' => null,
            'temporary_procurement_coa_id' => null,
            'manufacturing_labor_coa_id' => null,
            'manufacturing_overhead_coa_id' => null,
        ], $attributes));
    });
}

it('backfill command keeps dry-run read-only', function () {
    $product = createProductWithoutObserver();

    Artisan::call('products:backfill-default-coa');

    $product->refresh();

    expect($product->inventory_coa_id)->toBeNull()
        ->and($product->sales_coa_id)->toBeNull()
        ->and($product->temporary_procurement_coa_id)->toBeNull();
});

it('backfill command fills product coa fields using create-form defaults', function () {
    $standard = createProductWithoutObserver(['sku' => 'BACKFILL-STD']);
    $manufacture = createProductWithoutObserver([
        'sku' => 'BACKFILL-MFG',
        'is_manufacture' => true,
        'is_raw_material' => false,
    ]);
    $rawMaterial = createProductWithoutObserver([
        'sku' => 'BACKFILL-RAW',
        'is_manufacture' => false,
        'is_raw_material' => true,
    ]);

    $result = Artisan::call('products:backfill-default-coa', [
        '--execute' => true,
        '--force' => true,
        '--chunk' => 2,
    ]);

    expect($result)->toBe(0);

    $standard->refresh();
    $manufacture->refresh();
    $rawMaterial->refresh();

    expect($standard->inventoryCoa?->code)->toBe('1140.01')
        ->and($standard->salesCoa?->code)->toBe('4100.10')
        ->and($standard->salesReturnCoa?->code)->toBe('4120.10')
        ->and($standard->salesDiscountCoa?->code)->toBe('4110.10')
        ->and($standard->goodsDeliveryCoa?->code)->toBe('1140.20')
        ->and($standard->cogsCoa?->code)->toBe('5100.10')
        ->and($standard->purchaseReturnCoa?->code)->toBe('5120.10')
        ->and($standard->unbilledPurchaseCoa?->code)->toBe('2100.10')
        ->and($standard->temporaryProcurementCoa?->code)->toBe('2100.10');

    expect($manufacture->inventoryCoa?->code)->toBe('1140.02')
        ->and($rawMaterial->inventoryCoa?->code)->toBe('1-101');

    expect($standard->manufacturingLaborCoa?->code)->toBe('5230')
        ->and($standard->manufacturingOverheadCoa?->code)->toBe('6000');
});