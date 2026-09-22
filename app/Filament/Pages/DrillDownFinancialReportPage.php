<?php

namespace App\Filament\Pages;

use App\Models\ChartOfAccount;
use App\Services\Reports\DrillDownFinancialReportService;
use App\Services\Reports\FinancialStatementReportService;
use Filament\Pages\Page;

class DrillDownFinancialReportPage extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.pages.drill-down-financial-report-page';

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-plus';

    protected static ?string $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Drill Down Financial Report';

    protected static ?string $navigationParentItem = 'Laporan Keuangan';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'drill-down-financial-report';

    // Filter state
    public bool $showPreview = false;
    public string $report_mode = 'journal';
    public string $statement_type = 'all';
    public ?string $account_type = null;  // Asset, Liability, Equity, Revenue, Expense
    public ?int $coa_id = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;

    public function mount(): void
    {
        $this->showPreview = filter_var(request('preview', false), FILTER_VALIDATE_BOOL);
        $this->report_mode = request('report_mode', 'journal');
        $this->statement_type = request('statement_type', 'all');
        $this->account_type = request('account_type');
        $this->coa_id = request('coa_id');
        $this->start_date = request('start_date', now()->startOfMonth()->format('Y-m-d'));
        $this->end_date = request('end_date', now()->endOfMonth()->format('Y-m-d'));
        $this->cabang_id = request('cabang_id');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')
                ->label('Tampilkan Laporan')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(fn () => $this->generateReport()),

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

    public function getPreviewUrl(): string
    {
        if ($this->isFinancialStatementMode()) {
            return route('reports.financial-statement.preview', array_filter([
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'cabang_id' => $this->cabang_id,
                'statement_type' => $this->statement_type,
            ], fn ($value) => $value !== null && $value !== '' && $value !== []));
        }

        return route('reports.drill-down-financial-report.preview', array_filter([
            'report_mode' => $this->report_mode,
            'statement_type' => $this->statement_type,
            'account_type' => $this->account_type,
            'coa_id' => $this->coa_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function updatedAccountType(): void
    {
        // Reset COA selection when account type changes so the stale coa_id
        // (pointing to the previous type's account) does not pollute the new query.
        $this->coa_id = null;
    }

    public function updatedReportMode(): void
    {
        if ($this->isFinancialStatementMode()) {
            $this->account_type = null;
            $this->coa_id = null;
        }
    }

    public function getCoaOptionsProperty(): array
    {
        $query = ChartOfAccount::query()->orderBy('code');
        if ($this->account_type) {
            $query->where('type', $this->account_type);
        }
        return $query->get()
            ->mapWithKeys(fn ($a) => [$a->id => "{$a->code} - {$a->name}"])
            ->toArray();
    }

    public function getStatementTypeOptionsProperty(): array
    {
        return [
            'all' => 'Semua (Laba Rugi + Neraca)',
            'pl' => 'Laba Rugi',
            'bs' => 'Neraca',
            'cogm' => 'Harga Pokok Produksi (COGM)',
        ];
    }

    public function getStatementTypeLabelProperty(): string
    {
        return $this->statementTypeOptions[$this->statement_type] ?? 'Financial Statement';
    }

    public function isFinancialStatementMode(): bool
    {
        return $this->report_mode === 'financial_statement';
    }

    public function getFinancialStatementData(): array
    {
        if (! $this->showPreview || ! $this->isFinancialStatementMode()) {
            return [];
        }

        return app(FinancialStatementReportService::class)->generate([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
            'statement_type' => $this->statement_type,
        ]);
    }

    public function getDrillDownData(): array
    {
        if (!$this->showPreview) {
            return [];
        }

        return app(DrillDownFinancialReportService::class)->generate([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'account_type' => $this->account_type,
            'coa_id' => $this->coa_id,
            'cabang_id' => $this->cabang_id,
        ]);
    }
}
