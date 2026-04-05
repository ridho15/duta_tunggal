<?php

namespace Tests\Feature;

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

class InventoryReportPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_preview_view_renders_service_backed_stock_and_aging_data(): void
    {
        Carbon::setTestNow('2026-04-04 10:00:00');

        $fixture = $this->createInventoryFixture();
        $service = app(InventoryReportService::class);

        $stockHtml = view('reports.inventory_report', $service->pdfPayload([
            'warehouse_id' => $fixture['warehouse']->id,
            'product_id' => $fixture['product']->id,
            'type' => 'stock',
        ]))->render();

        $agingHtml = view('reports.inventory_report', $service->pdfPayload([
            'warehouse_id' => $fixture['warehouse']->id,
            'product_id' => $fixture['product']->id,
            'type' => 'aging',
            'as_of_date' => '2026-04-04',
        ]))->render();

        $this->assertStringContainsString('Stok Barang per Gudang', $stockHtml);
        $this->assertStringContainsString('Product Preview', $stockHtml);
        $this->assertStringContainsString('Warehouse Preview', $stockHtml);
        $this->assertStringContainsString('Normal', $stockHtml);

        $this->assertStringContainsString('Aging Stock Analysis', $agingHtml);
        $this->assertStringContainsString('Product Preview', $agingHtml);
        $this->assertStringContainsString('Warehouse Preview', $agingHtml);
        $this->assertStringContainsString('Aktif', $agingHtml);

        Carbon::setTestNow();
    }

    private function createInventoryFixture(): array
    {
        $cabang = Cabang::factory()->create();
        $category = ProductCategory::factory()->create();
        $uom = UnitOfMeasure::factory()->create();

        $warehouse = Warehouse::create([
            'kode' => 'WH-PREVIEW',
            'name' => 'Warehouse Preview',
            'tipe' => 'Kecil',
            'location' => 'Preview Location',
            'telepon' => '082222222222',
            'status' => 1,
            'warna_background' => '#ffffff',
            'cabang_id' => $cabang->id,
        ]);

        $product = Product::create([
            'code' => 'PROD-PREVIEW',
            'name' => 'Product Preview',
            'sku' => 'SKU-PREVIEW-001',
            'description' => 'Preview Product',
            'status' => 1,
            'cabang_id' => $cabang->id,
            'product_category_id' => $category->id,
            'supplier_id' => null,
            'uom_id' => $uom->id,
            'kode_merk' => 'MERK-PREVIEW',
            'cost_price' => 1200,
            'sell_price' => 1500,
            'is_active' => 1,
        ]);

        $rak = Rak::create([
            'code' => 'RAK-P1',
            'name' => 'Rak Preview',
            'warehouse_id' => $warehouse->id,
        ]);

        InventoryStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'qty_available' => 20,
            'qty_reserved' => 4,
            'qty_min' => 5,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 7,
            'value' => 8400,
            'date' => now()->subDays(15),
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            'from_model_id' => 2,
        ]);

        return compact('warehouse', 'product');
    }
}