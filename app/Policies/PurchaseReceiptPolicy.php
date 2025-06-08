<?php

namespace App\Policies;

use App\Models\PurchaseReceipt;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PurchaseReceiptPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any purchase receipt');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseReceipt $purchaseReceipt): bool
    {
        return $user->hasPermissionTo('view purchase receipt');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create purchase receipt');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseReceipt $purchaseReceipt): bool
    {
        return $user->hasPermissionTo('update purchase receipt');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseReceipt $purchaseReceipt): bool
    {
        return $user->hasPermissionTo('delete purchase receipt');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PurchaseReceipt $purchaseReceipt): bool
    {
        return $user->hasPermissionTo('restore purchase receipt');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PurchaseReceipt $purchaseReceipt): bool
    {
        return $user->hasRole('Super Admin');
    }
}
