<?php

namespace App\Policies;

use App\Models\ReturnProductItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReturnProductItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any return product item');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ReturnProductItem $returnProductItem): bool
    {
        return $user->hasPermissionTo('view return product item');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create return product item');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReturnProductItem $returnProductItem): bool
    {
        return $user->hasPermissionTo('update return product item');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReturnProductItem $returnProductItem): bool
    {
        return $user->hasPermissionTo('delete return product item');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReturnProductItem $returnProductItem): bool
    {
        return $user->hasPermissionTo('restore return product item');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReturnProductItem $returnProductItem): bool
    {
        return $user->hasPermissionTo('force-delete return product item');
    }
}
