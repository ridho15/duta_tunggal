<?php

/**
 * T5.1 — Nota Kredit (flag sales.controls.credit_notes): draf berbatas sisa qty, terbit = jurnal cermin seimbang (Dr Retur + PPN, Cr Piutang/Deposit),
 * piutang tidak pernah negatif, pembatalan penuh → invoice cancelled, idempoten, persetujuan.
 */

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.controls.credit_notes' => true]);
});

/** Konteks + pengguna Finance Manager yang berhak menerbitkan (bukan pembuat draf kecuali diminta). */
function cnUser(array $ctx, string $role = 'Finance Manager'): \App\Models\User
{
    $user = ctlUser($ctx, $role, ['approve credit note', 'create credit note']);
    Auth::login($user);

    return $user;
}

function cnEntries(CreditNote $cn): \Illuminate\Support\Collection
{
    return JournalEntry::where('source_type', CreditNote::class)->where('source_id', $cn->id)->get();
}

function cnCoa(string $code): int
{
    return (int) ChartOfAccount::where('code', $code)->value('id');
}

it('draf retur 3 dari 12 pcs: DPP 300.000 + PPN 33.000 = 333.000 (tepat 3/12); kuantitas melebihi sisa ditolak', function () {
    $ctx = stkContext();
    [$invoice, $item] = ctlCreditInvoice($ctx);   // 12 × 100.000 + PPN 11% = 1.332.000
    $service = app(CreditNoteService::class);

    $cn = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer');

    expect($cn->status)->toBe('draft')->and((float) $cn->subtotal)->toBe(300000.0)->and((float) $cn->tax_amount)->toBe(33000.0)->and((float) $cn->total)->toBe(333000.0)
        ->and($cn->items)->toHaveCount(1)->and((float) $cn->items->first()->unit_price)->toBe(100000.0);

    expect(fn () => $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 10], 'Melebihi sisa setelah draf pertama'))->toThrow(ValidationException::class, 'melebihi sisa');
    expect(fn () => $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 0], 'Tidak ada kuantitas sama sekali'))->toThrow(ValidationException::class, 'Tidak ada kuantitas');
    expect(fn () => $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'singkat'))->toThrow(ValidationException::class, 'minimal 10');
});

it('invoice draf atau sudah dibatalkan tidak dapat dikreditkan; flag mati menolak semuanya', function () {
    $ctx = stkContext();
    [$draftInvoice, $item] = ctlCreditInvoice($ctx, status: 'draft');
    [$cancelled, $item2] = ctlCreditInvoice($ctx, status: 'unpaid');
    $cancelled->forceFill(['status' => 'cancelled'])->saveQuietly();
    $service = app(CreditNoteService::class);

    expect(fn () => $service->draft($draftInvoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'Invoice masih draf tidak boleh'))->toThrow(ValidationException::class, 'belum terbit');
    expect(fn () => $service->draft($cancelled->fresh(), CreditNote::TYPE_RETURN, [$item2->id => 1], 'Invoice sudah dibatalkan tidak boleh'))->toThrow(ValidationException::class, 'sudah dibatalkan');

    config(['sales.controls.credit_notes' => false]);
    [$invoice, $item3] = ctlCreditInvoice($ctx);
    expect(fn () => $service->draft($invoice, CreditNote::TYPE_RETURN, [$item3->id => 1], 'Flag mati menolak nota kredit'))->toThrow(ValidationException::class, 'belum diaktifkan');
});

it('terbit (invoice belum dibayar): jurnal cermin seimbang, piutang turun tepat 3/12, invoice tetap terbuka; dua kali terbit = satu efek', function () {
    $ctx = stkContext();
    $user = cnUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $service = app(CreditNoteService::class);
    $cn = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer', actor: $user);

    $issued = $service->issue($cn, $user);
    $service->issue($cn->fresh(), $user);   // klik ganda

    $entries = cnEntries($cn);
    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();

    expect($issued->status)->toBe('issued')->and((float) $issued->applied_to_ar)->toBe(333000.0)->and((float) $issued->applied_to_deposit)->toBe(0.0)
        ->and($entries)->toHaveCount(3)
        ->and((float) $entries->where('coa_id', cnCoa('4120.10'))->sum('debit'))->toBe(300000.0)
        ->and((float) $entries->where('coa_id', cnCoa('2120.06'))->sum('debit'))->toBe(33000.0)
        ->and((float) $entries->where('coa_id', cnCoa('1120'))->sum('credit'))->toBe(333000.0)
        ->and((float) $entries->sum('debit'))->toBe((float) $entries->sum('credit'))
        ->and((float) $ar->total)->toBe(999000.0)->and((float) $ar->remaining)->toBe(999000.0)->and($ar->status)->toBe('Belum Lunas')
        ->and($invoice->fresh()->status)->toBe('unpaid');

    // Invarian: invoice − Nota Kredit − penerimaan = piutang
    expect((float) $invoice->total - (float) CreditNote::where('invoice_id', $invoice->id)->where('status', 'issued')->sum('total') - (float) $ar->paid)->toBe((float) $ar->remaining);
});

