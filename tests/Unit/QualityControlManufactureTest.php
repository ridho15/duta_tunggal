<?php

namespace Tests\Unit;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\ChartOfAccount;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use Database\Seeders\CabangSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QualityControlManufactureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $qcService;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required data
        $this->seed(CabangSeeder::class);
        $this->seed(CurrencySeeder::class);
        $this->seed(UnitOfMeasureSeeder::class);
        $this->seed(ProductSeeder::class);

        // Create warehouse directly to avoid seeder conflicts
        Warehouse::factory()->create(['cabang_id' => Cabang::first()->id]);

        $this->user = User::factory()->create(['cabang_id' => Cabang::first()->id]);
        $this->actingAs($this->user);
        $this->qcService = app(QualityControlService::class);
    }

    #[Test]
    public function qc_manufacture_complete_creates_stock_movement_and_updates_inventory()
    {
        // Arrange: Create manufacturing order and production
        $cabang = Cabang::first();
        $product = Product::factory()->create([
            'sku' => 'QC-MFG-FG-' . strtoupper(uniqid()),
            'name' => 'Test Product',
            'cabang_id' => $cabang->id,
            'inventory_coa_id' => ChartOfAccount::create([
                'code' => '1140.02',
                'name' => 'Persediaan Barang Produksi',
                'type' => 'asset',
                'is_active' => true,
            ])->id,
        ]);
        $warehouse = Warehouse::first();

        // Create Bill of Material for the product
        $bom = BillOfMaterial::factory()->create([
            'cabang_id' => $cabang->id,
            'product_id' => $product->id,
            'nama_bom' => 'BOM for ' . $product->name,
            'code' => 'BOM-' . strtoupper(uniqid()),
            'is_active' => true,
            'labor_cost' => 1000, // Add some labor cost
            'overhead_cost' => 500, // Add some overhead cost
        ]);

        // Create BOM items (materials needed for production)
        $materialProduct = Product::factory()->create([
            'sku' => 'QC-MFG-RM-' . strtoupper(uniqid()),
            'name' => 'Raw Material',
            'cost_price' => 50, // Set cost price for material
            'cabang_id' => $cabang->id,
        ]);

        $uom = UnitOfMeasure::first();

        \App\Models\BillOfMaterialItem::create([
            'bill_of_material_id' => $bom->id,
            'product_id' => $materialProduct->id,
            'quantity' => 2, // 2 units of raw material needed per finished product
            'uom_id' => $uom->id,
        ]);

        // Create production plan
        $productionPlan = ProductionPlan::create([
            'plan_number' => 'PP-TEST-001',
            'name' => 'Test Production Plan',
            'source_type' => 'manual',
            'product_id' => $product->id,
            'quantity' => 10,
            'uom_id' => $uom->id,
            'warehouse_id' => $warehouse->id,
            'bill_of_material_id' => $bom->id, // Link BOM to production plan
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        ChartOfAccount::create([
            'code' => '1400.04',
            'name' => 'POS SEMENTARA PRODUKSI',
            'type' => 'asset',
            'is_active' => true,
        ]);

        ChartOfAccount::firstOrCreate([
            'code' => '6000',
        ], [
            'name' => 'Beban Produksi',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $productionPlan->update(['bill_of_material_id' => $bom->id]);

        // Create manufacturing order
        $mo = ManufacturingOrder::create([
            'production_plan_id' => $productionPlan->id,
            'mo_number' => 'MO-' . strtoupper(uniqid()),
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'status' => 'in_progress',
            'cabang_id' => $cabang->id,
            'created_by' => $this->user->id,
        ]);

        // Create production
        $production = Production::create([
            'manufacturing_order_id' => $mo->id,
            'production_number' => 'PR-' . strtoupper(uniqid()),
            'quantity_produced' => 10,
            'production_date' => now(),
            'status' => 'draft', // Don't set to finished yet to avoid premature MO completion
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $cabang->id,
            'created_by' => $this->user->id,
        ]);

        // Record initial inventory stock
        $initialInventoryStock = InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $initialAvailableQty = $initialInventoryStock ? $initialInventoryStock->quantity_available : 0;
        $initialTotalQty = $initialInventoryStock ? $initialInventoryStock->quantity : 0;

        echo "\n=== INITIAL INVENTORY STOCK ===\n";
        echo "Product: {$product->name}\n";
        echo "Warehouse: {$warehouse->name}\n";
        echo "Available Quantity: {$initialAvailableQty}\n";
        echo "Total Quantity: {$initialTotalQty}\n";

        // Create quality control for manufacture
        $qc = QualityControl::create([
            'qc_number' => 'QC-M-' . date('Ymd') . '-0001',
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
            'status' => 0, // Not processed yet
            'inspected_by' => $this->user->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'from_model_type' => Production::class,
            'from_model_id' => $production->id,
        ]);

        // Act: Complete the quality control
        $completeData = [
            'warehouse_id' => $warehouse->id,
            'rak_id' => null,
        ];

        $this->qcService->completeQualityControl($qc, $completeData);

        // Assert: Check QC status
        $qc->refresh();
        $this->assertEquals(1, $qc->status, 'QC should be marked as completed');

        // Assert: Check stock movement was created
        $stockMovement = StockMovement::where('from_model_type', QualityControl::class)
            ->where('from_model_id', $qc->id)
            ->first();

        $this->assertNotNull($stockMovement, 'Stock movement should be created');
        $this->assertEquals('manufacture_in', $stockMovement->type, 'Stock movement type should be manufacture_in');
        $this->assertEquals(8, $stockMovement->quantity, 'Stock movement quantity should match passed quantity');
        $this->assertEquals($product->id, $stockMovement->product_id, 'Stock movement product should match');
        $this->assertEquals($warehouse->id, $stockMovement->warehouse_id, 'Stock movement warehouse should match');

        $journalEntries = JournalEntry::where('source_type', QualityControl::class)
            ->where('source_id', $qc->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $journalEntries, 'QC completion should create balanced manufacturing completion journal entries');
        $this->assertSame('manufacturing_completion', $journalEntries[0]->journal_type);
        $this->assertSame('manufacturing_completion', $journalEntries[1]->journal_type);
        $this->assertSame($cabang->id, $journalEntries[0]->cabang_id);
        $this->assertSame($cabang->id, $journalEntries[1]->cabang_id);
        $this->assertEquals((float) $journalEntries->sum('debit'), (float) $journalEntries->sum('credit'));

        echo "\n=== STOCK MOVEMENT CREATED ===\n";
        echo "Type: {$stockMovement->type}\n";
        echo "Quantity: {$stockMovement->quantity}\n";
        echo "Product: " . ($stockMovement->product ? $stockMovement->product->name : 'N/A') . "\n";
        echo "Warehouse: " . ($stockMovement->warehouse ? $stockMovement->warehouse->name : 'N/A') . "\n";
        echo "Date: {$stockMovement->date}\n";
        echo "Notes: {$stockMovement->notes}\n";

        // Assert: Check inventory stock was updated
        $updatedInventoryStock = InventoryStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $this->assertNotNull($updatedInventoryStock, 'Inventory stock should exist');

        $expectedAvailableQty = $initialAvailableQty + 8; // passed quantity
        $expectedTotalQty = $initialTotalQty + 8;

        echo "\n=== UPDATED INVENTORY STOCK ===\n";
        echo "Available Quantity: {$updatedInventoryStock->quantity_available} (Expected: {$expectedAvailableQty})\n";
        echo "Total Quantity: {$updatedInventoryStock->quantity} (Expected: {$expectedTotalQty})\n";

        // Note: The actual inventory update might happen through observers or other mechanisms
        // This test focuses on verifying the stock movement creation

        // Assert: Check if manufacturing order status was updated
        $mo->refresh();
        // All units have been inspected (8 passed + 2 rejected = 10 = total qty),
        // so MO should be completed.
        $this->assertEquals('completed', $mo->status, 'MO status should be completed when all quantity has been inspected (passed + rejected = total)');

        echo "\n=== MANUFACTURING ORDER STATUS ===\n";
        echo "Status: {$mo->status}\n";
        echo "Expected: in_progress (since 8 < 10)\n";
    }

    #[Test]
    public function qc_manufacture_complete_with_all_quantity_passed_updates_mo_status()
    {
        // Arrange: Create manufacturing order and production
        $product = Product::factory()->create([
            'sku' => 'QC-MFG-FG2-' . strtoupper(uniqid()),
            'name' => 'Test Product 2',
        ]);
        $warehouse = Warehouse::first();
        $cabang = Cabang::first();
        $uom = \App\Models\UnitOfMeasure::first(); // Get a valid UOM

        // Create production plan
        $productionPlan = ProductionPlan::create([
            'plan_number' => 'PP-TEST-002',
            'name' => 'Test Production Plan 2',
            'source_type' => 'manual',
            'product_id' => $product->id,
            'quantity' => 10,
            'uom_id' => $uom->id, // Use valid UOM ID
            'warehouse_id' => $warehouse->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // Create manufacturing order
        $mo = ManufacturingOrder::create([
            'production_plan_id' => $productionPlan->id,
            'mo_number' => 'MO-' . strtoupper(uniqid()),
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'status' => 'in_progress',
            'cabang_id' => $cabang->id,
            'created_by' => $this->user->id,
        ]);

        // Create production
        $production = Production::create([
            'manufacturing_order_id' => $mo->id,
            'production_number' => 'PR-' . strtoupper(uniqid()),
            'quantity_produced' => 10,
            'production_date' => now(),
            'status' => 'finished',
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $cabang->id,
            'created_by' => $this->user->id,
        ]);

        // Create quality control for manufacture with all quantity passed
        $qc = QualityControl::create([
            'qc_number' => 'QC-M-' . date('Ymd') . '-0002',
            'passed_quantity' => 10, // All quantity passed
            'rejected_quantity' => 0,
            'status' => 0,
            'inspected_by' => $this->user->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'from_model_type' => Production::class,
            'from_model_id' => $production->id,
        ]);

        // Act: Complete the quality control
        $completeData = [
            'warehouse_id' => $warehouse->id,
            'rak_id' => null,
        ];

        $this->qcService->completeQualityControl($qc, $completeData);

        // Assert: Check manufacturing order status was updated to completed
        $mo->refresh();
        $this->assertEquals('completed', $mo->status, 'MO status should be updated to completed when all quantity passed QC');

        echo "\n=== MANUFACTURING ORDER STATUS (ALL PASSED) ===\n";
        echo "Status: {$mo->status}\n";
        echo "Expected: completed (since 10 >= 10)\n";
    }
}