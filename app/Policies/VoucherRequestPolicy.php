<?php

namespace App\Policies;

use App\Models\VoucherRequest;
use App\Models\User;

class VoucherRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any voucher request');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VoucherRequest $voucherRequest): bool
    {
        return $user->hasPermissionTo('view voucher request');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create voucher request');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VoucherRequest $voucherRequest): bool
    {
        return $user->hasPermissionTo('update voucher request');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VoucherRequest $voucherRequest): bool
    {
        return $user->hasPermissionTo('delete voucher request');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VoucherRequest $voucherRequest): bool
    {
        return $user->hasPermissionTo('restore voucher request');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VoucherRequest $voucherRequest): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can submit the voucher request for approval.
     */
    public function submit(User $user, VoucherRequest $voucherRequest): bool
    {
        return $user->hasPermissionTo('submit voucher request');
    }

    /**
     * Determine whether the user can approve the voucher request.
     */
    public function approve(User $user, VoucherRequest $voucherRequest): bool
    {
        return $user->hasPermissionTo('approve voucher request');
    }

    /**
     * Determine whether the user can reject the voucher request.
     */
    public function reject(User $user, VoucherRequest $voucherRequest): bool
    {
        return $user->hasPermissionTo('reject voucher request');
    }

    /**
     * Determine whether the user can cancel the voucher request.
     */
    public function cancel(User $user, VoucherRequest $voucherRequest): bool
    {
        return $user->hasPermissionTo('cancel voucher request');
    }
}
