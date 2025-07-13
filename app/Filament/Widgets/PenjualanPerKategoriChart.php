<?php

namespace App\Filament\Widgets;

use App\Http\Controllers\HelperController;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PenjualanPerKategoriChart extends ChartWidget
{
    protected static ?string $heading = 'Penjualan Per Kategori';

    protected function getData(): array
    {
        $penjualanPerKategori = DB::table('sale_order_items')
            ->join('products', 'sale_order_items.product_id', '=', 'products.id')
            ->join('product_categories', 'products.product_category_id', '=', 'product_categories.id')
            ->join('sale_orders', 'sale_order_items.sale_order_id', '=', 'sale_orders.id')
            ->where('sale_orders.status', 'completed')
            ->select(
                'product_categories.name as kategori',
                DB::raw('SUM(sale_order_items.quantity * sale_order_items.unit_price) as total_penjualan')
            )
            ->groupBy('product_categories.name')
            ->orderByDesc('total_penjualan')
            ->get();
        $colors = HelperController::generateRandomColors(count($penjualanPerKategori));

        return [
            'labels' => $penjualanPerKategori->pluck('kategori')->toArray(),
            'datasets' => [
                [
                    'label' => 'Top Produk',
                    'data' => $penjualanPerKategori->pluck('total_penjualan')->toArray(),
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
