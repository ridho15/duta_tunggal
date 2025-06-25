<?php

namespace App\Policies;

use App\Models\AgeingSchedule;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AgeingSchedulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any ageing schedule');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AgeingSchedule $ageingSchedule): bool
    {
        return $user->hasPermissionTo('view ageing schedule');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create ageing schedule');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AgeingSchedule $ageingSchedule): bool
    {
        return $user->hasPermissionTo('update ageing schedule');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AgeingSchedule $ageingSchedule): bool
    {
        return $user->hasPermissionTo('delete ageing schedule');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AgeingSchedule $ageingSchedule): bool
    {
        return $user->hasPermissionTo('restore ageing schedule');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AgeingSchedule $ageingSchedule): bool
    {
        return $user->hasPermissionTo('force-delete ageing schedule');
    }
}
