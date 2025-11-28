<?php

namespace Database\Seeders\Finance;

use App\Models\Asset;
use App\Models\AssetDepreciation;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinanceFixedAssetSeeder extends Seeder
{
    public function __construct(private FinanceSeedContext $context)
    {
    }

    public function run(): void
    {
        $assetCoa = $this->context->getCoa('1210.01');
        $accumulatedCoa = $this->context->getCoa('1220.01');
        $expenseCoa = $this->context->getCoa('6311');

        if (!$assetCoa || !$accumulatedCoa || !$expenseCoa) {
            return;
        }

        $assets = [
            [
                'name' => 'Forklift Diesel Toyota 3 Ton',
                'purchase_date' => Carbon::now()->subMonths(18),
                'usage_date' => Carbon::now()->subMonths(17),
                'purchase_cost' => 240000000,
                'salvage_value' => 40000000,
                'useful_life_years' => 8,
            ],
            [
                'name' => 'Mesin Press Hidrolik 150 Ton',
                'purchase_date' => Carbon::now()->subMonths(8),
                'usage_date' => Carbon::now()->subMonths(7),
                'purchase_cost' => 180000000,
                'salvage_value' => 30000000,
                'useful_life_years' => 6,
            ],
        ];

        foreach ($assets as $config) {
            $depreciable = $config['purchase_cost'] - $config['salvage_value'];
            $annual = $depreciable / $config['useful_life_years'];
            $monthly = $annual / 12;
            $monthsUsed = max(0, $config['usage_date']->diffInMonths(Carbon::now()));
            $accumulated = min($depreciable, $monthly * $monthsUsed);
            $bookValue = $config['purchase_cost'] - $accumulated;

            $asset = Asset::updateOrCreate(
                ['name' => $config['name']],
                [
                    'purchase_date' => $config['purchase_date']->toDateString(),
                    'usage_date' => $config['usage_date']->toDateString(),
                    'purchase_cost' => $config['purchase_cost'],
                    'salvage_value' => $config['salvage_value'],
                    'useful_life_years' => $config['useful_life_years'],
                    'asset_coa_id' => $assetCoa->id,
                    'accumulated_depreciation_coa_id' => $accumulatedCoa->id,
                    'depreciation_expense_coa_id' => $expenseCoa->id,
                    'annual_depreciation' => $annual,
                    'monthly_depreciation' => $monthly,
                    'accumulated_depreciation' => $accumulated,
                    'book_value' => $bookValue,
                    'status' => 'active',
                ]
            );

            for ($i = 1; $i <= min(3, $monthsUsed); $i++) {
                $periodDate = Carbon::now()->subMonths($i)->endOfMonth();

                AssetDepreciation::updateOrCreate(
                    [
                        'asset_id' => $asset->id,
                        'period_month' => $periodDate->month,
                        'period_year' => $periodDate->year,
                    ],
                    [
                        'depreciation_date' => $periodDate->toDateString(),
                        'amount' => $monthly,
                        'accumulated_total' => min($accumulated, $monthly * $i),
                        'book_value' => max($asset->salvage_value, $asset->purchase_cost - $monthly * $i),
                        'status' => 'recorded',
                        'notes' => 'Penyusutan otomatis periode ' . $periodDate->format('F Y'),
                    ]
                );

                $reference = 'JE-DEP-' . $asset->id . '-' . $periodDate->format('Ym');
                $this->context->recordJournalEntry($reference, '6311', $periodDate, $monthly, 0, 'Beban penyusutan ' . $asset->name);
                $this->context->recordJournalEntry($reference, '1220.01', $periodDate, 0, $monthly, 'Akumulasi penyusutan ' . $asset->name);
            }

            $asset->updateAccumulatedDepreciation();
        }
    }
}
