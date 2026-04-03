<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\BalanceSheetService;
use App\Models\Cabang;
use Filament\Notifications\Notification;
use App\Exports\GenericViewExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class BalanceSheetPage extends Page
{
    protected static string $view = 'filament.pages.balance-sheet-page';

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Neraca (Balance Sheet) - Legacy';

    protected static ?int $navigationSort = 2;

    // Kept for backward compat; primary Balance Sheet is BalanceSheetResource
    public static function shouldRegisterNavigation(): bool { return false; }

    public ?string $as_of_date = null;
    public ?int $cabang_id = null;
    public bool $show_comparison = false;
    public ?string $comparison_date = null;
    
    // Multi-period selection
    public array $selected_periods = [];
    public bool $use_multi_period = false;
    
    // Display options
    public string $display_level = 'all'; // 'totals_only', 'parent_only', 'all', 'with_zero'
    public bool $show_zero_balance = false;
    
    // Drill-down state
    public ?int $selected_account_id = null;
    public bool $show_drill_down = false;
    public bool $showPreview = false;

    protected BalanceSheetService $service;

    public function boot(BalanceSheetService $service): void
    {
        $this->service = $service;
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

    public function mount(): void
    {
        // Default to end of current month
        $this->as_of_date = request()->query('as_of', now()->endOfMonth()->format('Y-m-d'));
        $this->cabang_id = request()->query('cabang_id');
        $this->showPreview = filter_var(request('preview', false), FILTER_VALIDATE_BOOL);
        
        // Default comparison to end of previous month
        $this->comparison_date = request()->query('comparison_date', now()->subMonth()->endOfMonth()->format('Y-m-d'));
        
        // Initialize multi-period with current date
        $this->selected_periods = (array) request()->query('selected_periods', [$this->as_of_date]);
        
        // Default display options
        $this->display_level = request()->query('display_level', 'all');
        $this->show_zero_balance = filter_var(request('show_zero_balance', false), FILTER_VALIDATE_BOOL);
    }

    public function generateReport(): void
    {
        // Validate date
        if (!$this->as_of_date) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Tanggal neraca harus diisi.')
                ->send();
            return;
        }

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
            'as_of' => $this->as_of_date,
            'cabang_id' => $this->cabang_id,
            'comparison_date' => $this->comparison_date,
            'display_level' => $this->display_level,
            'show_zero_balance' => $this->show_zero_balance ? 1 : 0,
            'selected_periods' => array_filter($this->selected_periods),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    public function getBalanceSheetData(): array
    {
        return $this->service->generate([
            'as_of_date' => $this->as_of_date,
            'cabang_id' => $this->cabang_id,
        ]);
    }

    public function getBalanceAlertData(): ?array
    {
        if (!$this->showPreview) {
            return null;
        }

        $data = $this->getBalanceSheetData();
        if ($data['is_balanced']) {
            return null;
        }

        return [
            'title' => 'Neraca Tidak Seimbang',
            'difference' => (float) $data['difference'],
            'message' => 'Total aset tidak sama dengan total kewajiban dan ekuitas. Laporan sekarang menampilkan selisih ledger aktual tanpa penyesuaian otomatis laba ditahan.',
        ];
    }

    public function getMultiPeriodData(): array
    {
        if (!$this->use_multi_period || empty($this->selected_periods)) {
            return [];
        }

        $multiPeriodData = [];
        foreach ($this->selected_periods as $period) {
            $multiPeriodData[$period] = $this->service->generate([
                'as_of_date' => $period,
                'cabang_id' => $this->cabang_id,
                'display_level' => $this->display_level,
                'show_zero_balance' => $this->show_zero_balance,
            ]);
        }
        
        return $multiPeriodData;
    }

    public function addPeriod(): void
    {
        $newDate = now()->endOfMonth()->format('Y-m-d');
        if (!in_array($newDate, $this->selected_periods)) {
            $this->selected_periods[] = $newDate;
            $this->dispatch('periods-updated');
        }
    }

    public function removePeriod(int $index): void
    {
        if (isset($this->selected_periods[$index])) {
            unset($this->selected_periods[$index]);
            $this->selected_periods = array_values($this->selected_periods);
            $this->dispatch('periods-updated');
        }
    }

    public function updatePeriod(int $index, string $date): void
    {
        if (isset($this->selected_periods[$index])) {
            $this->selected_periods[$index] = $date;
            $this->dispatch('periods-updated');
        }
    }

    public function getComparisonData(): ?array
    {
        if (!$this->show_comparison || !$this->comparison_date) {
            return null;
        }

        $comparison = $this->service->comparePeriods(
            [
                'as_of_date' => $this->as_of_date,
                'display_level' => $this->display_level,
                'show_zero_balance' => $this->show_zero_balance,
            ],
            [
                'as_of_date' => $this->comparison_date,
                'display_level' => $this->display_level,
                'show_zero_balance' => $this->show_zero_balance,
            ],
            $this->cabang_id
        );

        // Add the comparison date for the view to display
        $comparison['as_of_date'] = $this->comparison_date;

        return $comparison;
    }

    public function getCabangOptions(): array
    {
        return Cabang::query()
            ->whereNull('deleted_at')
            ->orderBy('kode')
            ->get()
            ->mapWithKeys(fn($c) => [$c->id => $c->kode . ' - ' . $c->nama])
            ->toArray();
    }

    public function exportPdf()
    {
        try {
            $data = $this->getBalanceSheetData();
            $comparison = $this->getComparisonData();
            $cabangOptions = $this->getCabangOptions();
            
            $pdf = Pdf::loadView('filament.exports.balance-sheet-pdf', [
                'data' => $data,
                'comparison' => $comparison,
                'cabang' => $this->cabang_id ? ($cabangOptions[$this->cabang_id] ?? 'Semua Cabang') : 'Semua Cabang',
                'as_of_date' => $this->as_of_date,
            ])
            ->setPaper('a4', 'portrait');
            
            $filename = 'Neraca_' . date('Y-m-d_His') . '.pdf';
            
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->stream();
            }, $filename);
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export PDF Gagal')
                ->danger()
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();
        }
    }

    public function exportExcel()
    {
        try {
            $data = $this->getBalanceSheetData();
            $comparison = $this->getComparisonData();
            $cabangOptions = $this->getCabangOptions();
            
            $filename = 'Neraca_' . date('Y-m-d_His') . '.xlsx';
            
            $view = view('filament.exports.balance-sheet-excel', [
                'data' => $data,
                'comparison' => $comparison,
                'cabang' => $this->cabang_id ? ($cabangOptions[$this->cabang_id] ?? 'Semua Cabang') : 'Semua Cabang',
                'as_of_date' => $this->as_of_date,
            ]);
            
            return Excel::download(
                new GenericViewExport($view),
                $filename
            );
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Excel Gagal')
                ->danger()
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();
        }
    }

    public function print(): void
    {
        $this->dispatch('print-report');
    }
    
    public function showAccountDetails(int $accountId): void
    {
        $this->selected_account_id = $accountId;
        $this->show_drill_down = true;
        $this->dispatch('open-drill-down-modal');
    }
    
    public function closeDrillDown(): void
    {
        $this->show_drill_down = false;
        $this->selected_account_id = null;
    }
    
    public function getComparisonProperty(): ?array
    {
        return $this->getComparisonData();
    }

    public function getDrillDownData(): ?array
    {
        if (!$this->selected_account_id || !$this->show_drill_down) {
            return null;
        }
        
        return $this->service->getAccountJournalEntries(
            $this->selected_account_id,
            $this->as_of_date,
            $this->cabang_id
        );
    }



    public function getTitle(): string
    {
        return 'Neraca (Balance Sheet)';
    }

    public function getHeading(): string
    {
        return 'Neraca';
    }

    protected function notifyIfBalanceSheetHasIssues(array $data): void
    {
        if ($data['is_balanced']) {
            return;
        }

        Notification::make()
            ->title('Neraca Tidak Seimbang')
            ->warning()
            ->persistent()
            ->body('Selisih neraca saat ini Rp ' . number_format(abs((float) $data['difference']), 0, ',', '.') . '. Periksa jurnal sumber karena laporan tidak lagi memaksa balance lewat laba ditahan.')
            ->send();
    }
}
