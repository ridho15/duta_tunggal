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
        return $user->hasPermissionTo('update surat jalan');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SuratJalan $suratJalan): bool
    {
        return $user->hasPermissionTo('delete surat jalan');
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
        return $user->hasRole('Super Admin');
    }
}
