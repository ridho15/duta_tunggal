<?php

namespace App\Policies;

use App\Models\OrderRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any order request');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OrderRequest $orderRequest): bool
    {
        return $user->hasPermissionTo('view order request');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create order request');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OrderRequest $orderRequest): bool
    {
        if ($orderRequest->purchaseOrders()->exists() || ! in_array($orderRequest->status, ['draft', 'request_approve'])) {
            return false;
        }

        return $user->hasPermissionTo('update order request');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OrderRequest $orderRequest): bool
    {
        if ($orderRequest->purchaseOrders()->exists() || $orderRequest->status !== 'draft') {
            return false;
        }

        return $user->hasPermissionTo('delete order request');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, OrderRequest $orderRequest): bool
    {
        return $user->hasPermissionTo('restore order request');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, OrderRequest $orderRequest): bool
    {
        return $user->hasPermissionTo('force-delete order request');
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, OrderRequest $orderRequest): bool
    {
        $check = app(\App\Services\ApprovalControlService::class)->canApproveOrderRequest($user, $orderRequest);

        return $check['allowed'];
    }

    /**
     * Determine whether the user can reject the model.
     */
    public function reject(User $user, OrderRequest $orderRequest): bool
    {
        return $user->hasPermissionTo('reject order request');
    }

    /**
     * Determine whether the user can submit the model.
     */
    public function submit(User $user, OrderRequest $orderRequest): bool
    {
        return $user->hasPermissionTo('submit order request');
    }
}
