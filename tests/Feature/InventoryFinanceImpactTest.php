<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\BalanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryFinanceImpactTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function inventory_stock_affects_balance_sheet()
    {
        // Seed master data
        $this->seed([
            \Database\Seeders\ZeroBalanceSheetSeeder::class, // This will clean journal entries
            \Database\Seeders\PermissionSeeder::class,
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\CurrencySeeder::class,
            \Database\Seeders\UnitOfMeasureSeeder::class,
            \Database\Seeders\CabangSeeder::class,
            \Database\Seeders\ChartOfAccountSeeder::class,
            \Database\Seeders\MasterDataSeeder::class,
            \Database\Seeders\ProductCategorySeeder::class,
            \Database\Seeders\CustomerSeeder::class,
            \Database\Seeders\SupplierSeeder::class,
            \Database\Seeders\DriverSeeder::class,
            \Database\Seeders\VehicleSeeder::class,
            \Database\Seeders\ProductSeeder::class,
            \Database\Seeders\BillOfMaterialSeeder::class,
            \Database\Seeders\WarehouseSeeder::class,
            \Database\Seeders\RakSeeder::class,
        ]);

        // Get initial balance sheet (should be zero after ZeroBalanceSheetSeeder)
        $initialBalance = app(BalanceSheetService::class)->generate();
        $this->assertEquals(0, $initialBalance['total_assets']);
        $this->assertEquals(0, $initialBalance['total_liabilities']);
        $this->assertEquals(0, $initialBalance['total_equity']);
        $this->assertTrue($initialBalance['is_balanced']);

        // Create inventory stock manually with unique product/warehouse combination
        $product = Product::factory()->create([
            'name' => 'Test Product for Finance Impact',
            'sku' => 'TEST-FINANCE-001',
            'cost_price' => 50000,
            'sell_price' => 75000,
        ]);
        $warehouse = Warehouse::factory()->create([
            'name' => 'Test Warehouse for Finance',
            'kode' => 'TEST-WH-001',
            'location' => 'Test Location',
            'cabang_id' => 1,
            'tipe' => 'Kecil',
            'status' => true,
        ]);
        $coa = ChartOfAccount::where('code', '1-1300')->first(); // Persediaan COA

        $inventoryStock = InventoryStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty_available' => 100,
            'qty_reserved' => 0,
            'qty_min' => 10,
        ]);

        // Calculate expected value (qty * cost_price)
        $expectedValue = $inventoryStock->qty_available * $product->cost_price;

        // Get balance sheet after creating inventory stock
        $afterBalance = app(BalanceSheetService::class)->generate();

        // Assert that inventory stock affects balance sheet
        $this->assertGreaterThan(0, $afterBalance['total_assets'], 'Inventory stock should increase total assets');
        $this->assertEquals($expectedValue, $afterBalance['total_assets'], 'Total assets should equal inventory value');
        $this->assertTrue($afterBalance['is_balanced'], 'Balance sheet should still be balanced');

        // Check that persediaan COA has balance
        $persediaanAccount = collect($afterBalance['current_assets']['accounts'])
            ->firstWhere('kode', $coa->code);
        $this->assertNotNull($persediaanAccount, 'Persediaan COA should have balance');
        $this->assertEquals($expectedValue, $persediaanAccount['balance'], 'Persediaan COA balance should equal inventory value');
    }
}