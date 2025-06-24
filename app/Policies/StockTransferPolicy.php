<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockTransferPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any stock transfer');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->hasPermissionTo('view stock transfer');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create stock transfer');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->hasPermissionTo('update stock transfer');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->hasPermissionTo('delete stock transfer');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->hasPermissionTo('restore stock transfer');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->hasPermissionTo('force-delete stock transfer');
    }
}
