<?php

namespace App\Policies;

use App\Models\AssetTransfer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssetTransferPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any asset transfer');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->hasPermissionTo('view asset transfer');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create asset transfer');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->hasPermissionTo('update asset transfer');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->hasPermissionTo('delete asset transfer');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->hasPermissionTo('restore asset transfer');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->hasPermissionTo('force-delete asset transfer');
    }
}