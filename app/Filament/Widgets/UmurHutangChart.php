<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class UmurHutangChart extends ChartWidget
{
    use InteractsWithPageFilters;
    protected static ?string $heading = 'Umur Hutang';

    protected function getData(): array
    {
        $data = DB::table('ageing_schedules as age')
            ->join('account_payables as ap', function ($join) {
                $join->on('age.from_model_id', '=', 'ap.id')
                    ->where('age.from_model_type', '=', 'App\\Models\\AccountPayable');
            })
            ->selectRaw("
        age.bucket,
        SUM(ap.remaining) as total
    ")
            ->groupBy('age.bucket')
            ->pluck('total', 'bucket');

        $labels = ['Belum Jatuh Tempo', '1–30 Hari', '31–60 Hari', '61–90 Hari', '>90 Hari'];

        $dataFormatted = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Umur Hutang',
                    'data' => [
                        $data['Current'] ?? 0,
                        $data['1-30'] ?? 0,
                        $data['31-60'] ?? 0,
                        $data['61-90'] ?? 0,
                        $data['>90'] ?? 0,
                    ],
                    'backgroundColor' => [
                        '#4ade80', // hijau
                        '#facc15', // kuning
                        '#f97316', // oranye
                        '#f43f5e', // merah muda
                        '#ef4444', // merah tua
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
