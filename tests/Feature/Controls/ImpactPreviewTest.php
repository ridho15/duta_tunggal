<?php

/**
 * T7.3 — Pratinjau dampak (preview = eksekusi, usulan 16), pola aksi seragam (D13, usulan 14), dan penjaga klik ganda (usulan 19).
 * Pratinjau dibandingkan dengan HASIL NYATA eksekusi; aksi utama terlihat tanpa membuka menu, sekunder di "Lainnya".
 */

use App\Filament\Resources\CreditNoteResource\Pages\ViewCreditNote;
use App\Filament\Resources\CustomerReceiptResource\Pages\ViewCustomerReceipt;
use App\Filament\Resources\CustomerReturnResource\Pages\ViewCustomerReturn;
use App\Filament\Resources\SalesInvoiceResource\Pages\ViewSalesInvoice;
use App\Filament\Support\DocumentActions;
use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CustomerReturn;
use App\Models\Deposit;
use App\Models\JournalEntry;
use App\Services\CreditNoteService;
use App\Services\CustomerReceiptCancellation;
use App\Services\ImpactPreview;
use Filament\Actions\ActionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.controls.credit_notes' => true, 'sales.controls.doc_lock' => true]);
});

function ipUser(array $ctx, array $extra = []): \App\Models\User
{
    $user = ctlUser($ctx, 'Finance Manager', array_merge(['approve credit note', 'create credit note', 'view credit note', 'view any credit note', 'delete credit note',
        'view invoice', 'view any invoice', 'delete customer receipt', 'view customer receipt', 'view any customer receipt', 'view customer return', 'view any customer return'], $extra));
    Auth::login($user);

    return $user;
}

/** @return array{0: array<int, string>, 1: array<int, string>} nama aksi tingkat atas yang terlihat, dan nama aksi di dalam menu "Lainnya" */
function ipVisibleActions($component): array
{
    $top = [];
    $grouped = [];
    foreach ($component->instance()->getCachedHeaderActions() as $action) {
        if ($action instanceof ActionGroup) {
            foreach ($action->getFlatActions() as $child) {
                $grouped[] = $child->getName();
            }

            continue;
        }
        if ($action->isVisible()) {
            $top[] = $action->getName();
        }
    }

    return [$top, $grouped];
}

it('Nota Kredit (invoice belum dibayar): pratinjau = hasil nyata (piutang, status, jurnal)', function () {
    $ctx = stkContext();
    $user = ipUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $service = app(CreditNoteService::class);
    $cn = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer', actor: $user);

    $preview = app(ImpactPreview::class)->creditNoteIssue($cn);
    expect($preview['data']['apply_to_ar'])->toBe(333000.0)->and($preview['data']['to_deposit'])->toBe(0.0)->and($preview['warnings'])->toBe([])
        ->and(implode(' ', $preview['lines']))->toContain('Rp 333.000,00')->toContain('Jurnal 3 baris')->toContain('Dr Retur Penjualan')->toContain('Cr Piutang Dagang')->toContain('final');

    $service->issue($cn, $user);
    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    $entries = JournalEntry::where('source_type', CreditNote::class)->where('source_id', $cn->id)->get();

    expect((float) $ar->total)->toBe($preview['data']['ar_after']['total'])->and((float) $ar->paid)->toBe($preview['data']['ar_after']['paid'])->and((float) $ar->remaining)->toBe($preview['data']['ar_after']['remaining'])
        ->and($invoice->fresh()->status)->toBe('unpaid')->and($preview['data']['invoice_status_after'])->toBeNull()
        ->and($entries->count())->toBe(count($preview['data']['journal']))
        ->and((float) $entries->sum('debit'))->toBe((float) array_sum(array_column($preview['data']['journal'], 'debit')))
        ->and($entries->pluck('coa_id')->sort()->values()->all())->toBe(collect($preview['data']['journal'])->pluck('coa_id')->sort()->values()->all());
});

it('Nota Kredit (invoice lunas): pratinjau = Deposit yang benar-benar terbentuk; pembatalan penuh → pratinjau "Dibatalkan" = status nyata', function () {
    $ctx = stkContext();
    $user = ipUser($ctx);
    [$paidInvoice, $paidItem] = ctlCreditInvoice($ctx, status: 'paid');
    $service = app(CreditNoteService::class);
    $cn = $service->draft($paidInvoice, CreditNote::TYPE_RETURN, [$paidItem->id => 3], 'Barang rusak dikembalikan customer', actor: $user);

    $preview = app(ImpactPreview::class)->creditNoteIssue($cn);
    expect($preview['data']['to_deposit'])->toBe(333000.0)->and($preview['data']['apply_to_ar'])->toBe(0.0)->and(implode(' ', $preview['lines']))->toContain('menjadi Deposit Customer');
    $service->issue($cn, $user);
    expect((float) Deposit::where('from_model_id', $ctx['customer']->id)->first()->remaining_amount)->toBe($preview['data']['to_deposit']);

    [$invoice] = ctlCreditInvoice($ctx, shipping: 50000);
    $full = $service->draft($invoice, CreditNote::TYPE_CANCELLATION, [], 'Invoice salah nominal, dibatalkan penuh', actor: $user);
    $fullPreview = app(ImpactPreview::class)->creditNoteIssue($full);
    expect($fullPreview['data']['fully_credited'])->toBeTrue()->and(implode(' ', $fullPreview['lines']))->toContain('menjadi Dibatalkan');
    $service->issue($full, $user);
    expect($invoice->fresh()->status)->toBe($fullPreview['data']['invoice_status_after']);
});

