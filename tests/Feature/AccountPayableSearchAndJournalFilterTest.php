<?php

namespace Tests\Feature;

use App\Filament\Resources\AccountPayableResource\Pages\ListAccountPayables;
use App\Filament\Resources\PurchaseInvoiceResource\Pages\ViewPurchaseInvoice;
use App\Models\AccountPayable;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dua bug ditemukan saat UAT (22/09/2026), keduanya pada halaman terkait invoice pembelian:
 *
 * 1. Tombol "Lihat Journal Entries" di Invoice memakai kunci filter 'tableFilters[source_id][value]', padahal
 *    filter Source ID adalah Filter::make() dengan field form 'source_id' (bukan SelectFilter yang memang memakai
 *    'value'), sehingga filternya tidak pernah terpasang dan daftar Journal Entries menampilkan jurnal SEMUA
 *    invoice, bukan invoice yang dibuka. Bug yang sama disalin ke 11 resource lain (Vendor Payment, Sales Invoice,
 *    Deposit, dst).
 * 2. Pencarian di halaman Utang Usaha (Account Payable) mengakibatkan error 500:
 *    - Kolom 'total' ada di tabel account_payables maupun invoices yang digabung (leftJoin), sehingga tanpa
 *      kualifikasi nama tabel, pencarian jadi "Column 'total' ... is ambiguous".
 *    - Kolom 'invoice.fromModel.po_number' melalui relasi morphTo memicu Eloquent meng-OR-kan EXISTS untuk semua
 *      tipe morph yang pernah dipakai (termasuk SaleOrder, yang tidak punya kolom po_number), sehingga error
 *      "Unknown column 'po_number'".
 */
class AccountPayableSearchAndJournalFilterTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;
    private PurchaseOrder $purchaseOrder;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([
            'view any invoice', 'view invoice',
            'view any account payable', 'view account payable',
            'view any journal entry', 'view journal entry',
        ] as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $user = User::factory()->create(['manage_type' => 'all']);
        $user->givePermissionTo([
            'view any invoice', 'view invoice',
            'view any account payable', 'view account payable',
            'view any journal entry', 'view journal entry',
        ]);
        $this->actingAs($user);

        Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
        $supplier = Supplier::factory()->create();

        $this->purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEST-JOURNAL-001',
            'status' => 'completed',
        ]);

        $this->invoice = Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PINV-TEST-JOURNAL-001',
            'status' => Invoice::STATUS_SENT,
            'total' => 111000,
        ]);

        // Invoice lain: jurnalnya TIDAK boleh ikut tampil saat memfilter jurnal invoice di atas.
        $otherInvoice = Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PINV-TEST-JOURNAL-999',
            'status' => Invoice::STATUS_SENT,
            'total' => 999000,
        ]);

        JournalEntry::factory()->count(2)->create([
            'source_type' => Invoice::class,
            'source_id' => $this->invoice->id,
        ]);
        JournalEntry::factory()->count(3)->create([
            'source_type' => Invoice::class,
            'source_id' => $otherInvoice->id,
        ]);

        AccountPayable::create([
            'invoice_id' => $this->invoice->id,
            'supplier_id' => $supplier->id,
            'total' => 111000,
            'paid' => 0,
            'remaining' => 111000,
            'status' => 'Belum Lunas',
        ]);
    }

    public function test_lihat_journal_entries_link_uses_the_correct_source_id_filter_key(): void
    {
        // Filter 'source_id' adalah Filter::make() dengan field form 'source_id' (bukan SelectFilter), jadi
        // kuncinya harus 'source_id', bukan 'value' (itu hanya berlaku untuk SelectFilter 'source_type').
        Livewire::test(ViewPurchaseInvoice::class, ['record' => $this->invoice->getRouteKey()])
            ->callAction('view_journal_entries')
            ->assertRedirectContains('tableFilters[source_id][source_id]='.$this->invoice->id)
            ->assertRedirectContains('tableFilters[source_type][value]=');
    }

    public function test_journal_entries_source_id_filter_shows_only_that_invoices_journals(): void
    {
        \Livewire\Livewire::test(\App\Filament\Resources\JournalEntryResource\Pages\ListJournalEntries::class)
            ->filterTable('source_type', Invoice::class)
            ->filterTable('source_id', ['source_id' => $this->invoice->id])
            ->assertCanSeeTableRecords(JournalEntry::where('source_id', $this->invoice->id)->get())
            ->assertCanNotSeeTableRecords(JournalEntry::where('source_id', '!=', $this->invoice->id)->get());
    }

    public function test_account_payable_search_by_amount_does_not_crash_and_finds_the_row(): void
    {
        Livewire::test(ListAccountPayables::class)
            ->searchTable('111000')
            ->assertSuccessful()
            ->assertCanSeeTableRecords(AccountPayable::where('invoice_id', $this->invoice->id)->get());
    }

    public function test_account_payable_search_by_invoice_number_does_not_crash(): void
    {
        Livewire::test(ListAccountPayables::class)
            ->searchTable('PINV-TEST-JOURNAL-001')
            ->assertSuccessful()
            ->assertCanSeeTableRecords(AccountPayable::where('invoice_id', $this->invoice->id)->get());
    }

    public function test_account_payable_search_by_po_number_does_not_crash(): void
    {
        Livewire::test(ListAccountPayables::class)
            ->searchTable('PO-TEST-JOURNAL-001')
            ->assertSuccessful()
            ->assertCanSeeTableRecords(AccountPayable::where('invoice_id', $this->invoice->id)->get());
    }
}
