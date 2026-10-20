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
use App\Models\Invoice;
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
use App\Services\SuratJalanService;
use App\Services\SuratJalanDocumentBuilder;
use App\Services\DeliveryOrderReturnProcessor;
use App\Services\ReturnProductService;
use App\Models\StockAdjustment;
use App\Filament\Resources\SalesInvoiceResource;
use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave4SuratJalanInvoiceTest extends TestCase
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

    private function returnFor(DeliveryOrder $do, DeliveryOrderItem $doItem, string $number, float $qty, string $condition = 'good', string $action = 'reduce_quantity_only'): ReturnProduct
    {
        $return = ReturnProduct::create([
            'return_number' => $number,
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'draft',
            'reason' => 'Reject saat diterima',
            'return_action' => $action,
        ]);
        ReturnProductItem::create([
            'return_product_id' => $return->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $doItem->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'condition' => $condition,
        ]);

        return $return;
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

    private function sj(array $deliveryOrders, ?string $number = null): SuratJalan
    {
        $sj = SuratJalan::create([
            'sj_number' => $number ?? 'SJ-W4-' . uniqid(), 'issued_at' => now(), 'status' => SuratJalan::STATUS_ISSUED,
            'created_by' => $this->user->id, 'cabang_id' => $this->cabang->id,
        ]);
        $sj->deliveryOrder()->sync(array_map(fn ($do) => $do->id, $deliveryOrders));

        return $sj;
    }

    private function approvedDo(string $suffix, float $qty, ?SaleOrder $so = null, ?SaleOrderItem $item = null): array
    {
        $this->stock($this->warehouse1->id, 100);
        [$do, $doItem] = $this->deliveredDo($suffix, [[$this->warehouse1->id, $qty]]);

        return [$do, $doItem, $do->salesOrders()->first()];
    }

    // ---------------------------------------------------------------- B19 SO sama

    public function test_surat_jalan_rejects_delivery_orders_from_different_sale_orders(): void
    {
        [$doA] = $this->approvedDo('A', 5);
        [$doB] = $this->approvedDo('B', 5);

        try {
            app(SuratJalanService::class)->assertDeliveryOrdersUsable(collect([$doA->fresh(), $doB->fresh()]));
            $this->fail('seharusnya ditolak: SO berbeda');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Sales Order yang sama', collect($e->errors())->flatten()->implode(' '));
        }
    }

    public function test_surat_jalan_accepts_several_delivery_orders_of_the_same_sale_order(): void
    {
        [$do1, , $so] = $this->approvedDo('SAME1', 5);
        $do2 = DeliveryOrder::create(['do_number' => 'DO-SAME2', 'cabang_id' => $this->cabang->id, 'delivery_date' => now(), 'status' => 'approved']);
        $do2->salesOrders()->attach($so->id);

        app(SuratJalanService::class)->assertDeliveryOrdersUsable(collect([$do1->fresh(), $do2->fresh()]));

        $this->assertTrue(true);   // tidak melempar
    }

    // ---------------------------------------------------------------- B20 DO diretur

    public function test_returned_delivery_order_cannot_get_a_surat_jalan_but_its_replacement_can(): void
    {
        [$do, $doItem] = $this->approvedDo('RET', 10);
        $return = $this->returnFor($do, $doItem, 'DO-RETR', 4, 'good');
        app(ReturnProductService::class)->updateQuantityFromModel($return->fresh());
        $replacement = DeliveryOrder::where('replacement_for_delivery_order_id', $do->id)->first();
        $replacement->update(['status' => 'approved']);   // setelah konfirmasi gudang
        $service = app(SuratJalanService::class);

        try {
            $service->assertDeliveryOrdersUsable(collect([$do->fresh()]));
            $this->fail('DO yang diretur harus ditolak');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = collect($e->errors())->flatten()->implode(' ');
            $this->assertStringContainsString('DO pengganti', $message);
            $this->assertStringContainsString('DO-RETC', $message);
        }

        $service->assertDeliveryOrdersUsable(collect([$replacement->fresh()]));   // pengganti lolos
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------- B21 SJ terbit → DO Diterima

    public function test_issuing_a_surat_jalan_marks_its_delivery_orders_sent_then_received_in_one_step(): void
    {
        [$do] = $this->approvedDo('RCVD', 5);
        $sj = $this->sj([$do]);

        app(SuratJalanService::class)->markDeliveryOrdersReceived($sj);

        $do->refresh();
        $this->assertSame('received', $do->status);
        $this->assertNotNull($do->received_at);
        $this->assertSame("Surat Jalan {$sj->sj_number}", $do->received_by_name);
        $this->assertSame(['sent', 'received'], \App\Models\DeliveryOrderLog::where('delivery_order_id', $do->id)->orderBy('id')->pluck('status')->all());
    }

    public function test_marking_received_is_idempotent_and_leaves_later_statuses_alone(): void
    {
        [$do] = $this->approvedDo('IDEMSJ', 5);
        $sj = $this->sj([$do]);
        $service = app(SuratJalanService::class);

        $service->markDeliveryOrdersReceived($sj);
        $service->markDeliveryOrdersReceived($sj);
        $this->assertSame('received', $do->fresh()->status);
        $this->assertSame(2, \App\Models\DeliveryOrderLog::where('delivery_order_id', $do->id)->count());
    }

    public function test_draft_surat_jalan_issue_marks_delivery_orders_received(): void
    {
        [$do] = $this->approvedDo('DRAFTISS', 5);
        $draft = SuratJalan::create(['sj_number' => 'SJ-W4-DRAFT', 'issued_at' => now(), 'status' => SuratJalan::STATUS_DRAFT, 'created_by' => $this->user->id, 'cabang_id' => $this->cabang->id]);
        $draft->deliveryOrder()->sync([$do->id]);
        $this->assertSame('approved', $do->fresh()->status);

        app(SuratJalanService::class)->issue($draft);

        $this->assertSame('received', $do->fresh()->status);
    }

    public function test_cancelling_a_surat_jalan_is_blocked_once_its_delivery_orders_are_received(): void
    {
        [$do] = $this->approvedDo('NOCANCEL', 5);
        $sj = $this->sj([$do]);
        app(SuratJalanService::class)->markDeliveryOrdersReceived($sj);

        try {
            app(SuratJalanService::class)->cancel($sj->fresh(), 'salah input nomor');
            $this->fail('pembatalan harus ditolak');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('sudah ditandai Diterima', collect($e->errors())->flatten()->implode(' '));
        }

        $this->assertTrue($sj->fresh()->isIssued());
    }

    public function test_create_page_issues_the_surat_jalan_and_marks_the_do_received(): void
    {
        $this->newUser();
        [$do, , $so] = $this->approvedDo('PAGE', 5);

        Livewire::test(\App\Filament\Resources\SuratJalanResource\Pages\CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => 'SJ-W4-PAGE',
                'issued_at' => now()->toDateTimeString(),
                'filter_customer_id' => $so->customer_id,
                'filter_sale_order_id' => $so->id,
                'deliveryOrder' => [$do->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(SuratJalan::where('sj_number', 'SJ-W4-PAGE')->where('status', SuratJalan::STATUS_ISSUED)->exists());
        $this->assertSame('received', $do->fresh()->status);
    }

    public function test_create_page_requires_a_sale_order_selection(): void
    {
        $this->newUser();
        [$do, , $so] = $this->approvedDo('NOSO', 5);

        Livewire::test(\App\Filament\Resources\SuratJalanResource\Pages\CreateSuratJalan::class)
            ->fillForm([
                'sj_number' => 'SJ-W4-NOSO',
                'issued_at' => now()->toDateTimeString(),
                'filter_customer_id' => $so->customer_id,
                'deliveryOrder' => [$do->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['filter_sale_order_id']);

        $this->assertFalse(SuratJalan::where('sj_number', 'SJ-W4-NOSO')->exists());
        $this->assertSame('approved', $do->fresh()->status);
    }

    // ---------------------------------------------------------------- B22 SJ wajib sebelum DO dikirim/selesai (sudah ada)

    public function test_delivery_order_cannot_be_sent_without_an_issued_surat_jalan(): void
    {
        [$do] = $this->approvedDo('NOSJ', 5);

        $this->expectException(\App\Exceptions\DeliveryOrderTransitionException::class);
        app(\App\Services\DeliveryOrderTransitions::class)->to($do->fresh(), 'sent');
    }

    // ---------------------------------------------------------------- B23 cetak SJ: gudang asal per barang

    public function test_surat_jalan_print_data_shows_the_source_warehouse_per_item(): void
    {
        $this->stock($this->warehouse1->id, 50);
        $this->stock($this->warehouse2->id, 50);
        [$doOne] = $this->deliveredDo('PRN1', [[$this->warehouse1->id, 5]]);
        [$doMulti] = $this->deliveredDo('PRN2', [[$this->warehouse1->id, 6], [$this->warehouse2->id, 4]]);
        $sj = $this->sj([$doOne, $doMulti]);

        $doc = app(SuratJalanDocumentBuilder::class)->build($sj->fresh());
        $items = collect($doc['groups'])->keyBy('do_number');

        $this->assertSame($this->warehouse1->name, $items['DO-PRN1']['items'][0]['warehouse']);
        $this->assertSame($this->warehouse1->name.' (6), '.$this->warehouse2->name.' (4)', $items['DO-PRN2']['items'][0]['warehouse']);
    }

    public function test_surat_jalan_pdf_template_has_the_source_warehouse_column(): void
    {
        $this->stock($this->warehouse1->id, 50);
        [$do] = $this->deliveredDo('PRNV', [[$this->warehouse1->id, 5]]);
        $sj = $this->sj([$do]);
        $doc = app(SuratJalanDocumentBuilder::class)->build($sj->fresh());

        $html = view('pdf.surat-jalan', ['doc' => $doc])->render();

        $this->assertStringContainsString('Gudang Asal', $html);
        $this->assertStringContainsString($this->warehouse1->name, $html);
    }

    // ---------------------------------------------------------------- B25 invoice tidak otomatis saat DO selesai

    public function test_completing_a_delivery_order_no_longer_creates_an_invoice(): void
    {
        [$do] = $this->approvedDo('NOINV', 5);
        $sj = $this->sj([$do]);
        app(SuratJalanService::class)->markDeliveryOrdersReceived($sj);

        app(\App\Services\DeliveryOrderTransitions::class)->complete($do->fresh());

        $this->assertSame('completed', $do->fresh()->status);
        $this->assertSame(0, \App\Models\Invoice::count());
    }

    public function test_legacy_auto_invoice_still_works_when_the_flag_is_enabled(): void
    {
        config(['sales.invoice.auto_on_do_complete' => true]);
        [$do, $doItem] = $this->approvedDo('AUTOINV', 5);
        $doItem->saleOrderItem->update(['unit_price' => 75000, 'tax' => 0, 'discount' => 0]);   // nilai DO > 0 agar invoice terbentuk
        $sj = $this->sj([$do]);
        app(SuratJalanService::class)->markDeliveryOrdersReceived($sj);

        app(\App\Services\DeliveryOrderTransitions::class)->complete($do->fresh());

        $this->assertSame(1, \App\Models\Invoice::count());
    }

    // ---------------------------------------------------------------- B24 form invoice: Customer → SO → centang SJ

    public function test_invoice_form_has_the_sale_order_step_before_surat_jalan(): void
    {
        $this->newUser();

        Livewire::test(\App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice::class)
            ->assertFormFieldExists('selected_customer')
            ->assertFormFieldExists('filter_sale_order')
            ->assertFormFieldExists('selected_surat_jalans');
    }

    public function test_invoice_creation_rejects_surat_jalans_from_different_sale_orders(): void
    {
        $this->newUser();
        [$doA, , $soA] = $this->approvedDo('INVA', 5);
        [$doB, , $soB] = $this->approvedDo('INVB', 5);
        $sjA = $this->sj([$doA]);
        $sjB = $this->sj([$doB]);

        Livewire::test(\App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice::class)
            ->fillForm([
                'selected_customer' => $soA->customer_id,
                'filter_sale_order' => $soA->id,
                'selected_surat_jalans' => [$sjA->id, $sjB->id],
                'invoice_number' => 'INV-W4-REJECT',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
            ])
            ->call('create')
            ->assertHasFormErrors(['selected_surat_jalans']);

        $this->assertSame(0, \App\Models\Invoice::count());
    }

    // ---------------------------------------------------------------- B26 invoice non-draft dikunci

    public function test_posted_invoice_fields_are_locked_and_redirects_to_view(): void
    {
        $this->newUser();
        [$so] = $this->approvedSo(2, 'SO-W4-LOCK');
        $invoice = Invoice::create([
            'invoice_number' => 'INV-W4-LOCKED',
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'unpaid',
            'subtotal' => 150000,
            'total' => 150000,
        ]);

        $this->assertTrue(SalesInvoiceResource::isInvoiceFieldLocked($invoice));

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->getKey()])
            ->assertRedirect(SalesInvoiceResource::getUrl('view', ['record' => $invoice]));
    }

    public function test_draft_invoice_is_not_locked_and_can_be_edited(): void
    {
        $this->newUser();
        [$so] = $this->approvedSo(2, 'SO-W4-DRF');
        $invoice = Invoice::create([
            'invoice_number' => 'INV-W4-DRAFT',
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'subtotal' => 150000,
            'total' => 150000,
        ]);

        $this->assertFalse(SalesInvoiceResource::isInvoiceFieldLocked($invoice));

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->getKey()])
            ->assertSuccessful();
    }
}
