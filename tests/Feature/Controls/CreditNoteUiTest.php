<?php

/**
 * T5.3 — UI Nota Kredit: resource (daftar/View) + aksi pada Invoice dan Retur + policy.
 *  - flag mati: menu/aksi tersembunyi dan opsi keputusan retur "Refund / Nota Kredit" tidak ditawarkan (perilaku lama)
 *  - "Buat Nota Kredit" (Invoice) → draf berbatas sisa qty; "Batalkan Invoice" → draf pembatalan penuh; "Terbitkan" memakai layanan
 *  - draf dapat dihapus, yang terbit final (tanpa update/delete di policy)
 */

use App\Filament\Resources\CreditNoteResource;
use App\Filament\Resources\CreditNoteResource\Pages\ListCreditNotes;
use App\Filament\Resources\CreditNoteResource\Pages\ViewCreditNote;
use App\Filament\Resources\CustomerReturnResource\Pages\ViewCustomerReturn;
use App\Filament\Resources\SalesInvoiceResource\Pages\ViewSalesInvoice;
use App\Models\CreditNote;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Services\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.controls.credit_notes' => true]);
});

function uiUser(array $ctx, string $role = 'Finance Manager', array $permissions = ['view any credit note', 'view credit note', 'create credit note', 'delete credit note', 'approve credit note', 'view any invoice', 'view invoice', 'view any customer return', 'view customer return']): \App\Models\User
{
    $user = ctlUser($ctx, $role, $permissions);
    Auth::login($user);

    return $user;
}

it('flag mati: resource tidak dapat dilihat, aksi Invoice/Retur tersembunyi, opsi keputusan "Refund / Nota Kredit" tidak ditawarkan', function () {
    config(['sales.controls.credit_notes' => false]);
    $ctx = stkContext();
    $user = uiUser($ctx);
    [$invoice] = ctlCreditInvoice($ctx);

    expect(CreditNoteResource::canViewAny())->toBeFalse()
        ->and(\App\Filament\Support\CreditNoteActions::canCreateFor($invoice))->toBeFalse()
        ->and(CustomerReturnItem::decisionOptions())->not->toHaveKey(CustomerReturnItem::DECISION_CREDIT)
        ->and(CustomerReturnItem::decisionOptions())->toHaveKeys([CustomerReturnItem::DECISION_REPAIR, CustomerReturnItem::DECISION_REPLACE, CustomerReturnItem::DECISION_REJECT]);

    Livewire::actingAs($user)->test(ViewSalesInvoice::class, ['record' => $invoice->id])
        ->assertActionHidden('create_credit_note')
        ->assertActionHidden('cancel_invoice');
});

it('flag hidup: opsi keputusan menyertakan "Refund / Nota Kredit"; Invoice draf/dibatalkan tidak dapat dikreditkan', function () {
    $ctx = stkContext();
    uiUser($ctx);
    [$invoice] = ctlCreditInvoice($ctx);
    [$draft] = ctlCreditInvoice($ctx, status: 'draft');
    [$cancelled] = ctlCreditInvoice($ctx);
    $cancelled->forceFill(['status' => 'cancelled'])->saveQuietly();

    expect(CustomerReturnItem::decisionOptions())->toHaveKey(CustomerReturnItem::DECISION_CREDIT)
        ->and(\App\Filament\Support\CreditNoteActions::canCreateFor($invoice))->toBeTrue()
        ->and(\App\Filament\Support\CreditNoteActions::canCreateFor($draft))->toBeFalse()
        ->and(\App\Filament\Support\CreditNoteActions::canCreateFor($cancelled->fresh()))->toBeFalse();
});

it('pengguna tanpa izin membuat Nota Kredit tidak melihat aksinya', function () {
    $ctx = stkContext();
    $user = uiUser($ctx, 'Sales', ['view any invoice', 'view invoice']);
    [$invoice] = ctlCreditInvoice($ctx);

    expect(\App\Filament\Support\CreditNoteActions::canCreateFor($invoice))->toBeFalse();
    Livewire::actingAs($user)->test(ViewSalesInvoice::class, ['record' => $invoice->id])->assertActionHidden('create_credit_note');
});

