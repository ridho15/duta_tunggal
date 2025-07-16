<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class UmurPiutangChart extends ChartWidget
{
    use InteractsWithPageFilters;
    protected static ?string $heading = 'Umur Piutang';

    protected function getData(): array
    {
        $data = DB::table('ageing_schedules as age')
            ->join('account_receivables as ar', function ($join) {
                $join->on('age.from_model_id', '=', 'ar.id')
                    ->where('age.from_model_type', '=', 'App\\Models\\AccountReceivable');
            })
            ->selectRaw("
        age.bucket,
        SUM(ar.remaining) as total
    ")
            ->groupBy('age.bucket')
            ->pluck('total', 'bucket');

        $labels = ['Belum Jatuh Tempo', '1–30 Hari', '31–60 Hari', '61–90 Hari', '>90 Hari'];

        $dataFormatted = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Umur Piutang',
                    'data' => [
                        $data['Current'] ?? 0,
                        $data['1-30'] ?? 0,
                        $data['31-60'] ?? 0,
                        $data['61-90'] ?? 0,
                        $data['>90'] ?? 0,
                    ],
                    'backgroundColor' => [
                        '#60a5fa',
                        '#34d399',
                        '#fbbf24',
                        '#f97316',
                        '#ef4444',
                    ],
                ]
            ]
        ];

        return $dataFormatted;
    }

    protected function getType(): string
    {
        return 'polarArea';
    }
}
