<?php

namespace App\Policies;

use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StockTransferItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any stock transfer item');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockTransferItem $stockTransferItem): bool
    {
        return $user->hasPermissionTo('view stock transfer item');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create stock transfer item');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockTransferItem $stockTransferItem): bool
    {
        return $user->hasPermissionTo('update stock transfer item');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockTransferItem $stockTransferItem): bool
    {
        return $user->hasPermissionTo('delete stock transfer item');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StockTransferItem $stockTransferItem): bool
    {
        return $user->hasPermissionTo('restore stock transfer item');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StockTransferItem $stockTransferItem): bool
    {
        return $user->hasPermissionTo('force-delete stock transfer item');
    }
}
