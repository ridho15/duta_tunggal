<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Services\IncomeStatementService;
use App\Models\Cabang;
use Filament\Notifications\Notification;
use App\Exports\GenericViewExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class IncomeStatementPage extends Page
{
    protected static string $view = 'filament.pages.income-statement-page';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Laba Rugi';

    protected static ?int $navigationSort = 6;

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;
    public bool $show_comparison = false;
    public ?string $comparison_start_date = null;
    public ?string $comparison_end_date = null;
    
    // Display options
    public bool $show_only_totals = false;
    public bool $show_parent_accounts = true;
    public bool $show_child_accounts = true;
    public bool $show_zero_balance = false;
    
    // Drill-down state
    public ?int $selected_account_id = null;
    public bool $show_drill_down = false;

    protected IncomeStatementService $service;

    public function boot(IncomeStatementService $service): void
    {
        $this->service = $service;
    }

    public function mount(): void
    {
        // Default to current month
        $this->start_date = request()->query('start', now()->startOfMonth()->format('Y-m-d'));
        $this->end_date = request()->query('end', now()->endOfMonth()->format('Y-m-d'));
        $this->cabang_id = request()->query('cabang_id');
        
        // Default comparison to previous month
        $this->comparison_start_date = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->comparison_end_date = now()->subMonth()->endOfMonth()->format('Y-m-d');
    }

    public function generateReport(): void
    {
        // Validate dates
        if (!$this->start_date || !$this->end_date) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Tanggal mulai dan akhir harus diisi.')
                ->send();
            return;
        }

        if ($this->start_date > $this->end_date) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.')
                ->send();
            return;
        }

        Notification::make()
            ->title('Laporan diperbarui')
            ->success()
            ->body('Laporan Laba Rugi telah diperbarui.')
            ->send();

        $this->dispatch('report-updated');
    }

    public function getIncomeStatementData(): array
    {
        return $this->service->generate([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
        ]);
    }

    public function getComparisonData(): ?array
    {
        if (!$this->show_comparison || !$this->comparison_start_date || !$this->comparison_end_date) {
            return null;
        }

        return $this->service->comparePeriods(
            [
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
            ],
            [
                'start_date' => $this->comparison_start_date,
                'end_date' => $this->comparison_end_date,
            ],
            $this->cabang_id
        );
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
            $data = $this->getIncomeStatementData();
            $comparison = $this->getComparisonData();
            $cabangOptions = $this->getCabangOptions();
            
            $pdf = Pdf::loadView('filament.exports.income-statement-pdf', [
                'data' => $data,
                'comparison' => $comparison,
                'cabang' => $this->cabang_id ? ($cabangOptions[$this->cabang_id] ?? 'Semua Cabang') : 'Semua Cabang',
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
            ])
            ->setPaper('a4', 'portrait');
            
            $filename = 'Laporan_Laba_Rugi_' . date('Y-m-d_His') . '.pdf';
            
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
            $data = $this->getIncomeStatementData();
            $comparison = $this->getComparisonData();
            $cabangOptions = $this->getCabangOptions();
            
            $filename = 'Laporan_Laba_Rugi_' . date('Y-m-d_His') . '.xlsx';
            
            $view = view('filament.exports.income-statement-excel', [
                'data' => $data,
                'comparison' => $comparison,
                'cabang' => $this->cabang_id ? ($cabangOptions[$this->cabang_id] ?? 'Semua Cabang') : 'Semua Cabang',
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
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
    
    public function getDrillDownData(): ?array
    {
        if (!$this->selected_account_id || !$this->show_drill_down) {
            return null;
        }
        
        return $this->service->getAccountJournalEntries(
            $this->selected_account_id,
            $this->start_date,
            $this->end_date,
            $this->cabang_id
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return 'Laporan Laba Rugi (Income Statement)';
    }

    public function getHeading(): string
    {
        return 'Laporan Laba Rugi';
    }
}
