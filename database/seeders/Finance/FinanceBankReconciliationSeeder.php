<?php

namespace Database\Seeders\Finance;

use App\Models\BankReconciliation;
use App\Models\CashBankTransaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinanceBankReconciliationSeeder extends Seeder
{
    public function __construct(private FinanceSeedContext $context)
    {
    }

    public function run(): void
    {
        $bankAccount = $this->context->getCoa('1112.01') ?? $this->context->getCoa('1112');
        if (!$bankAccount) {
            return;
        }

        $start = Carbon::now()->startOfMonth()->subMonth();
        $end = Carbon::now()->endOfDay();

        $incoming = CashBankTransaction::where('account_coa_id', $bankAccount->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('type', ['cash_in', 'bank_in'])
            ->sum('amount');

        $outgoing = CashBankTransaction::where('account_coa_id', $bankAccount->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('type', ['cash_out', 'bank_out'])
            ->sum('amount');

        $bookBalance = $incoming - $outgoing;

        BankReconciliation::updateOrCreate(
            [
                'coa_id' => $bankAccount->id,
                'period_start' => $start->toDateString(),
            ],
            [
                'period_end' => $end->toDateString(),
                'statement_ending_balance' => $bookBalance,
                'book_balance' => $bookBalance,
                'difference' => 0,
                'status' => 'closed',
            ]
        );
    }
}
