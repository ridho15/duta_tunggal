<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ArApChart extends ChartWidget
{
    protected static ?string $heading = 'Chart';

    protected function getData(): array
    {
        $startDate = Carbon::parse($this->filter['tanggalMulai'])->startOfDay();
        $endDate = Carbon::parse($this->filter['tanggalAkhir'])->endOfDay();
        // Query AR per tanggal
        $arQuery = DB::table('account_receivables')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(tostal) as total_ar'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'));

        // Query AP per tanggal
        $apQuery = DB::table('account_payables')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as total_ap'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'));

        // Gabungkan dua sumber
        $data = DB::table(DB::raw("({$arQuery->toSql()}) as ar"))
            ->mergeBindings($arQuery)
            ->leftJoinSub($apQuery, 'ap', 'ar.date', '=', 'ap.date')
            ->select(
                DB::raw('ar.date'),
                DB::raw('COALESCE(total_ar, 0) as ar'),
                DB::raw('COALESCE(total_ap, 0) as ap')
            )
            ->unionAll(
                DB::table(DB::raw("({$apQuery->toSql()}) as ap"))
                    ->mergeBindings($apQuery)
                    ->leftJoinSub($arQuery, 'ar', 'ap.date', '=', 'ar.date')
                    ->select(
                        DB::raw('ap.date'),
                        DB::raw('COALESCE(total_ar, 0) as ar'),
                        DB::raw('COALESCE(total_ap, 0) as ap')
                    )
            )
            ->orderBy('date')
            ->get();
        $labels = [];
        $arData = [];
        $apData = [];

        foreach ($data as $row) {
            $labels[] = Carbon::parse($row->date)->format('d M'); // Misalnya: 13 Jul
            $arData[] = (float) $row->ar;
            $apData[] = (float) $row->ap;
        }
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Account Receivable',
                    'data' => $arData,
                    'backgroundColor' => '#4ade80', // hijau
                ],
                [
                    'label' => 'Account Payable',
                    'data' => $apData,
                    'backgroundColor' => '#f87171', // merah
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'polarArea';
    }
}
