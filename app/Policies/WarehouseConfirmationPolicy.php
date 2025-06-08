<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseConfirmation;
use Illuminate\Auth\Access\Response;

class WarehouseConfirmationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any warehouse confirmation');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WarehouseConfirmation $warehouseConfirmation): bool
    {
        return $user->hasPermissionTo('view warehouse confirmation');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create warehouse confirmation');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WarehouseConfirmation $warehouseConfirmation): bool
    {
        return $user->hasPermissionTo('update warehouse confirmation');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WarehouseConfirmation $warehouseConfirmation): bool
    {
        return $user->hasPermissionTo('delete warehouse confirmation');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WarehouseConfirmation $warehouseConfirmation): bool
    {
        return $user->hasPermissionTo('restore warehouse confirmation');
    }
    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WarehouseConfirmation $warehouseConfirmation): bool
    {
        return $user->hasRole('Super Admin');
    }
}
