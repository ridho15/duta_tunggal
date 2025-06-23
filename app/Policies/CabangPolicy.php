<?php

namespace App\Policies;

use App\Models\Cabang;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CabangPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any cabang');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cabang $cabang): bool
    {
        return $user->hasPermissionTo('view cabang');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create cabang');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cabang $cabang): bool
    {
        return $user->hasPermissionTo('update cabang');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cabang $cabang): bool
    {
        return $user->hasPermissionTo('delete cabang');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Cabang $cabang): bool
    {
        return $user->hasPermissionTo('restore cabang');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Cabang $cabang): bool
    {
        return $user->hasPermissionTo('force-delete cabang');
    }
}
