<?php

/**
 * T7.3 — Pratinjau dampak (preview = eksekusi, usulan 16), pola aksi seragam (D13, usulan 14), dan penjaga klik ganda (usulan 19).
 * Pratinjau dibandingkan dengan HASIL NYATA eksekusi; aksi utama terlihat tanpa membuka menu, sekunder di "Lainnya".
 */

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CustomerReturn;
use App\Models\Deposit;
use App\Models\JournalEntry;
use App\Services\CreditNoteService;
use App\Services\CustomerReceiptCancellation;
use App\Services\ImpactPreview;
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

