<?php

namespace Tests\Unit;

use App\Exports\InventoryCardExport;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\Reports\InventoryCardReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCardReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_inventory_card_report_and_export_rows_from_shared_service(): void
    {
        Carbon::setTestNow('2026-04-04 09:00:00');

        $fixture = $this->createFixture();
        $service = app(InventoryCardReportService::class);

        $report = $service->reportData([
            'start' => '2026-04-01',
            'end' => '2026-04-30',
            'warehouse_id' => $fixture['warehouse']->id,
        ]);

        $this->assertSame('Semua Produk', $report['product_label']);
        $this->assertSame('Warehouse Card', $report['warehouse_label']);
        $this->assertCount(1, $report['rows']);
        $this->assertSame('Product With Movement', $report['rows'][0]['product_name']);
        $this->assertSame(10.0, $report['rows'][0]['opening_qty']);
        $this->assertSame(5.0, $report['rows'][0]['qty_in']);
        $this->assertSame(3.0, $report['rows'][0]['qty_out']);
        $this->assertSame(12.0, $report['rows'][0]['closing_qty']);
        $this->assertSame(12000.0, $report['totals']['closing_value']);

        $exportRows = (new InventoryCardExport('2026-04-01', '2026-04-30', null, $fixture['warehouse']->id))->array();

        $this->assertCount(2, $exportRows);
        $this->assertSame('Product With Movement', $exportRows[0][1]);
        $this->assertSame('TOTAL', $exportRows[1][1]);
        $this->assertSame(12.0, $exportRows[1][10]);

        Carbon::setTestNow();
    }

    private function createFixture(): array
    {
        $cabang = Cabang::factory()->create();
        $category = ProductCategory::factory()->create();
        $uom = UnitOfMeasure::factory()->create();

        $warehouse = Warehouse::create([
            'kode' => 'WH-CARD',
            'name' => 'Warehouse Card',
            'tipe' => 'Utama',
            'location' => 'Main Warehouse',
            'telepon' => '081100000001',
            'status' => 1,
            'warna_background' => '#ffffff',
            'cabang_id' => $cabang->id,
        ]);

        $rak = Rak::create([
            'code' => 'RAK-CARD',
            'name' => 'Rak Card',
            'warehouse_id' => $warehouse->id,
        ]);

        $productWithMovement = Product::create([
            'code' => 'PROD-CARD-1',
            'name' => 'Product With Movement',
            'sku' => 'SKU-CARD-1',
            'description' => 'Card Product One',
            'status' => 1,
            'cabang_id' => $cabang->id,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'kode_merk' => 'MERK-CARD-1',
            'cost_price' => 1000,
            'sell_price' => 1500,
            'is_active' => 1,
        ]);

        $productOpeningOnly = Product::create([
            'code' => 'PROD-CARD-2',
            'name' => 'Product Opening Only',
            'sku' => 'SKU-CARD-2',
            'description' => 'Card Product Two',
            'status' => 1,
            'cabang_id' => $cabang->id,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'kode_merk' => 'MERK-CARD-2',
            'cost_price' => 1000,
            'sell_price' => 1500,
            'is_active' => 1,
        ]);

        StockMovement::create([
            'product_id' => $productWithMovement->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 10,
            'value' => 10000,
            'date' => '2026-03-25 08:00:00',
        ]);

        StockMovement::create([
            'product_id' => $productWithMovement->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 5,
            'value' => 5000,
            'date' => '2026-04-02 08:00:00',
        ]);

        StockMovement::create([
            'product_id' => $productWithMovement->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'sales',
            'quantity' => 3,
            'value' => 3000,
            'date' => '2026-04-03 08:00:00',
        ]);

        StockMovement::create([
            'product_id' => $productOpeningOnly->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 7,
            'value' => 7000,
            'date' => '2026-03-20 08:00:00',
        ]);

        return [
            'warehouse' => $warehouse,
            'product' => $productWithMovement,
        ];
    }
}