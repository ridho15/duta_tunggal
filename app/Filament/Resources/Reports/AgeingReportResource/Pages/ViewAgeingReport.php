<?php

namespace App\Filament\Resources\Reports\AgeingReportResource\Pages;

use App\Filament\Resources\Reports\AgeingReportResource;
use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\Cabang;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AgeingReportExport;
use App\Exports\AgeingReportPdfExport;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;

class ViewAgeingReport extends Page
{
    protected static string $resource = AgeingReportResource::class;
    protected static string $view = 'filament.pages.reports.ageing-report';

    public ?string $as_of_date = null;
    public ?string $cabang_id = null;
    public ?string $report_type = 'receivables'; // 'receivables', 'payables', 'both'

    protected function getFormSchema(): array
    {
        return [
            Section::make('Report Filters')
                ->columns(3)
                ->schema([
                    DatePicker::make('as_of_date')
                        ->label('As of Date')
                        ->default(now())
                        ->required(),

                    Select::make('cabang_id')
                        ->label('Branch')
                        ->options(Cabang::pluck('nama', 'id'))
                        ->searchable()
                        ->visible(fn() => auth()->user()->hasRole('super_admin')),

                    Radio::make('report_type')
                        ->label('Report Type')
                        ->options([
                            'receivables' => 'Account Receivables',
                            'payables' => 'Account Payables',
                            'both' => 'Both AR & AP',
                        ])
                        ->default('receivables')
                        ->inline()
                        ->required(),
                ]),

            Section::make('Summary')
                ->columns(2)
                ->schema([
                    Card::make()
                        ->schema([
                            Placeholder::make('total_receivables')
                                ->label('Total Receivables')
                                ->content(function () {
                                    $query = AccountReceivable::query();
                                    if ($this->cabang_id) {
                                        $query->where('cabang_id', $this->cabang_id);
                                    }
                                    return 'Rp ' . number_format($query->sum('remaining'), 0, ',', '.');
                                }),

                            Placeholder::make('receivables_count')
                                ->label('Number of Receivables')
                                ->content(function () {
                                    $query = AccountReceivable::query();
                                    if ($this->cabang_id) {
                                        $query->where('cabang_id', $this->cabang_id);
                                    }
                                    return $query->where('remaining', '>', 0)->count();
                                }),
                        ])->columns(2),

                    Card::make()
                        ->schema([
                            Placeholder::make('total_payables')
                                ->label('Total Payables')
                                ->content(function () {
                                    $query = AccountPayable::query();
                                    if ($this->cabang_id) {
                                        $query->whereHas('invoice', function ($q) {
                                            $q->where('cabang_id', $this->cabang_id);
                                        });
                                    }
                                    return 'Rp ' . number_format($query->sum('remaining'), 0, ',', '.');
                                }),

                            Placeholder::make('payables_count')
                                ->label('Number of Payables')
                                ->content(function () {
                                    $query = AccountPayable::query();
                                    if ($this->cabang_id) {
                                        $query->whereHas('invoice', function ($q) {
                                            $q->where('cabang_id', $this->cabang_id);
                                        });
                                    }
                                    return $query->where('remaining', '>', 0)->count();
                                }),
                        ])->columns(2),
                ]),

            Section::make('Aging Summary')
                ->schema([
                    Grid::make(4)
                        ->columns(4)
                        ->schema([
                            Placeholder::make('current_ar')
                                ->label('Current (0-30 days)')
                                ->content(function () {
                                    return $this->getAgingSummary('receivables', 'Current');
                                }),
                            Placeholder::make('31_60_ar')
                                ->label('31-60 days')
                                ->content(function () {
                                    return $this->getAgingSummary('receivables', '31–60');
                                }),

                            Placeholder::make('61_90_ar')
                                ->label('61-90 days')
                                ->content(function () {
                                    return $this->getAgingSummary('receivables', '61–90');
                                }),

                            Placeholder::make('over_90_ar')
                                ->label('Over 90 days')
                                ->content(function () {
                                    return $this->getAgingSummary('receivables', '>90');
                                }),
                        ]),
                ]),

            Section::make('Cash Flow Impact')
                ->columns(2)
                ->schema([
                    Section::make()
                    ->columns(2)
                        ->schema([
                            Placeholder::make('expected_cash_inflow')
                                ->label('Expected Cash Inflow (Next 30 days)')
                                ->content(function () {
                                    $amount = $this->calculateExpectedCashFlow('receivables', 30);
                                    return 'Rp ' . number_format($amount, 0, ',', '.');
                                }),

                            Placeholder::make('overdue_receivables')
                                ->label('Overdue Receivables')
                                ->content(function () {
                                    $query = AccountReceivable::whereHas('invoice', function ($q) {
                                        $q->where('due_date', '<', now());
                                    });
                                    if ($this->cabang_id) {
                                        $query->where('cabang_id', $this->cabang_id);
                                    }
                                    $amount = $query->sum('remaining');
                                    return 'Rp ' . number_format($amount, 0, ',', '.');
                                }),
                        ]),

                    Section::make()
                        ->columns(2)
                        ->schema([
                            Placeholder::make('expected_cash_outflow')
                                ->label('Expected Cash Outflow (Next 30 days)')
                                ->content(function () {
                                    $amount = $this->calculateExpectedCashFlow('payables', 30);
                                    return 'Rp ' . number_format($amount, 0, ',', '.');
                                }),

                            Placeholder::make('overdue_payables')
                                ->label('Overdue Payables')
                                ->content(function () {
                                    $query = AccountPayable::whereHas('invoice', function ($q) {
                                        $q->where('due_date', '<', now());
                                    });
                                    if ($this->cabang_id) {
                                        $query->whereHas('invoice', function ($q) {
                                            $q->where('cabang_id', $this->cabang_id);
                                        });
                                    }
                                    $amount = $query->sum('remaining');
                                    return 'Rp ' . number_format($amount, 0, ',', '.');
                                }),
                        ]),
                ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return Excel::download(
                        new AgeingReportExport($this->as_of_date ?? now(), $this->cabang_id, $this->report_type),
                        'ageing-report-' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),
            Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-m-document-text')
                ->color('danger')
                ->action(function () {
                    $export = new AgeingReportPdfExport($this->as_of_date ?? now(), $this->cabang_id, $this->report_type);
                    $pdf = $export->generatePdf();

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'ageing-report-' . now()->format('Y-m-d') . '.pdf');
                }),
        ];
    }

    private function getAgingSummary($type, $bucket)
    {
        $query = $type === 'receivables' ? AccountReceivable::query() : AccountPayable::query();

        if ($this->cabang_id) {
            if ($type === 'receivables') {
                $query->where('cabang_id', $this->cabang_id);
            } else {
                $query->whereHas('invoice', function ($q) {
                    $q->where('cabang_id', $this->cabang_id);
                });
            }
        }

        $amount = $query->whereHas('ageingSchedule', function ($q) use ($bucket) {
            $q->where('bucket', $bucket);
        })->orWhere(function ($q) use ($bucket) {
            $q->whereDoesntHave('ageingSchedule')
                ->whereHas('invoice', function ($invoiceQuery) use ($bucket) {
                    $days = match ($bucket) {
                        'Current' => 30,
                        '31–60' => 60,
                        '61–90' => 90,
                        '>90' => PHP_INT_MAX,
                    };

                    $invoiceQuery->whereRaw('DATEDIFF(NOW(), invoice_date) <= ?', [$days]);
                });
        })->sum('remaining');

        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function calculateExpectedCashFlow($type, $days)
    {
        $futureDate = now()->addDays($days);
        $query = $type === 'receivables' ? AccountReceivable::query() : AccountPayable::query();

        if ($this->cabang_id) {
            if ($type === 'receivables') {
                $query->where('cabang_id', $this->cabang_id);
            } else {
                $query->whereHas('invoice', function ($q) {
                    $q->where('cabang_id', $this->cabang_id);
                });
            }
        }

        return $query->whereHas('invoice', function ($q) use ($futureDate) {
            $q->where('due_date', '<=', $futureDate)
                ->where('due_date', '>=', now());
        })->sum('remaining');
    }
}
