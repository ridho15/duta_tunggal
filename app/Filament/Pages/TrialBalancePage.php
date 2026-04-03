<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use App\Services\TrialBalanceService;
use App\Models\Cabang;

class TrialBalancePage extends Page implements HasForms
{
    use InteractsWithForms;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string $view = 'filament.pages.trial-balance-page';

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Neraca Saldo (Trial Balance)';

    protected static ?string $navigationParentItem = 'Laporan Keuangan';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'trial-balance';

    // Filter state
    public ?string $start_date = null;
    public ?string $end_date   = null;
    public ?int    $cabang_id  = null;
    public bool    $show_zero_balance = false;

    // Preview gate
    public bool $showPreview = false;

    // Cached report data
    protected ?array $reportData = null;

    public function mount(): void
    {
        $this->showPreview = filter_var(request('preview', false), FILTER_VALIDATE_BOOL);

        $this->form->fill([
            'start_date'        => request('start_date', now()->startOfYear()->format('Y-m-d')),
            'end_date'          => request('end_date', now()->format('Y-m-d')),
            'cabang_id'         => request('cabang_id'),
            'show_zero_balance' => filter_var(request('show_zero_balance', false), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Laporan')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('end_date')
                            ->label('Tanggal Akhir')
                            ->required()
                            ->displayFormat('d/m/Y'),
                        Select::make('cabang_id')
                            ->label('Cabang')
                            ->options(function () {
                                return Cabang::all()->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Semua Cabang'),
                        Toggle::make('show_zero_balance')
                            ->label('Tampilkan Akun Saldo Nol'),
                    ])->columns(4),
            ]);
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

            \Filament\Actions\Action::make('print')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->visible(fn () => $this->showPreview)
                ->extraAttributes(['onclick' => 'window.print(); return false;']),
        ];
    }

    public function generateReport(): void
    {
        $data = $this->form->getState();

        if (!$data['start_date'] || !$data['end_date']) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Tanggal mulai dan akhir harus diisi.')
                ->send();
            return;
        }

        if ($data['start_date'] > $data['end_date']) {
            Notification::make()
                ->title('Error')
                ->danger()
                ->body('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.')
                ->send();
            return;
        }

        $this->start_date        = $data['start_date'];
        $this->end_date          = $data['end_date'];
        $this->cabang_id         = $data['cabang_id'] ?? null;
        $this->show_zero_balance = $data['show_zero_balance'] ?? false;
        $this->dispatch('open-report-preview', url: $this->getPreviewUrl());
    }

    public function resetReport(): void
    {
        $this->redirect(url()->current());
    }

    public function getPreviewUrl(): string
    {
        return url()->current() . '?' . http_build_query(array_filter([
            'preview' => 1,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cabang_id' => $this->cabang_id,
            'show_zero_balance' => $this->show_zero_balance ? 1 : 0,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []));
    }

    /**
     * Called from the blade view to get the report data.
     */
    public function getTrialBalanceData(): array
    {
        $service = app(TrialBalanceService::class);
        return $service->generate([
            'start_date'        => $this->start_date,
            'end_date'          => $this->end_date,
            'cabang_id'         => $this->cabang_id,
            'show_zero_balance' => $this->show_zero_balance,
        ]);
    }

    /**
     * Helper to format number in Indonesian style.
     */
    public function fmt(float $value): string
    {
        return number_format(abs($value), 2, ',', '.');
    }
}
