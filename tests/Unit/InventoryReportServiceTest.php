<?php

namespace Tests\Unit;

use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\Reports\InventoryReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_service_backed_rows_and_pdf_payload(): void
    {
        Carbon::setTestNow('2026-04-04 10:00:00');

        $fixture = $this->createInventoryFixture();
        $service = app(InventoryReportService::class);

        $stockRows = $service->stockRows([
            'warehouse_id' => $fixture['warehouse']->id,
            'product_id' => $fixture['product']->id,
        ]);

        $movementRows = $service->movementRows([
            'warehouse_id' => $fixture['warehouse']->id,
            'product_id' => $fixture['product']->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-04-04',
        ]);

        $agingRows = $service->agingRows([
            'warehouse_id' => $fixture['warehouse']->id,
            'product_id' => $fixture['product']->id,
            'as_of_date' => '2026-04-04',
        ]);

        $this->assertCount(1, $stockRows);
        $this->assertSame('Warehouse Service', $stockRows[0]['Gudang']);
        $this->assertSame('Product Service', $stockRows[0]['Nama Produk']);
        $this->assertSame(130.0, (float) $stockRows[0]['Qty Fisik']);
        $this->assertSame(10.0, (float) $stockRows[0]['Qty Reserved']);
        $this->assertSame(120.0, (float) $stockRows[0]['Qty Tersedia Bebas']);
        $this->assertSame('Normal', $stockRows[0]['Status']);

        $this->assertCount(2, $movementRows);
        $this->assertSame('purchase_in', $movementRows[0]['Tipe Movement']);
        $this->assertSame('PurchaseReceipt #1', $movementRows[0]['Referensi']);
        $this->assertSame('sales', $movementRows[1]['Tipe Movement']);

        $this->assertCount(1, $agingRows);
        $this->assertSame('Aktif', $agingRows[0]['Kategori Aging']);
        $this->assertSame(14, $agingRows[0]['Hari Aging']);

        $payload = $service->pdfPayload([
            'warehouse_id' => $fixture['warehouse']->id,
            'product_id' => $fixture['product']->id,
            'type' => 'aging',
            'as_of_date' => '2026-04-04',
        ]);

        $this->assertSame('aging', $payload['type']);
        $this->assertSame('Warehouse Service', $payload['warehouse']?->name);
        $this->assertSame('Product Service', $payload['product']?->name);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Aktif', $payload['data'][0]['Kategori Aging']);

        Carbon::setTestNow();
    }

    private function createInventoryFixture(): array
    {
        $cabang = Cabang::factory()->create();
        $category = ProductCategory::factory()->create();
        $uom = UnitOfMeasure::factory()->create();

        $warehouse = Warehouse::create([
            'kode' => 'WH-SERVICE',
            'name' => 'Warehouse Service',
            'tipe' => 'Kecil',
            'location' => 'Service Location',
            'telepon' => '081111111111',
            'status' => 1,
            'warna_background' => '#ffffff',
            'cabang_id' => $cabang->id,
        ]);

        $product = Product::create([
            'code' => 'PROD-SERVICE',
            'name' => 'Product Service',
            'sku' => 'SKU-SERVICE-001',
            'description' => 'Service Product',
            'status' => 1,
            'cabang_id' => $cabang->id,
            'product_category_id' => $category->id,
            'supplier_id' => null,
            'uom_id' => $uom->id,
            'kode_merk' => 'MERK-SERVICE',
            'cost_price' => 1000,
            'sell_price' => 1500,
            'is_active' => 1,
        ]);

        $rak = Rak::create([
            'code' => 'RAK-S1',
            'name' => 'Rak Service',
            'warehouse_id' => $warehouse->id,
        ]);

        InventoryStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'qty_available' => 100,
            'qty_reserved' => 10,
            'qty_min' => 5,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 50,
            'value' => 50000,
            'date' => now()->subDays(15),
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            'from_model_id' => 1,
            'notes' => 'Test movement 15 days ago',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'sales',
            'quantity' => 20,
            'value' => 20000,
            'date' => now()->subDays(45),
            'from_model_type' => 'App\\Models\\DeliveryOrder',
            'from_model_id' => 1,
            'notes' => 'Test movement 45 days ago',
        ]);

        return compact('warehouse', 'product');
    }
}