<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockMovementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any stock movement');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockMovement $stockMovement): bool
    {
        return $user->hasPermissionTo('view stock movement');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create stock movement');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockMovement $stockMovement): bool
    {
        return $user->hasPermissionTo('update stock movement');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockMovement $stockMovement): bool
    {
        return $user->hasPermissionTo('delete stock movement');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StockMovement $stockMovement): bool
    {
        return $user->hasPermissionTo('restore stock movement');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StockMovement $stockMovement): bool
    {
        return $user->hasRole('Super Admin');
    }
}
