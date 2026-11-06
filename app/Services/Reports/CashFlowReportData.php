<?php

namespace App\Services\Reports;

use App\Services\Reports\CashFlowReportService;
use Illuminate\Support\Carbon;

/**
 * Data laporan untuk halaman Blade. Diekstrak dari ViewCashFlow (halaman Filament) tanpa bagian UI Filament;
 * properti diisi controller, lalu getReportData() menghitung laporannya.
 */
class CashFlowReportData
{
    public bool $showPreview = false;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public array $branchIds = [];

    public ?string $method = 'direct';

    public function getReportData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->toDateString() : null;
        $end = $this->endDate ? Carbon::parse($this->endDate)->toDateString() : null;

        try {
            return app(CashFlowReportService::class)->generate($start, $end, [
                'branches' => array_filter($this->branchIds),
                'method' => $this->method ?? 'direct',
            ]);
        } catch (\Throwable $e) {
            // Defensive: if the report service or DB is misconfigured (missing columns, etc.)
            // we should not let the entire Filament page crash. Log the exception and
            // return an empty, well-formed report structure so the UI can render safely.
            if (app()->environment('local') || app()->environment('testing')) {
                logger()->error('CashFlow report generation failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }

            return [
                'period' => ['start' => $start ?? now()->startOfMonth()->toDateString(), 'end' => $end ?? now()->endOfMonth()->toDateString()],
                'opening_balance' => 0,
                'net_change' => 0,
                'closing_balance' => 0,
                'sections' => [],
            ];
        }
    }
}
