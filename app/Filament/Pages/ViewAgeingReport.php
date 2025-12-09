<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Exports\AgeingReportExport;
use App\Exports\AgeingReportPdfExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ViewAgeingReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static string $view = 'filament.pages.reports.ageing-report';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $title = 'Aging Report';

    protected static ?int $navigationSort = 8;

    public ?string $report_type = 'both';
    public ?string $cabang_id = null;
    public ?string $as_of_date = null;

    public function mount(): void
    {
        $this->as_of_date = now()->format('Y-m-d');
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

    public function refreshData(): void
    {
        // This method can be used to refresh data when filters change
    }

    public function getAgingSummary($type, $bucket): string
    {
        $query = null;

        if ($type === 'receivables') {
            $query = AccountReceivable::where('remaining', '>', 0);
            if ($this->cabang_id) {
                $query->where('cabang_id', $this->cabang_id);
            }
        } elseif ($type === 'payables') {
            $query = AccountPayable::where('remaining', '>', 0);
            if ($this->cabang_id) {
                $query->whereHas('invoice', function($q) {
                    $q->where('cabang_id', $this->cabang_id);
                });
            }
        }

        if (!$query) return 'Rp 0';

        $total = 0;
        $records = $query->with(['ageingSchedule', 'invoice'])->get();

        foreach ($records as $record) {
            $recordBucket = $this->calculateBucket($record);
            if ($recordBucket === $bucket) {
                $total += $record->remaining;
            }
        }

        return 'Rp ' . number_format($total, 0, ',', '.');
    }

    public function calculateExpectedCashFlow($type, $days): float
    {
        $query = null;
        $total = 0;

        if ($type === 'receivables') {
            $query = AccountReceivable::where('remaining', '>', 0);
            if ($this->cabang_id) {
                $query->where('cabang_id', $this->cabang_id);
            }
        } elseif ($type === 'payables') {
            $query = AccountPayable::where('remaining', '>', 0);
            if ($this->cabang_id) {
                $query->whereHas('invoice', function($q) {
                    $q->where('cabang_id', $this->cabang_id);
                });
            }
        }

        if (!$query) return 0;

        $records = $query->with(['ageingSchedule', 'invoice'])->get();
        $futureDate = Carbon::parse($this->as_of_date)->addDays($days);

        foreach ($records as $record) {
            if ($record->invoice && $record->invoice->due_date) {
                $dueDate = Carbon::parse($record->invoice->due_date);
                if ($dueDate->lte($futureDate)) {
                    $total += $record->remaining;
                }
            }
        }

        return $total;
    }

    private function calculateBucket($record): string
    {
        $ageingSchedule = $record->ageingSchedule;
        $daysOutstanding = 0;

        if ($ageingSchedule && $ageingSchedule->days_outstanding) {
            $daysOutstanding = $ageingSchedule->days_outstanding;
        } elseif ($record->invoice && $record->invoice->invoice_date) {
            $invoiceDate = Carbon::parse($record->invoice->invoice_date);
            $daysOutstanding = $invoiceDate->diffInDays(Carbon::parse($this->as_of_date), false);
        }

        if ($daysOutstanding <= 30) return 'Current';
        if ($daysOutstanding <= 60) return '31–60';
        if ($daysOutstanding <= 90) return '61–90';
        return '>90';
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
}