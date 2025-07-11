<?php

namespace App\Policies;

use App\Models\BillOfMaterialItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BillOfMaterialItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any bill of material item');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BillOfMaterialItem $billOfMaterialItem): bool
    {
        return $user->hasPermissionTo('view bill of material item');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create bill of material item');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BillOfMaterialItem $billOfMaterialItem): bool
    {
        return $user->hasPermissionTo('update bill of material item');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BillOfMaterialItem $billOfMaterialItem): bool
    {
        return $user->hasPermissionTo('delete bill of material item');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BillOfMaterialItem $billOfMaterialItem): bool
    {
        return $user->hasPermissionTo('restore bill of material item');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BillOfMaterialItem $billOfMaterialItem): bool
    {
        return $user->hasPermissionTo('force-delete bill of material item');
    }
}
