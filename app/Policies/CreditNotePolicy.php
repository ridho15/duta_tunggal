<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;
use App\Services\CreditNoteService;
use App\Services\DocumentLock;

/**
 * Nota Kredit (T5, D36): draf dibuat lewat aksi dari Invoice/Retur, TIDAK diedit (dibuat ulang bila salah); yang terbit final.
 * Kunci ini berlaku sejak Nota Kredit ada (tidak bergantung flag doc_lock) karena menyangkut jurnal/pajak.
 */
class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return CreditNoteService::enabled() && $user->hasPermissionTo('view any credit note');
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $user->hasPermissionTo('view credit note');
    }

    /** Pembuatan lewat aksi khusus (form draf), bukan halaman Create bawaan. */
    public function create(User $user): bool
    {
        return CreditNoteService::enabled() && $user->hasPermissionTo('create credit note');
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return false;
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        if (! $creditNote->isDraft() || DocumentLock::blocks($creditNote, 'delete')) {
            return false;
        }

        return $user->hasPermissionTo('delete credit note');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, CreditNote $creditNote): bool
    {
        return false;
    }

    public function forceDelete(User $user, CreditNote $creditNote): bool
    {
        return false;
    }

    /** Terbitkan: izin `approve credit note` (aturan bertingkat T3.1 ditegakkan lagi oleh layanan saat terbit). */
    public function issue(User $user, CreditNote $creditNote): bool
    {
        return CreditNoteService::enabled() && $creditNote->isDraft() && $user->hasPermissionTo('approve credit note');
    }
}
