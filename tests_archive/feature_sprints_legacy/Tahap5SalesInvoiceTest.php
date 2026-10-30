<?php

namespace Tests\Feature\Sales;

use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentPrintBuilder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Tahap5SalesInvoiceTest extends TestCase
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

        // Seed basic COA accounts required for invoices & ledger
        foreach ([
            ['code' => '1120.01', 'name' => 'Piutang Dagang', 'type' => 'Asset'],
            ['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue'],
            ['code' => '4100.10', 'name' => 'Penjualan Produk', 'type' => 'Revenue'],
            ['code' => '1140.01', 'name' => 'Persediaan Barang', 'type' => 'Asset'],
            ['code' => '1140.10', 'name' => 'Persediaan Barang Dagangan', 'type' => 'Asset'],
            ['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset'],
            ['code' => '2110.01', 'name' => 'PPN Keluaran', 'type' => 'Liability'],
            ['code' => '4110.10', 'name' => 'Diskon Penjualan Produk', 'type' => 'Expense'],
            ['code' => '5100.10', 'name' => 'HPP Barang Dagangan', 'type' => 'Expense'],
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

        $salesCoa = ChartOfAccount::where('code', '4100.10')->first();

        $this->product1 = Product::create([
            'cabang_id' => $this->cabang1->id,
            'name' => 'Tensimeter Digital',
            'sku' => 'SKU-MED-01',
            'uom_id' => $this->uom->id,
            'product_category_id' => $category->id,
            'sell_price' => 250000,
            'cost_price' => 150000,
            'sales_coa_id' => $salesCoa?->id,
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
            'sales_coa_id' => $salesCoa?->id,
            'harga_batas' => 5,
            'kode_merk' => 'OMRON',
        ]);

        InventoryStock::updateOrCreate(
            ['product_id' => $this->product1->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );

        InventoryStock::updateOrCreate(
            ['product_id' => $this->product2->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );
    }

    /** SO milik Surat Jalan (langkah 2 form invoice, B24). */
    protected function soOf(SuratJalan $sj): ?int
    {
        return $sj->deliveryOrder()->first()?->salesOrders()->first()?->id;
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
            'tempo_pembayaran' => 30,
            'shipped_to' => $customer->address,
            'created_by' => $this->user->id,
        ]);

        foreach ($items as $item) {
            SaleOrderItem::create([
                'sale_order_id' => $so->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] ?? 250000,
                'discount' => $item['discount'] ?? 0,
                'subtotal' => ($item['quantity']) * ($item['unit_price'] ?? 250000) * (1 - ($item['discount'] ?? 0) / 100),
                'tipe_pajak' => $item['tipe_pajak'] ?? 'None',
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

    protected function createSuratJalan(array $deliveryOrders, array $attributes = []): SuratJalan
    {
        $firstDo = $deliveryOrders[0] ?? null;
        $cabangId = $attributes['cabang_id'] ?? ($firstDo ? $firstDo->cabang_id : $this->cabang1->id);

        $sj = SuratJalan::create(array_merge([
            'sj_number' => 'SJ-' . uniqid(),
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'cabang_id' => $cabangId,
            'created_by' => $this->user->id,
        ], $attributes));

        if (! empty($deliveryOrders)) {
            $doIds = array_map(fn ($do) => $do instanceof DeliveryOrder ? $do->id : $do, $deliveryOrders);
            $sj->deliveryOrder()->attach($doIds);
        }

        return $sj;
    }

    /**
     * Test 1: Pembuatan Sales Invoice mengacu pada Surat Jalan (status Terbit) berhasil.
     */
    public function test_sales_invoice_can_be_created_referring_to_issued_surat_jalan(): void
    {
        $so = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 10, 'unit_price' => 250000, 'discount' => 10],
        ]);
        $soItem = $so->saleOrderItem()->first();

        $do = $this->createApprovedDeliveryOrder($so, [
            ['product_id' => $this->product1->id, 'quantity' => 10, 'sale_order_item_id' => $soItem->id],
        ]);

        $sj = $this->createSuratJalan([$do], ['sj_number' => 'SJ-INV-001']);
        $invNumber = 'INV-2026-0001';

        $expectedPrice = 250000 * 0.9; // 225.000 net per item
        $expectedSubtotal = 10 * $expectedPrice; // 2.250.000

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'selected_customer' => $this->customerA->id,
                'filter_sale_order' => $this->soOf($sj),   // B24: urutan Customer → SO → SJ
                'selected_surat_jalans' => [$sj->id],
                'invoice_number' => $invNumber,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'cabang_id' => $this->cabang1->id,
                'delivery_orders' => [$do->id],
                'surat_jalan_ids' => [$sj->id],
                'subtotal' => $expectedSubtotal,
                'dpp' => $expectedSubtotal,
                'total' => $expectedSubtotal,
                'tipe_pajak' => 'None',
                'ppn_rate' => 0,
                'invoiceItem' => [
                    [
                        'product_id' => $this->product1->id,
                        'quantity' => 10,
                        'price' => $expectedPrice,
                        'total' => $expectedSubtotal,
                        'coa_id' => $this->product1->sales_coa_id,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => $invNumber,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj->id,
            'cabang_id' => $this->cabang1->id,
            'total' => $expectedSubtotal,
        ]);

        $invoice = Invoice::where('invoice_number', $invNumber)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals([$sj->id], $invoice->surat_jalan_ids);
        $this->assertEquals([$do->id], $invoice->delivery_orders);
        $this->assertTrue($sj->fresh()->isInvoiced());
        $this->assertCount(1, $invoice->invoiceItem);
        $this->assertEquals(10, $invoice->invoiceItem->first()->quantity);
    }

    /**
     * Test 2: Penolakan pembuatan Sales Invoice yang mengacu pada Surat Jalan Draft atau Dibatalkan.
     */
    public function test_cannot_create_sales_invoice_referring_to_draft_or_cancelled_surat_jalan(): void
    {
        $so = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $soItem = $so->saleOrderItem()->first();

        $do = $this->createApprovedDeliveryOrder($so, [
            ['product_id' => $this->product1->id, 'quantity' => 5, 'sale_order_item_id' => $soItem->id],
        ]);

        // 1. SJ Draft (STATUS_DRAFT = 0)
        $sjDraft = $this->createSuratJalan([$do], [
            'sj_number' => 'SJ-DRAFT-01',
            'status' => SuratJalan::STATUS_DRAFT,
        ]);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'selected_customer' => $this->customerA->id,
                'filter_sale_order' => $this->soOf($sjDraft),   // B24: urutan Customer → SO → SJ
                'selected_surat_jalans' => [$sjDraft->id],
                'invoice_number' => 'INV-DRAFT-FAIL',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'cabang_id' => $this->cabang1->id,
                'total' => 1000000,
                'subtotal' => 1000000,
                'invoiceItem' => [
                    ['product_id' => $this->product1->id, 'quantity' => 5, 'price' => 200000, 'total' => 1000000],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_surat_jalans']);

        // 2. SJ Dibatalkan (STATUS_CANCELLED = 2)
        $sjCancelled = $this->createSuratJalan([$do], [
            'sj_number' => 'SJ-CANCEL-01',
            'status' => SuratJalan::STATUS_CANCELLED,
        ]);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'selected_customer' => $this->customerA->id,
                'filter_sale_order' => $this->soOf($sjCancelled),   // B24: urutan Customer → SO → SJ
                'selected_surat_jalans' => [$sjCancelled->id],
                'invoice_number' => 'INV-CANCEL-FAIL',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'cabang_id' => $this->cabang1->id,
                'total' => 1000000,
                'subtotal' => 1000000,
                'invoiceItem' => [
                    ['product_id' => $this->product1->id, 'quantity' => 5, 'price' => 200000, 'total' => 1000000],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_surat_jalans']);
    }

    /**
     * Test 3: Konsolidasi Multi-SJ (>1 SJ dalam 1 Invoice) berhasil untuk customer sama & cabang sama.
     */
    public function test_multi_surat_jalan_consolidation_into_single_sales_invoice(): void
    {
        // B24: SJ yang digabung dalam satu invoice harus dari SO yang SAMA → satu SO dengan dua item, dua DO, dua SJ
        $so1 = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 10, 'unit_price' => 250000],
            ['product_id' => $this->product2->id, 'quantity' => 5, 'unit_price' => 150000],
        ]);
        $soItem1 = $so1->saleOrderItem()->first();
        $do1 = $this->createApprovedDeliveryOrder($so1, [
            ['product_id' => $this->product1->id, 'quantity' => 10, 'sale_order_item_id' => $soItem1->id],
        ]);
        $sj1 = $this->createSuratJalan([$do1], ['sj_number' => 'SJ-MULTI-001']);

        // DO 2 dari SO yang sama
        $so2 = $so1;
        $soItem2 = $so1->saleOrderItem()->where('product_id', $this->product2->id)->first();
        $do2 = $this->createApprovedDeliveryOrder($so2, [
            ['product_id' => $this->product2->id, 'quantity' => 5, 'sale_order_item_id' => $soItem2->id],
        ]);
        $sj2 = $this->createSuratJalan([$do2], ['sj_number' => 'SJ-MULTI-002']);

        $invNumber = 'INV-MULTI-001';
        $subtotal = (10 * 250000) + (5 * 150000); // 2.500.000 + 750.000 = 3.250.000

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'selected_customer' => $this->customerA->id,
                'filter_sale_order' => $this->soOf($sj1),   // B24: urutan Customer → SO → SJ
                'selected_surat_jalans' => [$sj1->id, $sj2->id],
                'invoice_number' => $invNumber,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'cabang_id' => $this->cabang1->id,
                'delivery_orders' => [$do1->id, $do2->id],
                'surat_jalan_ids' => [$sj1->id, $sj2->id],
                'subtotal' => $subtotal,
                'dpp' => $subtotal,
                'total' => $subtotal,
                'tipe_pajak' => 'None',
                'ppn_rate' => 0,
                'invoiceItem' => [
                    ['product_id' => $this->product1->id, 'quantity' => 10, 'price' => 250000, 'total' => 2500000],
                    ['product_id' => $this->product2->id, 'quantity' => 5, 'price' => 150000, 'total' => 750000],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::where('invoice_number', $invNumber)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals([$sj1->id, $sj2->id], $invoice->surat_jalan_ids);
        $this->assertEquals([$do1->id, $do2->id], $invoice->delivery_orders);
        $this->assertEquals($subtotal, (float) $invoice->total);

        // Verifikasi relasi & status kedua SJ
        $this->assertCount(2, $invoice->suratJalans());
        $this->assertTrue($sj1->fresh()->isInvoiced());
        $this->assertTrue($sj2->fresh()->isInvoiced());
        $this->assertStringContainsString($sj1->sj_number, $invoice->surat_jalans_display);
        $this->assertStringContainsString($sj2->sj_number, $invoice->surat_jalans_display);
    }

    /**
     * Test 4: Penolakan konsolidasi Surat Jalan dari Customer yang berbeda.
     */
    public function test_cannot_consolidate_surat_jalans_from_different_customers(): void
    {
        // SJ 1 untuk Customer A
        $soA = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $doA = $this->createApprovedDeliveryOrder($soA, [
            ['product_id' => $this->product1->id, 'quantity' => 5, 'sale_order_item_id' => $soA->saleOrderItem->first()->id],
        ]);
        $sjA = $this->createSuratJalan([$doA], ['sj_number' => 'SJ-CUST-A']);

        // SJ 2 untuk Customer B
        $soB = $this->createSaleOrder($this->customerB, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $doB = $this->createApprovedDeliveryOrder($soB, [
            ['product_id' => $this->product1->id, 'quantity' => 5, 'sale_order_item_id' => $soB->saleOrderItem->first()->id],
        ]);
        $sjB = $this->createSuratJalan([$doB], ['sj_number' => 'SJ-CUST-B']);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'selected_customer' => $this->customerA->id,
                'filter_sale_order' => $this->soOf($sjA),   // B24: urutan Customer → SO → SJ
                'selected_surat_jalans' => [$sjA->id, $sjB->id],
                'invoice_number' => 'INV-DIFF-CUST',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'cabang_id' => $this->cabang1->id,
                'total' => 2000000,
                'subtotal' => 2000000,
                'invoiceItem' => [
                    ['product_id' => $this->product1->id, 'quantity' => 10, 'price' => 200000, 'total' => 2000000],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_surat_jalans']);
    }

    /**
     * Test 5: Penolakan konsolidasi Surat Jalan dari Cabang yang berbeda.
     */
    public function test_cannot_consolidate_surat_jalans_from_different_branches(): void
    {
        // SJ 1 di Cabang Surabaya
        $soSby = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $doSby = $this->createApprovedDeliveryOrder($soSby, [
            ['product_id' => $this->product1->id, 'quantity' => 5, 'sale_order_item_id' => $soSby->saleOrderItem->first()->id],
        ]);
        $sjSby = $this->createSuratJalan([$doSby], ['sj_number' => 'SJ-SBY-01', 'cabang_id' => $this->cabang1->id]);

        // SJ 2 di Cabang Malang
        $soMlg = $this->createSaleOrder($this->customerA, $this->cabang2, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $doMlg = $this->createApprovedDeliveryOrder($soMlg, [
            ['product_id' => $this->product1->id, 'quantity' => 5, 'sale_order_item_id' => $soMlg->saleOrderItem->first()->id],
        ]);
        $sjMlg = $this->createSuratJalan([$doMlg], ['sj_number' => 'SJ-MLG-01', 'cabang_id' => $this->cabang2->id]);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'selected_customer' => $this->customerA->id,
                'filter_sale_order' => $this->soOf($sjSby),   // B24: urutan Customer → SO → SJ
                'selected_surat_jalans' => [$sjSby->id, $sjMlg->id],
                'invoice_number' => 'INV-DIFF-BRANCH',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'cabang_id' => $this->cabang1->id,
                'total' => 2000000,
                'subtotal' => 2000000,
                'invoiceItem' => [
                    ['product_id' => $this->product1->id, 'quantity' => 10, 'price' => 200000, 'total' => 2000000],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_surat_jalans']);
    }

    /**
     * Test 6: Penolakan penagihan ulang Surat Jalan yang sudah difakturkan (Anti-Double Invoicing).
     */
    public function test_cannot_create_invoice_for_already_invoiced_surat_jalan(): void
    {
        $so = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $do = $this->createApprovedDeliveryOrder($so, [
            ['product_id' => $this->product1->id, 'quantity' => 5, 'sale_order_item_id' => $so->saleOrderItem->first()->id],
        ]);
        $sj = $this->createSuratJalan([$do], ['sj_number' => 'SJ-DOUBLE-01']);

        // Buat invoice pertama untuk SJ ini
        Invoice::create([
            'invoice_number' => 'INV-FIRST-01',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj->id,
            'surat_jalan_ids' => [$sj->id],
            'delivery_orders' => [$do->id],
            'cabang_id' => $this->cabang1->id,
            'customer_name' => $this->customerA->name,
            'status' => 'draft',
            'subtotal' => 1250000,
            'dpp' => 1250000,
            'total' => 1250000,
        ]);

        $this->assertTrue($sj->fresh()->isInvoiced());

        // Coba buat invoice kedua yang mencakup SJ yang sama
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'selected_customer' => $this->customerA->id,
                'filter_sale_order' => $this->soOf($sj),   // B24: urutan Customer → SO → SJ
                'selected_surat_jalans' => [$sj->id],
                'invoice_number' => 'INV-SECOND-FAIL',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'cabang_id' => $this->cabang1->id,
                'total' => 1250000,
                'subtotal' => 1250000,
                'invoiceItem' => [
                    ['product_id' => $this->product1->id, 'quantity' => 5, 'price' => 250000, 'total' => 1250000],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_surat_jalans']);
    }

    /**
     * Test 7: Penolakan invoice dengan total bernilai Rp 0.
     */
    public function test_cannot_create_sales_invoice_with_zero_or_negative_total(): void
    {
        $so = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 5],
        ]);
        $do = $this->createApprovedDeliveryOrder($so, [
            ['product_id' => $this->product1->id, 'quantity' => 5, 'sale_order_item_id' => $so->saleOrderItem->first()->id],
        ]);
        $sj = $this->createSuratJalan([$do], ['sj_number' => 'SJ-ZERO-01']);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'selected_customer' => $this->customerA->id,
                'filter_sale_order' => $this->soOf($sj),   // B24: urutan Customer → SO → SJ
                'selected_surat_jalans' => [$sj->id],
                'invoice_number' => 'INV-ZERO-FAIL',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'cabang_id' => $this->cabang1->id,
                'total' => 0,
                'subtotal' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_surat_jalans']);
    }

    /**
     * Test 8: Verifikasi output cetak PDF menampilkan referensi nomor Surat Jalan.
     */
    public function test_pdf_stream_displays_surat_jalan_reference(): void
    {
        $so = $this->createSaleOrder($this->customerA, $this->cabang1, [
            ['product_id' => $this->product1->id, 'quantity' => 10, 'unit_price' => 250000],
        ]);
        $do = $this->createApprovedDeliveryOrder($so, [
            ['product_id' => $this->product1->id, 'quantity' => 10, 'sale_order_item_id' => $so->saleOrderItem->first()->id],
        ]);
        $sj = $this->createSuratJalan([$do], ['sj_number' => 'SJ-PDF-2026']);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-PDF-2026',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj->id,
            'surat_jalan_ids' => [$sj->id],
            'delivery_orders' => [$do->id],
            'cabang_id' => $this->cabang1->id,
            'customer_name' => $this->customerA->name,
            'status' => 'draft',
            'subtotal' => 2500000,
            'dpp' => 2500000,
            'total' => 2500000,
        ]);

        $invoice->invoiceItem()->create([
            'product_id' => $this->product1->id,
            'quantity' => 10,
            'price' => 250000,
            'total' => 2500000,
        ]);

        // Verifikasi data builder
        $builder = app(DocumentPrintBuilder::class);
        $docData = $builder->invoice($invoice);

        $this->assertEquals('SJ-PDF-2026', $docData['surat_jalan']);
        $this->assertNotEmpty(collect($docData['meta'])->firstWhere('label', 'No. Surat Jalan'));

        // Verifikasi endpoint PDF streaming
        $response = $this->get(route('pdf-stream', ['type' => 'sales-invoice', 'id' => $invoice->id]));
        $response->assertOk();
    }
}
