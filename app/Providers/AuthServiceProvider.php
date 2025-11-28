<?php

namespace App\Providers;

use App\Models\ManufacturingOrder;
use App\Policies\ManufacturingOrderPolicy;
use App\Models\CashBankTransaction;
use App\Policies\CashBankTransactionPolicy;
use App\Models\CashBankTransfer;
use App\Policies\CashBankTransferPolicy;
use App\Models\BankReconciliation;
use App\Policies\BankReconciliationPolicy;
use App\Models\VoucherRequest;
use App\Policies\VoucherRequestPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ManufacturingOrder::class => ManufacturingOrderPolicy::class,
        CashBankTransaction::class => CashBankTransactionPolicy::class,
        CashBankTransfer::class => CashBankTransferPolicy::class,
        BankReconciliation::class => BankReconciliationPolicy::class,
        VoucherRequest::class => VoucherRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        // Additional gates can be defined here if needed
    }
}
