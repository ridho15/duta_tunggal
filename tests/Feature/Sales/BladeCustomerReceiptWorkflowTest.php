<?php

namespace Tests\Feature\Sales;

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Deposit;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeCustomerReceiptWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected ChartOfAccount $cashCoa;
    protected ChartOfAccount $bankCoa;
    protected ChartOfAccount $depositCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-SBY',
            'nama' => 'Cabang Surabaya',
            'alamat' => 'Jl. Pemuda No. 1, Surabaya',
            'telepon' => '031-5551234',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin']);

        $this->user = User::withoutGlobalScopes()->first() ?? User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }

        $this->customer = Customer::withoutGlobalScopes()->first() ?? Customer::factory()->create([
            'name' => 'PT Pelanggan Sejahtera',
            'code' => 'CUST-001',
            'email' => 'pelanggan@example.com',
            'phone' => '081234567890',
            'address' => 'Jl. Basuki Rahmat No. 10',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::withoutGlobalScopes()->first()
            ?? Warehouse::create([
                'name' => 'Gudang Utama Surabaya',
                'kode' => 'GDG-SBY',
                'location' => 'Surabaya',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $cat = ProductCategory::withoutGlobalScopes()->first() ?? ProductCategory::create(['name' => 'General', 'kode' => 'GEN']);
        $uom = UnitOfMeasure::withoutGlobalScopes()->first() ?? UnitOfMeasure::create(['name' => 'PCS', 'abbreviation' => 'PCS']);

        $this->product = Product::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Product::withoutGlobalScopes()->first()
            ?? Product::create([
                'sku' => 'PRD-TEST-RECEIPT',
                'name' => 'Barang Uji Kwitansi',
                'sell_price' => 100000,
                'cost_price' => 60000,
                'product_category_id' => $cat->id,
                'uom_id' => $uom->id,
                'is_active' => true,
                'cabang_id' => $this->cabang->id,
                'kode_merk' => 'MRK-TEST',
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0,
                'biaya' => 0,
                'item_value' => 60000,
                'jumlah_kelipatan_gudang_besar' => 1,
                'jumlah_jual_kategori_banyak' => 1,
            ]);

        // COAs
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

        foreach ([
            ['code' => '1120.01', 'name' => 'Piutang Dagang', 'type' => 'Asset'],
            ['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue'],
            ['code' => '4100.10', 'name' => 'Penjualan Produk', 'type' => 'Revenue'],
            ['code' => '1140.01', 'name' => 'Persediaan Barang', 'type' => 'Asset'],
            ['code' => '2110.01', 'name' => 'PPN Keluaran', 'type' => 'Liability'],
        ] as $account) {
            ChartOfAccount::firstOrCreate(['code' => $account['code']], array_merge($account, ['is_active' => true]));
        }
    }

    protected function createInvoiceWithAR(float $total = 1000000): Invoice
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'payment_term' => 'net_30',
            'subtotal' => $total,
            'total_amount' => $total,
            'created_by' => $this->user->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang->id,
            'customer_name' => $this->customer->name,
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'subtotal' => $total,
            'dpp' => $total,
            'total' => $total,
            'status' => Invoice::STATUS_SENT,
            'created_by' => $this->user->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => $total / 10,
            'subtotal' => $total,
            'total' => $total,
        ]);

        // Pastikan AccountReceivable tersedia
        $ar = AccountReceivable::firstOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'customer_id' => $this->customer->id,
                'total_amount' => $total,
                'paid' => 0,
                'remaining' => $total,
                'total_amount_original' => $total,
                'paid_original' => 0,
                'remaining_original' => $total,
                'exchange_rate' => 1.0,
                'cabang_id' => $this->cabang->id,
                'status' => 'unpaid',
            ]
        );

        return $invoice;
    }

    public function test_receipts_index_page_loads_and_displays_kpis()
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.receipts.index'));

        $response->assertStatus(200);
        $response->assertSee('Terima Pembayaran Customer');
        $response->assertSee('Total Penerimaan');
        $response->assertSee('Piutang Tertagih (AR)');
        $response->assertSee('Sisa Piutang Berjalan');
    }

    public function test_receipts_create_page_loads_with_customers_and_coas()
    {
        $invoice = $this->createInvoiceWithAR(500000);

        $response = $this->actingAs($this->user)->get(route('penjualan.receipts.create', [
            'invoice_id' => $invoice->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Catat Penerimaan Customer');
        $response->assertSee($this->customer->name);
        $response->assertSee('Metode Pembayaran');
        $response->assertSee('Kas Operasional');
    }

    public function test_customer_invoices_json_endpoint_returns_unpaid_invoices()
    {
        $invoice = $this->createInvoiceWithAR(750000);

        $response = $this->actingAs($this->user)->get(route('penjualan.receipts.customerInvoices', $this->customer->id));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'customer' => ['id', 'name', 'phone'],
            'deposit_balance',
            'invoices' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'total',
                    'paid',
                    'remaining',
                    'cabang_id',
                ]
            ]
        ]);
        $response->assertJsonFragment([
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);
    }

    public function test_can_create_customer_receipt_for_full_payment_and_updates_ar_and_journals()
    {
        $invoice = $this->createInvoiceWithAR(1000000);

        $response = $this->actingAs($this->user)->post(route('penjualan.receipts.store'), [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'coa_id' => $this->cashCoa->id,
            'notes' => 'Pelunasan penuh via tunai',
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [
                $invoice->id => 1000000,
            ],
        ]);

        $receipt = CustomerReceipt::latest('id')->first();
        $this->assertNotNull($receipt);
        $this->assertEquals(1000000, (float) $receipt->total_payment);
        $this->assertEquals('Paid', $receipt->status);

        $response->assertRedirect(route('penjualan.receipts.show', $receipt->id));

        // Verifikasi AR dan Invoice terupdate penuh
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(0, (float) $ar->remaining);
        $this->assertEquals(1000000, (float) $ar->paid);
        $this->assertEquals('paid', strtolower((string) $invoice->fresh()->status));

        // Verifikasi CustomerReceiptItem terbentuk
        $this->assertDatabaseHas('customer_receipt_items', [
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
        ]);

        // Verifikasi Jurnal Terbentuk
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => CustomerReceipt::class,
            'source_id' => $receipt->id,
        ]);
    }

    public function test_can_create_customer_receipt_for_partial_payment()
    {
        $invoice = $this->createInvoiceWithAR(2000000);

        $response = $this->actingAs($this->user)->post(route('penjualan.receipts.store'), [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'coa_id' => $this->bankCoa->id,
            'payment_reference' => 'TRF-BCA-123456',
            'bank_name' => 'BCA',
            'notes' => 'Cicilan pertama',
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [
                $invoice->id => 800000,
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $receipt = CustomerReceipt::latest('id')->first();
        $this->assertNotNull($receipt);
        $this->assertEquals(800000, (float) $receipt->total_payment);
        $this->assertEquals('Partial', $receipt->status);

        $response->assertRedirect(route('penjualan.receipts.show', $receipt->id));

        // Verifikasi AR terupdate sebagian
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(1200000, (float) $ar->remaining);
        $this->assertEquals(800000, (float) $ar->paid);
        $this->assertEquals('partially_paid', strtolower((string) $invoice->fresh()->status));
    }

    public function test_non_cash_payment_requires_reference_number()
    {
        $invoice = $this->createInvoiceWithAR(1000000);

        $response = $this->actingAs($this->user)->post(route('penjualan.receipts.store'), [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'coa_id' => $this->bankCoa->id,
            'payment_reference' => '', // kosong
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [
                $invoice->id => 1000000,
            ],
        ]);

        $response->assertSessionHasErrors('payment_reference');
    }

    public function test_can_stream_customer_receipt_kwitansi_pdf()
    {
        $invoice = $this->createInvoiceWithAR(1000000);

        $receipt = CustomerReceipt::create([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'coa_id' => $this->cashCoa->id,
            'total_payment' => 1000000,
            'total_payment_idr' => 1000000,
            'status' => 'Paid',
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [$invoice->id => 1000000],
            'created_by' => $this->user->id,
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'amount_idr' => 1000000,
            'method' => 'Cash',
            'coa_id' => $this->cashCoa->id,
            'selected_invoices' => [$invoice->id],
        ]);

        $response = $this->actingAs($this->user)->get(route('penjualan.receipts.pdf', $receipt->id));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_can_cancel_customer_receipt_reverses_journals_and_restores_ar()
    {
        $invoice = $this->createInvoiceWithAR(1000000);

        // Buat penerimaan tunai resmi
        $this->actingAs($this->user)->post(route('penjualan.receipts.store'), [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'coa_id' => $this->cashCoa->id,
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [
                $invoice->id => 1000000,
            ],
        ]);

        $receipt = CustomerReceipt::latest('id')->first();
        $this->assertEquals('Paid', $receipt->status);

        // Batalkan penerimaan
        $cancelResponse = $this->actingAs($this->user)->post(route('penjualan.receipts.cancel', $receipt->id), [
            'reason' => 'Pelanggan membatalkan transaksi pembayaran tunai secara resmi.',
        ]);

        $cancelResponse->assertRedirect(route('penjualan.receipts.show', $receipt->id));

        $receipt->refresh();
        $this->assertEquals('Cancelled', $receipt->status);
        $this->assertNotNull($receipt->cancelled_at);

        // AR dan invoice harus pulih kembali
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(1000000, (float) $ar->remaining);
        $this->assertEquals(0, (float) $ar->paid);

        // Verifikasi reversal journal entry dibuat
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => CustomerReceipt::class,
            'source_id' => $receipt->id,
            'is_reversal' => true,
        ]);
    }

    public function test_reject_customer_receipt_cancellation_with_short_reason()
    {
        $invoice = $this->createInvoiceWithAR(1000000);

        $this->actingAs($this->user)->post(route('penjualan.receipts.store'), [
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'coa_id' => $this->cashCoa->id,
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [
                $invoice->id => 1000000,
            ],
        ]);

        $receipt = CustomerReceipt::latest('id')->first();

        $response = $this->actingAs($this->user)->post(route('penjualan.receipts.cancel', $receipt->id), [
            'reason' => 'pendek', // < 10 chars
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertEquals('Paid', $receipt->fresh()->status);
    }
}
