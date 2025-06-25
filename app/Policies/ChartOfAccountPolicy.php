<?php

namespace App\Policies;

use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChartOfAccountPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any chart of account');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $user->hasPermissionTo('view chart of account');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create chart of account');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $user->hasPermissionTo('update chart of account');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $user->hasPermissionTo('delete chart of account');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $user->hasPermissionTo('restore chart of account');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $user->hasPermissionTo('force-delete chart of account');
    }
}
