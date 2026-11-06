<?php

namespace App\Services\Reports;

use App\Services\Reports\HppReportService;
use Illuminate\Support\Carbon;

/**
 * Data laporan untuk halaman Blade. Diekstrak dari ViewHpp (halaman Filament) tanpa bagian UI Filament;
 * properti diisi controller, lalu getReportData() menghitung laporannya.
 */
class HppReportData
{
    public bool $showPreview = false;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public array $branchIds = [];

    public function getReportData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->toDateString() : null;
        $end = $this->endDate ? Carbon::parse($this->endDate)->toDateString() : null;

        return app(HppReportService::class)->generate($start, $end, [
            'branches' => array_filter($this->branchIds),
        ]);
    }
}
