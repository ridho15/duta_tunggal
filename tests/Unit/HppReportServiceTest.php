<?php

namespace Tests\Unit;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\CostVariance;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductStandardCost;
use App\Models\ProductionCostEntry;
use App\Models\Reports\HppOverheadItem;
use App\Models\Reports\HppPrefix;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\Reports\HppReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HppReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed HPP configuration data
        $this->seedHppConfiguration();
    }

    private function seedHppConfiguration(): void
    {
        $prefixGroups = [
            'raw_material_inventory' => ['1140.001'],
            'raw_material_purchase' => ['5110'],
            'direct_labor' => ['5120'],
            'wip_inventory' => ['1150.001'],
        ];

        foreach ($prefixGroups as $category => $prefixes) {
            $order = 1;
            foreach ($prefixes as $prefix) {
                HppPrefix::create([
                    'category' => $category,
                    'prefix' => $prefix,
                    'sort_order' => $order++,
                ]);
            }
        }

        $overheadItems = [
            [
                'key' => 'factory_electricity',
                'label' => 'Biaya Listrik Pabrik',
                'sort_order' => 1,
                'prefixes' => ['5130'],
            ],
            [
                'key' => 'machine_depreciation',
                'label' => 'Biaya Penyusutan Mesin',
                'sort_order' => 2,
                'prefixes' => ['5140'],
            ],
            [
                'key' => 'maintenance',
                'label' => 'Biaya Perawatan',
                'sort_order' => 3,
                'prefixes' => ['5150'],
            ],
        ];

        foreach ($overheadItems as $itemData) {
            $item = HppOverheadItem::create([
                'key' => $itemData['key'],
                'label' => $itemData['label'],
                'sort_order' => $itemData['sort_order'],
            ]);

            foreach ($itemData['prefixes'] as $prefix) {
                $item->prefixes()->create(['prefix' => $prefix]);
            }
        }
    }

    public function test_hpp_report_calculates_values_and_branch_filters(): void
    {
        Carbon::setTestNow('2025-02-01 00:00:00');

        $branchA = Cabang::factory()->create(['nama' => 'Branch A']);
        $branchB = Cabang::factory()->create(['nama' => 'Branch B']);

        $rawMaterial = ChartOfAccount::create([
            'code' => '1140.001',
            'name' => 'Persediaan Bahan Baku',
            'type' => 'Asset',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $purchases = ChartOfAccount::create([
            'code' => '5110.001',
            'name' => 'Pembelian Bahan Baku',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $directLabor = ChartOfAccount::create([
            'code' => '5120.001',
            'name' => 'Biaya Tenaga Kerja Langsung',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $overheadElectric = ChartOfAccount::create([
            'code' => '5130.001',
            'name' => 'Biaya Listrik Pabrik',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $overheadDepreciation = ChartOfAccount::create([
            'code' => '5140.001',
            'name' => 'Biaya Penyusutan Mesin',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $overheadMaintenance = ChartOfAccount::create([
            'code' => '5150.001',
            'name' => 'Biaya Perawatan',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $wip = ChartOfAccount::create([
            'code' => '1150.001',
            'name' => 'Persediaan Barang Dalam Proses',
            'type' => 'Asset',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        // Opening balances (prior period) for branch A
        $this->createJournal($rawMaterial, '2024-12-31', 1000, 0, $branchA->id);
        $this->createJournal($wip, '2024-12-31', 400, 0, $branchA->id);

        // Transactions within January for branch A
        $this->createJournal($rawMaterial, '2025-01-05', 0, 300, $branchA->id);
        $this->createJournal($rawMaterial, '2025-01-20', 0, 200, $branchA->id);
        $this->createJournal($purchases, '2025-01-10', 2000, 0, $branchA->id);
        $this->createJournal($directLabor, '2025-01-12', 1500, 0, $branchA->id);
        $this->createJournal($overheadElectric, '2025-01-18', 300, 0, $branchA->id);
        $this->createJournal($overheadDepreciation, '2025-01-25', 500, 0, $branchA->id);
        $this->createJournal($overheadMaintenance, '2025-01-28', 200, 0, $branchA->id);
        $this->createJournal($wip, '2025-01-10', 200, 0, $branchA->id);
        $this->createJournal($wip, '2025-01-25', 0, 350, $branchA->id);

        // Branch B contributions
        $this->createJournal($rawMaterial, '2024-12-31', 500, 0, $branchB->id);
        $this->createJournal($wip, '2024-12-31', 200, 0, $branchB->id);
        $this->createJournal($rawMaterial, '2025-01-07', 0, 100, $branchB->id);
        $this->createJournal($purchases, '2025-01-15', 400, 0, $branchB->id);
        $this->createJournal($directLabor, '2025-01-18', 300, 0, $branchB->id);
        $this->createJournal($overheadElectric, '2025-01-22', 100, 0, $branchB->id);
        $this->createJournal($overheadMaintenance, '2025-01-29', 50, 0, $branchB->id);
        $this->createJournal($wip, '2025-01-11', 100, 0, $branchB->id);
        $this->createJournal($wip, '2025-01-27', 0, 120, $branchB->id);

        $service = app(HppReportService::class);

        $all = $service->generate('2025-01-01', '2025-01-31');
        $this->assertEquals(1500.0, $all['raw_materials']['opening']);
        $this->assertEquals(2400.0, $all['raw_materials']['purchases']);
        $this->assertEquals(3900.0, $all['raw_materials']['available']);
        $this->assertEquals(900.0, $all['raw_materials']['closing']);
        $this->assertEquals(3000.0, $all['raw_materials']['used']);
        $this->assertEquals(1800.0, $all['direct_labor']);
        $this->assertEquals(1150.0, $all['overhead']['total']);
        $this->assertEquals(5950.0, $all['production_cost']);
        $this->assertEquals(600.0, $all['wip']['opening']);
        $this->assertEquals(430.0, $all['wip']['closing']);
        $this->assertEquals(6120.0, $all['cogm']);

        $branchAReport = $service->generate('2025-01-01', '2025-01-31', [
            'branches' => [$branchA->id],
        ]);

        $this->assertEquals(1000.0, $branchAReport['raw_materials']['opening']);
        $this->assertEquals(2000.0, $branchAReport['raw_materials']['purchases']);
        $this->assertEquals(3000.0, $branchAReport['raw_materials']['available']);
        $this->assertEquals(500.0, $branchAReport['raw_materials']['closing']);
        $this->assertEquals(2500.0, $branchAReport['raw_materials']['used']);
        $this->assertEquals(1500.0, $branchAReport['direct_labor']);
        $this->assertEquals(1000.0, $branchAReport['overhead']['total']);
        $this->assertEquals(5000.0, $branchAReport['production_cost']);
        $this->assertEquals(400.0, $branchAReport['wip']['opening']);
        $this->assertEquals(250.0, $branchAReport['wip']['closing']);
        $this->assertEquals(5150.0, $branchAReport['cogm']);
    }

    public function test_raw_material_balance_falls_back_to_stock_movements(): void
    {
        Carbon::setTestNow('2025-02-01 00:00:00');

        $branch = Cabang::factory()->create(['nama' => 'Fallback Branch']);
    $uom = UnitOfMeasure::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

        $rawMaterial = ChartOfAccount::create([
            'code' => '1140.900',
            'name' => 'Persediaan Bahan Baku - Test',
            'type' => 'Asset',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $purchases = ChartOfAccount::create([
            'code' => '5110.900',
            'name' => 'Pembelian Bahan Baku - Test',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $product = Product::factory()->create([
            'cabang_id' => $branch->id,
            'is_raw_material' => true,
            'inventory_coa_id' => $rawMaterial->id,
            'cost_price' => 10_000,
            'uom_id' => $uom->id,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'value' => 1000.0,
            'type' => 'purchase_in',
            'date' => '2024-12-15',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 40,
            'value' => 400.0,
            'type' => 'manufacture_out',
            'date' => '2025-01-05',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 60,
            'value' => 630.0,
            'type' => 'purchase_in',
            'date' => '2025-01-20',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 30,
            'value' => 315.0,
            'type' => 'manufacture_out',
            'date' => '2025-01-28',
        ]);

        $this->createJournal($purchases, '2025-01-20', 630.0, 0, $branch->id);

        $service = app(HppReportService::class);

        $report = $service->generate('2025-01-01', '2025-01-31', [
            'branches' => [$branch->id],
        ]);

        $this->assertEquals(1000.0, $report['raw_materials']['opening']);
        $this->assertEquals(630.0, $report['raw_materials']['purchases']);
        $this->assertEquals(1630.0, $report['raw_materials']['available']);
        $this->assertEquals(915.0, $report['raw_materials']['closing']);
        $this->assertEquals(715.0, $report['raw_materials']['used']);
    }

    private function createJournal(ChartOfAccount $coa, string $date, float $debit, float $credit, int $branchId): void
    {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $date,
            'reference' => 'TEST',
            'description' => 'test entry',
            'debit' => $debit,
            'credit' => $credit,
            'journal_type' => 'test',
            'cabang_id' => $branchId,
            'source_type' => self::class,
            'source_id' => 0,
        ]);
    }

    public function test_variance_analysis_calculates_correct_values(): void
    {
        Carbon::setTestNow('2025-02-01 00:00:00');

        $branch = Cabang::factory()->create(['nama' => 'Test Branch']);
        $product = Product::factory()->create(['cabang_id' => $branch->id, 'name' => 'Test Product']);

        // Create standard costs
        $standardCost = ProductStandardCost::create([
            'product_id' => $product->id,
            'standard_material_cost' => 100.00,
            'standard_labor_cost' => 50.00,
            'standard_overhead_cost' => 25.00,
            'total_standard_cost' => 175.00,
            'effective_date' => '2025-01-01',
        ]);

        // Create production cost entries (actual costs)
        $productionEntry = ProductionCostEntry::create([
            'product_id' => $product->id,
            'production_date' => '2025-01-15',
            'quantity_produced' => 10,
            'actual_material_cost' => 1200.00, // 120 per unit vs standard 100
            'actual_labor_cost' => 450.00,     // 45 per unit vs standard 50
            'actual_overhead_cost' => 300.00,  // 30 per unit vs standard 25
            'total_actual_cost' => 1950.00,
            'cabang_id' => $branch->id,
        ]);

        // Create cost variances
        CostVariance::create([
            'product_id' => $product->id,
            'production_cost_entry_id' => $productionEntry->id,
            'variance_type' => 'material',
            'standard_cost' => 1000.00, // 10 * 100
            'actual_cost' => 1200.00,
            'variance_amount' => -200.00, // unfavorable
            'variance_percentage' => -20.00,
            'period' => '2025-01',
        ]);

        CostVariance::create([
            'product_id' => $product->id,
            'production_cost_entry_id' => $productionEntry->id,
            'variance_type' => 'labor',
            'standard_cost' => 500.00, // 10 * 50
            'actual_cost' => 450.00,
            'variance_amount' => 50.00, // favorable
            'variance_percentage' => 10.00,
            'period' => '2025-01',
        ]);

        CostVariance::create([
            'product_id' => $product->id,
            'production_cost_entry_id' => $productionEntry->id,
            'variance_type' => 'overhead',
            'standard_cost' => 250.00, // 10 * 25
            'actual_cost' => 300.00,
            'variance_amount' => -50.00, // unfavorable
            'variance_percentage' => -20.00,
            'period' => '2025-01',
        ]);

        $service = app(HppReportService::class);

        // Use reflection to access private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateVarianceAnalysis');
        $method->setAccessible(true);

        $varianceAnalysis = $method->invoke($service, \Carbon\Carbon::parse('2025-01-01'), \Carbon\Carbon::parse('2025-01-31'));

        // Verify total variances
        $this->assertEquals(-200.00, $varianceAnalysis['material_variance']);
        $this->assertEquals(50.00, $varianceAnalysis['labor_variance']);
        $this->assertEquals(-50.00, $varianceAnalysis['overhead_variance']);
        $this->assertEquals(-200.00, $varianceAnalysis['total_variance']);

        // Verify details array
        $this->assertCount(3, $varianceAnalysis['details']);
        
        $materialDetail = collect($varianceAnalysis['details'])->firstWhere('variance_type', 'material');
        $this->assertEquals(1000.00, $materialDetail['standard_cost']);
        $this->assertEquals(1200.00, $materialDetail['actual_cost']);
        $this->assertEquals(-200.00, $materialDetail['variance_amount']);
        $this->assertEquals(-20.00, $materialDetail['variance_percentage']);

        $laborDetail = collect($varianceAnalysis['details'])->firstWhere('variance_type', 'labor');
        $this->assertEquals(500.00, $laborDetail['standard_cost']);
        $this->assertEquals(450.00, $laborDetail['actual_cost']);
        $this->assertEquals(50.00, $laborDetail['variance_amount']);
        $this->assertEquals(10.00, $laborDetail['variance_percentage']);
    }

    public function test_allocated_overhead_calculates_correctly(): void
    {
        Carbon::setTestNow('2025-02-01 00:00:00');

        $branch = Cabang::factory()->create(['nama' => 'Test Branch']);

        // Clear existing overhead items to avoid conflicts
        HppOverheadItem::query()->delete();

        // Create overhead item with allocation basis
        $overheadItem = HppOverheadItem::create([
            'key' => 'factory_electricity',
            'label' => 'Biaya Listrik Pabrik',
            'sort_order' => 1,
            'allocation_basis' => 'direct_labor',
            'allocation_rate' => 0.1, // 10% of direct labor
        ]);
        $overheadItem->prefixes()->create(['prefix' => '5130']);

        // Create COA for direct labor
        $directLaborCoa = ChartOfAccount::create([
            'code' => '5120.001',
            'name' => 'Biaya Tenaga Kerja Langsung',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        // Create COA for raw materials
        $rawMaterialCoa = ChartOfAccount::create([
            'code' => '5110.001',
            'name' => 'Biaya Bahan Baku',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        // Create journal entries for direct labor and raw materials
        $this->createJournal($directLaborCoa, '2025-01-15', 1000.00, 0, $branch->id);
        $this->createJournal($rawMaterialCoa, '2025-01-10', 500.00, 0, $branch->id);

        // Calculate totals for the period
        $start = \Carbon\Carbon::parse('2025-01-01');
        $end = \Carbon\Carbon::parse('2025-01-31');

        $rawMaterialUsed = JournalEntry::where('coa_id', $rawMaterialCoa->id)
            ->whereBetween('date', [$start, $end])
            ->sum('debit');

        $directLabor = JournalEntry::where('coa_id', $directLaborCoa->id)
            ->whereBetween('date', [$start, $end])
            ->sum('debit');

        $service = app(HppReportService::class);

        // Test allocation logic by calling generate with use_allocation = true
        $report = $service->generate('2025-01-01', '2025-01-31', ['use_allocation' => true]);

        // Should allocate 10% of direct labor (100) to factory electricity
        $electricityAllocation = collect($report['overhead']['items'])->firstWhere('key', 'factory_electricity');
        $this->assertEquals(100.00, $electricityAllocation['allocated_amount']);
        $this->assertEquals('Direct Labor Cost', $electricityAllocation['allocation_basis']);
        $this->assertEquals(0.1, $electricityAllocation['allocation_rate']);
    }
}
