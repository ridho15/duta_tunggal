<?php

/**
 * T5.3 — UI Nota Kredit: resource (daftar/View) + aksi pada Invoice dan Retur + policy.
 *  - flag mati: menu/aksi tersembunyi dan opsi keputusan retur "Refund / Nota Kredit" tidak ditawarkan (perilaku lama)
 *  - "Buat Nota Kredit" (Invoice) → draf berbatas sisa qty; "Batalkan Invoice" → draf pembatalan penuh; "Terbitkan" memakai layanan
 *  - draf dapat dihapus, yang terbit final (tanpa update/delete di policy)
 */

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