it('Nota Kredit: akun belum diatur → pratinjau memberi PERINGATAN (tidak melempar); penerbitan sungguhan tetap ditolak', function () {
    $ctx = stkContext();
    $user = ipUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $cn = app(CreditNoteService::class)->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'Retur satu unit dari invoice', actor: $user);
    ChartOfAccount::where('code', '4120.10')->delete();

    $preview = app(ImpactPreview::class)->creditNoteIssue($cn);

    expect($preview['warnings'])->not->toBe([])->and(implode(' ', $preview['warnings']))->toContain('Retur Penjualan');
    expect(fn () => app(CreditNoteService::class)->issue($cn, $user))->toThrow(\RuntimeException::class);
});

it('modal "Terbitkan" menampilkan pratinjau dampak dengan angka yang sama', function () {
    $ctx = stkContext();
    $user = ipUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $cn = app(CreditNoteService::class)->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer', actor: $user);
    $issuer = ipUser($ctx);

    Livewire::actingAs($issuer)->test(ViewCreditNote::class, ['record' => $cn->id])->mountAction('issue')
        ->assertSee('Dampak penerbitan Nota Kredit')->assertSee('Rp 333.000,00')->assertSee('Piutang invoice '.$invoice->invoice_number)->assertSee('Dr Retur Penjualan');
});

it('Batalkan Penerimaan: pratinjau = hasil nyata (jurnal dibalik, piutang kembali, status invoice); tidak dapat dibatalkan dua kali', function () {
    $ctx = stkContext();
    $user = ipUser($ctx);
    [$invoice] = ctlCreditInvoice($ctx);
    [$receipt] = ctlPaidReceipt($ctx, $invoice, 1332000);

    $preview = app(ImpactPreview::class)->receiptCancellation($receipt);
    expect($preview['warnings'])->toBe([])->and($preview['data']['reversed_entries'])->toBe(2)->and($preview['data']['reversed_debit'])->toBe(1332000.0)
        ->and($preview['data']['restored'][$invoice->id]['amount'])->toBe(1332000.0)->and(implode(' ', $preview['lines']))->toContain('Rp 1.332.000,00')->toContain('Belum Dibayar');

    $service = app(CustomerReceiptCancellation::class);
    $service->cancel($receipt, 'Transfer ternyata salah rekening, dana dikembalikan', $user);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    $reversals = JournalEntry::where('source_type', \App\Models\CustomerReceipt::class)->where('source_id', $receipt->id)->where('is_reversal', true)->count();
    expect((float) $ar->remaining)->toBe($preview['data']['restored'][$invoice->id]['remaining_after'])->and($invoice->fresh()->status)->toBe($preview['data']['restored'][$invoice->id]['invoice_status_after'])
        ->and($reversals)->toBe($preview['data']['reversed_entries']);

    // klik ganda / permintaan kedua: ditolak, tanpa efek kedua
    expect(fn () => $service->cancel($receipt->fresh(), 'Klik ganda pada tombol pembatalan penerimaan', $user))->toThrow(ValidationException::class, 'sudah dibatalkan');
    expect(JournalEntry::where('source_type', \App\Models\CustomerReceipt::class)->where('source_id', $receipt->id)->where('is_reversal', true)->count())->toBe($reversals)
        ->and((float) AccountReceivable::where('invoice_id', $invoice->id)->first()->remaining)->toBe((float) $ar->remaining);
    expect(app(ImpactPreview::class)->receiptCancellation($receipt->fresh())['warnings'])->not->toBe([]);   // pratinjau untuk yang sudah batal = peringatan
});

it('klik ganda pada penerbitan Nota Kredit: dua panggilan = satu efek (jurnal, piutang, Deposit)', function () {
    $ctx = stkContext();
    $user = ipUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx, status: 'paid');
    $service = app(CreditNoteService::class);
    $cn = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer', actor: $user);

    $service->issue($cn, $user);
    $service->issue($cn->fresh(), $user);

    expect(JournalEntry::where('source_type', CreditNote::class)->where('source_id', $cn->id)->count())->toBe(3)
        ->and((float) Deposit::where('from_model_id', $ctx['customer']->id)->sum('remaining_amount'))->toBe(333000.0)
        ->and((float) AccountReceivable::where('invoice_id', $invoice->id)->first()->total)->toBe(999000.0);
});

