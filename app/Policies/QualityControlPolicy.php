<?php

namespace App\Policies;

use App\Models\QualityControl;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class QualityControlPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any quality control');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, QualityControl $qualityControl): bool
    {
        return $user->hasPermissionTo('view quality control');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create quality control');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QualityControl $qualityControl): bool
    {
        return $user->hasPermissionTo('update quality control');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QualityControl $qualityControl): bool
    {
        return $user->hasPermissionTo('delete quality control');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, QualityControl $qualityControl): bool
    {
        return $user->hasPermissionTo('restore quality control');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, QualityControl $qualityControl): bool
    {
        return $user->hasRole('Super Admin');
    }
}
