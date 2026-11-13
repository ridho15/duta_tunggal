<?php

namespace Tests\Feature\Finance;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JournalSourceDocumentLinksTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Cabang $cabang;

    protected ChartOfAccount $cashCoa;

    protected ChartOfAccount $bankCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-JRN-SRC',
            'nama' => 'Cabang Jurnal Source Test',
            'alamat' => 'Jl. Jurnal No. 5',
            'telepon' => '021-123456',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = ['view any journal entry', 'view journal entry'];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->user->assignRole('Super Admin');

        $this->cashCoa = ChartOfAccount::create([
            'code' => '1111.01',
            'name' => 'Kas Operasional',
            'type' => 'Asset',
            'is_active' => true,
        ]);
        $this->bankCoa = ChartOfAccount::create([
            'code' => '1112.01',
            'name' => 'Bank Mandiri',
            'type' => 'Asset',
            'is_active' => true,
        ]);
    }

    private function createJournalVoucher(string $ref, ?string $sourceType = null, ?int $sourceId = null): void
    {
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Test Voucher '.$ref,
            'debit' => 100000,
            'credit' => 0,
            'journal_type' => $sourceType ? 'auto' : 'manual',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'cabang_id' => $this->cabang->id,
        ]);
        JournalEntry::create([
            'coa_id' => $this->bankCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Test Voucher '.$ref,
            'debit' => 0,
            'credit' => 100000,
            'journal_type' => $sourceType ? 'auto' : 'manual',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'cabang_id' => $this->cabang->id,
        ]);
    }

    public function test_manual_journal_shows_manual_text(): void
    {
        $ref = 'JV-MANUAL-'.uniqid();
        $this->createJournalVoucher($ref, null, null);

        $response = $this->actingAs($this->user)->get(route('keuangan.journal.show', $ref));

        $response->assertOk();
        $response->assertSee('Jurnal Input Manual / Penyesuaian Akuntansi');
        $response->assertDontSee('Buka Dokumen Sumber');
    }

    public function test_purchase_invoice_journal_links_to_pembelian_invoices_show(): void
    {
        $supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-LINK-'.uniqid(),
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'PI-TEST-'.uniqid(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'subtotal' => 100000,
            'total' => 100000,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
        ]);

        $ref = 'JV-PI-'.uniqid();
        $this->createJournalVoucher($ref, Invoice::class, $invoice->id);

        $response = $this->actingAs($this->user)->get(route('keuangan.journal.show', $ref));

        $response->assertOk();
        $response->assertSee('Faktur Pembelian');
        $response->assertSee($invoice->invoice_number);
        $response->assertSee(route('pembelian.invoices.show', $invoice->id));
        $response->assertSee('Buka Dokumen Sumber');
    }

    public function test_sales_invoice_journal_links_to_penjualan_invoices_show(): void
    {
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $so = \App\Models\SaleOrder::create([
            'so_number' => 'SO-LINK-'.uniqid(),
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'SI-TEST-'.uniqid(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'from_model_type' => \App\Models\SaleOrder::class,
            'from_model_id' => $so->id,
            'subtotal' => 100000,
            'total' => 100000,
            'customer_name' => $customer->name,
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
        ]);

        $ref = 'JV-SI-'.uniqid();
        $this->createJournalVoucher($ref, Invoice::class, $invoice->id);

        $response = $this->actingAs($this->user)->get(route('keuangan.journal.show', $ref));

        $response->assertOk();
        $response->assertSee('Faktur Penjualan');
        $response->assertSee($invoice->invoice_number);
        $response->assertSee(route('penjualan.invoices.show', $invoice->id));
        $response->assertSee('Buka Dokumen Sumber');
    }

    public function test_purchase_order_journal_links_to_pembelian_po_show(): void
    {
        $supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-'.uniqid(),
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $ref = 'JV-PO-'.uniqid();
        $this->createJournalVoucher($ref, PurchaseOrder::class, $po->id);

        $response = $this->actingAs($this->user)->get(route('keuangan.journal.show', $ref));

        $response->assertOk();
        $response->assertSee('Pesanan Pembelian (PO)');
        $response->assertSee($po->po_number);
        $response->assertSee(route('pembelian.po.show', $po->id));
        $response->assertSee('Buka Dokumen Sumber');
    }

    public function test_delivery_order_journal_links_to_penjualan_do_show(): void
    {
        $do = DeliveryOrder::create([
            'do_number' => 'DO-TEST-'.uniqid(),
            'delivery_date' => now(),
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $ref = 'JV-DO-'.uniqid();
        $this->createJournalVoucher($ref, DeliveryOrder::class, $do->id);

        $response = $this->actingAs($this->user)->get(route('keuangan.journal.show', $ref));

        $response->assertOk();
        $response->assertSee('Delivery Order (DO)');
        $response->assertSee($do->do_number);
        $response->assertSee(route('penjualan.do.show', $do->id));
        $response->assertSee('Buka Dokumen Sumber');
    }
}
