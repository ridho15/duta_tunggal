<?php

namespace App\Policies;

use App\Models\ManufacturingOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ManufacturingOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any manufacturing order');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('view manufacturing order');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create manufacturing order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('update manufacturing order');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('delete manufacturing order');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('restore manufacturing order');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('force-delete manufacturing order');
    }

    public function response(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('response manufacturing order');
    }

    public function request(User $user, ManufacturingOrder $manufacturingOrder): bool
    {
        return $user->hasPermissionTo('request manufacturing order');
    }

    public function updateStatus(User $user, ManufacturingOrder $mo, string $to): bool
    {
        // Require permission and enforce allowed transitions
        if (!$user->hasPermissionTo('request manufacturing order')) {
            return false;
        }

        $from = $mo->status;
        $allowed = [
            'draft' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }
}
