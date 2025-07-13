<?php

namespace App\Filament\Widgets;

use App\Http\Controllers\HelperController;
use App\Models\SaleOrderItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ProdukTerlarisChart extends ChartWidget
{
    protected static ?string $heading = 'Produk Terlaris';

    protected function getData(): array
    {
        $topProducts = SaleOrderItem::select('product_id', DB::raw('SUM(quantity) as total_terjual'))
            ->whereHas('saleOrder', function ($query) {
                $query->where('status', 'completed');
            })
            ->groupBy('product_id')
            ->orderByDesc('total_terjual')
            ->with('product:id,name,sku') // relasi ke produk
            ->limit(10)
            ->get();

        $colors = HelperController::generateRandomColors(count($topProducts));
        return [
            'labels' => $topProducts->pluck('product.name')->toArray(),
            'datasets' => [
                [
                    'label' => 'Top Produk',
                    'data' => $topProducts->pluck('total_terjual')->toArray(),
                    'backgroundColor' => $colors,
                    'hoverBackgroundColor' => $colors,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'polarArea';
    }
}
