<?php

namespace Database\Seeders;

use Database\Seeders\Finance\FinanceBankReconciliationSeeder;
use Database\Seeders\Finance\FinanceCashBankTransactionSeeder;
use Database\Seeders\Finance\FinanceChartOfAccountSeeder;
use Database\Seeders\Finance\FinanceCurrencySeeder;
use Database\Seeders\Finance\FinanceCustomerSupplierSeeder;
use Database\Seeders\Finance\FinanceFixedAssetSeeder;
use Database\Seeders\Finance\FinanceHppSeeder;
use Database\Seeders\Finance\FinanceMiscSeeder;
use Database\Seeders\Finance\FinancePurchaseSeeder;
use Database\Seeders\Finance\FinanceReportConfigSeeder;
use Database\Seeders\Finance\FinanceSalesSeeder;
use Database\Seeders\Finance\FinanceSeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $context = new FinanceSeedContext();

            (new FinanceCurrencySeeder($context))->run();
            (new FinanceChartOfAccountSeeder($context))->run();
            (new FinanceReportConfigSeeder())->run();
            (new FinanceCustomerSupplierSeeder($context))->run();

            $salesContext = (new FinanceSalesSeeder($context))->run();
            $purchaseContext = (new FinancePurchaseSeeder($context))->run();

            (new FinanceCashBankTransactionSeeder($context, $salesContext, $purchaseContext))->run();
            (new FinanceBankReconciliationSeeder($context))->run();
            (new FinanceFixedAssetSeeder($context))->run();
            (new FinanceHppSeeder($context))->run();
            (new FinanceMiscSeeder($context))->run();
        });
    }
}