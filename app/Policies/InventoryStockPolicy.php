<?php

namespace App\Policies;

use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InventoryStockPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any inventory stock');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InventoryStock $inventoryStock): bool
    {
        return $user->hasPermissionTo('view inventory stock');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create inventory stock');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InventoryStock $inventoryStock): bool
    {
        return $user->hasPermissionTo('update inventory stock');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InventoryStock $inventoryStock): bool
    {
        return $user->hasPermissionTo('delete inventory stock');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InventoryStock $inventoryStock): bool
    {
        return $user->hasPermissionTo('restore inventory stock');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InventoryStock $inventoryStock): bool
    {
        return $user->hasPermissionTo('force-delete inventory stock');
    }
}
