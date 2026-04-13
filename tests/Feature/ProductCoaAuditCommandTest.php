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

function createAuditedProduct(array $attributes = []): Product
{
    $category = ProductCategory::factory()->create();
    $uom = UnitOfMeasure::factory()->create();

    return Product::factory()->create(array_merge([
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
    ], $attributes));
}

it('reports products that are out of sync with the default coa form', function () {
    createAuditedProduct(['sku' => 'SYNC-001']);

    $outOfSync = createAuditedProduct(['sku' => 'DRIFT-001']);
    $wrongCoa = ChartOfAccount::firstOrCreate(
        ['code' => '9999.99'],
        ['name' => 'Dummy COA', 'type' => 'Asset', 'is_active' => true]
    );

    Product::query()->whereKey($outOfSync->id)->update([
        'sales_coa_id' => $wrongCoa->id,
    ]);

    $exitCode = Artisan::call('products:audit-default-coa', [
        '--limit' => 5,
        '--chunk' => 10,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Product COA audit summary')
        ->and($output)->toContain('products_out_of_sync')
        ->and($output)->toContain('sales_coa_id')
        ->and($output)->toContain('1');
});