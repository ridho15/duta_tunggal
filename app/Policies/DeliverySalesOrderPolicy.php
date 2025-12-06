<?php

namespace App\Policies;

use App\Models\DeliverySalesOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DeliverySalesOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any delivery sales order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DeliverySalesOrder $deliverySalesOrder): bool
    {
        return $user->hasPermissionTo('view delivery sales order');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create delivery sales order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DeliverySalesOrder $deliverySalesOrder): bool
    {
        return $user->hasPermissionTo('update delivery sales order');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DeliverySalesOrder $deliverySalesOrder): bool
    {
        return $user->hasPermissionTo('delete delivery sales order');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DeliverySalesOrder $deliverySalesOrder): bool
    {
        return $user->hasPermissionTo('restore delivery sales order');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DeliverySalesOrder $deliverySalesOrder): bool
    {
        return $user->hasPermissionTo('force-delete delivery sales order');
    }
}