it('invoice LUNAS: seluruh nilai Nota Kredit menjadi Deposit Customer (Cr Deposit), piutang tidak negatif, log deposit tercatat', function () {
    $ctx = stkContext();
    $user = cnUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx, status: 'paid');
    $service = app(CreditNoteService::class);
    $cn = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer', actor: $user);

    $service->issue($cn, $user);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    $deposit = Deposit::where('from_model_type', \App\Models\Customer::class)->where('from_model_id', $ctx['customer']->id)->first();
    $entries = cnEntries($cn);

    expect((float) $ar->remaining)->toBe(0.0)->and((float) $ar->total)->toBe(999000.0)->and((float) $ar->paid)->toBe(999000.0)->and($ar->status)->toBe('Lunas')
        ->and($cn->fresh()->applied_to_ar)->toEqual(0)->and((float) $cn->fresh()->applied_to_deposit)->toBe(333000.0)
        ->and((float) $deposit->remaining_amount)->toBe(333000.0)
        ->and((float) $entries->where('coa_id', cnCoa('2160.04'))->sum('credit'))->toBe(333000.0)
        ->and($entries->where('coa_id', cnCoa('1120'))->sum('credit'))->toEqual(0)
        ->and((float) $entries->sum('debit'))->toBe((float) $entries->sum('credit'))
        ->and(DepositLog::where('deposit_id', $deposit->id)->where('type', 'add')->where('reference_id', $cn->id)->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe('paid');
});

it('pembatalan penuh invoice dibayar sebagian: piutang sisa dihapus, yang sudah dibayar ke Deposit, invoice cancelled + alasan; tidak dapat dikreditkan lagi', function () {
    $ctx = stkContext();
    $user = cnUser($ctx);
    [$invoice] = ctlCreditInvoice($ctx, shipping: 50000, status: 'partially_paid', paid: 1000000);   // total 1.382.000; sisa 382.000
    $service = app(CreditNoteService::class);

    $cn = $service->draft($invoice, CreditNote::TYPE_CANCELLATION, [], 'Invoice salah nominal, dibatalkan penuh', actor: $user);
    expect((float) $cn->total)->toBe(1382000.0)->and((float) $cn->other_fee_amount)->toBe(50000.0);

    $service->issue($cn, $user);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    $fresh = $invoice->fresh();
    $entries = cnEntries($cn);

    expect($cn->fresh()->applied_to_ar)->toEqual(382000)->and($cn->fresh()->applied_to_deposit)->toEqual(1000000)
        ->and((float) $ar->remaining)->toBe(0.0)->and((float) $ar->total)->toBe(0.0)->and((float) $ar->paid)->toBe(0.0)
        ->and($fresh->status)->toBe('cancelled')->and($fresh->cancel_reason)->toContain('dibatalkan penuh')->and($fresh->cancelled_at)->not->toBeNull()
        ->and((float) $entries->where('coa_id', cnCoa('6100.02'))->sum('debit'))->toBe(50000.0)
        ->and((float) $entries->sum('debit'))->toBe(1382000.0)->and((float) $entries->sum('credit'))->toBe(1382000.0);

    expect(fn () => $service->draft($fresh, CreditNote::TYPE_RETURN, [1 => 1], 'Sudah dibatalkan tidak boleh lagi'))->toThrow(ValidationException::class, 'sudah dibatalkan');
});

it('deposit yang sudah ada dinaikkan TANPA menyentuh jurnal DEP-{id} lama (jurnal deposit tidak ditulis ulang)', function () {
    $ctx = stkContext();
    $user = cnUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx, status: 'paid');
    $coa = ChartOfAccount::where('code', '2160.04')->first();
    $existing = Deposit::create(['from_model_type' => \App\Models\Customer::class, 'from_model_id' => $ctx['customer']->id, 'amount' => 500000, 'used_amount' => 0,
        'remaining_amount' => 500000, 'coa_id' => $coa->id, 'status' => 'active', 'created_by' => $user->id, 'deposit_number' => 'DEP-LAMA-1']);
    $journal = JournalEntry::create(['coa_id' => $coa->id, 'date' => now()->toDateString(), 'reference' => 'DEP-'.$existing->id, 'description' => 'Deposit awal', 'debit' => 0, 'credit' => 500000,
        'journal_type' => 'deposit', 'source_type' => Deposit::class, 'source_id' => $existing->id, 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1]);
    $service = app(CreditNoteService::class);

    $service->issue($service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer', actor: $user), $user);

    expect((float) $existing->fresh()->remaining_amount)->toBe(833000.0)->and((float) $existing->fresh()->amount)->toBe(833000.0)
        ->and((float) $journal->fresh()->credit)->toBe(500000.0)                                   // jurnal awal tidak ditulis ulang
        ->and(Deposit::where('from_model_id', $ctx['customer']->id)->count())->toBe(1);
});

