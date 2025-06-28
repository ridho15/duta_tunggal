<?php

namespace App\Policies;

use App\Models\PurchaseOrderBiaya;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchaseOrderBiayaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any purchase order biaya');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseOrderBiaya $purchaseOrderBiaya): bool
    {
        return $user->hasPermissionTo('view purchase order biaya');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create purchase order biaya');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseOrderBiaya $purchaseOrderBiaya): bool
    {
        return $user->hasPermissionTo('update purchase order biaya');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseOrderBiaya $purchaseOrderBiaya): bool
    {
        return $user->hasPermissionTo('delete purchase order biaya');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PurchaseOrderBiaya $purchaseOrderBiaya): bool
    {
        return $user->hasPermissionTo('restore purchase order biaya');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PurchaseOrderBiaya $purchaseOrderBiaya): bool
    {
        return $user->hasPermissionTo('force-delete purchase order biaya');
    }
}
