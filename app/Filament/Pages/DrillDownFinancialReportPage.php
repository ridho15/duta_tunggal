<?php

namespace App\Filament\Pages;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

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
    public ?string $account_type = null;  // Asset, Liability, Equity, Revenue, Expense
    public ?int $coa_id = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;

    public function mount(): void
    {
        $this->showPreview = filter_var(request('preview', false), FILTER_VALIDATE_BOOL);
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
        return static::getUrl() . '?' . http_build_query(array_filter([
            'preview' => 1,
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

    public function getDrillDownData(): array
    {
        if (!$this->showPreview) {
            return [];
        }

        $start = $this->start_date ? Carbon::parse($this->start_date)->startOfDay() : now()->startOfMonth();
        $end = $this->end_date ? Carbon::parse($this->end_date)->endOfDay() : now()->endOfMonth();

        $query = JournalEntry::query()
            ->with('coa')
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('id');

        if ($this->coa_id) {
            $query->where('coa_id', $this->coa_id);
        } elseif ($this->account_type) {
            $query->whereHas('coa', fn ($q) => $q->where('type', $this->account_type));
        }

        if ($this->cabang_id) {
            $query->where('cabang_id', $this->cabang_id);
        }

        $entries = $query->get();

        $grouped = $entries->groupBy('coa_id')->map(function ($lines, $coaId) {
            $coa = $lines->first()->coa;
            $totalDebit = $lines->sum('debit');
            $totalCredit = $lines->sum('credit');
            return [
                'coa' => $coa,
                'lines' => $lines,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'balance' => in_array(optional($coa)->type, ['Asset', 'Expense'])
                    ? ($totalDebit - $totalCredit)
                    : ($totalCredit - $totalDebit),
            ];
        });

        return [
            'grouped' => $grouped->values()->toArray(),
            'total_debit' => $entries->sum('debit'),
            'total_credit' => $entries->sum('credit'),
            'count' => $entries->count(),
        ];
    }
}
