<?php

namespace App\Filament\Widgets;

use App\Http\Controllers\HelperController;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopCustomerChart extends ChartWidget
{
    protected static ?string $heading = 'Top Customer';

    protected function getData(): array
    {
        $topCustomers = DB::table('sale_orders')
            ->join('customers', 'sale_orders.customer_id', '=', 'customers.id')
            ->where('sale_orders.status', 'completed') // hanya transaksi selesai
            ->select(
                'customers.name',
                DB::raw('SUM(sale_orders.total_amount) as total_transaksi')
            )
            ->groupBy('customers.name')
            ->orderByDesc('total_transaksi')
            ->limit(10)
            ->get();
        $colors = HelperController::generateRandomColors(count($topCustomers));

        return [
            'labels' => $topCustomers->pluck('name')->toArray(),
            'datasets' => [
                [
                    'label' => 'Top Customer',
                    'data' => $topCustomers->pluck('total_transaksi')->toArray(),
                    'backgroundColor' => $colors,
                    'hoverBackgroundColor' => $colors,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
