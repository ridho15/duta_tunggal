<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Rak;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Exports\InventoryReportExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class InventoryReportTest extends TestCase
{
    use RefreshDatabase;

    protected $warehouse;
    protected $product;
    protected $rak;
    protected $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data using existing data or create manually
        $this->warehouse = Warehouse::first();
        if (!$this->warehouse) {
            $this->warehouse = Warehouse::create([
                'kode' => 'WH-TEST',
                'name' => 'Warehouse Test',
                'tipe' => 'Kecil',
                'location' => 'Test Location',
                'telepon' => '081234567890',
                'status' => 1,
                'warna_background' => '#ffffff',
                'cabang_id' => 1
            ]);
        }

        $this->product = Product::first();
        if (!$this->product) {
            $this->product = Product::create([
                'code' => 'PROD-TEST',
                'name' => 'Product Test',
                'sku' => 'SKU-TEST-001',
                'description' => 'Test Product',
                'unit' => 'pcs',
                'status' => 1,
                'cabang_id' => 1,
                'product_category_id' => 1,
                'supplier_id' => null,
                'uom_id' => 1,
                'kode_merk' => 'MERK-TEST',
                'cost_price' => 1000,
                'sell_price' => 1500,
                'is_active' => 1
            ]);
        }

        $this->rak = Rak::where('warehouse_id', $this->warehouse->id)->first();
        if (!$this->rak) {
            $this->rak = Rak::create([
                'code' => 'RAK-A1',
                'name' => 'Rak A1',
                'warehouse_id' => $this->warehouse->id,
                'status' => 1
            ]);
        }

        $this->inventory = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        if (!$this->inventory) {
            $this->inventory = InventoryStock::create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'rak_id' => $this->rak->id,
                'qty_available' => 100,
                'qty_reserved' => 10,
                'min_stock' => 5
            ]);
        }

        // Create stock movements for aging test
        $existingMovement = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('type', 'purchase_in')
            ->where('quantity', 50)
            ->first();

        if (!$existingMovement) {
            StockMovement::create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'rak_id' => $this->rak->id,
                'type' => 'purchase_in',
                'quantity' => 50,
                'value' => 50000,
                'date' => now()->subDays(15),
                'from_model_type' => 'App\\Models\\PurchaseReceipt',
                'from_model_id' => 1,
                'notes' => 'Test movement 15 days ago'
            ]);
        }

        $existingMovement2 = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('type', 'sales')
            ->where('quantity', 20)
            ->first();

        if (!$existingMovement2) {
            StockMovement::create([
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'rak_id' => $this->rak->id,
                'type' => 'sales',
                'quantity' => 20,
                'value' => 20000,
                'date' => now()->subDays(45),
                'from_model_type' => 'App\\Models\\DeliveryOrder',
                'from_model_id' => 1,
                'notes' => 'Test movement 45 days ago'
            ]);
        }
    }

    /** @test */
    public function it_can_generate_stock_by_warehouse_report()
    {
        Excel::fake();

        $export = new InventoryReportExport(null, null, 'stock', null, null);
        Excel::store($export, 'test_stock_report.xlsx');

        Excel::assertStored('test_stock_report.xlsx');
    }

    /** @test */
    public function it_can_generate_movement_history_report()
    {
        Excel::fake();

        $export = new InventoryReportExport(null, null, 'movement', now()->subDays(30), now());
        Excel::store($export, 'test_movement_report.xlsx');

        Excel::assertStored('test_movement_report.xlsx');
    }

    /** @test */
    public function it_can_generate_aging_stock_report()
    {
        Excel::fake();

        $export = new InventoryReportExport(null, null, 'aging', null, null);
        Excel::store($export, 'test_aging_report.xlsx');

        Excel::assertStored('test_aging_report.xlsx');
    }

    /** @test */
    public function it_filters_stock_report_by_warehouse()
    {
        Excel::fake();

        $export = new InventoryReportExport($this->warehouse->id, null, 'stock', null, null);
        $data = $export->collection();

        // Should only contain data for the specified warehouse
        $this->assertCount(1, $data);
        $this->assertEquals($this->warehouse->name, $data[0]['Gudang']);
    }

    /** @test */
    public function it_filters_movement_report_by_product()
    {
        Excel::fake();

        $export = new InventoryReportExport(null, $this->product->id, 'movement', now()->subDays(30), now());
        $data = $export->collection();

        // Should only contain movements for the specified product
        foreach ($data as $movement) {
            $this->assertEquals($this->product->code ?? '-', $movement['Kode Produk']);
        }
    }

    /** @test */
    public function it_calculates_aging_categories_correctly()
    {
        $export = new InventoryReportExport(null, null, 'aging', null, null);
        $data = $export->collection();

        $this->assertCount(1, $data);

        $agingData = $data[0];
        $this->assertEquals($this->warehouse->name, $agingData['Gudang']);
        $this->assertEquals($this->product->code ?? '-', $agingData['Kode Produk']);

        // Check aging calculation (last movement was 15 days ago, so should be "Aktif")
        $this->assertEquals('Aktif', $agingData['Kategori Aging']);
        $this->assertEqualsWithDelta(15, $agingData['Hari Aging'], 0.1); // Allow small floating point differences
    }

    /** @test */
    public function it_shows_correct_stock_levels()
    {
        $export = new InventoryReportExport(null, null, 'stock', null, null);
        $data = $export->collection();

        $this->assertCount(1, $data);

        $stockData = $data[0];
        // Calculate expected qty based on movements: initial 100 + 50 (purchase_in) - 20 (sales) = 130
        $this->assertEquals(130.0, $stockData['Qty Tersedia']);
        $this->assertEquals(10, $stockData['Qty Dipesan']);
        $this->assertEquals(120.0, $stockData['Qty On Hand']); // 130 - 10
    }

    /** @test */
    public function it_includes_movement_details_in_movement_report()
    {
        $export = new InventoryReportExport(null, null, 'movement', now()->subDays(60), now());
        $data = $export->collection();

        $this->assertCount(2, $data); // Should have 2 movements

        // Check first movement (purchase_in)
        $firstMovement = $data[0];
        $this->assertEquals('purchase_in', $firstMovement['Tipe Movement']);
        $this->assertEquals(50, $firstMovement['Quantity']);
        $this->assertEquals(50000, $firstMovement['Nilai']);

        // Check second movement (sales)
        $secondMovement = $data[1];
        $this->assertEquals('sales', $secondMovement['Tipe Movement']);
        $this->assertEquals(20, $secondMovement['Quantity']);
    }
}