it('aksi "Buat Nota Kredit" pada Invoice membuat draf sesuai kuantitas; melebihi sisa ditolak dan tidak membuat draf', function () {
    $ctx = stkContext();
    $user = uiUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);

    Livewire::actingAs($user)->test(ViewSalesInvoice::class, ['record' => $invoice->id])
        ->assertActionVisible('create_credit_note')
        ->callAction('create_credit_note', [
            'type' => CreditNote::TYPE_RETURN, 'credit_date' => now()->toDateString(), 'reason' => 'Barang cacat, dikembalikan customer',
            'lines' => [['invoice_item_id' => $item->id, 'label' => 'x', 'remaining' => 12, 'quantity' => 3]],
        ])
        ->assertHasNoActionErrors();

    $cn = CreditNote::where('invoice_id', $invoice->id)->first();
    expect($cn)->not->toBeNull()->and($cn->status)->toBe('draft')->and($cn->type)->toBe(CreditNote::TYPE_RETURN)->and((float) $cn->total)->toBe(333000.0);

    // melebihi sisa (3 sudah di draf + 10 > 12) → ditolak layanan, tidak ada draf kedua
    Livewire::actingAs($user)->test(ViewSalesInvoice::class, ['record' => $invoice->id])
        ->callAction('create_credit_note', [
            'type' => CreditNote::TYPE_RETURN, 'credit_date' => now()->toDateString(), 'reason' => 'Melebihi sisa kuantitas invoice',
            'lines' => [['invoice_item_id' => $item->id, 'label' => 'x', 'remaining' => 9, 'quantity' => 10]],
        ]);
    expect(CreditNote::where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('aksi "Batalkan Invoice" membuat draf pembatalan PENUH (invoice belum berubah sampai diterbitkan)', function () {
    $ctx = stkContext();
    $user = uiUser($ctx);
    [$invoice] = ctlCreditInvoice($ctx, shipping: 50000);

    Livewire::actingAs($user)->test(ViewSalesInvoice::class, ['record' => $invoice->id])
        ->callAction('cancel_invoice', ['reason' => 'Invoice salah nominal, dibatalkan penuh'])
        ->assertHasNoActionErrors();

    $cn = CreditNote::where('invoice_id', $invoice->id)->first();
    expect($cn->type)->toBe(CreditNote::TYPE_CANCELLATION)->and($cn->status)->toBe('draft')->and((float) $cn->total)->toBe(1382000.0)
        ->and($invoice->fresh()->status)->toBe('unpaid');
});

it('halaman daftar dan View menampilkan Nota Kredit; "Terbitkan" menerbitkan sekali dan menyimpan No. Nota Retur Pajak', function () {
    $ctx = stkContext();
    $creator = uiUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $cn = app(CreditNoteService::class)->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer', actor: $creator);
    $issuer = uiUser($ctx);   // penerbit berbeda dari pembuat draf

    Livewire::actingAs($issuer)->test(ListCreditNotes::class)->assertCanSeeTableRecords([$cn]);

    Livewire::actingAs($issuer)->test(ViewCreditNote::class, ['record' => $cn->id])
        ->assertSee($cn->credit_note_number)
        ->assertActionVisible('issue')
        ->assertActionVisible('delete_draft')
        ->assertActionHidden('tax_document_number')
        ->callAction('issue', ['tax_document_number' => 'NRP-0001'])
        ->assertHasNoActionErrors();

    $cn->refresh();
    expect($cn->status)->toBe('issued')->and($cn->tax_document_number)->toBe('NRP-0001')->and((float) $cn->applied_to_ar)->toBe(333000.0);

    Livewire::actingAs($issuer)->test(ViewCreditNote::class, ['record' => $cn->id])
        ->assertActionHidden('issue')
        ->assertActionHidden('delete_draft')
        ->assertActionVisible('tax_document_number')
        ->callAction('tax_document_number', ['tax_document_number' => 'NRP-0002']);
    expect($cn->fresh()->tax_document_number)->toBe('NRP-0002');
});

it('draf dapat dihapus lewat aksi; policy: yang terbit final (tanpa update/delete), tanpa izin approve tidak bisa menerbitkan', function () {
    $ctx = stkContext();
    $user = uiUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $service = app(CreditNoteService::class);
    $draft = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 2], 'Draf yang akan dihapus kembali', actor: $user);

    Livewire::actingAs($user)->test(ViewCreditNote::class, ['record' => $draft->id])->callAction('delete_draft');
    expect(CreditNote::find($draft->id))->toBeNull();

    $issued = $service->issue($service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 2], 'Draf yang akan diterbitkan', actor: $user), uiUser($ctx));
    $viewer = uiUser($ctx, 'Sales', ['view any credit note', 'view credit note', 'delete credit note']);

    expect($user->can('update', $issued))->toBeFalse()->and($user->can('delete', $issued))->toBeFalse()
        ->and($user->can('view', $issued))->toBeTrue()
        ->and($viewer->can('issue', $issued))->toBeFalse();

    $newDraft = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'Draf baru untuk uji izin penerbitan', actor: $user);
    expect($viewer->can('issue', $newDraft))->toBeFalse()->and($viewer->can('delete', $newDraft))->toBeTrue();
});

