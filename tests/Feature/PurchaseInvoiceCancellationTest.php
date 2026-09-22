<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseInvoiceResource\Pages\ViewPurchaseInvoice;
use App\Filament\Resources\VendorPaymentResource;
use App\Http\Controllers\HelperController;
use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PaymentRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPaymentDetail;
use App\Services\PurchaseInvoiceCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pembatalan invoice pembelian yang sudah diposting: jurnal dibalik, hutang dikeluarkan, status Dibatalkan,
 * dan invoice yang sudah dibayar / masih tercakup Permintaan Pembayaran aktif ditolak. Ditambah jadwal
 * dan perilaku invoices:check-overdue.
 */
class PurchaseInvoiceCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Cabang $cabang;
    private PurchaseInvoiceCancellationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (HelperController::listPermission() as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => sprintf('%s %s', $action, $resource), 'guard_name' => 'web']);
            }
        }

        $this->user = User::factory()->create(['manage_type' => 'all']);
        $this->user->givePermissionTo(['view any invoice', 'view invoice', 'delete invoice']);
        $this->actingAs($this->user);

        $this->cabang = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create(['perusahaan' => 'CV Uji Batal']);
        $this->service = app(PurchaseInvoiceCancellationService::class);
    }

    /**
     * Invoice pembelian yang sudah diposting: AP + 3 baris jurnal seimbang (persis bentuk hasil Posting Invoice).
     * Dibuat tanpa observer supaya jurnal/AP yang diuji adalah yang dibuat fixture ini.
     */
    private function postedInvoice(array $overrides = [], float $paid = 0.0): Invoice
    {
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'cabang_id' => $this->cabang->id]);

        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create($overrides + [
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'subtotal' => 25000,
            'total' => 25325,
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]));

        AccountPayable::withoutEvents(fn () => AccountPayable::factory()->create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 25325,
            'paid' => $paid,
            'remaining' => 25325 - $paid,
            'status' => 'Belum Lunas',
            'created_by' => $this->user->id,
        ]));

        $coa = fn (string $code, string $name) => ChartOfAccount::where('code', $code)->first()
            ?? ChartOfAccount::factory()->create(['code' => $code, 'name' => $name]);
        $unbilled = $coa('2100.10', 'Penerimaan Barang Belum Tertagih');
        $ppn = $coa('1170.06', 'PPN Masukan');
        $ap = $coa('2110', 'Hutang Dagang');

        foreach ([[$unbilled, 25000, 0], [$ppn, 325, 0], [$ap, 0, 25325]] as [$coa, $debit, $credit]) {
            JournalEntry::factory()->create([
                'coa_id' => $coa->id,
                'date' => $invoice->invoice_date,
                'reference' => $invoice->invoice_number,
                'description' => 'Posting ' . $invoice->invoice_number,
                'debit' => $debit,
                'credit' => $credit,
                'cabang_id' => $this->cabang->id,
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
                'is_reversal' => false,
                'reversal_of_transaction_id' => null,
            ]);
        }

        return $invoice->fresh();
    }

    public function test_cancel_reverses_journals_removes_payable_and_records_who_when_and_why(): void
    {
        $invoice = $this->postedInvoice();

        $cancelled = $this->service->cancel($invoice, 'Salah input harga', now()->toDateString(), $this->user->id);

        $this->assertSame(Invoice::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('Salah input harga', $cancelled->cancel_reason);
        $this->assertSame($this->user->id, (int) $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);

        $originals = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->where('is_reversal', false)->get();
        $reversals = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->where('is_reversal', true)->get();

        $this->assertCount(3, $originals);
        $this->assertCount(3, $reversals);
        $this->assertTrue($originals->every(fn ($entry) => filled($entry->reversal_of_transaction_id)), 'Jurnal asli harus bertanda sudah dibalik');

        foreach ($originals as $original) {
            $mirror = $reversals->firstWhere('coa_id', $original->coa_id);
            $this->assertEqualsWithDelta((float) $original->debit, (float) $mirror->credit, 0.001);
            $this->assertEqualsWithDelta((float) $original->credit, (float) $mirror->debit, 0.001);
        }

        $all = $originals->concat($reversals);
        $this->assertEqualsWithDelta(0.0, (float) $all->sum('debit') - (float) $all->sum('credit'), 0.001);
        $this->assertEqualsWithDelta(
            0.0,
            (float) $all->groupBy('coa_id')->map(fn ($rows) => abs($rows->sum('debit') - $rows->sum('credit')))->sum(),
            0.001,
            'Saldo bersih tiap akun harus nol'
        );

        $this->assertNull(AccountPayable::where('invoice_id', $invoice->id)->first(), 'Hutang usaha harus dikeluarkan');
        $this->assertSoftDeleted('account_payables', ['invoice_id' => $invoice->id]);
    }

    public function test_invoices_that_cannot_be_cancelled_are_rejected_without_any_change(): void
    {
        $draft = $this->postedInvoice(['status' => Invoice::STATUS_DRAFT]);
        $paidInvoice = $this->postedInvoice(['status' => Invoice::STATUS_PAID]);
        $withAp = $this->postedInvoice(['status' => Invoice::STATUS_PARTIALLY_PAID], paid: 10000);
        $withDetail = $this->postedInvoice();
        VendorPaymentDetail::withoutEvents(fn () => VendorPaymentDetail::factory()->create([
            'invoice_id' => $withDetail->id,
            'method' => 'Bank Transfer',
            'amount' => 5000,
            'amount_idr' => 5000,
        ]));
        $inRequest = $this->postedInvoice();
        PaymentRequest::factory()->create([
            'supplier_id' => $this->supplier->id,
            'selected_invoices' => [$inRequest->id],
            'total_amount' => 25325,
            'status' => PaymentRequest::STATUS_PENDING,
            'request_number' => 'PAY-REQ-UJI-0001',
        ]);
        $cancelledAlready = $this->service->cancel($this->postedInvoice(), 'Batal pertama', null, $this->user->id);

        $expectations = [
            'draft' => [$draft, 'Gunakan Hapus'],
            'lunas' => [$paidInvoice, 'pembayaran vendor'],
            'AP terbayar' => [$withAp, 'pembayaran vendor'],
            'detail pembayaran' => [$withDetail, 'pembayaran vendor'],
            'PR aktif' => [$inRequest, 'PAY-REQ-UJI-0001'],
            'sudah batal' => [$cancelledAlready, 'sudah dibatalkan'],
        ];

        foreach ($expectations as $label => [$invoice, $messagePart]) {
            $journalCount = JournalEntry::where('source_id', $invoice->id)->count();

            try {
                $this->service->cancel($invoice, 'Coba batal', null, $this->user->id);
                $this->fail("Invoice '{$label}' seharusnya ditolak.");
            } catch (\DomainException $exception) {
                $this->assertStringContainsString($messagePart, $exception->getMessage(), $label);
            }

            $this->assertSame($journalCount, JournalEntry::where('source_id', $invoice->id)->count(), "{$label}: jurnal tidak boleh berubah");
            $this->assertSame($invoice->fresh()->status, $invoice->status, "{$label}: status tidak boleh berubah");
        }
    }

    public function test_reason_is_required_and_reversal_date_cannot_precede_the_invoice_date(): void
    {
        $invoice = $this->postedInvoice();

        try {
            $this->service->cancel($invoice, '   ');
            $this->fail('Alasan kosong seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        try {
            $this->service->cancel($invoice, 'Salah input', now()->subDays(10)->toDateString());
            $this->fail('Tanggal sebelum tanggal invoice seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reversal_date', $exception->errors());
        }

        $this->assertSame(Invoice::STATUS_SENT, $invoice->fresh()->status);
        $this->assertSame(0, JournalEntry::where('source_id', $invoice->id)->where('is_reversal', true)->count());
    }

    public function test_cancelled_invoice_is_never_payable(): void
    {
        $invoice = $this->service->cancel($this->postedInvoice(), 'Batal uji', null, $this->user->id);

        $this->assertSame(0.0, PaymentRequest::getInvoiceRemainingPayable($invoice));
        $this->assertSame(0.0, VendorPaymentResource::resolveInvoiceRemainingAmount($invoice));
    }

    public function test_view_page_action_cancels_a_posted_invoice(): void
    {
        $invoice = $this->postedInvoice();

        Livewire::test(ViewPurchaseInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionVisible('cancel_invoice')
            ->callAction('cancel_invoice', ['reversal_date' => now()->toDateString(), 'reason' => 'Faktur supplier salah'])
            ->assertNotified('Invoice Dibatalkan');

        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->fresh()->status);

        // Halaman lihat menampilkan siapa, kapan, dan mengapa invoice dibatalkan.
        $this->get(\App\Filament\Resources\PurchaseInvoiceResource::getUrl('view', ['record' => $invoice]))
            ->assertOk()
            ->assertSee('Dibatalkan')
            ->assertSee('Faktur supplier salah')
            ->assertSee($this->user->name);
    }

    public function test_view_page_action_reports_why_an_invoice_cannot_be_cancelled(): void
    {
        $invoice = $this->postedInvoice(['status' => Invoice::STATUS_PARTIALLY_PAID], paid: 10000);

        Livewire::test(ViewPurchaseInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionVisible('cancel_invoice')
            ->callAction('cancel_invoice', ['reversal_date' => now()->toDateString(), 'reason' => 'Coba batal'])
            ->assertNotified('Invoice Belum Dapat Dibatalkan');

        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->fresh()->status);
    }

    public function test_view_page_action_is_hidden_for_drafts_cancelled_invoices_and_users_without_permission(): void
    {
        $draft = $this->postedInvoice(['status' => Invoice::STATUS_DRAFT]);
        $cancelled = $this->service->cancel($this->postedInvoice(), 'Batal uji', null, $this->user->id);

        Livewire::test(ViewPurchaseInvoice::class, ['record' => $draft->getRouteKey()])->assertActionHidden('cancel_invoice');
        Livewire::test(ViewPurchaseInvoice::class, ['record' => $cancelled->getRouteKey()])->assertActionHidden('cancel_invoice');

        $viewer = User::factory()->create(['manage_type' => 'all']);
        $viewer->givePermissionTo(['view any invoice', 'view invoice']);
        $posted = $this->postedInvoice();

        $this->actingAs($viewer);
        Livewire::test(ViewPurchaseInvoice::class, ['record' => $posted->getRouteKey()])->assertActionHidden('cancel_invoice');
    }

    public function test_overdue_check_is_scheduled_daily(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('invoices:check-overdue', $output);
    }

    public function test_overdue_check_marks_restores_and_skips_the_right_invoices(): void
    {
        $late = $this->postedInvoice(['due_date' => now()->subDays(5)->toDateString()]);
        $notDue = $this->postedInvoice(['due_date' => now()->addDays(5)->toDateString()]);
        $draft = $this->postedInvoice(['status' => Invoice::STATUS_DRAFT, 'due_date' => now()->subDays(5)->toDateString()]);
        $paidInvoice = $this->postedInvoice(['status' => Invoice::STATUS_PAID, 'due_date' => now()->subDays(5)->toDateString()]);
        $cancelled = $this->postedInvoice(['due_date' => now()->subDays(5)->toDateString()]);
        $cancelled = $this->service->cancel($cancelled, 'Batal uji', null, $this->user->id);
        $extended = $this->postedInvoice(['status' => Invoice::STATUS_OVERDUE, 'due_date' => now()->addDays(10)->toDateString()]);

        Artisan::call('invoices:check-overdue', ['--dry-run' => true]);
        $this->assertSame(Invoice::STATUS_SENT, $late->fresh()->status, 'Dry-run tidak boleh mengubah data');

        Artisan::call('invoices:check-overdue');

        $this->assertSame(Invoice::STATUS_OVERDUE, $late->fresh()->status);
        $this->assertSame(Invoice::STATUS_SENT, $notDue->fresh()->status);
        $this->assertSame(Invoice::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $paidInvoice->fresh()->status);
        $this->assertSame(Invoice::STATUS_CANCELLED, $cancelled->fresh()->status);
        $this->assertSame(Invoice::STATUS_SENT, $extended->fresh()->status, 'Tempo diperpanjang: status Terlambat dipulihkan');
    }
}
