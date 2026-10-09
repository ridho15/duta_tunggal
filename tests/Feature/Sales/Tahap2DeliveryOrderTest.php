<?php

namespace Tests\Feature\Sales;

use App\Exceptions\DeliveryOrderTransitionException;
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
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryOrderSourceValidator;
use App\Services\DeliveryOrderTransitions;
use App\Services\SaleOrderDeliveryProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Tahap2DeliveryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected User $user;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;
    protected Customer $customerA;
    protected Customer $customerB;
    protected Product $product;
    protected UnitOfMeasure $uom;
    protected ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sales.stock.strict_dispatch' => true,
            'sales.stock.require_surat_jalan' => true,
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
            'name' => 'Gudang Transit',
            'kode' => 'WH-02',
            'location' => 'Jakarta Utara',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->customerA = $this->createCustomer([
            'name' => 'PT Maju Bersama',
            'perusahaan' => 'PT Maju Bersama',
            'tipe' => 'PKP',
        ]);

        $this->customerB = $this->createCustomer([
            'name' => 'CV Sumber Rejeki',
            'perusahaan' => 'CV Sumber Rejeki',
            'tipe' => 'PRI',
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
            'name' => 'Semen Gresik 50kg',
            'sku' => 'SMN-GRS-50',
            'code' => 'SMN-GRS-50',
            'uom_id' => $this->uom->id,
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $this->category->id,
            'kode_merk' => 'GRESIK',
            'sell_price' => 75000,
            'cost_price' => 60000,
            'harga_batas' => 5,
        ]);

        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse1->id],
            ['qty_available' => 100, 'stock' => 100, 'qty_reserved' => 0, 'qty_min' => 0]
        );

        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse2->id],
            ['qty_available' => 50, 'stock' => 50, 'qty_reserved' => 0, 'qty_min' => 0]
        );
    }

    protected function createCustomer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'cabang_id' => $this->cabang->id,
            'name' => 'Customer Test',
            'code' => 'CUST-' . uniqid(),
            'address' => 'Jl. Test',
            'telephone' => '021-123',
            'phone' => '08123456789',
            'email' => 'customer' . uniqid() . '@test.com',
            'perusahaan' => 'PT Test',
            'tipe' => 'PKP',
            'fax' => '021-1234',
            'nik_npwp' => '1234567890',
            'tipe_pembayaran' => 'Bebas',
        ], $attrs));
    }

    /**
     * Test 1: Delivery Order merupakan gateway tunggal pemilihan gudang fisik (warehouseSources)
     * dan item DO wajib memiliki minimal 1 alokasi sumber gudang yang jumlah kuantitasnya sama dengan item.
     */
    public function test_delivery_order_requires_warehouse_sources_gateway(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-T2-001',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Industri No. 5, Jakarta Barat',
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price_per_unit' => 75000,
            'subtotal' => 750000,
            'total' => 750000,
            'delivered_quantity' => 0,
        ]);

        // 1. Buat DO
        $do = DeliveryOrder::create([
            'do_number' => 'DO-T2-001',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'draft',
        ]);
        $do->salesOrders()->attach($so->id);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        // Memeriksa warehouseSources sebelum diisi: kosong
        $this->assertCount(0, $doItem->warehouseSources);

        // Alokasikan sumber gudang fisik melalui DO (Gudang 1 = 6, Gudang 2 = 4 -> total 10)
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 6,
        ]);
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem->id,
            'warehouse_id' => $this->warehouse2->id,
            'quantity' => 4,
        ]);

        $doItem->refresh();
        $this->assertCount(2, $doItem->warehouseSources);
        $this->assertEquals(10, (float) $doItem->warehouseSources->sum('quantity'));
    }

    /**
     * Test 2: Partial Delivery - Pengiriman sebagian dari Sales Order.
     * Mengirim sebagian (4 dari 10) menyisakan 6 kuantitas untuk DO berikutnya.
     */
    public function test_partial_delivery_from_sales_order(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-T2-002',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Pelabuhan No. 1, Jakarta Utara',
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price_per_unit' => 75000,
            'subtotal' => 750000,
            'total' => 750000,
            'delivered_quantity' => 0,
        ]);

        // Kuantitas awal yang tersedia untuk DO: 10
        $this->assertEquals(10, $soItem->availableQuantityForDelivery());

        // DO pertama: kirim parsial 4 pcs
        $do1 = DeliveryOrder::create([
            'do_number' => 'DO-T2-PARTIAL-1',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do1->salesOrders()->attach($so->id);

        $doItem1 = DeliveryOrderItem::create([
            'delivery_order_id' => $do1->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
        ]);
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem1->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 4,
        ]);

        // Cek sisa kuantitas yang masih tersedia di SO untuk DO berikutnya
        $soItem->refresh();
        $progress = app(SaleOrderDeliveryProgress::class)->forItems([$soItem->id]);
        $this->assertEquals(6, $progress[$soItem->id]['available']);
        $this->assertEquals(6, $soItem->availableQuantityForDelivery());

        // DO kedua: kirim sisa 6 pcs
        $do2 = DeliveryOrder::create([
            'do_number' => 'DO-T2-PARTIAL-2',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do2->salesOrders()->attach($so->id);

        $doItem2 = DeliveryOrderItem::create([
            'delivery_order_id' => $do2->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 6,
        ]);
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem2->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 6,
        ]);

        // Seluruh 10 pcs sudah teralokasi ke DO, sisa kuantitas sekarang adalah 0
        $this->assertEquals(0, $soItem->availableQuantityForDelivery());
    }

    /**
     * Test 3: Multi-SO Consolidation - 1 DO dapat menggabungkan beberapa SO jika customer dan alamat kirim sama.
     * Jika customer berbeda atau alamat kirim berbeda, validator menolak penggabungan.
     */
    public function test_multi_so_consolidation_into_single_delivery_order(): void
    {
        // SO 1 untuk Customer A
        $so1 = SaleOrder::create([
            'so_number' => 'SO-T2-CONS-1',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Daan Mogot KM 12, Jakarta',
        ]);
        SaleOrderItem::create([
            'sale_order_id' => $so1->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price_per_unit' => 75000,
            'subtotal' => 375000,
            'total' => 375000,
        ]);

        // SO 2 untuk Customer A dengan alamat yang sama
        $so2 = SaleOrder::create([
            'so_number' => 'SO-T2-CONS-2',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Daan Mogot KM 12, Jakarta',
        ]);
        SaleOrderItem::create([
            'sale_order_id' => $so2->id,
            'product_id' => $this->product->id,
            'quantity' => 8,
            'price_per_unit' => 75000,
            'subtotal' => 600000,
            'total' => 600000,
        ]);

        // SO 3 untuk Customer B (berbeda customer)
        $so3 = SaleOrder::create([
            'so_number' => 'SO-T2-CONS-3',
            'customer_id' => $this->customerB->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Daan Mogot KM 12, Jakarta',
        ]);
        SaleOrderItem::create([
            'sale_order_id' => $so3->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'price_per_unit' => 75000,
            'subtotal' => 225000,
            'total' => 225000,
        ]);

        // SO 4 untuk Customer A tapi alamat berbeda
        $so4 = SaleOrder::create([
            'so_number' => 'SO-T2-CONS-4',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Raya Bogor KM 25, Depok',
        ]);
        SaleOrderItem::create([
            'sale_order_id' => $so4->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'price_per_unit' => 75000,
            'subtotal' => 300000,
            'total' => 300000,
        ]);

        $validator = app(DeliveryOrderSourceValidator::class);

        // 1. Konsolidasi SO1 dan SO2 (Customer sama & alamat sama) -> VALID
        $errorsValid = $validator->errors([$so1->id, $so2->id]);
        $this->assertEmpty($errorsValid, 'SO1 dan SO2 dengan customer dan alamat sama harus valid digabung.');

        // 2. Gabung SO1 dan SO3 (Customer beda) -> DITOLAK
        $errorsDiffCustomer = $validator->errors([$so1->id, $so3->id]);
        $this->assertNotEmpty($errorsDiffCustomer);
        $this->assertStringContainsString('customer yang sama', implode(' ', $errorsDiffCustomer));

        // 3. Gabung SO1 dan SO4 (Customer sama tapi alamat beda) -> DITOLAK
        $errorsDiffAddress = $validator->errors([$so1->id, $so4->id]);
        $this->assertNotEmpty($errorsDiffAddress);
        $this->assertStringContainsString('alamat kirim yang sama', implode(' ', $errorsDiffAddress));
    }

    /**
     * Test 4: Enforce Surat Jalan - DO TIDAK DAPAT diubah ke status 'sent' (Kirim)
     * atau 'completed' (Selesai) jika belum memiliki Surat Jalan dengan status Terbit (STATUS_ISSUED).
     */
    public function test_delivery_order_transition_to_sent_blocked_without_issued_surat_jalan(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-T2-SJ-1',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Gatot Subroto No. 8, Jakarta',
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price_per_unit' => 75000,
            'subtotal' => 375000,
            'total' => 375000,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-T2-NOSJ-1',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do->salesOrders()->attach($so->id);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 5,
        ]);

        $transitions = app(DeliveryOrderTransitions::class);

        // Pastikan DO belum memiliki Surat Jalan terbit
        $this->assertFalse($do->hasIssuedSuratJalan());

        // Percobaan transisi ke 'sent' harus gagal dan melempar DeliveryOrderTransitionException
        $this->expectException(DeliveryOrderTransitionException::class);
        $this->expectExceptionMessage('belum memiliki Surat Jalan (SJ) yang diterbitkan');

        $transitions->to($do, 'sent');
    }

    /**
     * Test 5: Transisi DO ke 'sent' BERHASIL setelah Surat Jalan terbit dikaitkan ke DO.
     */
    public function test_delivery_order_transition_to_sent_succeeds_with_issued_surat_jalan(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-T2-SJ-2',
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Gatot Subroto No. 8, Jakarta',
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price_per_unit' => 75000,
            'subtotal' => 375000,
            'total' => 375000,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-T2-WITHSJ-1',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do->salesOrders()->attach($so->id);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 5,
        ]);

        // Buat Surat Jalan berstatus Terbit (STATUS_ISSUED = 1) dan tautkan ke DO
        $sj = SuratJalan::create([
            'sj_number' => 'SJ-T2-001',
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);
        $sj->deliveryOrder()->attach($do->id);

        $do->refresh();
        $this->assertTrue($do->hasIssuedSuratJalan());

        // Transisi ke 'sent' harus sukses
        $transitions = app(DeliveryOrderTransitions::class);
        $transitions->to($do, 'sent');

        $this->assertEquals('sent', $do->fresh()->status);
    }
}
