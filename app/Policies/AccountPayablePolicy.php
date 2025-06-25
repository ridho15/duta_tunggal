<?php

namespace App\Policies;

use App\Models\AccountPayable;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AccountPayablePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any account payable');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AccountPayable $accountPayable): bool
    {
        return $user->hasPermissionTo('view account payable');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create account payable');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AccountPayable $accountPayable): bool
    {
        return $user->hasPermissionTo('update account payable');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AccountPayable $accountPayable): bool
    {
        return $user->hasPermissionTo('delete account payable');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AccountPayable $accountPayable): bool
    {
        return $user->hasPermissionTo('restore account payable');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AccountPayable $accountPayable): bool
    {
        return $user->hasPermissionTo('force-delete account payable');
    }
}
