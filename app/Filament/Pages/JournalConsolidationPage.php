<?php

namespace App\Filament\Pages;

use App\Models\JournalEntry;
use App\Models\Cabang;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Journal List of Consolidation
 * Aggregates journal entries across all branches (cabang) in a consolidated view.
 */
class JournalConsolidationPage extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.pages.journal-consolidation-page';

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Journal List of Consolidation';

    protected static ?string $navigationParentItem = 'Laporan Keuangan';

    protected static ?int $navigationSort = 13;

    protected static ?string $slug = 'journal-consolidation';

    // Filter state
    public bool $showPreview = false;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public array $branch_ids = [];
    public ?string $journal_type = null;
    public bool $group_by_branch = true;

    public function mount(): void
    {
        $this->showPreview = filter_var(request('preview', false), FILTER_VALIDATE_BOOL);
        $this->start_date = request('start_date', now()->startOfMonth()->format('Y-m-d'));
        $this->end_date = request('end_date', now()->endOfMonth()->format('Y-m-d'));
        $this->branch_ids = (array) request('branch_ids', []);
        $this->journal_type = request('journal_type');
        $this->group_by_branch = filter_var(request('group_by_branch', true), FILTER_VALIDATE_BOOL);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')
                ->label('Tampilkan Konsolidasi')
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
        $this->redirect(static::getUrl());
    }

    public function getPreviewUrl(): string
    {
        return static::getUrl() . '?' . http_build_query(array_filter([
            'preview' => 1,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'branch_ids' => array_filter($this->branch_ids),
            'journal_type' => $this->journal_type,
            'group_by_branch' => $this->group_by_branch ? 1 : 0,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    public function getConsolidationData(): array
    {
        if (!$this->showPreview) {
            return [];
        }

        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->endOfDay();

        $query = JournalEntry::query()
            ->with(['coa', 'cabang'])
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('transaction_id')
            ->orderBy('id');

        if (!empty($this->branch_ids)) {
            $query->whereIn('cabang_id', $this->branch_ids);
        }

        if ($this->journal_type) {
            $query->where('journal_type', $this->journal_type);
        }

        $entries = $query->get();

        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');

        if ($this->group_by_branch) {
            $grouped = $entries->groupBy('cabang_id')->map(function (Collection $lines, $cabangId) {
                $cabang = $lines->first()->cabang ?? (object)['nama' => 'Tidak Diketahui'];
                return [
                    'cabang_name' => $cabang->nama ?? 'Tanpa Cabang',
                    'entries'     => $lines,
                    'total_debit' => $lines->sum('debit'),
                    'total_credit' => $lines->sum('credit'),
                    'balance'     => $lines->sum('debit') - $lines->sum('credit'),
                ];
            })->values()->toArray();
        } else {
            $grouped = [
                [
                    'cabang_name'  => 'Semua Cabang (Konsolidasi)',
                    'entries'      => $entries,
                    'total_debit'  => $totalDebit,
                    'total_credit' => $totalCredit,
                    'balance'      => $totalDebit - $totalCredit,
                ]
            ];
        }

        // Consolidation summary by COA
        $coaSummary = $entries->groupBy('coa_id')->map(function (Collection $lines) {
            $coa = $lines->first()->coa;
            return [
                'coa'          => $coa,
                'total_debit'  => $lines->sum('debit'),
                'total_credit' => $lines->sum('credit'),
                'balance'      => $lines->sum('debit') - $lines->sum('credit'),
            ];
        })->sortBy(fn ($item) => optional($item['coa'])->code)->values()->toArray();

        return [
            'grouped'      => $grouped,
            'coa_summary'  => $coaSummary,
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced'     => abs($totalDebit - $totalCredit) < 0.01,
            'count'        => $entries->count(),
            'period'       => $start->format('d M Y') . ' s/d ' . $end->format('d M Y'),
        ];
    }

    public function getBranchOptionsProperty(): array
    {
        return Cabang::all()->mapWithKeys(fn ($c) => [$c->id => $c->nama])->toArray();
    }

    public function getJournalTypeOptionsProperty(): array
    {
        return [
            'INV'    => 'Invoice',
            'PAY'    => 'Payment',
            'REC'    => 'Receipt',
            'JV'     => 'Journal Voucher',
            'REV'    => 'Reversal',
            'SALES'  => 'Sales',
            'PURCH'  => 'Purchase',
            'MANU'   => 'Manufacturing',
        ];
    }
}
