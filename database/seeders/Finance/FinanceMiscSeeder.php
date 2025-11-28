<?php

namespace Database\Seeders\Finance;

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\TaxSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class FinanceMiscSeeder extends Seeder
{
    public function __construct(private FinanceSeedContext $context)
    {
    }

    public function run(): void
    {
        TaxSetting::updateOrCreate(
            ['name' => 'PPN'],
            [
                'rate' => 11.0,
                'effective_date' => now()->toDateString(),
                'status' => true,
                'type' => 'PPN',
            ]
        );

        $userId = $this->context->getDefaultUserId();
        $cashAccount = $this->context->getCoa('1113.01') ?? $this->context->getCoa('1112.01');
        $customer = Customer::where('code', 'CUST-FIN-002')->first();

        if ($customer && $cashAccount) {
            $logoutAfter = false;
            if (!Auth::check()) {
                Auth::loginUsingId($userId);
                $logoutAfter = true;
            }

            Deposit::updateOrCreate(
                [
                    'from_model_type' => Customer::class,
                    'from_model_id' => $customer->id,
                ],
                [
                    'amount' => 25000000,
                    'used_amount' => 5000000,
                    'remaining_amount' => 20000000,
                    'coa_id' => $cashAccount->id,
                    'note' => 'Titipan proyek pengadaan panel listrik',
                    'status' => 'active',
                    'created_by' => $userId,
                ]
            );

            if ($logoutAfter) {
                Auth::logout();
            }
        }
    }
}