it('koreksi bertahap: tiga Nota Kredit atas satu baris menjumlah TEPAT ke nilai baris (sisa pembulatan diserap yang terakhir)', function () {
    $ctx = stkContext();
    $user = cnUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx, qty: 3, price: 100 / 3);   // nilai baris 100,00 dan PPN 11,00 tidak habis dibagi 3
    $service = app(CreditNoteService::class);

    foreach ([1, 1, 1] as $qty) {
        $service->issue($service->draft($invoice, CreditNote::TYPE_CORRECTION, [$item->id => $qty], 'Koreksi harga bertahap sesuai kesepakatan', actor: $user), $user);
    }

    $credited = CreditNote::where('invoice_id', $invoice->id)->where('status', 'issued');
    expect((float) $credited->sum('subtotal'))->toBe((float) $item->subtotal)
        ->and((float) $credited->sum('tax_amount'))->toBe((float) $item->tax_amount)
        ->and($invoice->fresh()->status)->toBe('cancelled');   // seluruh baris dikreditkan penuh
});

it('kuantitas yang sudah dikreditkan Nota Kredit lain menghalangi terbit (draf ganda tidak boleh sama-sama terbit)', function () {
    $ctx = stkContext();
    $user = cnUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $service = app(CreditNoteService::class);
    $first = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 9], 'Retur sembilan unit dari dua belas', actor: $user);
    $other = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Retur tiga unit sisanya dari invoice', actor: $user);
    // sisipkan draf ketiga secara langsung agar total kuantitas melampaui invoice bila keduanya terbit
    $other->items()->update(['quantity' => 5]);

    $service->issue($first, $user);

    expect(fn () => $service->issue($other->fresh(), $user))->toThrow(ValidationException::class, 'melebihi sisa');
    expect($other->fresh()->status)->toBe('draft');
});

it('hapus: hanya draf yang dapat dihapus; yang terbit final', function () {
    $ctx = stkContext();
    $user = cnUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $service = app(CreditNoteService::class);
    $draft = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'Draf yang kemudian dihapus saja', actor: $user);
    $issued = $service->issue($service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'Nota kredit yang benar-benar terbit', actor: $user), $user);

    $service->deleteDraft($draft);
    expect(CreditNote::find($draft->id))->toBeNull();

    expect(fn () => $service->deleteDraft($issued))->toThrow(ValidationException::class, 'final');
    expect(CreditNote::find($issued->id))->not->toBeNull();
});

it('persetujuan: tanpa izin approve credit note ditolak; SoD dan ambang nominal berlaku bila aturan persetujuan hidup; override beralasan', function () {
    $ctx = stkContext();
    $creator = ctlUser($ctx, 'Finance Manager', ['create credit note', 'approve credit note']);
    Auth::login($creator);
    [$invoice, $item] = ctlCreditInvoice($ctx, qty: 200, price: 100000);   // nilai besar (> Rp10 jt)
    $service = app(CreditNoteService::class);
    $cn = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 150], 'Retur partai besar dari customer', actor: $creator);

    // tanpa izin
    $noPermission = ctlUser($ctx, 'Sales', ['create credit note']);
    expect(fn () => $service->issue($cn, $noPermission))->toThrow(ValidationException::class, 'hak akses');

    // aturan persetujuan hidup: pembuat tidak boleh menerbitkan sendiri; Sales Manager di atas ambang tidak berwenang; Finance Manager lain berwenang
    config(['sales.controls.approval_rules' => true]);
    expect(fn () => $service->issue($cn, $creator))->toThrow(ValidationException::class, 'Segregation of Duties');
    $salesManager = ctlUser($ctx, 'Sales Manager', ['approve credit note']);
    expect(fn () => $service->issue($cn, $salesManager))->toThrow(ValidationException::class, 'Persetujuan bertingkat');
    expect($cn->fresh()->status)->toBe('draft');

    $otherFinance = ctlUser($ctx, 'Finance Manager', ['approve credit note']);
    $service->issue($cn, $otherFinance);
    expect($cn->fresh()->status)->toBe('issued')->and((int) $cn->fresh()->issued_by)->toBe($otherFinance->id);
});

it('nomor Nota Retur Pajak dapat dicatat saat terbit; nomor Nota Kredit berformat CN', function () {
    $ctx = stkContext();
    $user = cnUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $service = app(CreditNoteService::class);
    $cn = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 2], 'Retur dua unit karena cacat produksi', actor: $user);

    $issued = $service->issue($cn, $user, ['tax_document_number' => '010.000-26.00000123']);

    expect($cn->credit_note_number)->toStartWith('CN-'.now()->format('Ymd'))->and($issued->tax_document_number)->toBe('010.000-26.00000123');

    config(['sales.controls.central_numbering' => true]);
    [$invoice2, $item2] = ctlCreditInvoice($ctx);
    $cn2 = $service->draft($invoice2, CreditNote::TYPE_RETURN, [$item2->id => 1], 'Nomor terpusat untuk nota kredit baru', actor: $user);
    expect($cn2->credit_note_number)->toMatch('/^CN-[A-Z0-9]+-\d{4}-0001$/');
});
