<?php

namespace App\Policies;

use App\Models\ProductUnitConversion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductUnitConversionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any product unit conversion');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProductUnitConversion $productUnitConversion): bool
    {
        return $user->hasPermissionTo('view product unit conversion');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create product unit conversion');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProductUnitConversion $productUnitConversion): bool
    {
        return $user->hasPermissionTo('update product unit conversion');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProductUnitConversion $productUnitConversion): bool
    {
        return $user->hasPermissionTo('delete product unit conversion');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProductUnitConversion $productUnitConversion): bool
    {
        return $user->hasPermissionTo('restore product unit conversion');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProductUnitConversion $productUnitConversion): bool
    {
        return $user->hasPermissionTo('force-delete product unit conversion');
    }
}
