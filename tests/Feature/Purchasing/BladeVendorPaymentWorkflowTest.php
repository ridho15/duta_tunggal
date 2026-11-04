<?php

namespace Tests\Feature\Purchasing;

use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeVendorPaymentWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Currency $currency;
    protected ChartOfAccount $cashBankCoa;
    protected ChartOfAccount $apCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-VP',
            'nama' => 'Cabang Test VP',
            'alamat' => 'Jl. Test No. 1',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any vendor payment',
            'view vendor payment',
            'create vendor payment',
            'update vendor payment',
            'delete vendor payment',
            'view any invoice',
            'view invoice',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

        $this->user = User::withoutGlobalScopes()->first() ?? User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (!$this->user->hasRole('Super Admin')) {
            $this->user->assignRole('Super Admin');
        }

        $this->supplier = Supplier::first() ?? Supplier::create([
            'code' => 'SUP-VP-01',
            'perusahaan' => 'PT Baja Perkasa Mandiri',
            'nama_bank' => 'BCA',
            'nomor_rekening' => '8880192837',
            'atas_nama' => 'PT Baja Perkasa Mandiri',
            'tempo_hutang' => 30,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->warehouse = Warehouse::where('status', 1)->first() ?? Warehouse::create([
            'kode' => 'GDG-VP-01',
            'name' => 'Gudang Utama Test',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'exchange_rate' => 1,
            'status' => 1,
        ]);

        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Pcs', 'code' => 'PCS']);

        $this->apCoa = ChartOfAccount::firstOrCreate([
            'code' => config('coa.accounts_payable', '21100'),
        ], [
            'name' => 'Hutang Usaha',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $this->cashBankCoa = ChartOfAccount::firstOrCreate([
            'code' => '11120.001',
        ], [
            'name' => 'Bank BCA Operasional',
            'type' => 'Asset',
            'is_cash_bank' => true,
            'is_active' => true,
        ]);

        $invCoa = ChartOfAccount::firstOrCreate([
            'code' => config('coa.inventory', '11300'),
        ], [
            'name' => 'Persediaan Barang',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->product = Product::first() ?? Product::create([
            'part_number' => 'PRD-VP-01',
            'name' => 'Pipa Baja Seamless 2 Inch',
            'type' => 'raw_material',
            'category_id' => 1,
            'unit_id' => $uom->id,
            'purchase_price' => 100000,
            'sale_price' => 150000,
            'status' => 1,
            'inventory_coa_id' => $invCoa->id,
        ]);
    }

    protected function createPostedInvoice(float $total = 500000): Invoice
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'subtotal' => $total,
            'total_amount' => $total,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'supplier_invoice_number' => 'VEND-INV-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => $total,
            'total' => $total,
            'status' => Invoice::STATUS_SENT, // Belum Lunas
        ]);

        AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1,
            'total' => $total,
            'paid' => 0,
            'remaining' => $total,
            'status' => 'belum lunas',
        ]);

        return $invoice;
    }

    public function test_payment_index_page_loads_and_displays_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('pembelian.payments.index'));

        $response->assertStatus(200);
        $response->assertSee('Pembayaran Hutang Vendor');
        $response->assertSee('+ Catat Pembayaran Baru');
        $response->assertSee('Total Pembayaran (Aktif)');
        $response->assertSee('Dibayar Bulan Ini');
        $response->assertSee('Total Sisa Hutang (AP)');
    }

    public function test_payment_create_page_loads_with_suppliers_and_accounts(): void
    {
        $response = $this->actingAs($this->user)->get(route('pembelian.payments.create'));

        $response->assertStatus(200);
        $response->assertSee('Catat Pembayaran Hutang Vendor');
        $response->assertSee($this->supplier->perusahaan);
        $response->assertSee('Akun Kas / Bank Pengeluar');
    }

    public function test_ajax_supplier_invoices_endpoint_returns_json(): void
    {
        $invoice = $this->createPostedInvoice(750000);

        $response = $this->actingAs($this->user)->getJson(route('pembelian.payments.supplier-invoices', $this->supplier->id));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'supplier' => ['id', 'code', 'perusahaan', 'target_bank_account'],
            'invoices' => [
                '*' => ['id', 'invoice_number', 'total', 'remaining'],
            ],
        ]);
        $response->assertJsonFragment([
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);
    }

    public function test_validation_blocks_empty_invoice_selection_or_zero_payment(): void
    {
        $response = $this->actingAs($this->user)->post(route('pembelian.payments.store'), [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'coa_id' => $this->cashBankCoa->id,
            'selected_invoices' => [],
            'allocations' => [],
        ]);

        $response->assertSessionHasErrors(['selected_invoices']);
    }

    public function test_validation_blocks_allocation_exceeding_remaining_ap(): void
    {
        $invoice = $this->createPostedInvoice(500000);

        $response = $this->actingAs($this->user)->post(route('pembelian.payments.store'), [
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'coa_id' => $this->cashBankCoa->id,
            'selected_invoices' => [$invoice->id],
            'allocations' => [
                $invoice->id => 9999999, // melebihi 500.000
            ],
        ]);

        $response->assertSessionHasErrors(["allocations.{$invoice->id}"]);
    }

    public function test_can_create_draft_vendor_payment_without_affecting_ap_or_gl(): void
    {
        $invoice = $this->createPostedInvoice(400000);
        $refNum = 'REF-DRAFT-' . uniqid();

        $response = $this->actingAs($this->user)->post(route('pembelian.payments.store'), [
            'action' => 'save_draft',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'coa_id' => $this->cashBankCoa->id,
            'target_bank_account' => 'BCA - 1234567890',
            'transfer_reference_number' => $refNum,
            'selected_invoices' => [$invoice->id],
            'allocations' => [
                $invoice->id => 400000,
            ],
            'notes' => 'Pembayaran draft pengadaan baja',
        ]);

        $response->assertRedirect();

        $payment = VendorPayment::where('transfer_reference_number', $refNum)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(VendorPayment::STATUS_DRAFT, $payment->status);
        $this->assertEquals(400000, (float)$payment->total_payment);

        // Pastikan AP belum terpotong karena draft
        $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(400000, (float)$ap->remaining);

        // Pastikan belum ada jurnal GL
        $this->assertFalse($payment->journalEntries()->exists());
    }

    public function test_can_create_and_directly_post_vendor_payment_with_gl_and_ap(): void
    {
        $invoice = $this->createPostedInvoice(600000);
        $refNum = 'REF-POSTED-' . uniqid();

        $response = $this->actingAs($this->user)->post(route('pembelian.payments.store'), [
            'action' => 'save_and_post',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'coa_id' => $this->cashBankCoa->id,
            'target_bank_account' => 'BCA - 1234567890',
            'transfer_reference_number' => $refNum,
            'selected_invoices' => [$invoice->id],
            'allocations' => [
                $invoice->id => 600000,
            ],
        ]);

        $response->assertRedirect();

        $payment = VendorPayment::where('transfer_reference_number', $refNum)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(VendorPayment::STATUS_PAID, $payment->status);

        // Cek AP terpotong lunas
        $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(0, (float)$ap->remaining);
        $this->assertEquals(600000, (float)$ap->paid);
        $this->assertContains(strtolower((string)$ap->status), ['paid', 'lunas']);

        // Cek Invoice status menjadi paid
        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);

        // Cek Jurnal GL terbentuk: Debit Hutang Dagang, Kredit Kas/Bank
        $this->assertTrue($payment->journalEntries()->exists());
        $debitTotal = (float) $payment->journalEntries()->sum('debit');
        $creditTotal = (float) $payment->journalEntries()->sum('credit');
        $this->assertEquals(600000, $debitTotal);
        $this->assertEquals(600000, $creditTotal);
    }

    public function test_partial_payment_updates_invoice_status_to_partially_paid(): void
    {
        $invoice = $this->createPostedInvoice(1000000);
        $refNum = 'REF-PARTIAL-' . uniqid();

        $response = $this->actingAs($this->user)->post(route('pembelian.payments.store'), [
            'action' => 'save_and_post',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'coa_id' => $this->cashBankCoa->id,
            'transfer_reference_number' => $refNum,
            'selected_invoices' => [$invoice->id],
            'allocations' => [
                $invoice->id => 400000, // Cicil 400.000 dari 1.000.000
            ],
        ]);

        $response->assertRedirect();

        $payment = VendorPayment::where('transfer_reference_number', $refNum)->first();
        $this->assertEquals(VendorPayment::STATUS_PARTIAL, $payment->status);

        // Cek AP sisa 600.000
        $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(600000, (float)$ap->remaining);
        $this->assertEquals(400000, (float)$ap->paid);

        // Cek Invoice status partially_paid
        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
    }

    public function test_can_post_draft_vendor_payment(): void
    {
        $invoice = $this->createPostedInvoice(300000);

        $payment = VendorPayment::create([
            'payment_number' => VendorPayment::generatePaymentNumber(),
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'coa_id' => $this->cashBankCoa->id,
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [$invoice->id => 300000],
            'total_payment' => 300000,
            'status' => VendorPayment::STATUS_DRAFT,
        ]);

        VendorPaymentDetail::create([
            'vendor_payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'method' => 'Cash',
            'amount' => 300000,
            'amount_idr' => 300000,
            'coa_id' => $this->cashBankCoa->id,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->user)->post(route('pembelian.payments.post', $payment->id));

        $response->assertRedirect();

        $payment->refresh();
        $this->assertEquals(VendorPayment::STATUS_PAID, $payment->status);

        $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(0, (float)$ap->remaining);
    }

    public function test_show_page_renders_stepper_and_tabs(): void
    {
        $invoice = $this->createPostedInvoice(250000);
        $paymentNumber = 'VP-SHOW-' . uniqid();

        $payment = VendorPayment::create([
            'payment_number' => $paymentNumber,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'coa_id' => $this->cashBankCoa->id,
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [$invoice->id => 250000],
            'total_payment' => 250000,
            'status' => VendorPayment::STATUS_PAID,
        ]);

        VendorPaymentDetail::create([
            'vendor_payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'method' => 'Bank Transfer',
            'amount' => 250000,
            'amount_idr' => 250000,
            'coa_id' => $this->cashBankCoa->id,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->user)->get(route('pembelian.payments.show', $payment->id));

        $response->assertStatus(200);
        $response->assertSee($paymentNumber);
        $response->assertSee('1. Draft Dibuat');
        $response->assertSee('2. Posting Buku Besar');
        $response->assertSee('Faktur yang Dilunasi');
    }

    public function test_draft_payment_can_be_edited_and_deleted(): void
    {
        $invoice = $this->createPostedInvoice(200000);
        $paymentNumber = 'VP-EDIT-' . uniqid();

        $payment = VendorPayment::create([
            'payment_number' => $paymentNumber,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'coa_id' => $this->cashBankCoa->id,
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [$invoice->id => 200000],
            'total_payment' => 200000,
            'status' => VendorPayment::STATUS_DRAFT,
        ]);

        // Edit page loads
        $response = $this->actingAs($this->user)->get(route('pembelian.payments.edit', $payment->id));
        $response->assertStatus(200);

        // Delete draft payment
        $delResponse = $this->actingAs($this->user)->delete(route('pembelian.payments.destroy', $payment->id));
        $delResponse->assertRedirect(route('pembelian.payments.index'));

        $this->assertSoftDeleted('vendor_payments', ['id' => $payment->id]);
    }

    public function test_posted_payment_cannot_be_deleted_directly(): void
    {
        $invoice = $this->createPostedInvoice(350000);
        $paymentNumber = 'VP-NODEL-' . uniqid();

        $payment = VendorPayment::create([
            'payment_number' => $paymentNumber,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'coa_id' => $this->cashBankCoa->id,
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [$invoice->id => 350000],
            'total_payment' => 350000,
            'status' => VendorPayment::STATUS_PAID,
        ]);

        $response = $this->actingAs($this->user)->delete(route('pembelian.payments.destroy', $payment->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('vendor_payments', ['id' => $payment->id, 'deleted_at' => null]);
    }

    public function test_can_cancel_posted_payment_with_ap_and_gl_reversal(): void
    {
        $invoice = $this->createPostedInvoice(500000);
        $cancelRef = 'REF-CANCEL-' . uniqid();

        $this->actingAs($this->user)->post(route('pembelian.payments.store'), [
            'action' => 'save_and_post',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'coa_id' => $this->cashBankCoa->id,
            'transfer_reference_number' => $cancelRef,
            'selected_invoices' => [$invoice->id],
            'allocations' => [
                $invoice->id => 500000,
            ],
        ]);

        $payment = VendorPayment::where('transfer_reference_number', $cancelRef)->first();
        $this->assertEquals(VendorPayment::STATUS_PAID, $payment->status);

        // Cancel payment
        $response = $this->actingAs($this->user)->post(route('pembelian.payments.cancel', $payment->id), [
            'cancel_reason' => 'Salah rekening tujuan transfer vendor',
        ]);

        $response->assertRedirect();

        $payment->refresh();
        $this->assertEquals(VendorPayment::STATUS_CANCELLED, $payment->status);

        // AP saldo harus kembali ke 500.000 (belum lunas)
        $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals(500000, (float)$ap->remaining);
        $this->assertEquals(0, (float)$ap->paid);

        // Jurnal GL harus bersih
        $this->assertFalse($payment->journalEntries()->exists());
    }

    public function test_stream_vendor_payment_pdf(): void
    {
        $invoice = $this->createPostedInvoice(150000);
        $paymentNumber = 'VP-PDF-' . uniqid();

        $payment = VendorPayment::create([
            'payment_number' => $paymentNumber,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Bank Transfer',
            'coa_id' => $this->cashBankCoa->id,
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [$invoice->id => 150000],
            'total_payment' => 150000,
            'status' => VendorPayment::STATUS_PAID,
        ]);

        $response = $this->actingAs($this->user)->get(route('pembelian.payments.pdf', $payment->id));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
