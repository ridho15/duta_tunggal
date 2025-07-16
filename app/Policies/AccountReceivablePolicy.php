<?php

namespace App\Policies;

use App\Models\AccountReceivable;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AccountReceivablePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any account receivable');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AccountReceivable $accountReceivable): bool
    {
        return $user->hasPermissionTo('view account receivable');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create account receivable');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AccountReceivable $accountReceivable): bool
    {
        return $user->hasPermissionTo('update account receivable');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AccountReceivable $accountReceivable): bool
    {
        return $user->hasPermissionTo('delete account receivable');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AccountReceivable $accountReceivable): bool
    {
        return $user->hasPermissionTo('restore account receivable');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AccountReceivable $accountReceivable): bool
    {
        return $user->hasPermissionTo('force-delete account receivable');
    }
}
