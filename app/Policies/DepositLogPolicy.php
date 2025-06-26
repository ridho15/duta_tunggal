<?php

namespace App\Policies;

use App\Models\DepositLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DepositLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo("view any deposit log");
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DepositLog $depositLog): bool
    {
        return $user->hasPermissionTo("view deposit log");
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo("create deposit log");
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DepositLog $depositLog): bool
    {
        return $user->hasPermissionTo("update deposit log");
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DepositLog $depositLog): bool
    {
        return $user->hasPermissionTo("delete deposit log");
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DepositLog $depositLog): bool
    {
        return $user->hasPermissionTo("restore deposit log");
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DepositLog $depositLog): bool
    {
        return $user->hasPermissionTo("force-delete deposit log");
    }
}
