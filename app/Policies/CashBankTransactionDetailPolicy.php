<?php

namespace App\Policies;

use App\Models\CashBankTransactionDetail;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CashBankTransactionDetailPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any cash bank transaction detail');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CashBankTransactionDetail $cashBankTransactionDetail): bool
    {
        return $user->hasPermissionTo('view cash bank transaction detail');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create cash bank transaction detail');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CashBankTransactionDetail $cashBankTransactionDetail): bool
    {
        return $user->hasPermissionTo('update cash bank transaction detail');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CashBankTransactionDetail $cashBankTransactionDetail): bool
    {
        return $user->hasPermissionTo('delete cash bank transaction detail');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CashBankTransactionDetail $cashBankTransactionDetail): bool
    {
        return $user->hasPermissionTo('restore cash bank transaction detail');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CashBankTransactionDetail $cashBankTransactionDetail): bool
    {
        return $user->hasPermissionTo('force-delete cash bank transaction detail');
    }
}