<?php

namespace Tests\Feature;

use App\Models\ManufacturingOrder;
use App\Models\Production;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\ProductionPlan;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Cabang;
use App\Exports\ProductionReportExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ProductionReportTest extends TestCase
{
    use RefreshDatabase;

    protected $manufacturingOrder;
    protected $productionPlan;
    protected $product;
    protected $warehouse;
    protected $unitOfMeasure;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable MaterialIssueObserver to prevent journal creation during testing
        MaterialIssue::unsetEventDispatcher();

        // Create test cabang (branch)
        $cabang = Cabang::first();
        if (!$cabang) {
            $cabang = Cabang::create([
                'kode' => 'CBG-TEST',
                'nama' => 'Cabang Test',
                'alamat' => 'Jl. Test No. 123',
                'telepon' => '081234567890',
                'kenaikan_harga' => 0,
                'status' => 1,
                'warna_background' => '#ffffff',
                'tipe_penjualan' => 'Semua',
                'kode_invoice_pajak' => 'INV-PJK-TEST',
                'kode_invoice_non_pajak' => 'INV-NPJK-TEST',
                'kode_invoice_pajak_walkin' => 'INV-WPJK-TEST',
                'nama_kwitansi' => 'Kwitansi Test',
                'label_invoice_pajak' => 'Pajak Test',
                'label_invoice_non_pajak' => 'Non Pajak Test',
                'logo_invoice_non_pajak' => null,
                'lihat_stok_cabang_lain' => false,
            ]);
        }

        // Create test data
        $this->warehouse = Warehouse::first();
        if (!$this->warehouse) {
            $this->warehouse = Warehouse::create([
                'kode' => 'WH-PROD',
                'name' => 'Warehouse Production',
                'tipe' => 'Kecil',
                'location' => 'Production Area',
                'telepon' => '081234567890',
                'status' => 1,
                'warna_background' => '#ffffff',
                'cabang_id' => $cabang->id
            ]);
        }

        // Create unit of measure
        $this->unitOfMeasure = UnitOfMeasure::first();
        if (!$this->unitOfMeasure) {
            $this->unitOfMeasure = UnitOfMeasure::create([
                'name' => 'Kilogram',
                'abbreviation' => 'kg'
            ]);
        }

        // Create test user
        $this->user = User::first();
        if (!$this->user) {
            $this->user = User::create([
                'name' => 'Test User',
                'first_name' => 'Test',
                'username' => 'testuser',
                'kode_user' => 'TEST001',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'cabang_id' => $cabang->id
            ]);
        }

        $this->product = Product::first();
        if (!$this->product) {
            $this->product = Product::create([
                'code' => 'MAT-001',
                'name' => 'Raw Material Test',
                'sku' => 'SKU-MAT-001',
                'description' => 'Test Raw Material',
                'unit' => 'kg',
                'status' => 1,
                'cabang_id' => $cabang->id,
                'product_category_id' => 1,
                'supplier_id' => null,
                'uom_id' => $this->unitOfMeasure->id,
                'kode_merk' => 'MERK-TEST',
                'cost_price' => 50000,
                'sell_price' => 75000,
                'is_active' => 1
            ]);
        }

        $this->productionPlan = ProductionPlan::first();
        if (!$this->productionPlan) {
            $this->productionPlan = ProductionPlan::create([
                'plan_number' => 'PP-' . now()->format('Ymd') . '-001',
                'name' => 'Production Plan Test',
                'product_id' => $this->product->id,
                'quantity' => 100,
                'uom_id' => $this->unitOfMeasure->id,
                'warehouse_id' => $this->warehouse->id,
                'start_date' => now()->subDays(10),
                'end_date' => now()->addDays(10),
                'status' => 'scheduled',
                'created_by' => $this->user->id,
                'cabang_id' => $cabang->id
            ]);
        }

        $this->manufacturingOrder = ManufacturingOrder::where('production_plan_id', $this->productionPlan->id)->first();
        if (!$this->manufacturingOrder) {
            $this->manufacturingOrder = ManufacturingOrder::create([
                'mo_number' => 'MO-TEST-001',
                'production_plan_id' => $this->productionPlan->id,
                'status' => 'completed',
                'start_date' => now()->subDays(5),
                'end_date' => now(),
                'cabang_id' => $cabang->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 50,
                        'unit' => 'kg'
                    ]
                ]
            ]);
        }

        // Create production record
        $existingProduction = Production::where('manufacturing_order_id', $this->manufacturingOrder->id)->first();
        if (!$existingProduction) {
            Production::create([
                'production_number' => 'PROD-TEST-001',
                'manufacturing_order_id' => $this->manufacturingOrder->id,
                'production_date' => now(),
                'status' => 'finished'
            ]);
        }

        // Create material issue
        $existingMaterialIssue = MaterialIssue::where('manufacturing_order_id', $this->manufacturingOrder->id)->first();
        if (!$existingMaterialIssue) {
            $materialIssue = MaterialIssue::create([
                'issue_number' => 'MI-TEST-001',
                'production_plan_id' => $this->productionPlan->id,
                'manufacturing_order_id' => $this->manufacturingOrder->id,
                'warehouse_id' => $this->warehouse->id,
                'issue_date' => now()->subDays(2),
                'type' => 'issue',
                'status' => 'completed',
                'total_cost' => 2500000,
                'notes' => 'Test material issue',
            ]);

            // Create material issue item
            MaterialIssueItem::create([
                'material_issue_id' => $materialIssue->id,
                'product_id' => $this->product->id,
                'uom_id' => $this->unitOfMeasure->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 50,
                'cost_per_unit' => 50000,
                'total_cost' => 2500000
            ]);
        }
    }

    /** @test */
    public function it_can_generate_production_summary_report()
    {
        Excel::fake();

        $export = new ProductionReportExport(null, now()->subDays(30), now(), 'summary');
        Excel::store($export, 'test_production_summary_report.xlsx');

        Excel::assertStored('test_production_summary_report.xlsx');
    }

    /** @test */
    public function it_can_generate_material_usage_report()
    {
        Excel::fake();

        $export = new ProductionReportExport(null, now()->subDays(30), now(), 'material_usage');
        Excel::store($export, 'test_material_usage_report.xlsx');

        Excel::assertStored('test_material_usage_report.xlsx');
    }

    /** @test */
    public function it_can_generate_efficiency_report()
    {
        Excel::fake();

        $export = new ProductionReportExport(null, now()->subDays(30), now(), 'efficiency');
        Excel::store($export, 'test_efficiency_report.xlsx');

        Excel::assertStored('test_efficiency_report.xlsx');
    }

    /** @test */
    public function it_filters_production_summary_by_manufacturing_order()
    {
        Excel::fake();

        $export = new ProductionReportExport($this->manufacturingOrder->id, null, null, 'summary');
        $data = $export->collection();

        $this->assertCount(1, $data);
        $this->assertEquals($this->manufacturingOrder->mo_number, $data[0]['No. MO']);
    }

    /** @test */
    public function it_shows_correct_production_summary_data()
    {
        $export = new ProductionReportExport(null, now()->subDays(30), now(), 'summary');
        $data = $export->collection();

        $this->assertNotEmpty($data);

        $record = $data->first();
        $this->assertArrayHasKey('No. MO', $record);
        $this->assertArrayHasKey('Plan Produksi', $record);
        $this->assertArrayHasKey('Status', $record);
        $this->assertArrayHasKey('Total Produksi', $record);
        $this->assertArrayHasKey('Total Biaya Material', $record);
        $this->assertArrayHasKey('Efisiensi (%)', $record);
    }

    /** @test */
    public function it_shows_correct_material_usage_data()
    {
        $export = new ProductionReportExport(null, now()->subDays(30), now(), 'material_usage');
        $data = $export->collection();

        $this->assertNotEmpty($data);

        $record = $data->first();
        $this->assertArrayHasKey('No. Issue', $record);
        $this->assertArrayHasKey('No. MO', $record);
        $this->assertArrayHasKey('Kode Material', $record);
        $this->assertArrayHasKey('Qty Diminta', $record);
        $this->assertArrayHasKey('Total Biaya', $record);
    }

    /** @test */
    public function it_calculates_efficiency_correctly()
    {
        $export = new ProductionReportExport(null, now()->subDays(30), now(), 'efficiency');
        $data = $export->collection();

        $this->assertNotEmpty($data);

        $record = $data->first();
        $this->assertArrayHasKey('Qty Direncanakan', $record);
        $this->assertArrayHasKey('Qty Diproduksi', $record);
        $this->assertArrayHasKey('Pencapaian (%)', $record);
        $this->assertArrayHasKey('Biaya per Unit', $record);
        $this->assertArrayHasKey('Status Efisiensi', $record);

        // Check if efficiency calculation is reasonable
        $planned = $record['Qty Direncanakan'];
        $produced = $record['Qty Diproduksi'];
        if ($planned > 0) {
            $expectedEfficiency = round(($produced / $planned) * 100, 2);
            $this->assertEquals($expectedEfficiency, $record['Pencapaian (%)']);
        }
    }

    /** @test */
    public function it_filters_reports_by_date_range()
    {
        Excel::fake();

        // Test with date range that should include our test data
        $export = new ProductionReportExport(null, now()->subDays(10), now(), 'summary');
        $data = $export->collection();

        $this->assertNotEmpty($data);

        // Test with date range that should exclude our test data
        $export2 = new ProductionReportExport(null, now()->subDays(100), now()->subDays(50), 'summary');
        $data2 = $export2->collection();

        // Should be empty or not contain our test MO
        $containsTestMO = $data2->contains('No. MO', $this->manufacturingOrder->mo_number);
        $this->assertFalse($containsTestMO);
    }
}