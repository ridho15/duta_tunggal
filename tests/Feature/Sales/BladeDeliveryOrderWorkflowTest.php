<?php

namespace Tests\Feature\Sales;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Driver;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeDeliveryOrderWorkflowTest extends TestCase
{
    protected User $user;
    protected User $unauthorizedUser;
    protected Cabang $cabang;
    protected Customer $customer;
    protected Product $product;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Rak $rakA;
    protected Rak $rakB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-DO-TEST',
            'nama' => 'Cabang DO Test',
            'status' => true,
        ]);

        $roleAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $roleGuest = Role::firstOrCreate(['name' => 'Guest']);

        $this->user = User::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? User::factory()->create([
                'cabang_id' => $this->cabang->id,
                'manage_type' => ['all'],
            ]);
        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($roleAdmin);
        }

        $unique = uniqid();
        $this->unauthorizedUser = User::factory()->create([
            'email' => "unauth_{$unique}@example.com",
            'username' => "unauth_{$unique}",
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['none'],
        ]);
        $this->unauthorizedUser->assignRole($roleGuest);

        $this->warehouseA = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'name' => 'Gudang Alpha DO Test',
                'kode' => 'GDG-DO-A',
                'location' => 'Jakarta Barat',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->warehouseB = Warehouse::withoutGlobalScopes()->where('id', '!=', $this->warehouseA->id)->first()
            ?? Warehouse::create([
                'name' => 'Gudang Beta DO Test',
                'kode' => 'GDG-DO-B',
                'location' => 'Jakarta Timur',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->rakA = Rak::withoutGlobalScopes()->where('warehouse_id', $this->warehouseA->id)->first()
            ?? Rak::create([
                'warehouse_id' => $this->warehouseA->id,
                'name' => 'A-01',
                'code' => 'RAK-A-01',
            ]);

        $this->rakB = Rak::withoutGlobalScopes()->where('warehouse_id', $this->warehouseB->id)->first()
            ?? Rak::create([
                'warehouse_id' => $this->warehouseB->id,
                'name' => 'B-01',
                'code' => 'RAK-B-01',
            ]);

        $category = ProductCategory::withoutGlobalScopes()->first() ?? ProductCategory::create(['name' => 'Bahan Bangunan', 'kode' => 'BB']);
        $uom = UnitOfMeasure::withoutGlobalScopes()->first() ?? UnitOfMeasure::create(['name' => 'Batang', 'abbreviation' => 'btg']);

        $uniqueSku = uniqid();
        $this->product = Product::create([
            'sku' => 'DO-PIPE-' . $uniqueSku,
            'name' => 'Pipa PVC DO Standar ' . $uniqueSku,
            'sell_price' => 25000,
            'cost_price' => 18000,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'is_active' => true,
            'cabang_id' => $this->cabang->id,
            'kode_merk' => 'MRK-DO',
            'tipe_pajak' => 'Non Pajak',
            'pajak' => 0,
            'biaya' => 0,
            'item_value' => 18000,
            'jumlah_kelipatan_gudang_besar' => 1,
            'jumlah_jual_kategori_banyak' => 1,
        ]);

        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->warehouseA->id, 'product_id' => $this->product->id],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );

        // COA Setup
        ChartOfAccount::firstOrCreate(['code' => '1140.10'], ['name' => 'Persediaan Barang Dagangan', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1140.20'], ['name' => 'Barang Terkirim', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '4100.10'], ['name' => 'Penjualan Produk', 'type' => 'Revenue', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '5100.10'], ['name' => 'HPP Barang Dagangan', 'type' => 'Expense', 'is_active' => true]);

        $this->customer = Customer::withoutGlobalScopes()->first() ?? Customer::create([
            'code' => 'CUST-DO-001',
            'name' => 'Toko Bangunan Berkah Sentosa',
            'cabang_id' => $this->cabang->id,
            'is_active' => true,
            'tipe_pembayaran' => 'Kredit',
            'credit_limit' => 50000000,
            'address' => 'Jl. Pembangunan No. 88, Jakarta',
            'telephone' => '08123456789',
        ]);
    }

    protected function createSampleSaleOrder(float $qty = 20): SaleOrder
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-DO-TEST-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'tipe_pembayaran' => 'Kredit',
            'tempo_pembayaran' => 30,
            'total_amount' => $qty * 25000,
            'grand_total' => $qty * 25000,
            'created_by' => $this->user->id,
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'unit_price' => 25000,
            'subtotal' => $qty * 25000,
        ]);

        return $so;
    }

    protected function createSampleDeliveryOrder(string $status = 'approved'): DeliveryOrder
    {
        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->warehouseA->id, 'product_id' => $this->product->id],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );

        $so = $this->createSampleSaleOrder(15);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-TEST-' . uniqid(),
            'delivery_date' => now(),
            'warehouse_id' => $this->warehouseA->id,
            'cabang_id' => $this->cabang->id,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);

        $do->salesOrders()->attach($so->id);

        DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $so->saleOrderItem->first()->id,
            'product_id' => $this->product->id,
            'quantity' => 15,
        ]);

        return $do;
    }

    public function test_user_can_access_delivery_order_index_page_and_kpis(): void
    {
        $do = $this->createSampleDeliveryOrder('approved');

        $response = $this->actingAs($this->user)->get(route('penjualan.do.index'));

        $response->assertOk();
        $response->assertViewIs('penjualan.do.index');
        $response->assertSee('Perintah Kirim (Delivery Order)');
        $response->assertSee('Total DO');
        $response->assertSee('Siap Dicek Gudang');
        $response->assertSee($do->do_number);
    }

    public function test_delivery_order_index_filters_by_tab_and_search(): void
    {
        $doA = $this->createSampleDeliveryOrder('approved');
        $doB = $this->createSampleDeliveryOrder('confirmed');

        // Test filter tab siap_dicek
        $responseTab = $this->actingAs($this->user)->get(route('penjualan.do.index', ['tab' => 'siap_dicek']));
        $responseTab->assertOk();
        $responseTab->assertSee($doA->do_number);

        // Test search query
        $responseSearch = $this->actingAs($this->user)->get(route('penjualan.do.index', ['q' => $doA->do_number]));
        $responseSearch->assertOk();
        $responseSearch->assertSee($doA->do_number);
    }

    public function test_user_can_view_delivery_order_detail_page(): void
    {
        $do = $this->createSampleDeliveryOrder('confirmed');

        $response = $this->actingAs($this->user)->get(route('penjualan.do.show', $do->id));

        $response->assertOk();
        $response->assertViewIs('penjualan.do.show');
        $response->assertSee($do->do_number);
        $response->assertSee('Informasi Dokumen');
        $response->assertSee('Rincian Barang yang Dikeluarkan');
        $response->assertSee($this->product->name);
    }

    public function test_user_can_stream_delivery_order_pdf(): void
    {
        $do = $this->createSampleDeliveryOrder('approved');

        $response = $this->actingAs($this->user)->get(route('penjualan.do.pdf', $do->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_can_access_create_delivery_order_page_with_so(): void
    {
        // Beri stok bebas pada gudang A
        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->warehouseA->id, 'product_id' => $this->product->id],
            ['qty_available' => 50, 'qty_reserved' => 0]
        );

        $so = $this->createSampleSaleOrder(10);

        $response = $this->actingAs($this->user)->get(route('penjualan.do.create', ['so_id' => $so->id]));

        $response->assertOk();
        $response->assertViewIs('penjualan.do.create');
        $response->assertSee($so->so_number);
        $response->assertSee($this->warehouseA->name);
    }

    public function test_can_create_delivery_order_single_warehouse(): void
    {
        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->warehouseA->id, 'product_id' => $this->product->id],
            ['qty_available' => 50, 'qty_reserved' => 0]
        );

        $so = $this->createSampleSaleOrder(10);
        $soItem = $so->saleOrderItem->first();

        $payload = [
            'sale_order_id' => $so->id,
            'items' => [
                [
                    'item_id' => $soItem->id,
                    'product_id' => $this->product->id,
                    'warehouses' => [
                        $this->warehouseA->id => 10,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('penjualan.do.store'), $payload);

        $response->assertRedirect(route('penjualan.so.show', $so->id));
        $this->assertDatabaseHas('delivery_orders', [
            'warehouse_id' => $this->warehouseA->id,
            'cabang_id' => $so->cabang_id,
        ]);
    }

    public function test_can_create_delivery_order_multi_warehouse_split(): void
    {
        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->warehouseA->id, 'product_id' => $this->product->id],
            ['qty_available' => 30, 'qty_reserved' => 0]
        );
        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->warehouseB->id, 'product_id' => $this->product->id],
            ['qty_available' => 30, 'qty_reserved' => 0]
        );

        $so = $this->createSampleSaleOrder(20);
        $soItem = $so->saleOrderItem->first();

        // 12 pcs dari Gudang A, 8 pcs dari Gudang B
        $payload = [
            'sale_order_id' => $so->id,
            'items' => [
                [
                    'item_id' => $soItem->id,
                    'product_id' => $this->product->id,
                    'warehouses' => [
                        $this->warehouseA->id => 12,
                        $this->warehouseB->id => 8,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('penjualan.do.store'), $payload);

        $response->assertRedirect(route('penjualan.so.show', $so->id));

        // Harus menghasilkan 2 DO terpisah untuk 2 gudang
        $this->assertDatabaseHas('delivery_orders', ['warehouse_id' => $this->warehouseA->id]);
        $this->assertDatabaseHas('delivery_orders', ['warehouse_id' => $this->warehouseB->id]);
    }

    public function test_delivery_order_creation_validates_positive_quantity(): void
    {
        $so = $this->createSampleSaleOrder(10);
        $soItem = $so->saleOrderItem->first();

        $payload = [
            'sale_order_id' => $so->id,
            'items' => [
                [
                    'item_id' => $soItem->id,
                    'product_id' => $this->product->id,
                    'warehouses' => [
                        $this->warehouseA->id => 0,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('penjualan.do.store'), $payload);

        $response->assertSessionHas('error');
    }

    public function test_user_can_cancel_unprocessed_delivery_order_and_release_stock(): void
    {
        $do = $this->createSampleDeliveryOrder('approved');

        $response = $this->actingAs($this->user)->post(route('penjualan.do.cancel', $do->id), [
            'reason' => 'Customer meminta jadwal ulang pengiriman',
        ]);

        $response->assertRedirect(route('penjualan.do.index'));
        $response->assertSessionHas('success');

        $this->assertEquals('closed', $do->fresh()->status);
    }

    public function test_cannot_cancel_delivery_order_with_issued_surat_jalan(): void
    {
        $do = $this->createSampleDeliveryOrder('confirmed');

        $driver = Driver::withoutGlobalScopes()->first() ?? Driver::create(['name' => 'Budi Santoso', 'cabang_id' => $this->cabang->id]);
        $vehicle = Vehicle::withoutGlobalScopes()->first() ?? Vehicle::create(['plate_number' => 'B 1234 CD', 'cabang_id' => $this->cabang->id]);

        $sj = SuratJalan::create([
            'sj_number' => 'SJ-TEST-' . uniqid(),
            'cabang_id' => $this->cabang->id,
            'status' => SuratJalan::STATUS_ISSUED,
            'issued_at' => now(),
            'created_by' => $this->user->id,
        ]);
        $sj->deliveryOrder()->attach($do->id);

        $response = $this->actingAs($this->user)->post(route('penjualan.do.cancel', $do->id), [
            'reason' => 'Mencoba batalkan yang sudah punya SJ',
        ]);

        $response->assertSessionHas('error');
        $this->assertNotEquals('closed', $do->fresh()->status);
    }
}
