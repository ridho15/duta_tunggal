<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Exports\InventoryReportExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
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

        $cabang = Cabang::factory()->create();
        $category = ProductCategory::factory()->create();
        $uom = UnitOfMeasure::factory()->create();

        $this->warehouse = Warehouse::create([
            'kode' => 'WH-TEST',
            'name' => 'Warehouse Test',
            'tipe' => 'Kecil',
            'location' => 'Test Location',
            'telepon' => '081234567890',
            'status' => 1,
            'warna_background' => '#ffffff',
            'cabang_id' => $cabang->id,
        ]);

        $this->product = Product::create([
            'code' => 'PROD-TEST',
            'name' => 'Product Test',
            'sku' => 'SKU-TEST-001',
            'description' => 'Test Product',
            'status' => 1,
            'cabang_id' => $cabang->id,
            'product_category_id' => $category->id,
            'supplier_id' => null,
            'uom_id' => $uom->id,
            'kode_merk' => 'MERK-TEST',
            'cost_price' => 1000,
            'sell_price' => 1500,
            'is_active' => 1,
        ]);

        $this->rak = Rak::create([
            'code' => 'RAK-A1',
            'name' => 'Rak A1',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->inventory = InventoryStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 100,
            'qty_reserved' => 10,
            'qty_min' => 5,
        ]);

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
            'notes' => 'Test movement 15 days ago',
        ]);

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
            'notes' => 'Test movement 45 days ago',
        ]);
    }

    #[Test]
    public function it_can_generate_stock_by_warehouse_report()
    {
        Excel::fake();

        $export = new InventoryReportExport(null, null, 'stock', null, null);
        Excel::store($export, 'test_stock_report.xlsx');

        Excel::assertStored('test_stock_report.xlsx');
    }

    #[Test]
    public function it_can_generate_movement_history_report()
    {
        Excel::fake();

        $export = new InventoryReportExport(null, null, 'movement', now()->subDays(30), now());
        Excel::store($export, 'test_movement_report.xlsx');

        Excel::assertStored('test_movement_report.xlsx');
    }

    #[Test]
    public function it_can_generate_aging_stock_report()
    {
        Excel::fake();

        $export = new InventoryReportExport(null, null, 'aging', null, null);
        Excel::store($export, 'test_aging_report.xlsx');

        Excel::assertStored('test_aging_report.xlsx');
    }

    #[Test]
    public function it_filters_stock_report_by_warehouse()
    {
        Excel::fake();

        $export = new InventoryReportExport($this->warehouse->id, null, 'stock', null, null);
        $data = $export->collection();

        // Should only contain data for the specified warehouse
        $this->assertCount(1, $data);
        $this->assertEquals($this->warehouse->name, $data[0]['Gudang']);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_shows_correct_stock_levels()
    {
        $export = new InventoryReportExport(null, null, 'stock', null, null);
        $data = $export->collection();

        $this->assertCount(1, $data);

        $stockData = $data[0];
        // Calculate expected qty based on movements: initial 100 + 50 (purchase_in) - 20 (sales) = 130
        $this->assertEquals(130.0, $stockData['Qty Fisik']);
        $this->assertEquals(10, $stockData['Qty Reserved']);
        $this->assertEquals(120.0, $stockData['Qty Tersedia Bebas']); // 130 - 10
    }

    #[Test]
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