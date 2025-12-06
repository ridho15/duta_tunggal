<?php

namespace App\Policies;

use App\Models\CashBankAccount;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CashBankAccountPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any cash bank account');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CashBankAccount $cashBankAccount): bool
    {
        return $user->hasPermissionTo('view cash bank account');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create cash bank account');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CashBankAccount $cashBankAccount): bool
    {
        return $user->hasPermissionTo('update cash bank account');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CashBankAccount $cashBankAccount): bool
    {
        return $user->hasPermissionTo('delete cash bank account');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CashBankAccount $cashBankAccount): bool
    {
        return $user->hasPermissionTo('restore cash bank account');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CashBankAccount $cashBankAccount): bool
    {
        return $user->hasPermissionTo('force-delete cash bank account');
    }
}