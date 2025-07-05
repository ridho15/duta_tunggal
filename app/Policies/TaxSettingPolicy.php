<?php

namespace App\Policies;

use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaxSettingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any tax setting');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TaxSetting $taxSetting): bool
    {
        return $user->hasPermissionTo('view tax setting');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create tax setting');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TaxSetting $taxSetting): bool
    {
        return $user->hasPermissionTo('update tax setting');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TaxSetting $taxSetting): bool
    {
        return $user->hasPermissionTo('delete tax setting');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TaxSetting $taxSetting): bool
    {
        return $user->hasPermissionTo('restore tax setting');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TaxSetting $taxSetting): bool
    {
        return $user->hasPermissionTo('force-delete tax setting');
    }
}
