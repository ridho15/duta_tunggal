<?php

namespace App\Filament\Pages;

use App\Services\ProfitLossMultiDivisionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;

class ProfitLossMultiDivisionPage extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $navigationIcon    = 'heroicon-o-table-cells';
    protected static ?string $navigationLabel   = 'Laba Rugi Per Divisi';
    protected static ?string $navigationGroup   = 'Finance - Laporan';
    protected static ?string $navigationParentItem = 'Laporan Keuangan';
    protected static ?int    $navigationSort    = 4;
    protected static string  $view              = 'filament.pages.profit-loss-multi-division';
    protected static ?string $slug              = 'profit-loss-multi-division';

    // ─── Form state ───────────────────────────────────────────────────────────

    public ?string $startDate  = null;
    public ?string $endDate    = null;
    public array   $cabangIds  = [];
    public bool    $showReport = false;

    public function mount(): void
    {
        $this->startDate = now()->startOfYear()->format('Y-m-d');
        $this->endDate   = now()->endOfYear()->format('Y-m-d');
        $this->showReport = filter_var(request('preview', false), FILTER_VALIDATE_BOOL);
    }

    // ─── Header actions ───────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Tampilkan Laporan')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(fn () => $this->generateReport()),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => $this->showReport)
                ->url(fn () => $this->getExportUrl())
                ->openUrlInNewTab(),

            Action::make('reset')
                ->label('Reset')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn () => $this->showReport)
                ->action(fn () => $this->resetReport()),
        ];
    }

    // ─── Form schema ──────────────────────────────────────────────────────────

    protected function getFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Section::make('Filter Laporan')
                ->columns(4)
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Tanggal Mulai')
                        ->required()
                        ->reactive(),

                    DatePicker::make('endDate')
                        ->label('Tanggal Selesai')
                        ->required()
                        ->reactive(),

                    Select::make('cabangIds')
                        ->label('Divisi / Cabang')
                        ->placeholder('Semua Divisi')
                        ->options(\App\Models\Cabang::query()->orderBy('kode')->pluck('nama', 'id'))
                        ->multiple()
                        ->searchable()
                        ->reactive(),
                ]),
        ];
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    public function generateReport(): void
    {
        $this->showReport = true;
        $this->dispatch('open-report-preview', url: $this->getPreviewUrl());
    }

    public function resetReport(): void
    {
        $this->showReport = false;
        $this->redirect(static::getUrl());
    }

    public function getPreviewUrl(): string
    {
        return route('reports.profit-loss-multi-division.preview', array_filter([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'cabangIds' => array_filter($this->cabangIds ?? []),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    public function getExportUrl(): string
    {
        return route('reports.profit-loss-multi-division.excel', array_filter([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'cabangIds' => array_filter($this->cabangIds ?? []),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    // ─── Report data ──────────────────────────────────────────────────────────

    public function getReportData(): array
    {
        $service = new ProfitLossMultiDivisionService();

        return $service->generate(
            $this->startDate ?? now()->startOfYear()->format('Y-m-d'),
            $this->endDate   ?? now()->endOfYear()->format('Y-m-d'),
            $this->cabangIds ?? []
        );
    }
}
