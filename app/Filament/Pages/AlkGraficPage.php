<?php

namespace App\Filament\Pages;

use App\Services\Reports\AlkGrafikReportService;
use Filament\Pages\Page;

/**
 * ALK Grafik = Analisis Laporan Keuangan (Financial Statement Analysis with Charts)
 * Uses a shared report payload for admin preview and exports.
 */
class AlkGraficPage extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.pages.alk-grafic-page';

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'ALK Grafik';

    protected static ?string $navigationParentItem = 'Laporan Keuangan';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'alk-grafik';

    // Filter state
    public bool $showPreview = false;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')
                ->label('Tampilkan Laporan')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(fn () => $this->generateReport()),

            \Filament\Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->url(fn () => $this->getExportUrl('excel')),

            \Filament\Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-m-document-text')
                ->color('danger')
                ->url(fn () => $this->getExportUrl('pdf'))
                ->openUrlInNewTab(),

            \Filament\Actions\Action::make('reset')
                ->label('Reset')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn () => $this->showPreview)
                ->action(fn () => $this->resetReport()),
        ];
    }

    public function generateReport(): void
    {
        $this->dispatch('open-report-preview', url: $this->getPreviewUrl());
    }

    public function resetReport(): void
    {
        $this->showPreview = false;
        $this->redirect(static::getUrl());
    }

    public function mount(): void
    {
        $this->showPreview = filter_var(request('preview', false), FILTER_VALIDATE_BOOL);
        $this->start_date = request('start_date', now()->startOfMonth()->format('Y-m-d'));
        $this->end_date = request('end_date', now()->endOfMonth()->format('Y-m-d'));
        $this->cabang_id = request('cabang_id');
    }

    public function getPreviewUrl(bool $embedded = false): string
    {
        return route('reports.alk-grafik.preview', array_filter([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
            'embedded' => $embedded ? 1 : null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    public function getExportUrl(string $format): string
    {
        $route = $format === 'pdf'
            ? 'reports.alk-grafik.pdf'
            : 'reports.alk-grafik.excel';

        return route($route, array_filter([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    public function getReportData(): array
    {
        if (! $this->showPreview) {
            return [];
        }

        return app(AlkGrafikReportService::class)->generate([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
        ]);
    }
}
