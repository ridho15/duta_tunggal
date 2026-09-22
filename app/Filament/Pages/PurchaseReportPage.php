<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use App\Models\Supplier;
use App\Exports\PurchaseReportExport;
use App\Services\Reports\PurchaseReportService;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class PurchaseReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.purchase-report-page';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Laporan Pembelian';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $supplier_id = null;
    public ?string $status = null;
    public ?string $sort_by_total = null;

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
        $this->supplier_id = null;
        $this->status = null;
        $this->sort_by_total = null;

        $this->form->fill([
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'supplier_id' => $this->supplier_id,
            'status' => $this->status,
            'sort_by_total' => $this->sort_by_total,
        ]);
    }

    public function table(Table $table): Table
    {
        $query = $this->getFilteredQuery();

        return $table
            ->defaultSort('created_at', 'desc')
            ->query($query)
            ->columns([
                TextColumn::make('po_number')->label('No. PO')->sortable(),
                TextColumn::make('order_date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('supplier.perusahaan')->label('Supplier')->sortable(),
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
                    ->url(fn($record) => route('filament.admin.resources.purchase-orders.view', $record))
                    ->icon('heroicon-o-eye'),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        $query = $this->getFilteredQuery();
                        return Excel::download(new PurchaseReportExport($query), 'purchase_report.xlsx');
                    }),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        $payload = $this->purchaseReportService()->pdfPayload($this->reportFilters(), Auth::user());

                        $pdf = Pdf::loadView('reports.purchase_report', [
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

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'purchase_report_' . now()->format('Ymd_His') . '.pdf');
                    }),
            ])
            ->paginated(false);
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

                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(function () {
                        return Supplier::all()->mapWithKeys(function ($supplier) {
                            return [$supplier->id => $supplier->code . ' - ' . $supplier->perusahaan];
                        });
                    })
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Supplier::where('code', 'like', "%{$search}%")
                            ->orWhere('perusahaan', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($supplier) {
                                return [$supplier->id => $supplier->code . ' - ' . $supplier->perusahaan];
                            })
                            ->toArray();
                    })
                    ->placeholder('Semua Supplier')
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
                    ->afterStateUpdated(function ($state) {
                        $this->status = $state;
                        $this->updateFilters();
                    }),

                Select::make('sort_by_total')
                    ->label('Urutkan Total')
                    ->options([
                        'asc' => 'Terendah ke Tertinggi',
                        'desc' => 'Tertinggi ke Terendah',
                    ])
                    ->placeholder('Tidak diurutkan')
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->sort_by_total = $state;
                        $this->updateFilters();
                    }),
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

    public function updatedSupplierId(): void
    {
        $this->updateFilters();
    }

    public function updatedStatus(): void
    {
        $this->updateFilters();
    }

    public function updatedSortByTotal(): void
    {
        $this->updateFilters();
    }

    public function updateFilters(): void
    {
        $this->resetTable();
    }

    private function getFilteredQuery()
    {
        return $this->purchaseReportService()->query($this->reportFilters(), Auth::user());
    }

    private function reportFilters(): array
    {
        return [
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'supplier_id' => $this->supplier_id,
            'status' => $this->status,
            'sort_by_total' => $this->sort_by_total,
        ];
    }

    private function purchaseReportService(): PurchaseReportService
    {
        return app(PurchaseReportService::class);
    }
}