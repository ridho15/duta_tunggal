<?php

namespace App\Policies;

use App\Models\DeliverySchedule;
use App\Models\User;

class DeliverySchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any delivery schedule');
    }

    public function view(User $user, DeliverySchedule $deliverySchedule): bool
    {
        return $user->hasPermissionTo('view delivery schedule');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create delivery schedule');
    }

    public function update(User $user, DeliverySchedule $deliverySchedule): bool
    {
        return $user->hasPermissionTo('update delivery schedule');
    }

    public function updateStatus(User $user, DeliverySchedule $deliverySchedule): bool
    {
        return $user->hasPermissionTo('update status delivery schedule');
    }

    public function rekap(User $user): bool
    {
        return $user->hasPermissionTo('rekap delivery schedule');
    }

    public function delete(User $user, DeliverySchedule $deliverySchedule): bool
    {
        return $user->hasPermissionTo('delete delivery schedule');
    }

    public function restore(User $user, DeliverySchedule $deliverySchedule): bool
    {
        return $user->hasPermissionTo('restore delivery schedule');
    }

    public function forceDelete(User $user, DeliverySchedule $deliverySchedule): bool
    {
        return $user->hasRole('Super Admin');
    }
}
