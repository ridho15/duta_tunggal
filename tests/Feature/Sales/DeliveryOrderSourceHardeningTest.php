<?php

namespace Tests\Feature\Sales;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Rak;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\Warehouse;
use App\Filament\Resources\DeliveryOrderResource\Pages\CreateDeliveryOrder;
use App\Services\ReturnProductService;
use App\Services\StockAvailability;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOrderSourceHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected User $user;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;
    protected Customer $customer;
    protected Product $product;
    protected UnitOfMeasure $uom;
    protected ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sales.stock.strict_dispatch' => true,
            'sales.stock.require_surat_jalan' => true,
            'sales.stock.ledger' => true,
        ]);

        foreach ([
            ['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset'],
            ['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue'],
            ['code' => '1140.01', 'name' => 'Persediaan Barang', 'type' => 'Asset'],
            ['code' => '1140.10', 'name' => 'Persediaan Barang Dagangan', 'type' => 'Asset'],
            ['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset'],
            ['code' => '4100.10', 'name' => 'Penjualan Produk', 'type' => 'Revenue'],
            ['code' => '4110.10', 'name' => 'Diskon Penjualan Produk', 'type' => 'Expense'],
            ['code' => '4120.10', 'name' => 'Retur Penjualan Produk', 'type' => 'Expense'],
            ['code' => '5100.10', 'name' => 'HPP Barang Dagangan', 'type' => 'Expense'],
            ['code' => '2100.10', 'name' => 'Pembelian Belum Tertagih', 'type' => 'Liability'],
            ['code' => '1400.01', 'name' => 'Persediaan Sementara', 'type' => 'Asset'],
        ] as $account) {
            ChartOfAccount::firstOrCreate(['code' => $account['code']], array_merge($account, ['is_active' => true]));
        }

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama',
            'kode' => 'CBU',
            'alamat' => 'Jl. Merdeka No. 10',
            'telepon' => '021-5551234',
        ]);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);
        $this->actingAs($this->user);

        $this->warehouse1 = Warehouse::create([
            'name' => 'Gudang Pusat',
            'kode' => 'WH-01',
            'location' => 'Jakarta Barat',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->warehouse2 = Warehouse::create([
            'name' => 'Gudang Cabang',
            'kode' => 'WH-02',
            'location' => 'Jakarta Timur',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->customer = $this->createCustomer([
            'name' => 'PT Sumber Rejeki',
            'perusahaan' => 'PT Sumber Rejeki',
            'tipe' => 'PKP',
        ]);

        $this->uom = UnitOfMeasure::create(['name' => 'Pcs', 'abbreviation' => 'pcs']);
        $this->category = ProductCategory::create([
            'name' => 'Bahan Bangunan',
            'kode' => 'BB',
            'code' => 'BB',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->product = Product::create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Semen Padang 50kg',
            'sku' => 'SMP-50KG',
            'code' => 'SMP-50KG',
            'uom_id' => $this->uom->id,
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $this->category->id,
            'kode_merk' => 'PADANG',
            'sell_price' => 75000,
            'cost_price' => 50000,
            'harga_batas' => 5,
        ]);
    }

    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'cabang_id' => $this->cabang->id,
            'name' => 'Customer Test',
            'code' => 'CUST-' . uniqid(),
            'address' => 'Jl. Test No. 1',
            'telephone' => '021-123',
            'phone' => '08123456789',
            'email' => 'customer' . uniqid() . '@test.com',
            'perusahaan' => 'PT Test',
            'tipe' => 'PKP',
            'fax' => '021-1234',
            'nik_npwp' => '1234567890',
            'tipe_pembayaran' => 'Bebas',
            'tempo_kredit' => 30,
            'credit_limit' => 500000000,
        ], $attributes));
    }

    private function stock(int $warehouseId, float $available, float $reserved = 0, ?int $rakId = null): InventoryStock
    {
        return InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $warehouseId, 'rak_id' => $rakId],
            ['qty_available' => $available, 'qty_reserved' => $reserved]
        );
    }

    private function approvedSo(float $qty, string $number): array
    {
        $so = SaleOrder::create([
            'so_number' => $number,
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Tujuan 1',
        ]);
        $item = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'price_per_unit' => 75000,
            'subtotal' => $qty * 75000,
            'total' => $qty * 75000,
        ]);

        return [$so, $item];
    }

    // ---------------------------------------------------------------- A1

    public function test_best_rak_id_picks_rak_with_most_free_stock(): void
    {
        $rakA = Rak::create(['name' => 'Rak A', 'code' => 'RA', 'warehouse_id' => $this->warehouse1->id]);
        $rakB = Rak::create(['name' => 'Rak B', 'code' => 'RB', 'warehouse_id' => $this->warehouse1->id]);
        $this->stock($this->warehouse1->id, 5, 0, $rakA->id);
        $this->stock($this->warehouse1->id, 40, 10, $rakB->id);

        $this->assertSame($rakB->id, app(StockAvailability::class)->bestRakId($this->product->id, $this->warehouse1->id));
        $this->assertNull(app(StockAvailability::class)->bestRakId($this->product->id, $this->warehouse2->id));
    }

    public function test_create_delivery_order_failure_during_stock_deduction_rolls_back_everything_and_notifies(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['cabang_id' => $this->cabang->id, 'manage_type' => 'all']);
        \Spatie\Permission\Models\Role::findOrCreate('Super Admin', 'web');
        $user->assignRole('Super Admin');
        $this->actingAs($user);

        $this->stock($this->warehouse1->id, 10);
        [$so, $soItem] = $this->approvedSo(12, 'SO-A1-ROLLBACK');

        // Tiap sumber (6) lolos pemeriksaan awal terhadap stok 10, tetapi totalnya (12) melebihi stok:
        // pemotongan stok gagal pada sumber ke-2, saat relasi disimpan.
        $component = Livewire::test(CreateDeliveryOrder::class)
            ->fillForm([
                'do_number' => 'DO-A1-ROLLBACK',
                'salesOrders' => [$so->id],
                'cabang_id' => $this->cabang->id,
                'delivery_date' => now()->addDay()->toDateTimeString(),
                'deliveryOrderItem' => [[
                    'options_from' => 2,
                    'sale_order_item_id' => $soItem->id,
                    'product_id' => $this->product->id,
                    'quantity' => 12,
                    'warehouseSources' => [
                        ['warehouse_id' => $this->warehouse1->id, 'quantity' => 6],
                        ['warehouse_id' => $this->warehouse1->id, 'quantity' => 6],
                    ],
                ]],
            ])
            ->call('create');

        $component->assertNotified('Delivery Order Gagal Dibuat');
        $this->assertDatabaseMissing('delivery_orders', ['do_number' => 'DO-A1-ROLLBACK']);
        $this->assertSame(0, DeliveryOrderItem::count());
        $this->assertSame(0, StockMovement::where('type', 'sales')->count());
        $this->assertEquals(10, InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse1->id)->value('qty_available'));
    }


    public function test_create_delivery_order_deducts_only_selected_source_warehouse_not_header_warehouse(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['cabang_id' => $this->cabang->id, 'manage_type' => 'all']);
        \Spatie\Permission\Models\Role::findOrCreate('Super Admin', 'web');
        $user->assignRole('Super Admin');
        $this->actingAs($user);

        // Gudang header DO (dari item SO) = gudang1 yang stoknya KOSONG; user memilih sumber gudang2 yang berstok.
        $this->stock($this->warehouse1->id, 0);
        $this->stock($this->warehouse2->id, 20);
        [$so, $soItem] = $this->approvedSo(8, 'SO-A1-HEADER');
        $soItem->update(['warehouse_id' => $this->warehouse1->id]);

        Livewire::test(CreateDeliveryOrder::class)
            ->fillForm([
                'do_number' => 'DO-A1-HEADER',
                'salesOrders' => [$so->id],
                'cabang_id' => $this->cabang->id,
                'delivery_date' => now()->addDay()->toDateTimeString(),
                'deliveryOrderItem' => [[
                    'options_from' => 2,
                    'sale_order_item_id' => $soItem->id,
                    'product_id' => $this->product->id,
                    'quantity' => 8,
                    'warehouseSources' => [['warehouse_id' => $this->warehouse2->id, 'quantity' => 8]],
                ]],
            ])
            ->call('create');

        $this->assertDatabaseHas('delivery_orders', ['id' => DeliveryOrder::where('do_number', 'like', 'DO-%')->value('id')]);
        $this->assertEquals(0, InventoryStock::where('warehouse_id', $this->warehouse1->id)->value('qty_available'));
        $this->assertEquals(12, InventoryStock::where('warehouse_id', $this->warehouse2->id)->value('qty_available'));
        $this->assertEquals(8, StockMovement::where('type', 'sales')->sum('quantity'));
    }

    public function test_free_for_delivery_order_source_adds_back_stock_already_deducted_by_the_same_do_item(): void
    {
        $this->stock($this->warehouse1->id, 30);
        [$do, $doItem] = $this->deliveredDo('EDIT-ADD', [[$this->warehouse1->id, 21]]);   // stok 30 → 9 setelah DO dibuat

        $availability = app(StockAvailability::class);
        $soItemId = $doItem->sale_order_item_id;

        // Stok bebas biasa hanya 9, sehingga edit DO yang MENGURANGI qty ke 11 dulu ditolak ("tersedia 9, diminta 11")
        $this->assertEquals(9, $availability->freeForSaleOrderItem($this->product->id, $this->warehouse1->id, $soItemId));

        // Dengan 21 yang sudah dipotong DO ini ditambahkan kembali: 30 → pengurangan/penambahan sampai total semula diizinkan
        $this->assertEquals(30, $availability->freeForDeliveryOrderSource($this->product->id, $this->warehouse1->id, $soItemId, null, $doItem->id));

        // Tanpa item DO (pembuatan baru) perilaku tidak berubah
        $this->assertEquals(9, $availability->freeForDeliveryOrderSource($this->product->id, $this->warehouse1->id, $soItemId, null, null));

        // Item DO lain tidak ikut ditambahkan kembali
        [, $otherItem] = $this->deliveredDo('EDIT-ADD-2', [[$this->warehouse1->id, 4]]);
        $this->assertEquals(5 + 21, $availability->freeForDeliveryOrderSource($this->product->id, $this->warehouse1->id, $soItemId, null, $doItem->id));
    }

    // ---------------------------------------------------------------- B8

    public function test_fit_sources_keeps_allocation_when_stock_is_sufficient(): void
    {
        $this->stock($this->warehouse1->id, 100);
        [, $soItem] = $this->approvedSo(30, 'SO-B8-OK');

        $fitted = app(StockAvailability::class)->fitSourcesToStock(
            [['warehouse_id' => $this->warehouse1->id, 'quantity' => 30, 'rak_id' => null]],
            $this->product->id, $soItem->id, 30, $this->cabang->id
        );

        $this->assertCount(1, $fitted);
        $this->assertSame($this->warehouse1->id, $fitted[0]['warehouse_id']);
        $this->assertEquals(30, $fitted[0]['quantity']);
    }

    public function test_fit_sources_moves_shortfall_from_short_allocation_to_warehouse_with_stock(): void
    {
        $this->stock($this->warehouse1->id, 10);   // gudang alokasi, stok kurang
        $this->stock($this->warehouse2->id, 50);   // gudang lain berstok
        [, $soItem] = $this->approvedSo(30, 'SO-B8-SPILL');

        $fitted = collect(app(StockAvailability::class)->fitSourcesToStock(
            [['warehouse_id' => $this->warehouse1->id, 'quantity' => 30, 'rak_id' => null]],
            $this->product->id, $soItem->id, 30, $this->cabang->id
        ))->keyBy('warehouse_id');

        $this->assertEquals(10, $fitted[$this->warehouse1->id]['quantity']);
        $this->assertEquals(20, $fitted[$this->warehouse2->id]['quantity']);
        $this->assertEquals(30, $fitted->sum('quantity'));
    }

    public function test_fit_sources_drops_empty_allocation_and_uses_warehouse_with_stock(): void
    {
        $this->stock($this->warehouse1->id, 0);
        $this->stock($this->warehouse2->id, 80);
        [, $soItem] = $this->approvedSo(30, 'SO-B8-EMPTY');

        $fitted = app(StockAvailability::class)->fitSourcesToStock(
            [['warehouse_id' => $this->warehouse1->id, 'quantity' => 30, 'rak_id' => null]],
            $this->product->id, $soItem->id, 30, $this->cabang->id
        );

        $this->assertCount(1, $fitted);
        $this->assertSame($this->warehouse2->id, $fitted[0]['warehouse_id']);
        $this->assertEquals(30, $fitted[0]['quantity']);
    }

    public function test_fit_sources_charges_shortfall_to_first_source_when_total_stock_is_insufficient(): void
    {
        $this->stock($this->warehouse1->id, 10);
        $this->stock($this->warehouse2->id, 5);
        [, $soItem] = $this->approvedSo(30, 'SO-B8-SHORT');

        $fitted = app(StockAvailability::class)->fitSourcesToStock(
            [['warehouse_id' => $this->warehouse1->id, 'quantity' => 30, 'rak_id' => null]],
            $this->product->id, $soItem->id, 30, $this->cabang->id
        );

        // Total tetap = qty agar validasi form/halaman Create melaporkan kekurangan stok dengan jelas.
        $this->assertEquals(30, collect($fitted)->sum('quantity'));
    }

    public function test_fit_sources_fills_rak_with_most_stock(): void
    {
        $rakA = Rak::create(['name' => 'Rak A', 'code' => 'RA', 'warehouse_id' => $this->warehouse1->id]);
        $rakB = Rak::create(['name' => 'Rak B', 'code' => 'RB', 'warehouse_id' => $this->warehouse1->id]);
        $this->stock($this->warehouse1->id, 3, 0, $rakA->id);
        $this->stock($this->warehouse1->id, 60, 0, $rakB->id);
        [, $soItem] = $this->approvedSo(20, 'SO-B8-RAK');

        $fitted = app(StockAvailability::class)->fitSourcesToStock(
            [['warehouse_id' => $this->warehouse1->id, 'quantity' => 20, 'rak_id' => null]],
            $this->product->id, $soItem->id, 20, $this->cabang->id
        );

        $this->assertSame($rakB->id, $fitted[0]['rak_id']);
    }

    // ---------------------------------------------------------------- B16

    private function deliveredDo(string $suffix, array $sources): array
    {
        [$so, $soItem] = $this->approvedSo(array_sum(array_column($sources, 1)), 'SO-' . $suffix);
        $do = DeliveryOrder::create([
            'do_number' => 'DO-' . $suffix,
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do->salesOrders()->attach($so->id);
        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => array_sum(array_column($sources, 1)),
        ]);
        foreach ($sources as [$warehouseId, $qty]) {
            DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $doItem->id, 'warehouse_id' => $warehouseId, 'quantity' => $qty]);
        }

        return [$do, $doItem];
    }

    private function returnFor(DeliveryOrder $do, DeliveryOrderItem $doItem, string $number, float $qty): ReturnProduct
    {
        $return = ReturnProduct::create([
            'return_number' => $number,
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'draft',
            'reason' => 'Reject saat diterima',
            'return_action' => 'reduce_quantity_only',
        ]);
        ReturnProductItem::create([
            'return_product_id' => $return->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $doItem->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'condition' => 'good',   // barang baik kembali ke gudang sumber; kondisi damage/repair masuk karantina (lihat Wave3)
        ]);

        return $return;
    }

    public function test_return_product_from_multi_warehouse_do_restocks_each_origin_warehouse(): void
    {
        $this->stock($this->warehouse1->id, 10);
        $this->stock($this->warehouse2->id, 10);
        [$do, $doItem] = $this->deliveredDo('B16', [[$this->warehouse1->id, 6], [$this->warehouse2->id, 4]]);

        $this->assertEquals(4, InventoryStock::where('warehouse_id', $this->warehouse1->id)->value('qty_available'));
        $this->assertEquals(6, InventoryStock::where('warehouse_id', $this->warehouse2->id)->value('qty_available'));

        $return = $this->returnFor($do, $doItem, 'RN-B16', 8);
        app(ReturnProductService::class)->updateQuantityFromModel($return->fresh());

        // 8 pcs: 6 kembali ke gudang1 (asal), 2 ke gudang2 (asal) — bukan semuanya ke gudang header.
        // (Stok akhir tidak diasersikan: DO pengganti otomatis (B14) ikut memakai stok yang kembali; lihat Wave3ReturnByDeliveryOrderTest.)
        $returned = StockMovement::where('type', 'do_return')->get()->groupBy('warehouse_id')->map(fn ($rows) => (float) $rows->sum('quantity'));
        $this->assertEquals([$this->warehouse1->id => 6.0, $this->warehouse2->id => 2.0], $returned->all());   // jenis mutasi sendiri (B17), bukan customer_return
        $this->assertSame(0, StockMovement::where('type', 'customer_return')->count());
    }

    public function test_return_product_approval_is_idempotent_for_stock_movements(): void
    {
        $this->stock($this->warehouse1->id, 10);
        [$do, $doItem] = $this->deliveredDo('B16-IDEM', [[$this->warehouse1->id, 10]]);

        $return = $this->returnFor($do, $doItem, 'RN-B16-IDEM', 3);
        $service = app(ReturnProductService::class);
        $service->updateQuantityFromModel($return->fresh());
        $service->updateQuantityFromModel($return->fresh());

        $this->assertEquals(1, StockMovement::where('type', 'do_return')->count());
        $this->assertEquals(3.0, (float) StockMovement::where('type', 'do_return')->sum('quantity'));
    }
}
