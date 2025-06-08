<?php

namespace App\Policies;

use App\Models\ManufacturingOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ManufacturingOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any manufacturing order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('view manufacturing order');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create manufacturing order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('update manufacturing order');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('delete manufacturing order');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('restore manufacturing order');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasRole('Super Admin');
    }
}
