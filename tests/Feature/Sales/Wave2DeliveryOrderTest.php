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
use App\Services\DeliveryOrderSplitter;
use App\Services\ReturnProductService;
use App\Services\StockAvailability;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave2DeliveryOrderTest extends TestCase
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

    private function newUser(): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['cabang_id' => $this->cabang->id, 'manage_type' => 'all']);
        \Spatie\Permission\Models\Role::findOrCreate('Super Admin', 'web');
        $user->assignRole('Super Admin');
        $this->actingAs($user);

        return $user;
    }

    // ---------------------------------------------------------------- B7 baris sumber = semua gudang berstok

    public function test_source_rows_list_every_warehouse_with_free_stock_and_fill_quantity_greedily(): void
    {
        $this->stock($this->warehouse1->id, 10);
        $this->stock($this->warehouse2->id, 50);
        [, $soItem] = $this->approvedSo(30, 'SO-B7-ROWS');

        $rows = collect(app(StockAvailability::class)->sourceRowsForItem($this->product->id, $soItem->id, 30, $this->cabang->id))->keyBy('warehouse_id');

        // Kedua gudang tampil; gudang berstok terbanyak (2) diisi dulu 30, gudang 1 tetap tampil dengan qty 0
        $this->assertCount(2, $rows);
        $this->assertEquals(30, $rows[$this->warehouse2->id]['quantity']);
        $this->assertEquals(0, $rows[$this->warehouse1->id]['quantity']);
        $this->assertEquals(10, $rows[$this->warehouse1->id]['free']);
    }

    public function test_source_rows_prefer_item_warehouse_up_to_its_stock_and_spill_to_others(): void
    {
        $this->stock($this->warehouse1->id, 12);
        $this->stock($this->warehouse2->id, 50);
        [, $soItem] = $this->approvedSo(30, 'SO-B7-PREF');

        $rows = collect(app(StockAvailability::class)->sourceRowsForItem(
            $this->product->id, $soItem->id, 30, $this->cabang->id, [$this->warehouse1->id], [$this->warehouse1->id => 30.0]
        ))->keyBy('warehouse_id');

        $this->assertEquals(12, $rows[$this->warehouse1->id]['quantity']);   // gudang pilihan habis dulu
        $this->assertEquals(18, $rows[$this->warehouse2->id]['quantity']);
    }

    public function test_source_rows_exclude_warehouses_without_free_stock_and_list_each_rak(): void
    {
        $rakA = Rak::create(['name' => 'Rak A', 'code' => 'RA', 'warehouse_id' => $this->warehouse1->id]);
        $rakB = Rak::create(['name' => 'Rak B', 'code' => 'RB', 'warehouse_id' => $this->warehouse1->id]);
        $this->stock($this->warehouse1->id, 5, 0, $rakA->id);
        $this->stock($this->warehouse1->id, 20, 0, $rakB->id);
        $this->stock($this->warehouse2->id, 0);   // tidak ada stok → tidak tampil
        [, $soItem] = $this->approvedSo(10, 'SO-B7-RAK');

        $rows = collect(app(StockAvailability::class)->sourceRowsForItem($this->product->id, $soItem->id, 10, $this->cabang->id));

        $this->assertCount(2, $rows);
        $this->assertSame([$this->warehouse1->id], $rows->pluck('warehouse_id')->unique()->values()->all());
        $this->assertEquals(10, $rows->firstWhere('rak_id', $rakB->id)['quantity']);   // rak berstok terbanyak diisi dulu
        $this->assertEquals(0, $rows->firstWhere('rak_id', $rakA->id)['quantity']);
    }

    // ---------------------------------------------------------------- B9 pemecahan DO per gudang

    private function doWithSources(string $suffix, array $sources, float $additionalCost = 0): array
    {
        [$so, $soItem] = $this->approvedSo(array_sum(array_column($sources, 1)), 'SO-' . $suffix);
        \App\Observers\DeliveryOrderObserver::$deferStockDeduction = true;
        try {
            $do = DeliveryOrder::create([
                'do_number' => 'DO-' . $suffix, 'cabang_id' => $this->cabang->id, 'delivery_date' => now(), 'status' => 'draft',
                'warehouse_id' => $sources[0][0], 'additional_cost' => $additionalCost, 'additional_cost_description' => $additionalCost ? 'Ongkir uji' : null,
            ]);
            $do->salesOrders()->attach($so->id);
            $item = DeliveryOrderItem::create(['delivery_order_id' => $do->id, 'sale_order_item_id' => $soItem->id, 'product_id' => $this->product->id, 'quantity' => array_sum(array_column($sources, 1))]);
            foreach ($sources as [$warehouseId, $qty]) {
                DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $item->id, 'warehouse_id' => $warehouseId, 'quantity' => $qty]);
            }
        } finally {
            \App\Observers\DeliveryOrderObserver::$deferStockDeduction = false;
        }

        return [$do, $so, $item];
    }

    public function test_splitter_splits_a_multi_warehouse_do_into_one_do_per_warehouse(): void
    {
        $this->stock($this->warehouse1->id, 50);
        $this->stock($this->warehouse2->id, 50);
        [$do, $so] = $this->doWithSources('SPLIT', [[$this->warehouse1->id, 6], [$this->warehouse2->id, 4]], additionalCost: 75000);

        $orders = app(DeliveryOrderSplitter::class)->splitByWarehouse($do);

        $this->assertCount(2, $orders);
        $this->assertSame(['DO-SPLIT-01', 'DO-SPLIT-02'], collect($orders)->pluck('do_number')->all());
        $this->assertSame([$this->warehouse1->id, $this->warehouse2->id], collect($orders)->pluck('warehouse_id')->map(fn ($id) => (int) $id)->all());

        // Item & sumber per gudang; total kuantitas SO tetap 10
        $first = $orders[0]->deliveryOrderItem()->with('warehouseSources')->get();
        $second = $orders[1]->deliveryOrderItem()->with('warehouseSources')->get();
        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertEquals(6, $first[0]->quantity);
        $this->assertEquals(4, $second[0]->quantity);
        $this->assertSame([$this->warehouse1->id], $first[0]->warehouseSources->pluck('warehouse_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$this->warehouse2->id], $second[0]->warehouseSources->pluck('warehouse_id')->map(fn ($id) => (int) $id)->all());

        // Kedua DO terhubung ke SO yang sama; ongkir hanya di DO pertama
        $this->assertEquals([$so->id], $orders[1]->salesOrders()->pluck('sale_orders.id')->all());
        $this->assertEquals(75000.0, (float) $orders[0]->additional_cost);
        $this->assertEquals(0.0, (float) $orders[1]->additional_cost);

        // Belum ada stok terpotong (pemotongan dijalankan setelah pemecahan)
        $this->assertSame(0, StockMovement::where('type', 'sales')->count());
    }

    public function test_splitter_leaves_a_single_warehouse_do_untouched(): void
    {
        $this->stock($this->warehouse1->id, 50);
        [$do] = $this->doWithSources('NOSPLIT', [[$this->warehouse1->id, 8]]);

        $orders = app(DeliveryOrderSplitter::class)->splitByWarehouse($do);

        $this->assertCount(1, $orders);
        $this->assertSame('DO-NOSPLIT', $orders[0]->do_number);
        $this->assertSame(1, DeliveryOrder::count());
    }

    public function test_splitter_handles_two_items_with_mixed_warehouses(): void
    {
        $this->stock($this->warehouse1->id, 50);
        $this->stock($this->warehouse2->id, 50);
        [$do, $so, $item] = $this->doWithSources('MIX', [[$this->warehouse1->id, 5], [$this->warehouse2->id, 5]]);

        // Item kedua hanya dari gudang 2
        $product2 = Product::create([
            'cabang_id' => $this->cabang->id, 'name' => 'Produk 2', 'sku' => 'P2', 'code' => 'P2', 'uom_id' => $this->uom->id, 'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $this->category->id, 'kode_merk' => 'X', 'sell_price' => 1000, 'cost_price' => 500, 'harga_batas' => 5,
        ]);
        $soItem2 = SaleOrderItem::create(['sale_order_id' => $so->id, 'product_id' => $product2->id, 'quantity' => 3, 'price_per_unit' => 1000, 'subtotal' => 3000, 'total' => 3000]);
        \App\Observers\DeliveryOrderObserver::$deferStockDeduction = true;
        $item2 = DeliveryOrderItem::create(['delivery_order_id' => $do->id, 'sale_order_item_id' => $soItem2->id, 'product_id' => $product2->id, 'quantity' => 3]);
        DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $item2->id, 'warehouse_id' => $this->warehouse2->id, 'quantity' => 3]);
        \App\Observers\DeliveryOrderObserver::$deferStockDeduction = false;

        $orders = app(DeliveryOrderSplitter::class)->splitByWarehouse($do);

        $this->assertCount(2, $orders);
        $this->assertEquals(1, $orders[0]->deliveryOrderItem()->count());   // hanya item 1 (5 dari gudang 1)
        $this->assertEquals(2, $orders[1]->deliveryOrderItem()->count());   // item 1 (5) + item 2 (3) dari gudang 2
        $this->assertEquals(13, DeliveryOrderItem::sum('quantity'));        // total kuantitas tetap 5+5+3
    }

    // ---------------------------------------------------------------- B7+B9 lewat halaman Create (Livewire)

    public function test_create_page_splits_by_warehouse_ignores_zero_rows_and_deducts_each_warehouse(): void
    {
        $this->newUser();
        $this->stock($this->warehouse1->id, 10);
        $this->stock($this->warehouse2->id, 50);
        $warehouse3 = Warehouse::create(['name' => 'Gudang Ketiga', 'kode' => 'WH-03', 'location' => 'Bekasi', 'cabang_id' => $this->cabang->id, 'status' => 1]);
        $this->stock($warehouse3->id, 7);   // tampil di form tetapi dibiarkan 0
        [$so, $soItem] = $this->approvedSo(25, 'SO-W2-CREATE');
        $so->update(['shipping_cost' => 90000]);

        Livewire::test(CreateDeliveryOrder::class)
            ->fillForm([
                'do_number' => 'DO-W2-CREATE',
                'salesOrders' => [$so->id],
                'cabang_id' => $this->cabang->id,
                'delivery_date' => now()->addDay()->toDateTimeString(),
                'deliveryOrderItem' => [[
                    'options_from' => 2, 'sale_order_item_id' => $soItem->id, 'product_id' => $this->product->id, 'quantity' => 25,
                    'warehouseSources' => [
                        ['warehouse_id' => $this->warehouse1->id, 'quantity' => 10],
                        ['warehouse_id' => $this->warehouse2->id, 'quantity' => 15],
                        ['warehouse_id' => $warehouse3->id, 'quantity' => 0],
                    ],
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $orders = DeliveryOrder::orderBy('do_number')->get();
        $this->assertCount(2, $orders);
        $this->assertSame(['DO-W2-CREATE-01', 'DO-W2-CREATE-02'], $orders->pluck('do_number')->all());
        $this->assertEquals(0, DeliveryOrderItemWarehouseSource::where('quantity', '<=', 0)->count());   // baris 0 dibuang
        $this->assertEquals(2, DeliveryOrderItemWarehouseSource::count());

        // Stok terpotong per gudang tepat sekali; gudang ketiga tidak tersentuh
        $this->assertEquals(0, InventoryStock::where('warehouse_id', $this->warehouse1->id)->value('qty_available'));
        $this->assertEquals(35, InventoryStock::where('warehouse_id', $this->warehouse2->id)->value('qty_available'));
        $this->assertEquals(7, InventoryStock::where('warehouse_id', $warehouse3->id)->value('qty_available'));
        $this->assertEquals(2, StockMovement::where('type', 'sales')->count());

        // Ongkir hanya di DO pertama; keduanya menunggu konfirmasi gudang masing-masing
        $this->assertEquals(90000.0, (float) $orders[0]->additional_cost);
        $this->assertEquals(0.0, (float) $orders[1]->additional_cost);
        $this->assertSame(['request_stock', 'request_stock'], $orders->pluck('status')->all());
        $this->assertSame(2, \App\Models\WarehouseConfirmation::where('confirmable_type', DeliveryOrder::class)->count());
    }

    public function test_create_page_with_one_warehouse_keeps_a_single_do_with_the_typed_number(): void
    {
        $this->newUser();
        $this->stock($this->warehouse1->id, 40);
        $this->stock($this->warehouse2->id, 40);
        [$so, $soItem] = $this->approvedSo(12, 'SO-W2-ONE');

        Livewire::test(CreateDeliveryOrder::class)
            ->fillForm([
                'do_number' => 'DO-W2-ONE',
                'salesOrders' => [$so->id],
                'cabang_id' => $this->cabang->id,
                'delivery_date' => now()->addDay()->toDateTimeString(),
                'deliveryOrderItem' => [[
                    'options_from' => 2, 'sale_order_item_id' => $soItem->id, 'product_id' => $this->product->id, 'quantity' => 12,
                    'warehouseSources' => [
                        ['warehouse_id' => $this->warehouse1->id, 'quantity' => 0],
                        ['warehouse_id' => $this->warehouse2->id, 'quantity' => 12],
                    ],
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['DO-W2-ONE'], DeliveryOrder::pluck('do_number')->all());
        $this->assertEquals(28, InventoryStock::where('warehouse_id', $this->warehouse2->id)->value('qty_available'));
        $this->assertEquals(40, InventoryStock::where('warehouse_id', $this->warehouse1->id)->value('qty_available'));
    }
}
