<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorPaymentDetail;
use Illuminate\Auth\Access\Response;

class VendorPaymentDetailPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any vendor payment detail');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VendorPaymentDetail $vendorPaymentDetail): bool
    {
        return $user->hasPermissionTo('view vendor payment detail');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create vendor payment detail');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VendorPaymentDetail $vendorPaymentDetail): bool
    {
        return $user->hasPermissionTo('update vendor payment detail');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VendorPaymentDetail $vendorPaymentDetail): bool
    {
        return $user->hasPermissionTo('delete vendor payment detail');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VendorPaymentDetail $vendorPaymentDetail): bool
    {
        return $user->hasPermissionTo('restore vendor payment detail');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VendorPaymentDetail $vendorPaymentDetail): bool
    {
        return $user->hasPermissionTo('force-delete vendor payment detail');
    }
}
