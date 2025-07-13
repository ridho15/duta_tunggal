<?php

namespace App\Filament\Widgets;

use App\Models\SaleOrder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class Penjualan7HariChart extends ChartWidget
{
    protected static ?string $heading = 'Penjualan 7 Hari';

    protected function getData(): array
    {
        $listTanggal = CarbonPeriod::between(Carbon::now()->subDays(6), Carbon::now())->toArray();
        $penjualan7Hari = SaleOrder::query()
            ->selectRaw('DATE(order_date) as tanggal, SUM(total_amount) as total_penjualan')
            ->where('status', 'completed')
            ->whereBetween('order_date', [Carbon::now()->subDays(6)->startOfDay(), Carbon::now()->endOfDay()])
            ->groupByRaw('DATE(order_date)')
            ->orderBy('tanggal')
            ->get();
        return [
            'datasets' => [
                [
                    'label' => 'Penjualan Harian',
                    'data' => collect($penjualan7Hari)->map(function ($data) {
                        return $data['total_penjualan'];
                    })->toArray(), // sesuaikan jumlah datanya
                ],
            ],
            'labels' => collect($listTanggal)->map(function (Carbon $date) {
                return $date->translatedFormat('d F Y'); // contoh: 13 Juli
            })->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    // public function getColumnSpan(): int | string | array
    // {
    //     return 'full'; // agar tampil full width
    // }
}
