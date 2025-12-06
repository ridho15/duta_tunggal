<?php

namespace App\Policies;

use App\Models\AssetDisposal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AssetDisposalPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any asset disposal');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssetDisposal $assetDisposal): bool
    {
        return $user->hasPermissionTo('view asset disposal');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create asset disposal');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssetDisposal $assetDisposal): bool
    {
        return $user->hasPermissionTo('update asset disposal');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssetDisposal $assetDisposal): bool
    {
        return $user->hasPermissionTo('delete asset disposal');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AssetDisposal $assetDisposal): bool
    {
        return $user->hasPermissionTo('restore asset disposal');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AssetDisposal $assetDisposal): bool
    {
        return $user->hasPermissionTo('force-delete asset disposal');
    }
}