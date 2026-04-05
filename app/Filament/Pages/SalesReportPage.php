<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\Action;
use App\Models\Customer;
use App\Exports\SalesReportExport;
use App\Services\Reports\SalesReportService;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class SalesReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.sales-report-page';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Laporan Penjualan';

    protected static ?string $navigationParentItem = 'Laporan Operasional';

    protected static ?int $navigationSort = 1;

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $customer_id = null;
    public ?string $so_number = null;
    public ?string $sort_by_total = null;
    public ?string $status = null;

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
        $this->customer_id = null;
        $this->so_number = null;
        $this->sort_by_total = null;
        $this->status = null;

        $this->form->fill([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'customer_id' => $this->customer_id,
            'so_number' => $this->so_number,
            'sort_by_total' => $this->sort_by_total,
            'status' => $this->status,
        ]);
    }

    public function table(Table $table): Table
    {
        $query = $this->salesReportService()->query($this->reportFilters(), Auth::user());

        return $table
            ->defaultSort('created_at', 'desc')
            ->query($query)
            ->columns([
                TextColumn::make('so_number')->label('No. SO')->sortable(),
                TextColumn::make('created_at')->label('Tanggal')->date()->sortable(),
                TextColumn::make('customer.code')->label('Kode Customer')->sortable(),
                TextColumn::make('customer.name')->label('Nama Customer')->sortable(),
                TextColumn::make('total_amount')->label('Total')->rupiah()->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'confirmed' => 'info',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('Lihat Detail')
                    ->url(fn($record) => route('filament.admin.resources.sale-orders.view', $record))
                    ->icon('heroicon-o-eye'),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        $query = $this->getFilteredQuery();
                        return Excel::download(new SalesReportExport($query), 'sales_report.xlsx');
                    }),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        $payload = $this->salesReportService()->pdfPayload($this->reportFilters(), Auth::user());

                        return response()->streamDownload(function () use ($payload) {
                            $pdf = Pdf::loadView('reports.sales_report', [
                                'rows' => $payload['rows'],
                                'summary' => $payload['summary'],
                                'start_date' => $this->start_date,
                                'end_date' => $this->end_date,
                            ]);

                            $pdf->setOptions([
                                'defaultFont' => 'DejaVu Sans',
                                'isHtml5ParserEnabled' => true,
                                'isRemoteEnabled' => false,
                                'isPhpEnabled' => false,
                                'orientation' => 'landscape',
                                'defaultPaperSize' => 'a4',
                            ]);

                            echo $pdf->output();
                        }, 'sales_report_' . now()->format('Ymd_His') . '.pdf');
                    }),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('start_date')
                    ->label('Tanggal Mulai')
                    ->default(now()->startOfMonth())
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                DatePicker::make('end_date')
                    ->label('Tanggal Akhir')
                    ->default(now())
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                Select::make('customer_id')
                    ->label('Customer')
                    ->options(function () {
                        return Customer::all()->mapWithKeys(function ($customer) {
                            return [$customer->id => $customer->code . ' - ' . $customer->name];
                        });
                    })
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Customer::where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($customer) {
                                return [$customer->id => $customer->code . ' - ' . $customer->name];
                            })
                            ->toArray();
                    })
                    ->placeholder('Semua Customer')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                TextInput::make('so_number')
                    ->label('No. SO')
                    ->placeholder('Cari berdasarkan No. SO')
                    ->live(debounce: 500)
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                Select::make('sort_by_total')
                    ->label('Urutkan Total')
                    ->options([
                        'asc' => 'Terendah ke Tertinggi',
                        'desc' => 'Tertinggi ke Terendah',
                    ])
                    ->placeholder('Tidak diurutkan')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'confirmed' => 'Dikonfirmasi',
                        'processing' => 'Diproses',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->placeholder('Semua Status')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),
            ])
            ->columns(3);
    }

    public function updatedStartDate(): void
    {
        $this->updateFilters();
    }

    public function updatedEndDate(): void
    {
        $this->updateFilters();
    }

    public function updatedCustomerId(): void
    {
        $this->updateFilters();
    }

    public function updatedSoNumber(): void
    {
        $this->updateFilters();
    }

    public function updatedSortByTotal(): void
    {
        $this->updateFilters();
    }

    public function updatedStatus(): void
    {
        $this->updateFilters();
    }

    public function updateFilters(): void
    {
        $this->resetTable();
    }

    private function getFilteredQuery()
    {
        return $this->salesReportService()->query($this->reportFilters(), Auth::user());
    }

    private function reportFilters(): array
    {
        return [
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'customer_id' => $this->customer_id,
            'so_number' => $this->so_number,
            'sort_by_total' => $this->sort_by_total,
            'status' => $this->status,
        ];
    }

    private function salesReportService(): SalesReportService
    {
        return app(SalesReportService::class);
    }
}