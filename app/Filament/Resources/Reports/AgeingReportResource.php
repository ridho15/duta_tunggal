<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\AgeingReportResource\Pages;
use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\Cabang;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Actions\ExportAction;
use App\Exports\AgeingReportExport;
use App\Exports\AgeingReportPdfExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class AgeingReportResource extends Resource
{
    protected static ?string $model = AccountReceivable::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Aging Report (AR/AP)';

    protected static ?int $navigationSort = 25;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Customer/Supplier')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        if ($record instanceof AccountReceivable) {
                            return $record->customer->name ?? '-';
                        } elseif ($record instanceof AccountPayable) {
                            return $record->supplier->name ?? '-';
                        }
                        return '-';
                    }),

                TextColumn::make('invoice.no_invoice')
                    ->label('Invoice Number')
                    ->sortable()
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
                        $ageingSchedule = $record->ageingSchedule;
                        if ($ageingSchedule && $ageingSchedule->days_outstanding) {
                            return $ageingSchedule->days_outstanding;
                        }

                        // Calculate if not exists
                        if ($record->invoice && $record->invoice->invoice_date) {
                            $invoiceDate = Carbon::parse($record->invoice->invoice_date);
                            return $invoiceDate->diffInDays(now(), false);
                        }
                        return 0;
                    })
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total Amount')
                    ->money('IDR')
                    ->summarize(Sum::make()->money('IDR')),

                TextColumn::make('paid')
                    ->label('Paid Amount')
                    ->money('IDR')
                    ->summarize(Sum::make()->money('IDR')),

                TextColumn::make('remaining')
                    ->label('Remaining Amount')
                    ->money('IDR')
                    ->summarize(Sum::make()->money('IDR'))
                    ->color('danger'),

                TextColumn::make('aging_bucket')
                    ->label('Aging Bucket')
                    ->getStateUsing(function ($record) {
                        $ageingSchedule = $record->ageingSchedule;
                        if ($ageingSchedule && $ageingSchedule->bucket) {
                            return $ageingSchedule->bucket;
                        }

                        // Calculate bucket if not exists
                        $days = 0;
                        if ($record->invoice && $record->invoice->invoice_date) {
                            $invoiceDate = Carbon::parse($record->invoice->invoice_date);
                            $days = $invoiceDate->diffInDays(now(), false);
                        }

                        return self::calculateBucket($days);
                    })
                    ->badge()
                    ->color(function ($state) {
                        return match ($state) {
                            'Current' => 'success',
                            '31–60' => 'warning',
                            '61–90' => 'orange',
                            '>90' => 'danger',
                            default => 'gray',
                        };
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Lunas' => 'success',
                        'Belum Lunas' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('cabang.nama')
                    ->label('Branch')
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->getStateUsing(function ($record) {
                        if ($record instanceof AccountReceivable) {
                            return $record->cabang->nama ?? '-';
                        }
                        return '-';
                    }),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Report Type')
                    ->options([
                        'receivables' => 'Account Receivables',
                        'payables' => 'Account Payables',
                        'both' => 'Both AR & AP',
                    ])
                    ->default('receivables')
                    ->query(function (Builder $query, array $data): Builder {
                        $type = $data['value'] ?? 'receivables';

                        if ($type === 'receivables') {
                            return $query->where('remaining', '>', 0);
                        } elseif ($type === 'payables') {
                            return AccountPayable::query()->where('remaining', '>', 0);
                        } else {
                            // For 'both', we'll handle this in the query modification
                            return $query->whereRaw('1 = 0'); // Return empty for receivables, we'll handle in getEloquentQuery
                        }
                    }),

                SelectFilter::make('aging_bucket')
                    ->label('Aging Bucket')
                    ->options([
                        'Current' => 'Current (0-30 days)',
                        '31–60' => '31-60 days',
                        '61–90' => '61-90 days',
                        '>90' => '>90 days',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['value']) return $query;

                        return $query->whereHas('ageingSchedule', function ($q) use ($data) {
                            $q->where('bucket', $data['value']);
                        })->orWhere(function ($q) use ($data) {
                            $q->whereDoesntHave('ageingSchedule')
                              ->whereHas('invoice', function ($invoiceQuery) use ($data) {
                                  $days = match ($data['value']) {
                                      'Current' => 30,
                                      '31–60' => 60,
                                      '61–90' => 90,
                                      '>90' => PHP_INT_MAX,
                                  };

                                  $invoiceQuery->whereRaw('DATEDIFF(NOW(), invoice_date) <= ?', [$days]);
                              });
                        });
                    }),

                Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('invoice', function ($q) {
                            $q->where('due_date', '<', now());
                        });
                    }),

                SelectFilter::make('cabang_id')
                    ->label('Branch')
                    ->options(Cabang::pluck('nama', 'id'))
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ExportAction::make()
                    ->label('Export Aging Report')
                    ->exports([
                        \Filament\Actions\Exports\ExportColumn::make('customer.name')
                            ->label('Customer/Supplier'),
                        \Filament\Actions\Exports\ExportColumn::make('invoice.no_invoice')
                            ->label('Invoice Number'),
                        \Filament\Actions\Exports\ExportColumn::make('invoice.invoice_date')
                            ->label('Invoice Date'),
                        \Filament\Actions\Exports\ExportColumn::make('invoice.due_date')
                            ->label('Due Date'),
                        \Filament\Actions\Exports\ExportColumn::make('days_outstanding')
                            ->label('Days Outstanding'),
                        \Filament\Actions\Exports\ExportColumn::make('total')
                            ->label('Total Amount'),
                        \Filament\Actions\Exports\ExportColumn::make('paid')
                            ->label('Paid Amount'),
                        \Filament\Actions\Exports\ExportColumn::make('remaining')
                            ->label('Remaining Amount'),
                        \Filament\Actions\Exports\ExportColumn::make('aging_bucket')
                            ->label('Aging Bucket'),
                        \Filament\Actions\Exports\ExportColumn::make('status')
                            ->label('Status'),
                    ])
            ])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('export_excel')
                    ->label('Export to Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () {
                        $type = request('tableFilters.type.value') ?? 'receivables';
                        $cabangId = request('tableFilters.cabang_id.value') ?? null;

                        return Excel::download(
                            new AgeingReportExport(now(), $cabangId, $type),
                            'aging-report-' . now()->format('Y-m-d') . '.xlsx'
                        );
                    }),

                \Filament\Tables\Actions\Action::make('export_pdf')
                    ->label('Export to PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->action(function () {
                        $type = request('tableFilters.type.value') ?? 'receivables';
                        $cabangId = request('tableFilters.cabang_id.value') ?? null;

                        $export = new AgeingReportPdfExport(now(), $cabangId, $type);
                        $pdf = $export->generatePdf();

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'ageing-report-' . now()->format('Y-m-d') . '.pdf');
                    }),
            ])
            ->defaultSort('invoice.invoice_date', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $type = request('tableFilters.type.value') ?? 'receivables';

        if ($type === 'payables') {
            return AccountPayable::query()->where('remaining', '>', 0);
        } elseif ($type === 'both') {
            // For 'both', we need to union receivables and payables
            // This is complex, so we'll default to receivables for now
            return AccountReceivable::query()->where('remaining', '>', 0);
        }

        return AccountReceivable::query()->where('remaining', '>', 0);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewAgeingReport::route('/'),
        ];
    }

    private static function calculateBucket($days)
    {
        if ($days <= 30) return 'Current';
        if ($days <= 60) return '31–60';
        if ($days <= 90) return '61–90';
        return '>90';
    }
}