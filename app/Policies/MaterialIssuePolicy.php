<?php

namespace App\Policies;

use App\Models\MaterialIssue;
use App\Models\User;

class MaterialIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any material issue');
    }

    public function view(User $user, MaterialIssue $materialIssue): bool
    {
        return $user->hasPermissionTo('view material issue');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create material issue');
    }

    public function update(User $user, MaterialIssue $materialIssue): bool
    {
        return $user->hasPermissionTo('update material issue');
    }

    public function delete(User $user, MaterialIssue $materialIssue): bool
    {
        return $user->hasPermissionTo('delete material issue');
    }

    public function restore(User $user, MaterialIssue $materialIssue): bool
    {
        return $user->hasPermissionTo('restore material issue');
    }

    public function forceDelete(User $user, MaterialIssue $materialIssue): bool
    {
        return $user->hasPermissionTo('force-delete material issue');
    }

    public function approve(User $user, MaterialIssue $materialIssue): bool
    {
        return $user->hasPermissionTo('approve material issue');
    }

    public function complete(User $user, MaterialIssue $materialIssue): bool
    {
        return $user->hasPermissionTo('complete material issue');
    }
}
