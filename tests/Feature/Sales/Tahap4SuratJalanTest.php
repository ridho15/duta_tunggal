<?php

namespace Tests\Feature\Sales;

use App\Filament\Resources\SuratJalanResource\Pages\CreateSuratJalan;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SuratJalanDocumentBuilder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Tahap4SuratJalanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang1;
    protected Cabang $cabang2;
    protected Customer $customerA;
    protected Customer $customerB;
    protected Warehouse $warehouse1;
    protected Product $product1;
    protected Product $product2;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Seed basic COA accounts required for inventory & ledger
        foreach ([
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

        $this->cabang1 = Cabang::create([
            'nama' => 'Cabang Surabaya',
            'kode' => 'SBY',
            'alamat' => 'Jl. Pemuda No. 1, Surabaya',
            'telepon' => '031-5551234',
        ]);

        $this->cabang2 = Cabang::create([
            'nama' => 'Cabang Malang',
            'kode' => 'MLG',
            'alamat' => 'Jl. Ijen No. 10, Malang',
            'telepon' => '0341-5551234',
        ]);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang1->id,
            'manage_type' => 'all',
        ]);

        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        $this->actingAs($this->user);

        $this->customerA = $this->createCustomer([
            'name' => 'PT Pelanggan Sejahtera',
            'perusahaan' => 'PT Pelanggan Sejahtera',
            'code' => 'CUST-001',
            'address' => 'Jl. Industri No. 10, Surabaya',
        ]);

        $this->customerB = $this->createCustomer([
            'name' => 'CV Maju Lancar',
            'perusahaan' => 'CV Maju Lancar',
            'code' => 'CUST-002',
            'address' => 'Jl. Raya Barat No. 50, Surabaya',
        ]);

        $this->warehouse1 = Warehouse::create([
            'cabang_id' => $this->cabang1->id,
            'name' => 'Gudang Pusat',
            'kode' => 'G-PST',
            'location' => 'Surabaya Barat',
            'status' => 1,
        ]);

        $this->uom = UnitOfMeasure::firstOrCreate(['name' => 'PCS', 'abbreviation' => 'PCS']);

        $category = ProductCategory::firstOrCreate([
            'name' => 'Alat Kesehatan',
            'kode' => 'ALKES',
        ]);

        $this->product1 = Product::create([
            'cabang_id' => $this->cabang1->id,
            'name' => 'Tensimeter Digital',
            'sku' => 'SKU-MED-01',
            'uom_id' => $this->uom->id,
            'product_category_id' => $category->id,
            'sell_price' => 250000,
            'cost_price' => 150000,
            'harga_batas' => 5,
            'kode_merk' => 'OMRON',
        ]);

        $this->product2 = Product::create([
            'cabang_id' => $this->cabang1->id,
            'name' => 'Termometer Infrared',
            'sku' => 'SKU-MED-02',
            'uom_id' => $this->uom->id,
            'product_category_id' => $category->id,
            'sell_price' => 150000,
            'cost_price' => 100000,
            'harga_batas' => 5,
            'kode_merk' => 'OMRON',
        ]);

        // Beri stok fisik awal melimpah agar lolos validasi stok DO
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product1->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );

        InventoryStock::updateOrCreate(
            ['product_id' => $this->product2->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );
    }

    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'cabang_id' => $this->cabang1->id,
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

    protected function createSaleOrder(Customer $customer, Cabang $cabang, array $items = []): SaleOrder
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-' . uniqid(),
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'tipe_pembayaran' => 'kredit',
            'shipped_to' => $customer->address,
            'created_by' => $this->user->id,
        ]);

        foreach ($items as $item) {
            SaleOrderItem::create([
                'sale_order_id' => $so->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? 100000,
                'subtotal' => ($item['quantity']) * ($item['unit_price'] ?? 100000),
            ]);
        }

        return $so;
    }

    protected function createApprovedDeliveryOrder(SaleOrder $so, array $items = []): DeliveryOrder
    {
        $do = DeliveryOrder::create([
            'do_number' => 'DO-' . uniqid(),
            'cabang_id' => $so->cabang_id,
            'status' => 'approved',
            'delivery_date' => now(),
            'warehouse_id' => $this->warehouse1->id,
            'created_by' => $this->user->id,
        ]);

        $do->salesOrders()->attach($so->id);

        foreach ($items as $item) {
            DeliveryOrderItem::create([
                'delivery_order_id' => $do->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'sale_order_item_id' => $item['sale_order_item_id'] ?? null,
            ]);
        }

        return $do;
    }

    /**
     * Test 1: Pembuatan Surat Jalan mengacu pada DO (approved) berhasil dan otomatis berstatus Terbit (STATUS_ISSUED).
     */
    public function test_surat_jalan_created_referring_to_approved_delivery_order_successfully(): void
    {
        $so = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 10],
        ]);
        $soItem = $so->saleOrderItem()->first();

        $do = $this->createApprovedDeliveryOrder($so, [
            ['product_id' => $this->product1->id, 'quantity' => 10, 'sale_order_item_id' => $soItem->id],
        ]);

        $sjNumber = 'SJ-TEST-001';

        Livewire::test(CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => $sjNumber,
                'issued_at' => now()->toDateTimeString(),
                'deliveryOrder' => [$do->id],
                'cabang_id' => $this->cabang1->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('surat_jalans', [
            'sj_number' => $sjNumber,
            'status' => SuratJalan::STATUS_ISSUED,
            'cabang_id' => $this->cabang1->id,
        ]);

        $sj = SuratJalan::where('sj_number', $sjNumber)->first();
        $this->assertNotNull($sj);
        $this->assertTrue($sj->isIssued());
        $this->assertCount(1, $sj->deliveryOrder);
        $this->assertEquals($do->id, $sj->deliveryOrder->first()->id);
    }

    /**
     * Test 2: Pembuatan Surat Jalan ditolak bila memilih DO yang belum approved (misal: draft / request_stock).
     */
    public function test_surat_jalan_cannot_be_created_from_unapproved_delivery_order(): void
    {
        $so = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);

        $unapprovedDo = DeliveryOrder::create([
            'do_number' => 'DO-DRAFT-' . uniqid(),
            'cabang_id' => $this->cabang1->id,
            'status' => 'draft',
            'delivery_date' => now(),
            'created_by' => $this->user->id,
        ]);
        $unapprovedDo->salesOrders()->attach($so->id);

        $sjNumber = 'SJ-UNAPPROVED-' . uniqid();

        $component = Livewire::test(CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => $sjNumber,
                'issued_at' => now()->toDateTimeString(),
                'deliveryOrder' => [$unapprovedDo->id],
                'cabang_id' => $this->cabang1->id,
            ])
            ->call('create');

        $component->assertHasFormErrors(['deliveryOrder'])
            ->assertNotified('Gagal Membuat Surat Jalan');

        $this->assertDatabaseMissing('surat_jalans', [
            'sj_number' => $sjNumber,
        ]);
    }

    /**
     * Test 3: 1 Surat Jalan boleh menggabungkan >1 DO dengan syarat Customer sama dan Cabang sama.
     */
    public function test_surat_jalan_can_consolidate_multiple_delivery_orders_for_same_customer_and_branch(): void
    {
        $so1 = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $so2 = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product2->id, 'quantity' => 8],
        ]);

        $do1 = $this->createApprovedDeliveryOrder($so1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $do2 = $this->createApprovedDeliveryOrder($so2, [
            ['product_id' => $this->product2->id, 'quantity' => 8],
        ]);

        $sjNumber = 'SJ-MULTI-DO-' . uniqid();

        Livewire::test(CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => $sjNumber,
                'issued_at' => now()->toDateTimeString(),
                'deliveryOrder' => [$do1->id, $do2->id],
                'cabang_id' => $this->cabang1->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sj = SuratJalan::where('sj_number', $sjNumber)->first();
        $this->assertNotNull($sj);
        $this->assertCount(2, $sj->deliveryOrder);
        $this->assertEqualsCanonicalizing([$do1->id, $do2->id], $sj->deliveryOrder->pluck('id')->all());
    }

    /**
     * Test 4: Penolakan konsolidasi DO bila customer berbeda dalam 1 Surat Jalan.
     */
    public function test_surat_jalan_rejects_consolidation_of_delivery_orders_with_different_customers(): void
    {
        // DO 1 untuk Customer A
        $soA = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $doA = $this->createApprovedDeliveryOrder($soA, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);

        // DO B untuk Customer B
        $soB = $this->createSaleOrder($this->customerB, $this->cabang1, [
            ['product_id' => $this->product2->id, 'quantity' => 5],
        ]);
        $doB = $this->createApprovedDeliveryOrder($soB, [
            ['product_id' => $this->product2->id, 'quantity' => 5],
        ]);

        $sjNumber = 'SJ-DIFF-CUST-' . uniqid();

        $component = Livewire::test(CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => $sjNumber,
                'issued_at' => now()->toDateTimeString(),
                'deliveryOrder' => [$doA->id, $doB->id],
                'cabang_id' => $this->cabang1->id,
            ])
            ->call('create');

        $component->assertHasFormErrors(['deliveryOrder'])
            ->assertNotified('Gagal Membuat Surat Jalan');

        $this->assertDatabaseMissing('surat_jalans', [
            'sj_number' => $sjNumber,
        ]);
    }

    /**
     * Test 5: Penolakan konsolidasi DO bila cabang berbeda dalam 1 Surat Jalan.
     */
    public function test_surat_jalan_rejects_consolidation_of_delivery_orders_with_different_branches(): void
    {
        $so1 = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $do1 = $this->createApprovedDeliveryOrder($so1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);

        $so2 = $this->createSaleOrder($this->customerA, $this->cabang2, [
            ['product_id' => $this->product2->id, 'quantity' => 5],
        ]);
        $do2 = DeliveryOrder::create([
            'do_number' => 'DO-CAB2-' . uniqid(),
            'cabang_id' => $this->cabang2->id,
            'status' => 'approved',
            'delivery_date' => now(),
            'created_by' => $this->user->id,
        ]);
        $do2->salesOrders()->attach($so2->id);

        $sjNumber = 'SJ-DIFF-BRANCH-' . uniqid();

        $component = Livewire::test(CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => $sjNumber,
                'issued_at' => now()->toDateTimeString(),
                'deliveryOrder' => [$do1->id, $do2->id],
                'cabang_id' => $this->cabang1->id,
            ])
            ->call('create');

        $component->assertHasFormErrors(['deliveryOrder'])
            ->assertNotified('Gagal Membuat Surat Jalan');

        $this->assertDatabaseMissing('surat_jalans', [
            'sj_number' => $sjNumber,
        ]);
    }

    /**
     * Test 6: DO yang sudah tercantum pada Surat Jalan aktif lain ditolak saat dipakai kembali.
     */
    public function test_surat_jalan_cannot_use_delivery_order_already_attached_to_active_surat_jalan(): void
    {
        $so = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $do = $this->createApprovedDeliveryOrder($so, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);

        // Buat SJ pertama
        $sj1 = SuratJalan::create([
            'sj_number' => 'SJ-ACTIVE-1-' . uniqid(),
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'cabang_id' => $this->cabang1->id,
            'created_by' => $this->user->id,
        ]);
        $sj1->deliveryOrder()->attach($do->id);

        // Coba buat SJ kedua dengan DO yang sama
        $sjNumber2 = 'SJ-DUPLICATE-DO-' . uniqid();

        $component = Livewire::test(CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => $sjNumber2,
                'issued_at' => now()->toDateTimeString(),
                'deliveryOrder' => [$do->id],
                'cabang_id' => $this->cabang1->id,
            ])
            ->call('create');

        $component->assertHasFormErrors(['deliveryOrder'])
            ->assertNotified('Gagal Membuat Surat Jalan');

        $this->assertDatabaseMissing('surat_jalans', [
            'sj_number' => $sjNumber2,
        ]);
    }

    /**
     * Test 7: Cetak PDF Surat Jalan mengonsolidasi seluruh nomor DO, SO, dan rincian item barang.
     */
    public function test_surat_jalan_pdf_document_builder_and_stream_consolidates_all_delivery_orders_and_items(): void
    {
        $so1 = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 4],
        ]);
        $so2 = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product2->id, 'quantity' => 7],
        ]);

        $do1 = $this->createApprovedDeliveryOrder($so1, [
            ['product_id' => $this->product1->id, 'quantity' => 4],
        ]);
        $do2 = $this->createApprovedDeliveryOrder($so2, [
            ['product_id' => $this->product2->id, 'quantity' => 7],
        ]);

        $sj = SuratJalan::create([
            'sj_number' => 'SJ-DOC-TEST-' . uniqid(),
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'cabang_id' => $this->cabang1->id,
            'created_by' => $this->user->id,
        ]);
        $sj->deliveryOrder()->sync([$do1->id, $do2->id]);

        // Verifikasi SuratJalanDocumentBuilder
        $doc = app(SuratJalanDocumentBuilder::class)->build($sj);

        $this->assertEquals($sj->sj_number, $doc['number']);
        $this->assertEqualsCanonicalizing([$do1->do_number, $do2->do_number], $doc['delivery_order_numbers']);
        $this->assertEqualsCanonicalizing([$so1->so_number, $so2->so_number], $doc['sales_order_numbers']);
        $this->assertContains($this->customerA->name, $doc['customers']);
        $this->assertEquals(2, count($doc['groups']));
        $this->assertEquals(2, $doc['total_lines']);

        // Verifikasi HTTP Stream PDF preview
        $response = $this->get(route('pdf-stream', [
            'type' => 'surat-jalan',
            'id' => $sj->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }
}
