<?php

namespace App\Policies;

use App\Models\DeliveryOrderLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DeliveryOrderLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any delivery order log');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DeliveryOrderLog $deliveryOrderLog): bool
    {
        return $user->hasPermissionTo('view delivery order log');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create delivery order log');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DeliveryOrderLog $deliveryOrderLog): bool
    {
        return $user->hasPermissionTo('update delivery order log');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DeliveryOrderLog $deliveryOrderLog): bool
    {
        return $user->hasPermissionTo('delete delivery order log');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DeliveryOrderLog $deliveryOrderLog): bool
    {
        return $user->hasPermissionTo('restore delivery order log');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DeliveryOrderLog $deliveryOrderLog): bool
    {
        return $user->hasPermissionTo('force-delete delivery order log');
    }
}