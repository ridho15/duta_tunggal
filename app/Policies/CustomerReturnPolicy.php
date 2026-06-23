<?php

namespace App\Policies;

use App\Models\CustomerReturn;
use App\Models\User;

class CustomerReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any customer return');
    }

    public function view(User $user, CustomerReturn $customerReturn): bool
    {
        return $user->hasPermissionTo('view customer return');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create customer return');
    }

    public function update(User $user, CustomerReturn $customerReturn): bool
    {
        return $user->hasPermissionTo('update customer return');
    }

    public function qc(User $user, CustomerReturn $customerReturn): bool
    {
        return $user->hasPermissionTo('qc customer return');
    }

    public function approve(User $user, CustomerReturn $customerReturn): bool
    {
        return $user->hasPermissionTo('approve customer return');
    }

    public function delete(User $user, CustomerReturn $customerReturn): bool
    {
        return $user->hasPermissionTo('delete customer return');
    }

    public function restore(User $user, CustomerReturn $customerReturn): bool
    {
        return $user->hasPermissionTo('restore customer return');
    }

    public function forceDelete(User $user, CustomerReturn $customerReturn): bool
    {
        return $user->hasRole('Super Admin');
    }
}
