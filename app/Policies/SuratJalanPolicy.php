<?php

namespace App\Policies;

use App\Models\SuratJalan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SuratJalanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any surat jalan');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SuratJalan $suratJalan): bool
    {
        return $user->hasPermissionTo('view surat jalan');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create surat jalan');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SuratJalan $suratJalan): bool
    {
        // Surat Jalan yang sudah terbit terkunci; koreksi lewat Batalkan + terbitkan ulang.
        return $suratJalan->isEditable() && $user->hasPermissionTo('update surat jalan');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SuratJalan $suratJalan): bool
    {
        return $suratJalan->isEditable() && $user->hasPermissionTo('delete surat jalan');
    }

    /** Terbitkan Draft. */
    public function issue(User $user, SuratJalan $suratJalan): bool
    {
        return $suratJalan->isDraft() && $user->hasPermissionTo('update surat jalan');
    }

    /** Batalkan Surat Jalan yang sudah terbit (memakai izin ubah; tidak menambah izin baru). */
    public function cancel(User $user, SuratJalan $suratJalan): bool
    {
        return $suratJalan->isIssued() && $user->hasPermissionTo('update surat jalan');
    }

    /** Terbitkan ulang dari Surat Jalan yang dibatalkan. */
    public function reissue(User $user, SuratJalan $suratJalan): bool
    {
        return $suratJalan->isCancelled() && $user->hasPermissionTo('create surat jalan');
    }

    /** Unggah dokumen bertanda tangan (bukti serah-terima): satu-satunya perubahan yang boleh pada SJ terbit. */
    public function uploadDocument(User $user, SuratJalan $suratJalan): bool
    {
        return ! $suratJalan->isCancelled() && $user->hasPermissionTo('update surat jalan');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SuratJalan $suratJalan): bool
    {
        return $user->hasPermissionTo('restore surat jalan');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SuratJalan $suratJalan): bool
    {
        return $user->hasPermissionTo('force-delete surat jalan');
    }

    public function request(User $user, SuratJalan $suratJalan): bool
    {
        return $user->hasPermissionTo('request surat jalan');
    }

    public function response(User $user, SuratJalan $suratJalan): bool
    {
        return $user->hasPermissionTo('response surat jalan');
    }
}
