<?php

namespace Tests\Feature\Sales;

use App\Filament\Resources\CustomerReceiptResource;
use App\Filament\Resources\CustomerReceiptResource\Pages\CreateCustomerReceipt;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Deposit;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

class Tahap6CustomerReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang1;
    protected Cabang $cabang2;
    protected Customer $customerA;
    protected Customer $customerB;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;
    protected Product $product1;
    protected UnitOfMeasure $uom;
    protected ChartOfAccount $cashCoa;
    protected ChartOfAccount $bankCoa;
    protected ChartOfAccount $depositCoa;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Basic COA accounts
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

        // Cash & Bank COA
        $this->cashCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1110.01'],
            ['name' => 'Kas Operasional', 'type' => 'Asset', 'is_active' => true, 'is_cash_bank' => true]
        );
        $this->bankCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1111.01'],
            ['name' => 'Bank BCA', 'type' => 'Asset', 'is_active' => true, 'is_cash_bank' => true]
        );
        $this->depositCoa = ChartOfAccount::firstOrCreate(
            ['code' => '2160.04'],
            ['name' => 'Deposit Pelanggan', 'type' => 'Liability', 'is_active' => true]
        );

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

        $this->customerA = Customer::factory()->create([
            'name' => 'PT Pelanggan Sejahtera',
            'cabang_id' => $this->cabang1->id,
        ]);

        $this->customerB = Customer::factory()->create([
            'name' => 'CV Mitra Mandiri',
            'cabang_id' => $this->cabang1->id,
        ]);

        $this->warehouse1 = Warehouse::create([
            'name' => 'Gudang Utama Surabaya',
            'kode' => 'GDG-SBY',
            'location' => 'Surabaya Barat',
            'cabang_id' => $this->cabang1->id,
            'status' => 1,
            'is_active' => true,
        ]);

        $this->warehouse2 = Warehouse::create([
            'name' => 'Gudang Utama Malang',
            'kode' => 'GDG-MLG',
            'location' => 'Malang Kota',
            'cabang_id' => $this->cabang2->id,
            'status' => 1,
            'is_active' => true,
        ]);

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'PCS'],
            ['abbreviation' => 'PCS']
        );

        $cat = ProductCategory::firstOrCreate(['kode' => 'GEN'], ['name' => 'General', 'kode' => 'GEN']);

        $salesCoa = ChartOfAccount::where('code', '4100.10')->first();

        $this->product1 = Product::create([
            'cabang_id' => $this->cabang1->id,
            'name' => 'Pipa Tembaga 1/2',
            'sku' => 'PIP-001',
            'product_category_id' => $cat->id,
            'uom_id' => $this->uom->id,
            'cost_price' => 50000,
            'sell_price' => 100000,
            'sales_coa_id' => $salesCoa?->id,
            'kode_merk' => 'GENERIC',
            'harga_batas' => 0,
            'is_active' => true,
        ]);

        InventoryStock::updateOrCreate(
            ['product_id' => $this->product1->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );

        InventoryStock::updateOrCreate(
            ['product_id' => $this->product1->id, 'warehouse_id' => $this->warehouse2->id, 'rak_id' => null],
            ['qty_available' => 500, 'qty_reserved' => 0]
        );
    }

    protected function createCompleteSuratJalan(Customer $customer, Cabang $cabang, Warehouse $warehouse, int $qty = 5): SuratJalan
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-' . uniqid(),
            'customer_id' => $customer->id,
            'cabang_id' => $cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'payment_term' => 'net_30',
            'subtotal' => $qty * 100000,
            'total_amount' => $qty * 100000,
            'created_by' => $this->user->id,
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product1->id,
            'quantity' => $qty,
            'unit_price' => 100000,
            'subtotal' => $qty * 100000,
            'total' => $qty * 100000,
            'unit_of_measure_id' => $this->uom->id,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-' . uniqid(),
            'delivery_date' => now(),
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $cabang->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $do->salesOrders()->attach($so->id);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product1->id,
            'quantity' => $qty,
            'unit_of_measure_id' => $this->uom->id,
        ]);

        $sj = SuratJalan::create([
            'sj_number' => 'SJ-' . uniqid(),
            'status' => SuratJalan::STATUS_ISSUED,
            'issued_at' => now(),
            'cabang_id' => $cabang->id,
            'created_by' => $this->user->id,
        ]);

        $sj->deliveryOrder()->attach($do->id);

        return $sj;
    }

    public function test_customer_receipt_can_pay_sales_invoice_from_surat_jalan()
    {
        $sj = $this->createCompleteSuratJalan($this->customerA, $this->cabang1, $this->warehouse1, 10);

        // Buat Sales Invoice mengacu pada Surat Jalan (Tahap 5)
        $invoice = Invoice::create([
            'invoice_number' => 'INV-SJ-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang1->id,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj->id,
            'surat_jalan_ids' => [$sj->id],
            'subtotal' => 1000000,
            'dpp' => 1000000,
            'total' => 1000000,
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product1->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'subtotal' => 1000000,
            'total' => 1000000,
        ]);

        // Verifikasi AR terbit dari Surat Jalan
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar, 'Account Receivable harus terbentuk otomatis oleh InvoiceObserver.');
        $this->assertEquals(1000000, (float) $ar->remaining);
        $this->assertEquals($this->customerA->id, (int) $ar->customer_id);

        // Verifikasi invoice ditemukan oleh query invoiceableInvoicesQuery
        $invoiceableIds = CustomerReceiptResource::invoiceableInvoicesQuery($this->customerA->id)->pluck('id')->all();
        $this->assertContains($invoice->id, $invoiceableIds, 'Invoice dari Surat Jalan harus muncul pada form Customer Receipt.');

        // Buat Customer Receipt untuk melunasi
        $receipt = CustomerReceipt::create([
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang1->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'total_payment' => 1000000,
            'coa_id' => $this->cashCoa->id,
            'status' => 'Draft',
            'selected_invoices' => [$invoice->id],
            'created_by' => $this->user->id,
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'method' => 'Cash',
            'selected_invoices' => [$invoice->id],
        ]);

        $receipt->update(['status' => 'Paid']);

        $ar->refresh();
        $this->assertEquals(0, (float) $ar->remaining, 'Remaining AR harus 0 setelah pelunasan penuh.');
        $this->assertEquals(1000000, (float) $ar->paid, 'Paid AR harus 1.000.000.');
        $this->assertEquals('paid', strtolower((string) $invoice->fresh()->status), 'Status invoice harus paid.');
    }

    public function test_customer_receipt_can_pay_consolidated_multi_surat_jalan_invoice()
    {
        $sj1 = $this->createCompleteSuratJalan($this->customerA, $this->cabang1, $this->warehouse1, 3);
        $sj2 = $this->createCompleteSuratJalan($this->customerA, $this->cabang1, $this->warehouse1, 2);

        // Konsolidasi 2 Surat Jalan ke dalam 1 Sales Invoice
        $invoice = Invoice::create([
            'invoice_number' => 'INV-MULTI-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang1->id,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj1->id,
            'surat_jalan_ids' => [$sj1->id, $sj2->id],
            'subtotal' => 500000,
            'dpp' => 500000,
            'total' => 500000,
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar);
        $this->assertEquals(500000, (float) $ar->remaining);

        $receipt = CustomerReceipt::create([
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang1->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'total_payment' => 500000,
            'coa_id' => $this->bankCoa->id,
            'status' => 'Draft',
            'selected_invoices' => [$invoice->id],
            'created_by' => $this->user->id,
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'method' => 'Transfer',
            'selected_invoices' => [$invoice->id],
        ]);

        $receipt->update(['status' => 'Paid']);

        $ar->refresh();
        $this->assertEquals(0, (float) $ar->remaining);
        $this->assertEquals('paid', strtolower((string) $invoice->fresh()->status));

        // Verifikasi dokumen Kwitansi memuat kedua nomor Surat Jalan
        $doc = app(DocumentPrintBuilder::class)->receipt($receipt);
        $metaMap = collect($doc['meta'])->pluck('value', 'label');
        $this->assertNotNull($metaMap->get('No. Surat Jalan'));
        $this->assertStringContainsString($sj1->sj_number, $metaMap->get('No. Surat Jalan'));
        $this->assertStringContainsString($sj2->sj_number, $metaMap->get('No. Surat Jalan'));
    }

    public function test_partial_customer_receipt_updates_ar_remaining_and_sets_partial_status()
    {
        $sj = $this->createCompleteSuratJalan($this->customerA, $this->cabang1, $this->warehouse1, 10);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-PARTIAL-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang1->id,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj->id,
            'surat_jalan_ids' => [$sj->id],
            'subtotal' => 1000000,
            'dpp' => 1000000,
            'total' => 1000000,
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(1000000, (float) $ar->remaining);

        // Bayar sebagian: 400.000
        $receipt = CustomerReceipt::create([
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang1->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'total_payment' => 400000,
            'coa_id' => $this->cashCoa->id,
            'status' => 'Draft',
            'selected_invoices' => [$invoice->id],
            'created_by' => $this->user->id,
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 400000,
            'method' => 'Cash',
            'selected_invoices' => [$invoice->id],
        ]);

        $receipt->update(['status' => 'Partial']);

        $ar->refresh();
        $this->assertEquals(400000, (float) $ar->paid);
        $this->assertEquals(600000, (float) $ar->remaining);
        $this->assertContains(strtolower((string) $invoice->fresh()->status), ['partial', 'partially_paid']);
    }

    public function test_customer_receipt_via_livewire_allocates_payment_correctly()
    {
        $sj = $this->createCompleteSuratJalan($this->customerA, $this->cabang1, $this->warehouse1, 5);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-LW-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang1->id,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj->id,
            'surat_jalan_ids' => [$sj->id],
            'subtotal' => 500000,
            'dpp' => 500000,
            'total' => 500000,
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        $remaining = (float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining');
        $this->assertEquals(500000, $remaining);

        Livewire::actingAs($this->user)
            ->test(CreateCustomerReceipt::class)
            ->fillForm([
                'customer_id' => $this->customerA->id,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Transfer',
                'payment_reference' => 'TRF-TEST-' . uniqid(),
                'coa_id' => $this->bankCoa->id,
                'selected_invoices' => json_encode([$invoice->id]),
                'invoice_receipts' => json_encode([$invoice->id => 500000]),
                'total_payment' => '500000',
                'status' => 'Draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $receipt = CustomerReceipt::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertEquals(500000, (float) $receipt->total_payment);
        $this->assertEquals($this->cabang1->id, (int) $receipt->cabang_id);
        $this->assertEquals(0, (float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining'));
        $this->assertEquals('paid', strtolower((string) $invoice->fresh()->status));
    }

    public function test_reject_customer_receipt_overpayment_without_deposit_option()
    {
        $sj = $this->createCompleteSuratJalan($this->customerA, $this->cabang1, $this->warehouse1, 3);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-OVER-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang1->id,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj->id,
            'surat_jalan_ids' => [$sj->id],
            'subtotal' => 300000,
            'dpp' => 300000,
            'total' => 300000,
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        $remaining = (float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining');

        // Bayar 350.000 (kelebihan 50.000 tanpa opsi deposit) -> wajib ditolak
        Livewire::actingAs($this->user)
            ->test(CreateCustomerReceipt::class)
            ->fillForm([
                'customer_id' => $this->customerA->id,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Transfer',
                'payment_reference' => 'TRF-OVER-' . uniqid(),
                'coa_id' => $this->bankCoa->id,
                'selected_invoices' => json_encode([$invoice->id]),
                'invoice_receipts' => json_encode([$invoice->id => $remaining + 50000]),
                'total_payment' => (string) ($remaining + 50000),
                'overpayment_as_deposit' => false,
                'status' => 'Draft',
            ])
            ->call('create')
            ->assertHasFormErrors(['total_payment']);
    }

    public function test_customer_receipt_overpayment_creates_customer_deposit_when_option_checked()
    {
        $sj = $this->createCompleteSuratJalan($this->customerA, $this->cabang1, $this->warehouse1, 2);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-DEP-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang1->id,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj->id,
            'surat_jalan_ids' => [$sj->id],
            'subtotal' => 200000,
            'dpp' => 200000,
            'total' => 200000,
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        $remaining = (float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining');

        // Bayar 250.000 dengan overpayment_as_deposit = true
        Livewire::actingAs($this->user)
            ->test(CreateCustomerReceipt::class)
            ->fillForm([
                'customer_id' => $this->customerA->id,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Transfer',
                'payment_reference' => 'TRF-DEP-' . uniqid(),
                'coa_id' => $this->bankCoa->id,
                'selected_invoices' => json_encode([$invoice->id]),
                'invoice_receipts' => json_encode([$invoice->id => $remaining + 50000]),
                'total_payment' => (string) ($remaining + 50000),
                'overpayment_as_deposit' => true,
                'status' => 'Draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $receipt = CustomerReceipt::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertEquals(200000, (float) $receipt->total_payment);
        $this->assertEquals(50000, (float) $receipt->overpayment_amount);

        $deposit = Deposit::where('id', $receipt->deposit_id)->first();
        $this->assertNotNull($deposit, 'Deposit harus terbentuk untuk kelebihan bayar.');
        $this->assertEquals(50000, (float) $deposit->amount);
        $this->assertEquals($this->customerA->id, (int) $deposit->from_model_id);
    }

    public function test_reject_customer_receipt_consolidating_invoices_from_different_branches()
    {
        $sj1 = $this->createCompleteSuratJalan($this->customerA, $this->cabang1, $this->warehouse1, 1);
        $sj2 = $this->createCompleteSuratJalan($this->customerA, $this->cabang2, $this->warehouse2, 1);

        $invCabang1 = Invoice::create([
            'invoice_number' => 'INV-C1-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang1->id,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj1->id,
            'surat_jalan_ids' => [$sj1->id],
            'subtotal' => 100000,
            'dpp' => 100000,
            'total' => 100000,
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        $invCabang2 = Invoice::create([
            'invoice_number' => 'INV-C2-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang2->id,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj2->id,
            'surat_jalan_ids' => [$sj2->id],
            'subtotal' => 100000,
            'dpp' => 100000,
            'total' => 100000,
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        // Coba bayar kedua invoice sekaligus yang berbeda cabang
        Livewire::actingAs($this->user)
            ->test(CreateCustomerReceipt::class)
            ->fillForm([
                'customer_id' => $this->customerA->id,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'Transfer',
                'payment_reference' => 'TRF-DIFF-' . uniqid(),
                'coa_id' => $this->bankCoa->id,
                'selected_invoices' => json_encode([$invCabang1->id, $invCabang2->id]),
                'invoice_receipts' => json_encode([$invCabang1->id => 100000, $invCabang2->id => 100000]),
                'total_payment' => '200000',
                'status' => 'Draft',
            ])
            ->call('create')
            ->assertHasFormErrors(['cabang_id']);
    }

    public function test_customer_receipt_print_displays_invoice_and_surat_jalan_details()
    {
        $sj = $this->createCompleteSuratJalan($this->customerA, $this->cabang1, $this->warehouse1, 4);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-PRN-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang1->id,
            'from_model_type' => SuratJalan::class,
            'from_model_id' => $sj->id,
            'surat_jalan_ids' => [$sj->id],
            'subtotal' => 400000,
            'dpp' => 400000,
            'total' => 400000,
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        $receipt = CustomerReceipt::create([
            'customer_id' => $this->customerA->id,
            'cabang_id' => $this->cabang1->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'payment_reference' => 'REF-PRN-001',
            'bank_name' => 'BCA',
            'total_payment' => 400000,
            'coa_id' => $this->bankCoa->id,
            'status' => 'Paid',
            'selected_invoices' => [$invoice->id],
            'created_by' => $this->user->id,
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 400000,
            'method' => 'Transfer',
            'selected_invoices' => [$invoice->id],
        ]);

        $doc = app(DocumentPrintBuilder::class)->receipt($receipt);
        $this->assertEquals('KWITANSI', $doc['title']);
        $this->assertEquals(400000, $doc['amount']);

        $metaMap = collect($doc['meta'])->pluck('value', 'label');
        $this->assertStringContainsString($invoice->invoice_number, $metaMap->get('Untuk pembayaran'));
        $this->assertStringContainsString($sj->sj_number, $metaMap->get('No. Surat Jalan'));
    }
}
