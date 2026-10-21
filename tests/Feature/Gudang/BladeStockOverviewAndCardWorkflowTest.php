<?php

namespace Tests\Feature\Gudang;

use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeStockOverviewAndCardWorkflowTest extends TestCase
{
    protected User $authorizedUser;
    protected User $unauthorizedUser;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected ProductCategory $category;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        // Bersihkan data mutasi uji jika ada
        StockMovement::where('notes', 'like', 'TEST-STOCK-CARD-%')->delete();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-STOCK',
            'nama' => 'Cabang Test Stok',
            'alamat' => 'Jl. Test Gudang No. 1',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::withoutGlobalScopes()->first()
            ?? Warehouse::create([
                'kode' => 'WH-TEST-01',
                'name' => 'Gudang Utama Test',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->category = ProductCategory::firstOrCreate(
            ['kode' => 'CAT-TEST'],
            ['name' => 'Kategori Uji Stok', 'kenaikan_harga' => 10]
        );

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'Pcs']
        );

        // Siapkan permission dan role
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perm = Permission::firstOrCreate(['name' => 'view any inventory stock', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $this->authorizedUser = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'stock_tester',
            'first_name' => 'Stock',
            'last_name' => 'Tester',
            'email' => 'stock-tester@dutatunggal.co.id',
            'name' => 'Stock Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (!$this->authorizedUser->hasRole('Super Admin')) {
            $this->authorizedUser->assignRole('Super Admin');
        }

        $this->unauthorizedUser = User::firstOrCreate(
            ['email' => 'unauth-stock@dutatunggal.co.id'],
            [
                'username' => 'unauth_stock_' . uniqid(),
                'first_name' => 'Unauthorized',
                'last_name' => 'Stock',
                'name' => 'Unauthorized Stock User',
                'password' => bcrypt('password'),
                'cabang_id' => $this->cabang->id,
                'manage_type' => [],
            ]
        );
        $this->unauthorizedUser->syncRoles([]);
        $this->unauthorizedUser->syncPermissions([]);
    }

    protected function createTestProduct(array $attributes = []): Product
    {
        $unique = uniqid();
        return Product::create(array_merge([
            'name' => 'Produk Test ' . $unique,
            'sku' => 'SKU-' . $unique,
            'product_category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'kode_merk' => 'MRK-TEST',
            'cabang_id' => $this->cabang->id,
            'cost_price' => 50000,
            'sell_price' => 65000,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * 1. Test akses halaman Cek Stok (/gudang/stok) dan melihat kartu KPI.
     */
    public function test_user_can_access_stock_overview_page_and_see_kpi_cards(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.stok.index'));

        $response->assertSee('Cek Stok');
        $response->assertSee('Ketersediaan Barang');
        $response->assertSee('Katalog Produk Aktif');
        $response->assertSee('Total Fisik di Gudang');
        $response->assertSee('Stok Bebas (Siap Kirim)');
        $response->assertSee('Estimasi Nilai Aset');
    }

    /**
     * 2. Test filter pencarian berdasarkan nama dan SKU produk.
     */
    public function test_stock_overview_filters_by_search_sku_and_name(): void
    {
        $uniqueSuffix = uniqid();
        $prodA = $this->createTestProduct([
            'name' => 'Besi Beton A Ulir ' . $uniqueSuffix,
            'sku' => 'SKU-A-' . $uniqueSuffix,
        ]);

        $prodB = $this->createTestProduct([
            'name' => 'Semen Padang B ' . $uniqueSuffix,
            'sku' => 'SKU-B-' . $uniqueSuffix,
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('gudang.stok.index', [
            'search' => 'Besi Beton A Ulir ' . $uniqueSuffix,
        ]));

        $response->assertStatus(200);
        $response->assertSee($prodA->name);
        $response->assertDontSee($prodB->name);
    }

    /**
     * 3. Test filter kategori dan gudang pada Cek Stok.
     */
    public function test_stock_overview_filters_by_category_and_warehouse(): void
    {
        $response = $this->actingAs($this->authorizedUser)->get(route('gudang.stok.index', [
            'category_id' => $this->category->id,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Daftar Persediaan Produk');
    }

    /**
     * 4. Test filter status stok (in_stock, low_stock, out_of_stock).
     */
    public function test_stock_overview_filters_by_stock_status(): void
    {
        foreach (['all', 'in_stock', 'low_stock', 'out_of_stock'] as $status) {
            $response = $this->actingAs($this->authorizedUser)->get(route('gudang.stok.index', [
                'status' => $status,
            ]));
            $response->assertStatus(200);
        }
    }

    /**
     * 5. Test akses kartu persediaan per produk (/gudang/stok/{product}/kartu).
     */
    public function test_user_can_access_stock_card_page_for_product(): void
    {
        $product = $this->createTestProduct([
            'name' => 'Pipa Galvanis Test ' . uniqid(),
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.stok.card', $product->id));

        $response->assertStatus(200);
        $response->assertSee('Kartu Persediaan: ' . $product->name);
        $response->assertSee('Saldo Awal');
        $response->assertSee('Total Masuk (+)');
        $response->assertSee('Total Keluar (-)');
        $response->assertSee('Saldo Akhir');
    }

    /**
     * 6. Test perhitungan saldo awal, mutasi masuk/keluar, dan running balance.
     */
    public function test_stock_card_calculates_opening_balance_and_running_balance_correctly(): void
    {
        $product = $this->createTestProduct([
            'name' => 'Baja WF Mutasi ' . uniqid(),
            'cost_price' => 500000,
        ]);

        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        // Saldo awal: mutasi 50 unit masuk bulan lalu
        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'value' => 25000000,
            'type' => 'purchase_in',
            'reference_id' => 'GR-TEST-OP-' . uniqid(),
            'date' => now()->subMonths(1)->toDateString() . ' 10:00:00',
            'notes' => 'TEST-STOCK-CARD-Saldo-Awal',
        ]);

        // Mutasi periode ini: Masuk 20 unit
        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
            'value' => 10000000,
            'type' => 'purchase_in',
            'reference_id' => 'GR-TEST-IN-' . uniqid(),
            'date' => now()->startOfMonth()->addDays(2)->toDateString() . ' 11:00:00',
            'notes' => 'TEST-STOCK-CARD-Masuk',
        ]);

        // Mutasi periode ini: Keluar 15 unit
        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 15,
            'value' => 7500000,
            'type' => 'sales',
            'reference_id' => 'DO-TEST-OUT-' . uniqid(),
            'date' => now()->startOfMonth()->addDays(5)->toDateString() . ' 14:00:00',
            'notes' => 'TEST-STOCK-CARD-Keluar',
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('gudang.stok.card', [
            'product' => $product->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertStatus(200);

        // Saldo Awal = 50, Masuk = +20, Keluar = -15, Saldo Akhir = 55 (50 + 20 - 15)
        $response->assertSee('50');
        $response->assertSee('+20');
        $response->assertSee('-15');
        $response->assertSee('55');
    }

    /**
     * 7. Test filter rentang tanggal dan gudang pada kartu persediaan.
     */
    public function test_stock_card_filters_by_date_range_and_warehouse(): void
    {
        $product = $this->createTestProduct([
            'name' => 'Seng Gelombang ' . uniqid(),
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('gudang.stok.card', [
            'product' => $product->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee($product->name);
    }

    /**
     * 8. Test streaming PDF Kartu Persediaan Produk Tunggal (A4 Landscape).
     */
    public function test_user_can_stream_stock_card_pdf(): void
    {
        $product = $this->createTestProduct([
            'name' => 'Cat Tembok Putih ' . uniqid(),
        ]);

        $response = $this->actingAs($this->authorizedUser)->get(route('gudang.stok.card.pdf', [
            'product' => $product->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * 9. Test akses laporan kartu persediaan konsolidasi dan streaming PDF.
     */
    public function test_user_can_access_consolidated_inventory_card_report_and_pdf(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.stok.inventory-card'));

        $response->assertStatus(200);
        $response->assertSee('Laporan Kartu Persediaan (Konsolidasi)');
        $response->assertSee('Total Saldo Awal');
        $response->assertSee('Total Mutasi Masuk');

        $pdfResponse = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.stok.inventory-card.pdf', [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ]));

        $pdfResponse->assertStatus(200);
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('content-type'));
    }

    /**
     * 10. Test otorisasi: pengguna tanpa hak akses tidak dapat mengakses halaman Cek Stok.
     */
    public function test_user_without_permission_cannot_access_stock_pages(): void
    {
        $product = Product::first() ?? $this->createTestProduct();

        $resIndex = $this->actingAs($this->unauthorizedUser)
            ->get(route('gudang.stok.index'));
        $resIndex->assertStatus(403);

        $resCard = $this->actingAs($this->unauthorizedUser)
            ->get(route('gudang.stok.card', $product->id));
        $resCard->assertStatus(403);
    }
}
