<?php

namespace App\Policies;

use App\Models\AccountingPeriod;
use App\Models\User;

class AccountingPeriodPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any accounting period')
            || $user->hasPermissionTo('view journal entry')
            || $user->hasRole(['Super Admin', 'Director', 'Finance Manager', 'Accounting Staff', 'Admin']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AccountingPeriod $period): bool
    {
        if (! $user->canAccessCabang($period->cabang_id)) {
            return false;
        }

        return $user->hasPermissionTo('view accounting period')
            || $user->hasPermissionTo('view journal entry')
            || $user->hasRole(['Super Admin', 'Director', 'Finance Manager', 'Accounting Staff', 'Admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create accounting period')
            || $user->hasRole(['Super Admin', 'Director', 'Finance Manager', 'Admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AccountingPeriod $period): bool
    {
        if (! $user->canAccessCabang($period->cabang_id)) {
            return false;
        }

        if ($period->status === 'closed') {
            return false;
        }

        return $user->hasPermissionTo('update accounting period')
            || $user->hasRole(['Super Admin', 'Director', 'Finance Manager', 'Admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AccountingPeriod $period): bool
    {
        if (! $user->canAccessCabang($period->cabang_id)) {
            return false;
        }

        if ($period->status === 'closed') {
            return false;
        }

        return $user->hasPermissionTo('delete accounting period')
            || $user->hasRole(['Super Admin', 'Director', 'Finance Manager']);
    }

    /**
     * Determine whether the user can close the period.
     */
    public function close(User $user, AccountingPeriod $period): bool
    {
        if (! $user->canAccessCabang($period->cabang_id)) {
            return false;
        }

        return $period->status === 'open'
            && ($user->hasPermissionTo('close accounting period')
                || $user->hasRole(['Super Admin', 'Director', 'Finance Manager', 'Admin']));
    }

    /**
     * Determine whether the user can reopen the period.
     */
    public function reopen(User $user, AccountingPeriod $period): bool
    {
        if (! $user->canAccessCabang($period->cabang_id)) {
            return false;
        }

        return $period->status === 'closed'
            && ($user->hasPermissionTo('reopen accounting period')
                || $user->hasRole(['Super Admin', 'Director', 'Finance Manager']));
    }
}
