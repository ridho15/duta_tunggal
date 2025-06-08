<?php

namespace App\Policies;

use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchaseOrderItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any purchase order item');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseOrderItem $purchaseOrderItem): bool
    {
        return $user->hasPermissionTo('view purchase order item');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create purchase order item');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseOrderItem $purchaseOrderItem): bool
    {
        return $user->hasPermissionTo('update purchase order item');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseOrderItem $purchaseOrderItem): bool
    {
        return $user->hasPermissionTo('delete purchase order item');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PurchaseOrderItem $purchaseOrderItem): bool
    {
        return $user->hasPermissionTo('restore purchase order item');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PurchaseOrderItem $purchaseOrderItem): bool
    {
        return $user->hasRole('Super Admin');
    }
}
