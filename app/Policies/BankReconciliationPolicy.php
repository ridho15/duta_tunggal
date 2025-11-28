<?php

namespace App\Policies;

use App\Models\BankReconciliation;
use App\Models\User;

class BankReconciliationPolicy
{
    private function canFinance(User $user): bool
    {
        return $user->hasRole(['Finance Manager', 'Accounting', 'Super Admin']);
    }

    public function viewAny(User $user): bool { return $this->canFinance($user); }
    public function view(User $user, BankReconciliation $model): bool { return $this->canFinance($user); }
    public function create(User $user): bool { return $this->canFinance($user); }
    public function update(User $user, BankReconciliation $model): bool { return $this->canFinance($user); }
    public function delete(User $user, BankReconciliation $model): bool { return $user->hasRole(['Super Admin', 'Finance Manager']); }
    public function restore(User $user, BankReconciliation $model): bool { return $user->hasRole(['Super Admin']); }
    public function forceDelete(User $user, BankReconciliation $model): bool { return $user->hasRole(['Super Admin']); }
}
