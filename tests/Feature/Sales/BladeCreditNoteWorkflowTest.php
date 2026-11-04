<?php

namespace Tests\Feature\Sales;

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CreditNoteService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeCreditNoteWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected SaleOrder $saleOrder;
    protected Invoice $invoice;
    protected InvoiceItem $invoiceItem;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sales.controls.credit_notes' => true]);

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-SBY',
            'nama' => 'Cabang Surabaya',
            'alamat' => 'Jl. Pemuda No. 1, Surabaya',
            'telepon' => '031-5551234',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $perms = [
            'view any credit note',
            'view credit note',
            'create credit note',
            'update credit note',
            'delete credit note',
            'approve credit note',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

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

        $uom = UnitOfMeasure::withoutGlobalScopes()->first() ?? UnitOfMeasure::create([
            'name' => 'Pcs',
            'abbreviation' => 'pcs',
        ]);

        $category = ProductCategory::withoutGlobalScopes()->first() ?? ProductCategory::create([
            'name' => 'Fitting Tembaga',
            'code' => 'FT-CU',
        ]);

        $this->product = Product::withoutGlobalScopes()->first() ?? Product::create([
            'name' => 'COPPER ELBO 5/8',
            'code' => 'ELB-058',
            'unit' => 'Pcs',
            'uom_id' => $uom->id,
            'category_id' => $category->id,
            'cost_price' => 50000,
            'price' => 70000,
            'status' => true,
        ]);

        $this->saleOrder = SaleOrder::withoutGlobalScopes()->first() ?? SaleOrder::create([
            'so_number' => 'SO-TEST-' . uniqid(),
            'order_date' => now(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => SaleOrder::STATUS_APPROVED,
            'total_amount' => 700000,
            'created_by' => $this->user->id,
        ]);

        // Buat Invoice Terbit
        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-CN-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'customer_name' => $this->customer->name,
            'customer_address' => $this->customer->address,
            'status' => Invoice::STATUS_SENT,
            'subtotal' => 700000,
            'tax_amount' => 77000,
            'other_fee' => [['name' => 'Ongkir', 'amount' => 25000]],
            'total' => 802000,
            'cabang_id' => $this->cabang->id,
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
        ]);

        $this->invoiceItem = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 70000,
            'cost_price' => 50000,
            'subtotal' => 700000,
            'tax_amount' => 77000,
            'total' => 777000,
        ]);

        // Piutang Usaha
        AccountReceivable::firstOrCreate(
            ['invoice_id' => $this->invoice->id],
            [
                'customer_id' => $this->customer->id,
                'total' => 802000,
                'paid' => 0,
                'remaining' => 802000,
                'status' => 'Belum Lunas',
            ]
        );
    }

    public function test_credit_notes_index_page_loads_and_displays_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.credit-notes.index'));

        $response->assertStatus(200);
        $response->assertViewIs('penjualan.credit-notes.index');
        $response->assertViewHasAll(['creditNotes', 'tab', 'kpis', 'cabangs']);
        $response->assertSee('Nota Kredit (Credit Note)');
        $response->assertSee('Total Dokumen');
        $response->assertSee('Draf Menunggu Terbit');
        $response->assertSee('Kredit Diterbitkan');
    }

    public function test_credit_notes_create_page_loads_with_customers(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.credit-notes.create'));

        $response->assertStatus(200);
        $response->assertViewIs('penjualan.credit-notes.create');
        $response->assertViewHas('customers');
        $response->assertSee('Buat Nota Kredit (Draf)');
        $response->assertSee('Customer (Pelanggan)');
    }

    public function test_customer_invoices_api_returns_creditable_invoices(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            route('penjualan.credit-notes.customerInvoices', $this->customer->id)
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonFragment([
            'id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
        ]);
    }

    public function test_invoice_details_api_returns_items_and_creditable_quantities(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            route('penjualan.credit-notes.invoiceDetails', $this->invoice->id)
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'invoice' => [
                'id',
                'invoice_number',
                'total',
                'ar_remaining',
                'creditable_shipping',
            ],
            'items' => [
                '*' => [
                    'id',
                    'product_name',
                    'original_qty',
                    'creditable_qty',
                    'unit_price',
                    'subtotal',
                    'tax_amount',
                    'total',
                ],
            ],
        ]);
    }

    public function test_can_create_draft_credit_note_and_validates_short_reason(): void
    {
        // 1. Alasan kurang dari 10 karakter -> gagal validasi
        $invalidResponse = $this->actingAs($this->user)->post(route('penjualan.credit-notes.store'), [
            'invoice_id' => $this->invoice->id,
            'type' => CreditNote::TYPE_CORRECTION,
            'reason' => 'Pendek',
            'quantities' => [
                $this->invoiceItem->id => 2,
            ],
        ]);

        $invalidResponse->assertSessionHasErrors('reason');

        // 2. Alasan valid -> sukses membuat draf
        $validResponse = $this->actingAs($this->user)->post(route('penjualan.credit-notes.store'), [
            'invoice_id' => $this->invoice->id,
            'type' => CreditNote::TYPE_CORRECTION,
            'reason' => 'Koreksi harga kesepakatan diskon tambahan untuk pelanggan',
            'credit_date' => now()->toDateString(),
            'quantities' => [
                $this->invoiceItem->id => 2,
            ],
        ]);

        $cn = CreditNote::where('invoice_id', $this->invoice->id)->latest('id')->first();
        $this->assertNotNull($cn);
        $this->assertEquals(CreditNote::STATUS_DRAFT, $cn->status);
        $this->assertEquals(CreditNote::TYPE_CORRECTION, $cn->type);
        $this->assertEquals(2, $cn->items()->where('invoice_item_id', $this->invoiceItem->id)->value('quantity'));

        $validResponse->assertRedirect(route('penjualan.credit-notes.show', $cn->id));
    }

    public function test_cannot_credit_quantity_exceeding_creditable_quantity(): void
    {
        $response = $this->actingAs($this->user)->post(route('penjualan.credit-notes.store'), [
            'invoice_id' => $this->invoice->id,
            'type' => CreditNote::TYPE_CORRECTION,
            'reason' => 'Mencoba mengkreditkan kuantitas melebihi sisa invoice',
            'quantities' => [
                $this->invoiceItem->id => 50, // melebihi kuantitas invoice (10)
            ],
        ]);

        $response->assertSessionHasErrors('quantities');
    }

    public function test_can_create_full_cancellation_credit_note(): void
    {
        // Buat invoice terpisah untuk pembatalan penuh
        $cancelInvoice = Invoice::create([
            'invoice_number' => 'INV-TEST-CANCEL-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'customer_name' => $this->customer->name,
            'status' => Invoice::STATUS_SENT,
            'subtotal' => 140000,
            'tax_amount' => 15400,
            'other_fee' => [['name' => 'Ongkir', 'amount' => 10000]],
            'total' => 165400,
            'cabang_id' => $this->cabang->id,
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
        ]);

        $cancelItem = InvoiceItem::create([
            'invoice_id' => $cancelInvoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 70000,
            'cost_price' => 50000,
            'subtotal' => 140000,
            'tax_amount' => 15400,
            'total' => 155400,
        ]);

        AccountReceivable::firstOrCreate(
            ['invoice_id' => $cancelInvoice->id],
            [
                'customer_id' => $this->customer->id,
                'total' => 165400,
                'paid' => 0,
                'remaining' => 165400,
                'status' => 'Belum Lunas',
            ]
        );

        $response = $this->actingAs($this->user)->post(route('penjualan.credit-notes.store'), [
            'invoice_id' => $cancelInvoice->id,
            'type' => CreditNote::TYPE_CANCELLATION,
            'reason' => 'Invoice keliru nominal dan alamat kirim, dibatalkan penuh',
        ]);

        $cn = CreditNote::where('invoice_id', $cancelInvoice->id)->first();
        $this->assertNotNull($cn);
        $this->assertEquals(CreditNote::TYPE_CANCELLATION, $cn->type);
        $this->assertEquals(CreditNote::STATUS_DRAFT, $cn->status);
        $this->assertEquals(165400.0, (float) $cn->total);

        $response->assertRedirect(route('penjualan.credit-notes.show', $cn->id));
    }

    public function test_can_create_credit_note_from_customer_return(): void
    {
        // Buat customer return dengan item berkeputusan 'credit'
        $customerReturn = CustomerReturn::create([
            'return_number' => 'CR-TEST-' . uniqid(),
            'return_date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => CustomerReturn::STATUS_APPROVED,
            'reason' => 'Barang rusak tidak dapat diperbaiki, minta refund nota kredit',
            'created_by' => $this->user->id,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        CustomerReturnItem::create([
            'customer_return_id' => $customerReturn->id,
            'product_id' => $this->product->id,
            'invoice_item_id' => $this->invoiceItem->id,
            'quantity' => 1,
            'problem_description' => 'Pipa bocor saat pemasangan',
            'qc_result' => CustomerReturnItem::QC_RESULT_PASS,
            'decision' => CustomerReturnItem::DECISION_CREDIT,
        ]);

        $response = $this->actingAs($this->user)->post(
            route('penjualan.credit-notes.fromReturn', $customerReturn->id)
        );

        $cn = CreditNote::where('customer_return_id', $customerReturn->id)->first();
        $this->assertNotNull($cn);
        $this->assertEquals(CreditNote::TYPE_RETURN, $cn->type);
        $this->assertEquals(CreditNote::STATUS_DRAFT, $cn->status);
        $this->assertEquals(1, $cn->items()->where('invoice_item_id', $this->invoiceItem->id)->value('quantity'));

        $response->assertRedirect(route('penjualan.credit-notes.show', $cn->id));
    }

    public function test_issue_credit_note_applies_to_ar_and_posts_balanced_journals(): void
    {
        // 1. Buat Draf Nota Kredit
        $cn = app(CreditNoteService::class)->draft(
            $this->invoice,
            CreditNote::TYPE_CORRECTION,
            [$this->invoiceItem->id => 2],
            'Koreksi diskon volume pembelian yang disepakati',
            actor: $this->user
        );

        $this->assertEquals(CreditNote::STATUS_DRAFT, $cn->status);

        // 2. Terbitkan Nota Kredit
        $response = $this->actingAs($this->user)->post(
            route('penjualan.credit-notes.issue', $cn->id),
            [
                'tax_document_number' => 'NR-2026-TEST-001',
            ]
        );

        $response->assertRedirect(route('penjualan.credit-notes.show', $cn->id));
        $cn->refresh();

        $this->assertEquals(CreditNote::STATUS_ISSUED, $cn->status);
        $this->assertNotNull($cn->issued_at);
        $this->assertEquals('NR-2026-TEST-001', $cn->tax_document_number);
        $this->assertGreaterThan(0, (float) $cn->applied_to_ar);

        // Cek jurnal akuntansi seimbang
        $journals = JournalEntry::where('source_type', CreditNote::class)
            ->where('source_id', $cn->id)
            ->get();

        $this->assertNotEmpty($journals);
        $totalDebit = (float) $journals->sum('debit');
        $totalCredit = (float) $journals->sum('credit');

        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.05, 'Jurnal Nota Kredit harus seimbang Debit == Kredit');
    }

    public function test_can_delete_draft_credit_note_but_cannot_delete_issued(): void
    {
        // 1. Draf dapat dihapus
        $draftCn = app(CreditNoteService::class)->draft(
            $this->invoice,
            CreditNote::TYPE_CORRECTION,
            [$this->invoiceItem->id => 1],
            'Draf nota kredit yang akan dibatalkan/dihapus',
            actor: $this->user
        );

        $deleteResponse = $this->actingAs($this->user)->delete(
            route('penjualan.credit-notes.destroy', $draftCn->id)
        );

        $deleteResponse->assertRedirect(route('penjualan.credit-notes.index'));
        $this->assertNull(CreditNote::find($draftCn->id));

        // 2. Nota kredit yang sudah terbit tidak dapat dihapus
        $issuedCn = app(CreditNoteService::class)->issue(
            app(CreditNoteService::class)->draft(
                $this->invoice,
                CreditNote::TYPE_CORRECTION,
                [$this->invoiceItem->id => 1],
                'Nota kredit resmi yang akan difinalisasi',
                actor: $this->user
            ),
            $this->user
        );

        $deleteIssuedResponse = $this->actingAs($this->user)->delete(
            route('penjualan.credit-notes.destroy', $issuedCn->id)
        );

        $deleteIssuedResponse->assertSessionHas('error');
        $this->assertNotNull(CreditNote::find($issuedCn->id));
    }

    public function test_stream_credit_note_pdf(): void
    {
        $cn = app(CreditNoteService::class)->draft(
            $this->invoice,
            CreditNote::TYPE_CORRECTION,
            [$this->invoiceItem->id => 1],
            'Pengujian streaming dokumen PDF Nota Kredit',
            actor: $this->user
        );

        $response = $this->actingAs($this->user)->get(route('penjualan.credit-notes.pdf', $cn->id));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }
}
