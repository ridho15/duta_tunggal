<?php

namespace App\Policies;

use App\Models\ProductionPlan;
use App\Models\User;

class ProductionPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any production plan');
    }

    public function view(User $user, ProductionPlan $productionPlan): bool
    {
        return $user->hasPermissionTo('view production plan');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create production plan');
    }

    public function update(User $user, ProductionPlan $productionPlan): bool
    {
        return $user->hasPermissionTo('update production plan');
    }

    public function delete(User $user, ProductionPlan $productionPlan): bool
    {
        return $user->hasPermissionTo('delete production plan');
    }

    public function restore(User $user, ProductionPlan $productionPlan): bool
    {
        return $user->hasPermissionTo('restore production plan');
    }

    public function forceDelete(User $user, ProductionPlan $productionPlan): bool
    {
        return $user->hasPermissionTo('force-delete production plan');
    }

    public function approve(User $user, ProductionPlan $productionPlan): bool
    {
        return $user->hasPermissionTo('approve production plan');
    }

    public function schedule(User $user, ProductionPlan $productionPlan): bool
    {
        return $user->hasPermissionTo('schedule production plan');
    }
}
