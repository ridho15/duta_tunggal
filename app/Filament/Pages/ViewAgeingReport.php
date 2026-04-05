<?php

namespace App\Filament\Pages;

use App\Enums\PaymentStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Exports\AgeingReportExport;
use App\Exports\AgeingReportPdfExport;
use App\Services\Reports\AgeingReportService;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ViewAgeingReport extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static string $view = 'filament.pages.reports.ageing-report';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $title = 'Aging Report';

    protected static ?int $navigationSort = 8;

    public ?string $report_type = 'both';
    public ?string $cabang_id = null;
    public ?string $as_of_date = null;
    public bool $showPreview = true;

    public function mount(): void
    {
        $this->as_of_date = now()->format('Y-m-d');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Report Filters')
                    ->schema([
                        Select::make('report_type')
                            ->label('Report Type')
                            ->options([
                                'receivables' => 'Account Receivables',
                                'payables' => 'Account Payables',
                                'both' => 'Both AR & AP',
                            ])
                            ->default('both')
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshData()),

                        Select::make('cabang_id')
                            ->label('Branch')
                            ->options(Cabang::pluck('nama', 'id'))
                            ->placeholder('All Branches')
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshData()),

                        DatePicker::make('as_of_date')
                            ->label('As of Date')
                            ->default(now())
                            ->live()
                            ->afterStateUpdated(fn () => $this->refreshData()),
                    ])
                    ->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (Builder $query) {
                if ($this->report_type === 'receivables') {
                    $query = AccountReceivable::query()
                        ->with(['customer', 'invoice', 'ageingSchedule', 'cabang'])
                        ->where('remaining', '>', 0);

                    if ($this->cabang_id) {
                        $query->where('cabang_id', $this->cabang_id);
                    }

                    return $query;
                } elseif ($this->report_type === 'payables') {
                    $query = AccountPayable::query()
                        ->with(['supplier', 'invoice', 'ageingSchedule'])
                        ->where('remaining', '>', 0);

                    if ($this->cabang_id) {
                        $query->whereHas('invoice', function($q) {
                            $q->where('cabang_id', $this->cabang_id);
                        });
                    }

                    return $query;
                } else {
                    // For 'both', show AR records (AP will be shown separately in view)
                    $query = AccountReceivable::query()
                        ->with(['customer', 'invoice', 'ageingSchedule', 'cabang'])
                        ->where('remaining', '>', 0);

                    if ($this->cabang_id) {
                        $query->where('cabang_id', $this->cabang_id);
                    }

                    return $query;
                }
            })
            ->columns([
                TextColumn::make('customer_supplier_name')
                    ->label('Customer/Supplier')
                    ->getStateUsing(function ($record) {
                        if ($record instanceof AccountReceivable) {
                            return $record->customer->name ?? '-';
                        } else {
                            return $record->supplier->perusahaan ?? '-';
                        }
                    })
                    ->searchable(),

                TextColumn::make('invoice.no_invoice')
                    ->label('Invoice')
                    ->searchable(),

                TextColumn::make('invoice.invoice_date')
                    ->label('Invoice Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('invoice.due_date')
                    ->label('Due Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('days_outstanding')
                    ->label('Days Outstanding')
                    ->getStateUsing(function ($record) {
                        return $this->ageingReportService()->resolveDaysOutstanding($record, $this->as_of_date);
                    }),

                TextColumn::make('remaining')
                    ->label('Remaining Amount')
                    ->rupiah()
                    ->sortable(),

                BadgeColumn::make('aging_bucket')
                    ->label('Aging Bucket')
                    ->getStateUsing(function ($record) {
                        return $this->ageingReportService()->resolveBucketLabel($record, $this->as_of_date);
                    })
                    ->colors([
                        'success' => 'Current',
                        'warning' => '31–60',
                        'orange' => '61–90',
                        'danger' => '>90',
                    ]),

                BadgeColumn::make('status')
                    ->colors([
                        'success' => PaymentStatus::PAID->value,
                        'warning' => fn ($state) => !in_array($state, [PaymentStatus::PAID->value]),
                    ]),
            ])
            ->defaultSort('invoice.due_date', 'asc')
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }

    public function refreshData(): void
    {
        // This method can be used to refresh data when filters change
    }

    public function getAgingSummary($type, $bucket): string
    {
        $records = $type === 'receivables'
            ? $this->ageingReportService()->getReceivableRecords($this->ageingFilters())
            : $this->ageingReportService()->getPayableRecords($this->ageingFilters());

        $total = $records->where('aging_bucket_computed', $bucket)->sum('remaining');

        return 'Rp ' . number_format($total, 0, ',', '.');
    }

    public function calculateExpectedCashFlow($type, $days): float
    {
        $projection = $this->ageingReportService()->projectCashFlow($this->ageingFilters(), $days);

        return $type === 'receivables'
            ? $projection['receivables']
            : $projection['payables'];
    }

    protected function getActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $export = new AgeingReportExport(
                        $this->as_of_date,
                        $this->cabang_id,
                        $this->report_type
                    );

                    return Excel::download($export, 'aging-report-' . now()->format('Y-m-d') . '.xlsx');
                }),

            Action::make('export_pdf')
                ->label('Export to PDF')
                ->icon('heroicon-o-document-text')
                ->color('danger')
                ->action(function () {
                    $export = new AgeingReportPdfExport(
                        $this->as_of_date,
                        $this->cabang_id,
                        $this->report_type
                    );

                    $pdf = $export->generatePdf();

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'ageing-report-' . now()->format('Y-m-d') . '.pdf');
                }),
        ];
    }

    private function ageingFilters(): array
    {
        return [
            'as_of_date' => $this->as_of_date,
            'cabang_id' => $this->cabang_id,
        ];
    }

    private function ageingReportService(): AgeingReportService
    {
        return app(AgeingReportService::class);
    }
}