it('pola aksi (D13): Invoice — aksi utama terlihat, sekunder (Ubah, Hapus, Cetak, Jurnal, Batalkan) di menu "Lainnya"', function () {
    $ctx = stkContext();
    $user = ipUser($ctx, ['update invoice', 'delete invoice', 'updateTaxNumber invoice']);
    [$invoice] = ctlCreditInvoice($ctx);

    [$top, $grouped] = ipVisibleActions(Livewire::actingAs($user)->test(ViewSalesInvoice::class, ['record' => $invoice->id]));

    expect($top)->toContain('create_credit_note')->not->toContain('delete')->not->toContain('edit')->not->toContain('cancel_invoice')->not->toContain('print_invoice')
        ->and($grouped)->toContain('edit')->toContain('delete')->toContain('print_invoice')->toContain('view_journal_entries')->toContain('cancel_invoice');
});

it('pola aksi (D13): Nota Kredit draf — "Terbitkan" langsung, "Hapus Draf"/Cetak/Jurnal di "Lainnya"; terbit — hanya No. Nota Retur Pajak di atas', function () {
    $ctx = stkContext();
    $creator = ipUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $cn = app(CreditNoteService::class)->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer', actor: $creator);
    $issuer = ipUser($ctx);

    [$top, $grouped] = ipVisibleActions(Livewire::actingAs($issuer)->test(ViewCreditNote::class, ['record' => $cn->id]));
    expect($top)->toBe(['issue'])->and($grouped)->toContain('print_credit_note')->toContain('delete_draft');

    app(CreditNoteService::class)->issue($cn, $issuer);
    [$top2] = ipVisibleActions(Livewire::actingAs($issuer)->test(ViewCreditNote::class, ['record' => $cn->id]));
    expect($top2)->toBe(['tax_document_number']);
});

it('pola aksi (D13): Retur — tiap status memperlihatkan paling banyak 3 aksi utama; Ubah/Hapus/Cetak di "Lainnya"', function () {
    $ctx = stkContext();
    $user = ipUser($ctx, ['update customer return', 'delete customer return', 'approve customer return', 'qc customer return']);
    [$invoice] = ctlCreditInvoice($ctx);

    foreach ([CustomerReturn::STATUS_PENDING, CustomerReturn::STATUS_RECEIVED, CustomerReturn::STATUS_QC_INSPECTION, CustomerReturn::STATUS_APPROVED, CustomerReturn::STATUS_COMPLETED] as $status) {
        $return = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber(), 'invoice_id' => $invoice->id, 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
            'warehouse_id' => $ctx['warehouse']->id, 'return_date' => now()->toDateString(), 'reason' => 'Unit cacat produksi', 'status' => $status,
        ]);
        [$top, $grouped] = ipVisibleActions(Livewire::actingAs($user)->test(ViewCustomerReturn::class, ['record' => $return->id]));

        expect(count($top))->toBeLessThanOrEqual(3, "status {$status}: ".implode(',', $top))->and($top)->not->toContain('edit')->not->toContain('delete')->not->toContain('print_customer_return')
            ->and($grouped)->toContain('edit')->toContain('delete')->toContain('print_customer_return');
    }
});

it('pola aksi (D13): Penerimaan — Cetak Kwitansi di atas; Ubah, Jurnal, Batalkan di "Lainnya"', function () {
    $ctx = stkContext();
    $user = ipUser($ctx, ['update customer receipt']);
    [$invoice] = ctlCreditInvoice($ctx);
    [$receipt] = ctlPaidReceipt($ctx, $invoice, 1332000);

    [$top, $grouped] = ipVisibleActions(Livewire::actingAs($user)->test(ViewCustomerReceipt::class, ['record' => $receipt->id]));
    expect($top)->toBe(['print_receipt'])->and($grouped)->toContain('edit')->toContain('view_journal_entries')->toContain('cancel_receipt');
});

it('penjaga klik ganda: setiap aksi berefek Nota Kredit/Penerimaan menonaktifkan tombol selama permintaan berjalan', function () {
    $ctx = stkContext();
    $user = ipUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $cn = app(CreditNoteService::class)->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'Retur satu unit dari invoice', actor: $user);

    $actions = [
        \App\Filament\Support\CreditNoteActions::create(\Filament\Actions\Action::make('a')),
        \App\Filament\Support\CreditNoteActions::cancelInvoice(\Filament\Actions\Action::make('b')),
        \App\Filament\Support\CreditNoteActions::fromReturn(\Filament\Actions\Action::make('c')),
        \App\Filament\Support\CreditNoteActions::issue(\Filament\Actions\Action::make('d')),
        \App\Filament\Support\CreditNoteActions::taxDocumentNumber(\Filament\Actions\Action::make('e')),
        \App\Filament\Support\CreditNoteActions::deleteDraft(\Filament\Actions\Action::make('f')),
        \App\Filament\Support\CustomerReceiptActions::cancel(\Filament\Actions\Action::make('g')),
    ];
    foreach ($actions as $action) {
        expect($action->getExtraAttributes())->toHaveKey('wire:loading.attr', 'disabled');
    }

    expect(DocumentActions::guard(\Filament\Actions\Action::make('x'))->getExtraAttributes())->toHaveKey('wire:loading.attr', 'disabled');
});