it('"Terbitkan" tersembunyi bagi pengguna tanpa izin approve; No. Nota Retur Pajak hanya untuk yang sudah terbit dan berizin', function () {
    $ctx = stkContext();
    $creator = uiUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $service = app(CreditNoteService::class);
    $draft = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 2], 'Draf untuk uji visibilitas terbitkan', actor: $creator);
    $noApprove = uiUser($ctx, 'Sales', ['view any credit note', 'view credit note', 'create credit note', 'delete credit note']);

    Livewire::actingAs($noApprove)->test(ViewCreditNote::class, ['record' => $draft->id])->assertActionHidden('issue');
    expect(fn () => $service->issue($draft, $noApprove))->toThrow(\Illuminate\Validation\ValidationException::class, 'hak akses');

    // nomor pajak: ditolak pada draf, ditolak tanpa izin, diterima pada yang terbit
    $approver = uiUser($ctx);
    expect(fn () => $service->setTaxDocumentNumber($draft, 'NRP-9', $approver))->toThrow(\Illuminate\Validation\ValidationException::class, 'setelah terbit');
    $issued = $service->issue($draft, $approver);
    expect(fn () => $service->setTaxDocumentNumber($issued, 'NRP-9', $noApprove))->toThrow(\Illuminate\Validation\ValidationException::class, 'hak akses');
    expect($service->setTaxDocumentNumber($issued, '  NRP-9  ', $approver)->tax_document_number)->toBe('NRP-9')
        ->and($service->setTaxDocumentNumber($issued, '', $approver)->tax_document_number)->toBeNull();
});

it('aksi "Buat Nota Kredit" pada Retur: hanya retur disetujui/selesai berkeputusan credit yang belum punya Nota Kredit', function () {
    $ctx = stkContext();
    $user = uiUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $make = function (string $status, string $decision) use ($ctx, $invoice, $item): CustomerReturn {
        $return = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber(), 'invoice_id' => $invoice->id, 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
            'warehouse_id' => $ctx['warehouse']->id, 'return_date' => now()->toDateString(), 'reason' => 'Unit cacat produksi, customer minta uang kembali', 'status' => $status,
        ]);
        CustomerReturnItem::create(['customer_return_id' => $return->id, 'product_id' => $item->product_id, 'invoice_item_id' => $item->id, 'quantity' => 2,
            'problem_description' => 'Cacat', 'qc_result' => 'fail', 'decision' => $decision]);

        return $return;
    };

    $pending = $make(CustomerReturn::STATUS_PENDING, CustomerReturnItem::DECISION_CREDIT);
    $replace = $make(CustomerReturn::STATUS_APPROVED, CustomerReturnItem::DECISION_REPLACE);
    $ok = $make(CustomerReturn::STATUS_APPROVED, CustomerReturnItem::DECISION_CREDIT);

    Livewire::actingAs($user)->test(ViewCustomerReturn::class, ['record' => $pending->id])->assertActionHidden('create_credit_note');
    Livewire::actingAs($user)->test(ViewCustomerReturn::class, ['record' => $replace->id])->assertActionHidden('create_credit_note');
    Livewire::actingAs($user)->test(ViewCustomerReturn::class, ['record' => $ok->id])->assertActionVisible('create_credit_note')->callAction('create_credit_note');

    $cn = CreditNote::where('customer_return_id', $ok->id)->first();
    expect($cn)->not->toBeNull()->and($cn->type)->toBe(CreditNote::TYPE_RETURN)->and((float) $cn->total)->toBe(222000.0);
    Livewire::actingAs($user)->test(ViewCustomerReturn::class, ['record' => $ok->id])->assertActionHidden('create_credit_note');   // sudah punya Nota Kredit
});

it('pengguna cabang lain tidak melihat Nota Kredit cabang ini (CabangScope)', function () {
    $ctx = stkContext();
    $owner = uiUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $cn = app(CreditNoteService::class)->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'Nota kredit untuk uji cabang lain', actor: $owner);

    $otherCabang = \App\Models\Cabang::factory()->create();
    $outsider = ctlUser($ctx, 'Finance Manager', ['view any credit note', 'view credit note'], ['cabang_id' => $otherCabang->id, 'manage_type' => 'cabang']);

    Auth::login($outsider);
    expect(CreditNote::find($cn->id))->toBeNull();
    Auth::login($owner);
    expect(CreditNote::find($cn->id))->not->toBeNull();
});

it('tampilan hub Keuangan Penjualan memuat kartu Nota Kredit hanya bagi yang berhak', function () {
    $ctx = stkContext();
    $user = uiUser($ctx);

    Livewire::actingAs($user)->test(\App\Filament\Pages\FinanceSalesHubPage::class)->assertSee('Nota Kredit');

    config(['sales.controls.credit_notes' => false]);
    Livewire::actingAs($user)->test(\App\Filament\Pages\FinanceSalesHubPage::class)->assertDontSee('Nota Kredit');
});
