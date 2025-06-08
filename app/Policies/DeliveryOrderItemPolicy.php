<?php

namespace App\Policies;

use App\Models\DeliveryOrderItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DeliveryOrderItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('view any delivery order item');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DeliveryOrderItem $deliveryOrderItem): bool
    {
        return $user->hasRole('view delivery order item');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('create delivery order item');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DeliveryOrderItem $deliveryOrderItem): bool
    {
        return $user->hasRole('update delivery order item');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DeliveryOrderItem $deliveryOrderItem): bool
    {
        return $user->hasRole('delete delivery order item');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DeliveryOrderItem $deliveryOrderItem): bool
    {
        return $user->hasRole('restore delivery order item');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DeliveryOrderItem $deliveryOrderItem): bool
    {
        return $user->hasRole('Super Admin');
    }
}
