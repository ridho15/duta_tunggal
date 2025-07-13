<?php

namespace App\Policies;

use App\Models\CustomerReceipt;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerReceiptPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any customer receipt');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CustomerReceipt $customerReceipt): bool
    {
        return $user->hasPermissionTo('view customer receipt');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create customer receipt');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomerReceipt $customerReceipt): bool
    {
        return $user->hasPermissionTo('update customer receipt');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomerReceipt $customerReceipt): bool
    {
        return $user->hasPermissionTo('delete customer receipt');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomerReceipt $customerReceipt): bool
    {
        return $user->hasPermissionTo('restore customer receipt');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomerReceipt $customerReceipt): bool
    {
        return $user->hasPermissionTo('force-delete customer receipt');
    }
}
