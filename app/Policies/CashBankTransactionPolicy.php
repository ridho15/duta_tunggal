<?php

namespace App\Policies;

use App\Models\CashBankTransaction;
use App\Models\User;

class CashBankTransactionPolicy
{
    private function canFinance(User $user): bool
    {
        return $user->hasRole(['Finance Manager', 'Accounting', 'Super Admin']);
    }

    public function viewAny(User $user): bool { return $this->canFinance($user); }
    public function view(User $user, CashBankTransaction $model): bool { return $this->canFinance($user); }
    public function create(User $user): bool { return $this->canFinance($user); }
    public function update(User $user, CashBankTransaction $model): bool { return $this->canFinance($user); }
    public function delete(User $user, CashBankTransaction $model): bool { return $user->hasRole(['Super Admin', 'Finance Manager']); }
    public function restore(User $user, CashBankTransaction $model): bool { return $user->hasRole(['Super Admin']); }
    public function forceDelete(User $user, CashBankTransaction $model): bool { return $user->hasRole(['Super Admin']); }
}
