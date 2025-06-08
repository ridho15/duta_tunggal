<?php

namespace App\Policies;

use App\Models\SaleOrderItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SaleOrderItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any sales order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SaleOrderItem $saleOrderItem): bool
    {
        return $user->hasPermissionTo('view sales order');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create sales order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SaleOrderItem $saleOrderItem): bool
    {
        return $user->hasPermissionTo('update sales order');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SaleOrderItem $saleOrderItem): bool
    {
        return $user->hasPermissionTo('delete sales order');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SaleOrderItem $saleOrderItem): bool
    {
        return $user->hasPermissionTo('restore sales order');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SaleOrderItem $saleOrderItem): bool
    {
        return $user->hasRole('Super Admin');
    }
}
