<?php

namespace App\Filament\Pages;

use App\Exports\GenericViewExport;
use App\Models\Cabang;
use App\Services\Reports\JournalConsolidationReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Journal List of Consolidation
 * Aggregates journal entries across all branches (cabang) in a consolidated view.
 */
class JournalConsolidationPage extends Page implements HasForms
{
    use InteractsWithForms;

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

        $this->form->fill([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'branch_ids' => $this->branch_ids,
            'journal_type' => $this->journal_type,
            'group_by_branch' => $this->group_by_branch,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Filter Konsolidasi Jurnal')
                    ->description('Pilih periode, tipe jurnal, dan cabang untuk preview atau export laporan konsolidasi.')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->default(now()->startOfMonth()),

                        DatePicker::make('end_date')
                            ->label('Tanggal Akhir')
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->default(now()->endOfMonth()),

                        Select::make('journal_type')
                            ->label('Jenis Jurnal')
                            ->options($this->getJournalTypeOptionsProperty())
                            ->searchable()
                            ->preload()
                            ->placeholder('Semua Tipe'),

                        Select::make('group_by_branch')
                            ->label('Tampilan')
                            ->options([
                                1 => 'Dikelompokkan per Cabang',
                                0 => 'Konsolidasi Semua Cabang',
                            ])
                            ->default(1)
                            ->native(false),

                        Select::make('branch_ids')
                            ->label('Cabang')
                            ->multiple()
                            ->options($this->getBranchOptionsProperty())
                            ->searchable()
                            ->preload()
                            ->placeholder('Semua Cabang')
                            ->helperText('Kosongkan bila ingin menampilkan semua cabang')
                            ->getSearchResultsUsing(function (string $search): array {
                                return Cabang::query()
                                    ->where(function ($query) use ($search) {
                                        $query->where('nama', 'like', "%{$search}%")
                                            ->orWhere('kode', 'like', "%{$search}%");
                                    })
                                    ->orderBy('kode')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Cabang $cabang) => [$cabang->id => "({$cabang->kode}) {$cabang->nama}"])
                                    ->toArray();
                            })
                            ->getOptionLabelsUsing(function (array $values): array {
                                if ($values === []) {
                                    return [];
                                }

                                return Cabang::query()
                                    ->whereIn('id', $values)
                                    ->orderBy('kode')
                                    ->get()
                                    ->mapWithKeys(fn (Cabang $cabang) => [$cabang->id => "({$cabang->kode}) {$cabang->nama}"])
                                    ->toArray();
                            }),
                    ])
                    ->columns(4),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')
                ->label('Tampilkan Konsolidasi')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(fn () => $this->generateReport()),

            \Filament\Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->export('excel')),

            \Filament\Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-m-document-text')
                ->color('danger')
                ->action(fn () => $this->export('pdf')),

            \Filament\Actions\Action::make('reset')
                ->label('Reset')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->action(fn () => $this->resetReport()),
        ];
    }

    public function generateReport(): void
    {
        $this->syncFiltersFromForm();

        if (! $this->start_date || ! $this->end_date) {
            Notification::make()
                ->title('Filter belum lengkap')
                ->danger()
                ->body('Tanggal mulai dan tanggal akhir harus diisi.')
                ->send();

            return;
        }

        if ($this->start_date > $this->end_date) {
            Notification::make()
                ->title('Rentang tanggal tidak valid')
                ->danger()
                ->body('Tanggal mulai tidak boleh lebih besar dari tanggal akhir.')
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
        $this->syncFiltersFromForm();

        return route('reports.journal-consolidation.preview', array_filter([
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

        return app(JournalConsolidationReportService::class)->generate($this->reportFilters());
    }

    public function export(string $format = 'excel')
    {
        $this->syncFiltersFromForm();

        $report = app(JournalConsolidationReportService::class)->generate($this->reportFilters());
        $selectedBranches = $this->getSelectedBranchNames();
        $filename = 'journal-consolidation-' . now()->format('Ymd_His');

        $view = view('exports.journal-consolidation', [
            'report' => $report,
            'selectedBranches' => $selectedBranches,
        ]);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.journal-consolidation', [
                'report' => $report,
                'selectedBranches' => $selectedBranches,
            ])
                ->setPaper('a4', 'landscape')
                ->setOptions([
                    'defaultFont' => 'DejaVu Sans',
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                ]);

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, $filename . '.pdf');
        }

        return Excel::download(new GenericViewExport($view), $filename . '.xlsx');
    }

    public function getBranchOptionsProperty(): array
    {
        return Cabang::query()
            ->orderBy('kode')
            ->get()
            ->mapWithKeys(fn (Cabang $cabang) => [$cabang->id => "({$cabang->kode}) {$cabang->nama}"])
            ->toArray();
    }

    public function getJournalTypeOptionsProperty(): array
    {
        return [
            'INV' => 'Invoice',
            'PAY' => 'Payment',
            'REC' => 'Receipt',
            'JV' => 'Journal Voucher',
            'REV' => 'Reversal',
            'SALES' => 'Sales',
            'PURCH' => 'Purchase',
            'MANU' => 'Manufacturing',
            'manual' => 'Manual',
        ];
    }

    public function getSelectedBranchNames(): array
    {
        if ($this->branch_ids === []) {
            return [];
        }

        return Cabang::query()
            ->whereIn('id', $this->branch_ids)
            ->orderBy('nama')
            ->pluck('nama')
            ->toArray();
    }

    protected function reportFilters(): array
    {
        return [
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'branch_ids' => $this->branch_ids,
            'journal_type' => $this->journal_type,
            'group_by_branch' => $this->group_by_branch,
        ];
    }

    protected function syncFiltersFromForm(): void
    {
        $data = $this->form->getState();

        $this->start_date = $data['start_date'] ?? $this->start_date;
        $this->end_date = $data['end_date'] ?? $this->end_date;
        $this->branch_ids = array_values(array_filter((array) ($data['branch_ids'] ?? $this->branch_ids), fn ($value) => $value !== null && $value !== ''));
        $this->journal_type = $data['journal_type'] ?? $this->journal_type;
        $this->group_by_branch = filter_var($data['group_by_branch'] ?? $this->group_by_branch, FILTER_VALIDATE_BOOL);
    }
}
