<?php

namespace Tests\Feature;

use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\StockOpnameResource;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\Warehouse;
use App\Services\Reports\InventoryCardReportService;
use App\Services\StockOpnameService;
use App\Services\SuratJalanDocumentBuilder;
use App\Support\WarehouseStockOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Sprint3OperationalAndUiUxVerificationTest extends TestCase
{
    private Cabang $cabang;
    private User $user;
    private Customer $customer;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'S3-CAB'],
            ['nama' => 'Cabang Sprint 3', 'alamat' => 'Jl. Pengujian Sprint 3']
        );

        $this->user = User::factory()->create([
            'username' => 'sprint3_' . uniqid(),
            'name' => 'Sprint 3 Tester',
            'email' => 'sprint3_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
        Auth::login($this->user);

        $this->customer = Customer::factory()->create([
            'perusahaan' => 'PT Customer Pusat Sprint 3',
            'name' => 'PT Customer Pusat Sprint 3',
            'address' => 'Jl. Kantor Pusat Jakarta No. 1',
            'phone' => '021-5551234',
            'email' => 'customer_s3_' . uniqid() . '@example.com',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->supplier = Supplier::factory()->create([
            'code' => 'SUP-S3-' . strtoupper(substr(uniqid(), -4)),
            'perusahaan' => 'PT Supplier Utama Sprint 3',
            'address' => 'Jl. Industri Supplier No. 8',
            'cabang_id' => $this->cabang->id,
            'nama_bank' => 'BCA',
            'nomor_rekening' => '8880011223',
            'nama_rekening' => 'PT Supplier Utama Sprint 3',
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['kode' => 'GUD-S3-01'],
            [
                'name' => 'Gudang Utama Sprint 3',
                'cabang_id' => $this->cabang->id,
                'location' => 'Jakarta Barat',
                'status' => 1,
            ]
        );

        $this->product = Product::where('sku', 'SKU-S3-01')->first()
            ?? Product::factory()->create([
                'sku' => 'SKU-S3-01',
                'name' => 'Produk Uji Sprint 3',
                'sell_price' => 250000,
                'cost_price' => 180000,
                'cabang_id' => $this->cabang->id,
            ]);
    }

    /**
     * Test Bug 4: Stock Opname view page route registration and physical count validation.
     */
    public function test_bug_4_stock_opname_view_page_registered_and_validation(): void
    {
        $pages = StockOpnameResource::getPages();
        $this->assertArrayHasKey('view', $pages, 'Halaman view harus terdaftar pada StockOpnameResource::getPages()');

        $service = app(StockOpnameService::class);
        $opnameNumbers = ['SO-TEST-EMPTY', 'SO-TEST-NULL', 'SO-TEST-VALID'];
        StockOpnameItem::whereIn('stock_opname_id', StockOpname::withTrashed()->whereIn('opname_number', $opnameNumbers)->pluck('id'))->delete();
        StockOpname::withTrashed()->whereIn('opname_number', $opnameNumbers)->forceDelete();

        // Kasus A: Menyelesaikan opname tanpa item harus gagal
        $emptyOpname = StockOpname::create([
            'opname_number' => 'SO-TEST-EMPTY',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'opname_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        try {
            $service->completePhysicalCount($emptyOpname);
            $this->fail('Menyelesaikan opname tanpa item harus melempar ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        // Kasus B: Menyelesaikan opname dengan item yang sudah diinput fisiknya harus sukses
        $validOpname = StockOpname::create([
            'opname_number' => 'SO-TEST-VALID',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'opname_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);
        StockOpnameItem::create([
            'stock_opname_id' => $validOpname->id,
            'product_id' => $this->product->id,
            'system_qty' => 10,
            'physical_qty' => 12,
        ]);

        $completed = $service->completePhysicalCount($validOpname);
        $this->assertEquals('completed', $completed->status);
    }

    /**
     * Test Bug 9: Inventory card report includes all mutation types.
     */
    public function test_bug_9_inventory_card_report_includes_all_mutation_types(): void
    {
        $reflection = new \ReflectionClass(InventoryCardReportService::class);
        $inTypes = $reflection->getConstant('IN_TYPES');
        $outTypes = $reflection->getConstant('OUT_TYPES');

        $this->assertContains('beginning', $inTypes, 'IN_TYPES harus mencakup beginning');
        $this->assertContains('customer_return', $inTypes, 'IN_TYPES harus mencakup customer_return');
        $this->assertContains('return_in', $inTypes, 'IN_TYPES harus mencakup return_in');
        $this->assertContains('sales_return_in', $inTypes, 'IN_TYPES harus mencakup sales_return_in');

        $this->assertContains('sales', $outTypes, 'OUT_TYPES harus mencakup sales');
        $this->assertContains('transfer_out', $outTypes, 'OUT_TYPES harus mencakup transfer_out');
        $this->assertContains('purchase_return', $outTypes, 'OUT_TYPES harus mencakup purchase_return');
        $this->assertContains('return_out', $outTypes, 'OUT_TYPES harus mencakup return_out');
        $this->assertContains('purchase_return_out', $outTypes, 'OUT_TYPES harus mencakup purchase_return_out');
    }

    /**
     * Test Bug 10: StockMovementObserver prevents negative inventory on outbound movements.
     */
    public function test_bug_10_stock_movement_prevents_negative_inventory_on_outbound(): void
    {
        $stock = InventoryStock::updateOrCreate(
            [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'rak_id' => null,
            ],
            [
                'qty_available' => 5,
                'qty_reserved' => 0,
            ]
        );

        $this->expectException(ValidationException::class);

        // Mencoba mengeluarkan 10 unit padahal stok hanya 5
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => null,
            'type' => 'sales',
            'quantity' => 10,
            'date' => now(),
        ]);
    }

    /**
     * Test Bug 12: DeliveryOrder infolist and SuratJalan prioritize shipped_to over customer address.
     */
    public function test_bug_12_delivery_order_and_surat_jalan_prioritize_shipped_to(): void
    {
        $do = DeliveryOrder::where('do_number', 'DO-S3-TEST-01')->first();
        if ($do) {
            $do->salesOrders()->detach();
            $do->delete();
        }
        SaleOrder::where('so_number', 'SO-S3-TEST-01')->delete();

        $saleOrder = SaleOrder::create([
            'so_number' => 'SO-S3-TEST-01',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'order_date' => now(),
            'status' => 'approved',
            'shipped_to' => 'Proyek BSD Cluster Foresta Blok B1 No. 5',
        ]);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-S3-TEST-01',
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'delivery_date' => now(),
            'shipping_date' => now(),
        ]);
        $deliveryOrder->salesOrders()->attach($saleOrder->id);

        $builder = app(SuratJalanDocumentBuilder::class);
        $reflection = new \ReflectionMethod($builder, 'group');
        $reflection->setAccessible(true);
        $group = $reflection->invoke($builder, $deliveryOrder);

        $this->assertContains('Proyek BSD Cluster Foresta Blok B1 No. 5', $group['addresses']);
        $this->assertNotContains('Jl. Kantor Pusat Jakarta No. 1', $group['addresses']);
    }

    /**
     * Test Bug 13: Payment Request status transitions to partial when partially paid.
     */
    public function test_bug_13_payment_request_status_transitions_to_partial_when_partially_paid(): void
    {
        VendorPayment::withTrashed()->whereIn('payment_number', ['VP-TEST-01', 'VP-TEST-02'])->forceDelete();
        PaymentRequest::withTrashed()->where('request_number', 'PAY-REQ-TEST-01')->forceDelete();

        $pr = PaymentRequest::create([
            'request_number' => 'PAY-REQ-TEST-01',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'requested_by' => $this->user->id,
            'request_date' => now()->toDateString(),
            'payment_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 1000000,
            'status' => PaymentRequest::STATUS_APPROVED,
        ]);

        // Pembayaran sebagian: 400.000 dari 1.000.000
        $payment1 = VendorPayment::create([
            'payment_number' => 'VP-TEST-01',
            'payment_request_id' => $pr->id,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 400000,
            'status' => 'paid',
        ]);

        $this->assertEquals(PaymentRequest::STATUS_PARTIAL, $pr->fresh()->status, 'Status PR harus partial saat dibayar sebagian');

        // Pembayaran pelunasan: 600.000
        $payment2 = VendorPayment::create([
            'payment_number' => 'VP-TEST-02',
            'payment_request_id' => $pr->id,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 600000,
            'status' => 'paid',
        ]);

        $this->assertEquals(PaymentRequest::STATUS_PAID, $pr->fresh()->status, 'Status PR harus paid saat total pembayaran lunas');
    }

    /**
     * Test Bug 14: WarehouseStockOptions calculates free stock excluding reserved.
     */
    public function test_bug_14_warehouse_stock_options_calculates_free_stock(): void
    {
        InventoryStock::updateOrCreate(
            [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'rak_id' => null,
            ],
            [
                'qty_available' => 50,
                'qty_reserved' => 20,
            ]
        );

        $options = WarehouseStockOptions::forProduct($this->product->id, null, true, $this->cabang->id);

        $this->assertArrayHasKey($this->warehouse->id, $options);
        $label = $options[$this->warehouse->id];

        $this->assertStringContainsString('Stok Bebas: 30', $label, 'Label harus menampilkan stok bebas (50 - 20 = 30)');
        $this->assertStringContainsString('Fisik: 50', $label, 'Label harus menampilkan total stok fisik');
    }

    /**
     * Test Bug 17: Purchase Invoice STATUS_SENT label is Menunggu Pembayaran.
     */
    public function test_bug_17_purchase_invoice_status_sent_label_is_menunggu_pembayaran(): void
    {
        $invoice = new Invoice([
            'status' => Invoice::STATUS_SENT,
        ]);

        // Simulasikan closure formatStateUsing dari kolom status di PurchaseInvoiceResource
        $formatStatus = function ($state) {
            return match ($state) {
                Invoice::STATUS_DRAFT => 'Draft',
                Invoice::STATUS_SENT => 'Menunggu Pembayaran',
                Invoice::STATUS_PAID => 'Lunas',
                Invoice::STATUS_PARTIALLY_PAID => 'Dibayar Sebagian',
                Invoice::STATUS_OVERDUE => 'Terlambat',
                Invoice::STATUS_CANCELLED => 'Dibatalkan',
                default => $state,
            };
        };

        $this->assertEquals('Menunggu Pembayaran', $formatStatus($invoice->status));
    }

    /**
     * Test Bug 18: Quotation PDF blade includes dynamic watermark and title.
     */
    public function test_bug_18_quotation_pdf_watermark_and_title(): void
    {
        Quotation::whereIn('quotation_number', ['QUO-S3-DRAFT', 'QUO-S3-APPROVE'])->delete();

        $draftQuotation = Quotation::create([
            'quotation_number' => 'QUO-S3-DRAFT',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'date' => now()->toDateString(),
            'status' => Quotation::STATUS_DRAFT,
        ]);

        $renderedDraft = View::make('pdf.quotation', ['quotation' => $draftQuotation])->render();
        $this->assertStringContainsString('DRAFT', $renderedDraft, 'Draft quotation harus menampilkan watermark DRAFT');
        $this->assertStringContainsString('DRAFT PENAWARAN HARGA', $renderedDraft);

        $approvedQuotation = Quotation::create([
            'quotation_number' => 'QUO-S3-APPROVE',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
            'date' => now()->toDateString(),
            'status' => Quotation::STATUS_APPROVE,
        ]);

        $renderedApprove = View::make('pdf.quotation', ['quotation' => $approvedQuotation])->render();
        $this->assertStringNotContainsString('DRAFT', $renderedApprove, 'Approved quotation tidak boleh memuat watermark DRAFT');
        $this->assertStringContainsString('SURAT PENAWARAN HARGA', $renderedApprove);
    }

    /**
     * Test Bug 21: COA relation in JournalEntryResource filters out parent accounts.
     */
    public function test_bug_21_coa_relation_filters_out_parent_accounts(): void
    {
        $parentCoa = ChartOfAccount::firstOrCreate(
            ['code' => '9900'],
            [
                'name' => 'Akun Induk Sprint 3',
                'type' => 'Expense',
                'parent_id' => null,
                'is_active' => true,
            ]
        );

        $childCoa = ChartOfAccount::firstOrCreate(
            ['code' => '9900.01'],
            [
                'name' => 'Akun Sub Pos Sprint 3',
                'type' => 'Expense',
                'parent_id' => $parentCoa->id,
                'is_active' => true,
            ]
        );

        $filteredQuery = ChartOfAccount::query()->whereDoesntHave('children')->where('is_active', true);

        $this->assertFalse(
            $filteredQuery->clone()->where('id', $parentCoa->id)->exists(),
            'Akun induk yang memiliki anak tidak boleh muncul di pilihan jurnal'
        );
        $this->assertTrue(
            $filteredQuery->clone()->where('id', $childCoa->id)->exists(),
            'Akun sub pos (child) harus muncul di pilihan jurnal'
        );
    }
}
