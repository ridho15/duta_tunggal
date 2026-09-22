<?php

namespace App\Filament\Resources\Reports;

use App\Enums\PaymentStatus;
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
use App\Services\Reports\AgeingReportService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class AgeingReportResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = AccountReceivable::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Aging Report (AR/AP)';

    protected static ?string $navigationParentItem = 'Laporan Keuangan';

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
                    ->getStateUsing(fn ($record) => self::customerOrSupplierName($record)),

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
                    ->getStateUsing(fn ($record) => self::daysOutstandingForRecord($record, self::currentAsOfDate()))
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total Amount')
                    ->rupiah()
                    ->summarize(Sum::make()->rupiah()),

                TextColumn::make('paid')
                    ->label('Paid Amount')
                    ->rupiah()
                    ->summarize(Sum::make()->rupiah()),

                TextColumn::make('remaining')
                    ->label('Remaining Amount')
                    ->rupiah()
                    ->summarize(Sum::make()->rupiah())
                    ->color('danger'),

                TextColumn::make('aging_bucket')
                    ->label('Aging Bucket')
                    ->getStateUsing(fn ($record) => self::bucketForRecord($record, self::currentAsOfDate()))
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
                        PaymentStatus::PAID->value => 'success',
                        PaymentStatus::UNPAID->value => 'warning',
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

                        $ids = self::matchingBucketRecordIds($query, $data['value'], self::currentAsOfDate(), request('tableFilters.cabang_id.value'));

                        return $query->whereKey(empty($ids) ? [0] : $ids);
                    }),

                Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(function (Builder $query): Builder {
                        return $query->whereHas('invoice', function ($q) {
                            $q->where('due_date', '<', Carbon::parse(self::currentAsOfDate())->toDateString());
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
                        $asOfDate = self::currentAsOfDate();

                        return Excel::download(
                            new AgeingReportExport($asOfDate, $cabangId, $type),
                            'aging-report-' . Carbon::parse($asOfDate)->format('Y-m-d') . '.xlsx'
                        );
                    }),

                \Filament\Tables\Actions\Action::make('export_pdf')
                    ->label('Export to PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->action(function () {
                        $type = request('tableFilters.type.value') ?? 'receivables';
                        $cabangId = request('tableFilters.cabang_id.value') ?? null;
                        $asOfDate = self::currentAsOfDate();

                        $export = new AgeingReportPdfExport($asOfDate, $cabangId, $type);
                        $pdf = $export->generatePdf();

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'ageing-report-' . Carbon::parse($asOfDate)->format('Y-m-d') . '.pdf');
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

    public static function customerOrSupplierName($record): string
    {
        if ($record instanceof AccountReceivable) {
            return $record->customer->name ?? '-';
        }

        if ($record instanceof AccountPayable) {
            return $record->supplier->perusahaan ?? '-';
        }

        return '-';
    }

    public static function daysOutstandingForRecord($record, Carbon|string|null $asOfDate = null): int
    {
        return self::ageingReportService()->resolveDaysOutstanding($record, $asOfDate);
    }

    public static function bucketForRecord($record, Carbon|string|null $asOfDate = null): string
    {
        return self::ageingReportService()->resolveBucketLabel($record, $asOfDate);
    }

    public static function currentAsOfDate(): string
    {
        return request('tableFilters.as_of_date.value')
            ?? request('as_of_date')
            ?? now()->toDateString();
    }

    private static function matchingBucketRecordIds(Builder $query, string $bucket, Carbon|string|null $asOfDate = null, $cabangId = null): array
    {
        $filters = [
            'as_of_date' => $asOfDate,
            'cabang_id' => $cabangId,
        ];

        $records = $query->getModel() instanceof AccountPayable
            ? self::ageingReportService()->getPayableRecords($filters)
            : self::ageingReportService()->getReceivableRecords($filters);

        return $records
            ->where('aging_bucket_computed', $bucket)
            ->pluck('id')
            ->all();
    }

    private static function ageingReportService(): AgeingReportService
    {
        return app(AgeingReportService::class);
    }
}