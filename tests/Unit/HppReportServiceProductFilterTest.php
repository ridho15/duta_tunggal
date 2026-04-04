<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\Reports\HppOverheadItem;
use App\Models\Reports\HppPrefix;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\HppReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HppReportServiceProductFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedHppConfiguration();
    }

    public function test_generate_can_scope_cogm_values_by_product(): void
    {
        Carbon::setTestNow('2025-02-01 00:00:00');

        [$productA] = $this->seedProductScopedData();

        $report = app(HppReportService::class)->generate('2025-01-01', '2025-01-31', [
            'product_id' => $productA->id,
        ]);

        $this->assertEquals(450.0, $report['raw_materials']['used']);
        $this->assertEquals(200.0, $report['direct_labor']);
        $this->assertEquals(100.0, $report['overhead']['total']);
        $this->assertEquals(750.0, $report['production_cost']);
        $this->assertEquals(0.0, $report['wip']['opening']);
        $this->assertEquals(450.0, $report['wip']['closing']);
        $this->assertEquals(300.0, $report['cogm']);
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

        $item = HppOverheadItem::create([
            'key' => 'factory_overhead',
            'label' => 'Factory Overhead',
            'sort_order' => 1,
        ]);

        $item->prefixes()->create(['prefix' => '6100']);
    }

    private function seedProductScopedData(): array
    {
        $branch = Cabang::factory()->create(['nama' => 'Branch Product Filter']);
        $uom = UnitOfMeasure::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create(['cabang_id' => $branch->id]);

        $wipCoa = ChartOfAccount::create([
            'code' => '1150.001',
            'name' => 'Persediaan Barang Dalam Proses',
            'type' => 'Asset',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $laborCoa = ChartOfAccount::create([
            'code' => '5230.001',
            'name' => 'Biaya Tenaga Kerja Proses Produksi',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $overheadCoa = ChartOfAccount::create([
            'code' => '6100.001',
            'name' => 'Biaya Overhead Produksi',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $productA = Product::factory()->create(['name' => 'Produk A', 'cabang_id' => $branch->id, 'uom_id' => $uom->id]);
        $productB = Product::factory()->create(['name' => 'Produk B', 'cabang_id' => $branch->id, 'uom_id' => $uom->id]);

        $bomA = BillOfMaterial::factory()->create([
            'product_id' => $productA->id,
            'cabang_id' => $branch->id,
            'uom_id' => $uom->id,
            'quantity' => 1,
            'labor_cost' => 20,
            'overhead_cost' => 10,
            'is_active' => true,
        ]);

        $bomB = BillOfMaterial::factory()->create([
            'product_id' => $productB->id,
            'cabang_id' => $branch->id,
            'uom_id' => $uom->id,
            'quantity' => 1,
            'labor_cost' => 25,
            'overhead_cost' => 15,
            'is_active' => true,
        ]);

        $planA = ProductionPlan::factory()->create([
            'product_id' => $productA->id,
            'bill_of_material_id' => $bomA->id,
            'quantity' => 10,
            'uom_id' => $uom->id,
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $branch->id,
            'created_by' => $user->id,
        ]);

        $planB = ProductionPlan::factory()->create([
            'product_id' => $productB->id,
            'bill_of_material_id' => $bomB->id,
            'quantity' => 8,
            'uom_id' => $uom->id,
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $branch->id,
            'created_by' => $user->id,
        ]);

        $moA = ManufacturingOrder::factory()->create([
            'production_plan_id' => $planA->id,
            'cabang_id' => $branch->id,
            'created_at' => '2025-01-05 09:00:00',
            'updated_at' => '2025-01-05 09:00:00',
        ]);

        $moB = ManufacturingOrder::factory()->create([
            'production_plan_id' => $planB->id,
            'cabang_id' => $branch->id,
            'created_at' => '2025-01-06 09:00:00',
            'updated_at' => '2025-01-06 09:00:00',
        ]);

        $productionA = Production::create([
            'production_number' => 'PROD-A-001',
            'manufacturing_order_id' => $moA->id,
            'quantity_produced' => 10,
            'production_date' => '2025-01-10',
            'status' => 'finished',
            'created_at' => '2025-01-10 08:00:00',
            'updated_at' => '2025-01-10 08:00:00',
        ]);

        $productionB = Production::create([
            'production_number' => 'PROD-B-001',
            'manufacturing_order_id' => $moB->id,
            'quantity_produced' => 8,
            'production_date' => '2025-01-12',
            'status' => 'finished',
            'created_at' => '2025-01-12 08:00:00',
            'updated_at' => '2025-01-12 08:00:00',
        ]);

        QualityControl::create([
            'qc_number' => 'QC-A-001',
            'inspected_by' => $user->id,
            'passed_quantity' => 4,
            'rejected_quantity' => 0,
            'quantity_received' => 4,
            'status' => 1,
            'warehouse_id' => $warehouse->id,
            'product_id' => $productA->id,
            'date_send_stock' => '2025-01-20',
            'from_model_id' => $productionA->id,
            'from_model_type' => Production::class,
            'cabang_id' => $branch->id,
            'created_at' => '2025-01-20 10:00:00',
            'updated_at' => '2025-01-20 10:00:00',
        ]);

        QualityControl::create([
            'qc_number' => 'QC-B-001',
            'inspected_by' => $user->id,
            'passed_quantity' => 2,
            'rejected_quantity' => 0,
            'quantity_received' => 2,
            'status' => 1,
            'warehouse_id' => $warehouse->id,
            'product_id' => $productB->id,
            'date_send_stock' => '2025-01-21',
            'from_model_id' => $productionB->id,
            'from_model_type' => Production::class,
            'cabang_id' => $branch->id,
            'created_at' => '2025-01-21 10:00:00',
            'updated_at' => '2025-01-21 10:00:00',
        ]);

        MaterialIssue::create([
            'issue_number' => 'MI-A-ISSUE',
            'production_plan_id' => $planA->id,
            'manufacturing_order_id' => $moA->id,
            'warehouse_id' => $warehouse->id,
            'issue_date' => '2025-01-09',
            'type' => 'issue',
            'status' => MaterialIssue::STATUS_COMPLETED,
            'total_cost' => 500,
            'created_by' => $user->id,
            'created_at' => '2025-01-09 08:00:00',
            'updated_at' => '2025-01-09 08:00:00',
        ]);

        MaterialIssue::create([
            'issue_number' => 'MI-A-RETURN',
            'production_plan_id' => $planA->id,
            'manufacturing_order_id' => $moA->id,
            'warehouse_id' => $warehouse->id,
            'issue_date' => '2025-01-11',
            'type' => 'return',
            'status' => MaterialIssue::STATUS_COMPLETED,
            'total_cost' => 50,
            'created_by' => $user->id,
            'created_at' => '2025-01-11 08:00:00',
            'updated_at' => '2025-01-11 08:00:00',
        ]);

        MaterialIssue::create([
            'issue_number' => 'MI-B-ISSUE',
            'production_plan_id' => $planB->id,
            'manufacturing_order_id' => $moB->id,
            'warehouse_id' => $warehouse->id,
            'issue_date' => '2025-01-09',
            'type' => 'issue',
            'status' => MaterialIssue::STATUS_COMPLETED,
            'total_cost' => 700,
            'created_by' => $user->id,
            'created_at' => '2025-01-09 09:00:00',
            'updated_at' => '2025-01-09 09:00:00',
        ]);

        $qcAId = QualityControl::where('qc_number', 'QC-A-001')->value('id');
        $qcBId = QualityControl::where('qc_number', 'QC-B-001')->value('id');

        $this->createJournal($wipCoa, '2025-01-10', 750, 0, $branch->id, Production::class, $productionA->id, 'Produksi in progress - MO A');
        $this->createJournal($laborCoa, '2025-01-10', 0, 200, $branch->id, Production::class, $productionA->id, 'Produksi in progress - MO A (tenaga kerja langsung)');
        $this->createJournal($overheadCoa, '2025-01-10', 0, 100, $branch->id, Production::class, $productionA->id, 'Produksi in progress - MO A (overhead produksi)');
        $this->createJournal($wipCoa, '2025-01-20', 0, 300, $branch->id, QualityControl::class, $qcAId, 'Penyelesaian produksi - MO A (Produk A)');

        $this->createJournal($wipCoa, '2025-01-12', 920, 0, $branch->id, Production::class, $productionB->id, 'Produksi in progress - MO B');
        $this->createJournal($laborCoa, '2025-01-12', 0, 200, $branch->id, Production::class, $productionB->id, 'Produksi in progress - MO B (tenaga kerja langsung)');
        $this->createJournal($overheadCoa, '2025-01-12', 0, 120, $branch->id, Production::class, $productionB->id, 'Produksi in progress - MO B (overhead produksi)');
        $this->createJournal($wipCoa, '2025-01-21', 0, 220, $branch->id, QualityControl::class, $qcBId, 'Penyelesaian produksi - MO B (Produk B)');

        return [$productA, $productB];
    }

    private function createJournal(ChartOfAccount $coa, string $date, float $debit, float $credit, int $branchId, string $sourceType, int $sourceId, string $description): void
    {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $date,
            'reference' => 'TEST',
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
            'journal_type' => str_contains($description, 'Penyelesaian produksi') ? 'manufacturing_completion' : 'manufacturing_wip',
            'cabang_id' => $branchId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }
}