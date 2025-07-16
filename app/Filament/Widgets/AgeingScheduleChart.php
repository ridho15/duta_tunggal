<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AgeingScheduleChart extends ChartWidget
{
    protected static ?string $heading = 'Umur Piutang vs Hutang';
    protected static string $color = 'info';
    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'polarArea';
    }

    protected function getData(): array
    {
        // Ambil data dari tabel ageing_schedules dan group by bucket & model type
        $data = DB::table('ageing_schedules')
            ->select(
                'bucket',
                'from_model_type',
                DB::raw('count(*) as total')
            )
            ->groupBy('bucket', 'from_model_type')
            ->get();

        $buckets = ['Current', '1-30', '31-60', '61-90', '>90'];

        $arData = [];
        $apData = [];

        foreach ($buckets as $bucket) {
            $ar = $data->firstWhere('bucket', $bucket)?->from_model_type === 'App\Models\AccountReceivable'
                ? $data->where('bucket', $bucket)->where('from_model_type', 'App\Models\AccountReceivable')->sum('total')
                : 0;

            $ap = $data->firstWhere('bucket', $bucket)?->from_model_type === 'App\Models\AccountPayable'
                ? $data->where('bucket', $bucket)->where('from_model_type', 'App\Models\AccountPayable')->sum('total')
                : 0;

            $arData[] = $ar;
            $apData[] = $ap;
        }
        return [
            'labels' => $buckets,
            'datasets' => [
                [
                    'label' => 'Account Receivable',
                    'data' => $arData,
                    'backgroundColor' => '#4ade80',
                ],
                [
                    'label' => 'Account Payable',
                    'data' => $apData,
                    'backgroundColor' => '#f87171',
                ],
            ],
        ];
    }
}
