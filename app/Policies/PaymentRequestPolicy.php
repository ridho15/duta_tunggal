<?php

namespace App\Policies;

use App\Models\PaymentRequest;
use App\Models\User;

class PaymentRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any payment request')
            || $user->hasPermissionTo('view any voucher request')
            || $user->hasPermissionTo('view any vendor payment');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PaymentRequest $paymentRequest): bool
    {
        if (! $user->canAccessCabang($paymentRequest->cabang_id)) {
            return false;
        }

        return $user->hasPermissionTo('view payment request')
            || $user->hasPermissionTo('view voucher request')
            || $user->hasPermissionTo('view vendor payment');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create payment request')
            || $user->hasPermissionTo('create voucher request')
            || $user->hasPermissionTo('create vendor payment');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PaymentRequest $paymentRequest): bool
    {
        if (! $user->canAccessCabang($paymentRequest->cabang_id)) {
            return false;
        }

        if (in_array(strtolower((string) $paymentRequest->status), [PaymentRequest::STATUS_PAID, PaymentRequest::STATUS_REJECTED], true)) {
            return false;
        }

        return $user->hasPermissionTo('update payment request')
            || $user->hasPermissionTo('update voucher request')
            || $user->hasPermissionTo('update vendor payment');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PaymentRequest $paymentRequest): bool
    {
        if (! $user->canAccessCabang($paymentRequest->cabang_id)) {
            return false;
        }

        if (strtolower((string) $paymentRequest->status) !== PaymentRequest::STATUS_DRAFT) {
            return false;
        }

        return $user->hasPermissionTo('delete payment request')
            || $user->hasPermissionTo('delete voucher request')
            || $user->hasPermissionTo('delete vendor payment');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PaymentRequest $paymentRequest): bool
    {
        if (! $user->canAccessCabang($paymentRequest->cabang_id)) {
            return false;
        }

        return $user->hasPermissionTo('restore payment request')
            || $user->hasPermissionTo('restore voucher request');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PaymentRequest $paymentRequest): bool
    {
        if (! $user->canAccessCabang($paymentRequest->cabang_id)) {
            return false;
        }

        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can approve the payment request.
     */
    public function approve(User $user, PaymentRequest $paymentRequest): bool
    {
        if (! $user->canAccessCabang($paymentRequest->cabang_id)) {
            return false;
        }

        return $user->hasPermissionTo('approve voucher request')
            || $user->hasPermissionTo('approve payment request')
            || $user->hasRole(['Super Admin', 'Director', 'Finance Manager']);
    }

    /**
     * Determine whether the user can reject the payment request.
     */
    public function reject(User $user, PaymentRequest $paymentRequest): bool
    {
        if (! $user->canAccessCabang($paymentRequest->cabang_id)) {
            return false;
        }

        return $user->hasPermissionTo('reject voucher request')
            || $user->hasPermissionTo('reject payment request')
            || $user->hasRole(['Super Admin', 'Director', 'Finance Manager']);
    }
}
