<?php

namespace App\Filament\Pages;

use App\Services\BalanceSheetService;
use App\Services\Reports\FinancialStatementReportService;
use Filament\Pages\Page;

class FinancialStatementPage extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.pages.financial-statement-page';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Financial Statement';

    protected static ?string $navigationParentItem = 'Laporan Keuangan';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'financial-statement';

    // Filter state
    public bool $showPreview = false;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;
    public string $statement_type = 'all';

    protected BalanceSheetService $balanceSheetService;

    public function boot(BalanceSheetService $balanceSheetService): void
    {
        $this->balanceSheetService = $balanceSheetService;
    }

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
        $this->statement_type = $this->normalizeStatementType(request('statement_type', 'all'));
    }

    public function getPreviewUrl(): string
    {
        return route('reports.financial-statement.preview', array_filter([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
            'statement_type' => $this->statement_type,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    public function getExportUrl(string $format): string
    {
        $route = $format === 'pdf'
            ? 'reports.financial-statement.pdf'
            : 'reports.financial-statement.excel';

        return route($route, array_filter([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
            'statement_type' => $this->statement_type,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    public function getStatementData(): array
    {
        if (!$this->showPreview) {
            return [];
        }

        return app(FinancialStatementReportService::class)->generate([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
            'statement_type' => $this->statement_type,
        ]);
    }

    protected function normalizeStatementType(?string $statementType): string
    {
        return in_array($statementType, ['all', 'pl', 'bs', 'cogm'], true) ? $statementType : 'all';
    }
}
