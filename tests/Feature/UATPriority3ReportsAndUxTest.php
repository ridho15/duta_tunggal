<?php

namespace Tests\Feature;

use App\Exports\InventoryReportExport;
use App\Filament\Pages\InventoryReportPage;
use App\Filament\Resources\Reports\InventoryCardResource\Pages\ViewInventoryCard;
use App\Filament\Resources\Reports\StockReportResource\Pages\ViewStockReport;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\StockReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class UATPriority3ReportsAndUxTest extends TestCase
{
    private Cabang $cabang;
    private User $user;
    private Warehouse $warehouse;
    private Rak $rak;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(
            ['code' => 'IDR'],
            [
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'exchange_rate' => 1.0,
                'is_default' => true,
                'status' => 1,
            ]
        );

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-TEST-UAT3'],
            [
                'nama' => 'Cabang Test UAT Prioritas 3',
                'alamat' => 'Jl. Test Prioritas 3',
                'status' => 1,
                'lihat_stok_cabang_lain' => true,
            ]
        );

        $this->user = User::factory()->create([
            'username' => 'uat3_' . uniqid(),
            'email' => 'uat3_' . uniqid() . '@example.com',
            'kode_user' => 'U' . strtoupper(substr(uniqid(), -4)),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->user->givePermissionTo('view any inventory stock');
        $this->user->givePermissionTo('view any stock movement');

        Auth::login($this->user);

        $this->warehouse = Warehouse::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'WH-UAT3-' . strtoupper(substr(uniqid(), -4)),
            'name' => 'Gudang UAT 3',
            'location' => 'Area Gudang Prioritas 3',
            'status' => 1,
        ]);

        $this->rak = Rak::create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RAK-UAT3',
            'name' => 'Rak Prioritas 3',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Produk UAT 3',
            'sku' => 'SKU-UAT3-' . strtoupper(substr(uniqid(), -4)),
            'cost_price' => 15000,
        ]);

        InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->update([
                'rak_id' => $this->rak->id,
                'qty_available' => 50,
                'qty_reserved' => 5,
                'qty_min' => 10,
            ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'type' => 'purchase_in',
            'quantity' => 50,
            'value' => 750000,
            'date' => now()->subDays(5),
            'notes' => 'Penerimaan barang UAT 3',
        ]);
    }

    public function test_product_model_code_attribute_aliases_sku(): void
    {
        $this->assertEquals($this->product->sku, $this->product->code);
    }

    public function test_view_inventory_card_page_renders_and_dispatches_preview_event(): void
    {
        $startDate = now()->startOfMonth()->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $previewUrl = route('inventory-card.print', [
            'start' => $startDate,
            'end' => $endDate,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        Livewire::test(ViewInventoryCard::class)
            ->set('startDate', $startDate)
            ->set('endDate', $endDate)
            ->set('productId', $this->product->id)
            ->set('warehouseId', $this->warehouse->id)
            ->callAction('preview')
            ->assertDispatched('open-inventory-card-preview', url: $previewUrl);
    }

    public function test_inventory_card_print_and_export_routes_respond_ok(): void
    {
        $params = [
            'start' => now()->startOfMonth()->format('Y-m-d'),
            'end' => now()->format('Y-m-d'),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ];

        $printResponse = $this->get(route('inventory-card.print', $params));
        $printResponse->assertOk();
        $printResponse->assertSee('KARTU PERSEDIAAN');

        $excelResponse = $this->get(route('inventory-card.excel', $params));
        $excelResponse->assertOk();
    }

    public function test_view_stock_report_page_renders_and_dispatches_preview(): void
    {
        $startDate = now()->startOfMonth()->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $expectedUrl = route('reports.stock-report.preview') . '?' . http_build_query([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'product_ids' => [$this->product->id],
            'warehouse_ids' => [$this->warehouse->id],
        ]);

        Livewire::test(ViewStockReport::class)
            ->set('startDate', $startDate)
            ->set('endDate', $endDate)
            ->set('productIds', [$this->product->id])
            ->set('warehouseIds', [$this->warehouse->id])
            ->callAction('preview')
            ->assertDispatched('open-stock-preview', url: $expectedUrl);
    }

    public function test_inventory_report_page_renders_successfully_without_sql_errors(): void
    {
        $component = Livewire::test(InventoryReportPage::class)
            ->assertOk();

        // Switch to history movement tab
        $component->set('show_movement_history', true)
            ->set('show_aging_stock', false)
            ->assertOk();

        // Switch to aging stock tab
        $component->set('show_movement_history', false)
            ->set('show_aging_stock', true)
            ->assertOk();

        // Switch back to stock by warehouse tab
        $component->set('show_movement_history', false)
            ->set('show_aging_stock', false)
            ->assertOk();
    }

    public function test_inventory_report_export_service_and_excel_generation(): void
    {
        Excel::fake();

        $export = new InventoryReportExport($this->warehouse->id, $this->product->id, 'stock');
        $collection = $export->collection();

        $this->assertNotEmpty($collection);
        $firstRow = $collection->first();
        $this->assertEquals($this->warehouse->name, $firstRow['Gudang']);
        $this->assertEquals($this->product->sku, $firstRow['Kode Produk']);

        $service = app(InventoryReportService::class);
        $payload = $service->pdfPayload([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'stock',
        ]);

        $this->assertArrayHasKey('data', $payload);
        $this->assertNotEmpty($payload['data']);
    }
}
