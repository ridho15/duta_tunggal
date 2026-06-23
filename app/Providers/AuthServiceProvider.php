<?php

namespace App\Providers;

use App\Models\CustomerReturn;
use App\Policies\CustomerReturnPolicy;
use App\Models\ManufacturingOrder;
use App\Policies\ManufacturingOrderPolicy;
use App\Models\MaterialIssue;
use App\Policies\MaterialIssuePolicy;
use App\Models\ProductionPlan;
use App\Policies\ProductionPlanPolicy;
use App\Models\CashBankTransaction;
use App\Policies\CashBankTransactionPolicy;
use App\Models\CashBankTransfer;
use App\Policies\CashBankTransferPolicy;
use App\Models\BankReconciliation;
use App\Policies\BankReconciliationPolicy;
use App\Models\VoucherRequest;
use App\Policies\VoucherRequestPolicy;
use App\Models\DeliverySchedule;
use App\Policies\DeliverySchedulePolicy;
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
        CustomerReturn::class => CustomerReturnPolicy::class,
        ManufacturingOrder::class => ManufacturingOrderPolicy::class,
        MaterialIssue::class => MaterialIssuePolicy::class,
        ProductionPlan::class => ProductionPlanPolicy::class,
        CashBankTransaction::class => CashBankTransactionPolicy::class,
        CashBankTransfer::class => CashBankTransferPolicy::class,
        BankReconciliation::class => BankReconciliationPolicy::class,
        VoucherRequest::class => VoucherRequestPolicy::class,
        DeliverySchedule::class => DeliverySchedulePolicy::class,
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
