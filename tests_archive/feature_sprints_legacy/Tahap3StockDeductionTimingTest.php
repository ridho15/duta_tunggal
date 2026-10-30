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
use App\Models\Warehouse;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Tahap3StockDeductionTimingTest extends TestCase
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

    /**
     * Test 1: Stok TIDAK terpotong saat SO dibuat atau disetujui (Bukan saat SO).
     */
    public function test_stock_is_not_deducted_during_sales_order_creation_and_approval(): void
    {
        // Setup initial stock: 100 pcs
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 100, 'qty_reserved' => 0]
        );

        // 1. Buat Sales Order 25 pcs
        $so = SaleOrder::create([
            'so_number' => 'SO-T3-STOCK-1',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'draft',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 25,
            'price_per_unit' => 75000,
            'subtotal' => 1875000,
            'total' => 1875000,
        ]);

        // Verifikasi: stok fisik di inventory_stocks TETAP 100
        $stock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->first();
        $this->assertEquals(100, $stock->qty_available);

        // 2. Setujui SO
        $so->update(['status' => 'approved']);

        // Verifikasi: setelah SO approved, stok fisik TETAP 100 (tidak terpotong saat SO)
        $stock->refresh();
        $this->assertEquals(100, $stock->qty_available);

        // Tidak ada pergerakan stok penjualan yang terbentuk
        $movementsCount = StockMovement::where('product_id', $this->product->id)
            ->where('type', 'sales')
            ->count();
        $this->assertEquals(0, $movementsCount);
    }

    /**
     * Test 2: Stok TERPOTONG saat DO dibuat/disetujui, sebelum DO berstatus 'sent'.
     */
    public function test_stock_is_deducted_immediately_when_delivery_order_is_created(): void
    {
        // Setup initial stock: 100 pcs
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 100, 'qty_reserved' => 0]
        );

        $so = SaleOrder::create([
            'so_number' => 'SO-T3-STOCK-2',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
        ]);
        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 25,
            'price_per_unit' => 75000,
            'subtotal' => 1875000,
            'total' => 1875000,
        ]);

        // Buat DO untuk 25 pcs
        $do = DeliveryOrder::create([
            'do_number' => 'DO-T3-STOCK-2',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do->salesOrders()->attach($so->id);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 25,
        ]);

        // Alokasikan sumber gudang pengiriman
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 25,
        ]);

        // Status DO masih 'approved' (belum dikirim / status != 'sent')
        $this->assertEquals('approved', $do->status);

        // Verifikasi: Stok fisik SEKETIKA TERPOTONG dari 100 menjadi 75 pcs!
        $stock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->first();
        $this->assertEquals(75, $stock->qty_available);

        // Verifikasi: Stock movement 'sales' sudah tercatat dengan kuantitas 25
        $movement = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->where('type', 'sales')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(25, $movement->quantity);
    }

    /**
     * Test 3: Idempoten — Saat status DO berubah menjadi 'sent', stok TIDAK dipotong dua kali.
     */
    public function test_stock_is_not_deducted_again_when_delivery_order_transitions_to_sent(): void
    {
        // Setup initial stock: 100 pcs
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 100, 'qty_reserved' => 0]
        );

        $so = SaleOrder::create([
            'so_number' => 'SO-T3-STOCK-3',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
        ]);
        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'price_per_unit' => 75000,
            'subtotal' => 1500000,
            'total' => 1500000,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-T3-STOCK-3',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do->salesOrders()->attach($so->id);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 20,
        ]);

        // Cek stok setelah DO dibuat: 100 - 20 = 80
        $stock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->first();
        $this->assertEquals(80, $stock->qty_available);

        // Buat Surat Jalan yang diterbitkan (prasyarat alur Tahap 2)
        $sj = SuratJalan::create([
            'sj_number' => 'SJ-T3-TEST-001',
            'tanggal_surat_jalan' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'issued_at' => now(),
            'cabang_id' => $this->cabang->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'alamat_pengiriman' => 'Jl. Merdeka No. 10',
        ]);
        $sj->deliveryOrder()->attach($do->id);

        // Ubah status DO menjadi 'sent'
        app(DeliveryOrderTransitions::class)->to($do, 'sent', [
            'comments' => 'Driver berangkat membawa barang',
        ]);

        $this->assertEquals('sent', $do->refresh()->status);

        // Verifikasi: stok TETAP 80 (TIDAK terpotong dua kali menjadi 60!)
        $stock->refresh();
        $this->assertEquals(80, $stock->qty_available);

        // Total pergerakan stok 'sales' tetap hanya 20 pcs
        $totalShipped = StockMovement::where('product_id', $this->product->id)
            ->where('type', 'sales')
            ->sum('quantity');
        $this->assertEquals(20, $totalShipped);
    }

    /**
     * Test 4: Multi-gudang — Stok terpotong dari masing-masing gudang sumber saat DO dibuat.
     */
    public function test_multi_warehouse_sources_stock_deduction_on_delivery_order_creation(): void
    {
        // Gudang 1: 50 pcs, Gudang 2: 50 pcs
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 50, 'qty_reserved' => 0]
        );
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse2->id, 'rak_id' => null],
            ['qty_available' => 50, 'qty_reserved' => 0]
        );

        $so = SaleOrder::create([
            'so_number' => 'SO-T3-MULTI-1',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
        ]);
        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 35,
            'price_per_unit' => 75000,
            'subtotal' => 2625000,
            'total' => 2625000,
        ]);

        // Buat DO untuk 35 pcs (20 dari Gudang 1, 15 dari Gudang 2)
        $do = DeliveryOrder::create([
            'do_number' => 'DO-T3-MULTI-1',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do->salesOrders()->attach($so->id);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 35,
        ]);

        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 20,
        ]);
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem->id,
            'warehouse_id' => $this->warehouse2->id,
            'quantity' => 15,
        ]);

        // Verifikasi: Gudang 1 berkurang dari 50 -> 30 pcs
        $stock1 = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse1->id)->first();
        $this->assertEquals(30, $stock1->qty_available);

        // Verifikasi: Gudang 2 berkurang dari 50 -> 35 pcs
        $stock2 = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse2->id)->first();
        $this->assertEquals(35, $stock2->qty_available);
    }

    /**
     * Test 5: Reversal — Saat DO dibatalkan (closed / reject) atau dihapus, stok yang sudah terpotong dikembalikan.
     */
    public function test_stock_is_reversed_when_delivery_order_is_closed_or_deleted(): void
    {
        // Setup initial stock: 100 pcs
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 100, 'qty_reserved' => 0]
        );

        $so = SaleOrder::create([
            'so_number' => 'SO-T3-REV-1',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
        ]);
        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'price_per_unit' => 75000,
            'subtotal' => 2250000,
            'total' => 2250000,
        ]);

        // Buat DO untuk 30 pcs
        $do = DeliveryOrder::create([
            'do_number' => 'DO-T3-REV-1',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do->salesOrders()->attach($so->id);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
        ]);
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 30,
        ]);

        // Stok fisik berkurang dari 100 -> 70
        $stock = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse1->id)->first();
        $this->assertEquals(70, $stock->qty_available);

        // 1. Batalkan / tutup DO
        app(DeliveryOrderTransitions::class)->to($do, 'closed', [
            'reason' => 'Customer membatalkan pengiriman',
        ]);

        // Verifikasi: stok kembali utuh ke 100 pcs lewat adjustment_in!
        $stock->refresh();
        $this->assertEquals(100, $stock->qty_available);

        $reversalMovement = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->where('type', 'adjustment_in')
            ->first();
        $this->assertNotNull($reversalMovement);
        $this->assertEquals(30, $reversalMovement->quantity);

        // 2. Buat DO kedua untuk menguji skenario hapus (delete)
        $do2 = DeliveryOrder::create([
            'do_number' => 'DO-T3-REV-2',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do2->salesOrders()->attach($so->id);

        $doItem2 = DeliveryOrderItem::create([
            'delivery_order_id' => $do2->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 15,
        ]);
        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $doItem2->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 15,
        ]);

        // Stok berkurang dari 100 -> 85
        $stock->refresh();
        $this->assertEquals(85, $stock->qty_available);

        // Hapus DO2
        $do2->delete();

        // Verifikasi: stok kembali ke 100 pcs saat DO dihapus!
        $stock->refresh();
        $this->assertEquals(100, $stock->qty_available);
    }
}
