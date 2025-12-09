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
use Filament\Tables\Enums\SortDirection;
use Filament\Tables\Actions\Action;
use App\Models\SaleOrder;
use App\Models\Customer;
use App\Exports\SalesReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Milon\Barcode\Facades\DNS2DFacade;

class SalesReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.sales-report-page';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Laporan Penjualan';

    protected static ?int $navigationSort = 1;

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $customer_id = null;
    public ?string $so_number = null;
    public ?string $sort_by_total = null;
    public ?string $status = null;

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'customer_id' => null,
            'so_number' => null,
            'sort_by_total' => null,
            'status' => null,
        ]);

        $this->updateFilters();
    }

    public function table(Table $table): Table
    {
        $query = SaleOrder::query()
            ->when($this->start_date, fn($q) => $q->whereDate('created_at', '>=', $this->start_date))
            ->when($this->end_date, fn($q) => $q->whereDate('created_at', '<=', $this->end_date))
            ->when($this->customer_id, fn($q) => $q->where('customer_id', $this->customer_id))
            ->when($this->so_number, fn($q) => $q->where('so_number', 'like', '%' . $this->so_number . '%'))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->with(['customer', 'saleOrderItem']);

        // Apply branch scoping
        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->where('cabang_id', $user->cabang_id);
        }

        // Apply sorting
        if ($this->sort_by_total === 'asc') {
            $query->orderBy('total_amount', 'asc');
        } elseif ($this->sort_by_total === 'desc') {
            $query->orderBy('total_amount', 'desc');
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('so_number')->label('No. SO')->sortable(),
                TextColumn::make('created_at')->label('Tanggal')->date()->sortable(),
                TextColumn::make('customer.code')->label('Kode Customer')->sortable(),
                TextColumn::make('customer.name')->label('Nama Customer')->sortable(),
                TextColumn::make('total_amount')->label('Total')->money('IDR')->sortable(),
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
                        $this->updateFilters();
                        $query = $this->getFilteredQuery();
                        return Excel::download(new SalesReportExport($query), 'sales_report.xlsx');
                    }),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $this->updateFilters();
                            $query = $this->getFilteredQuery();

                            // Clean data to ensure UTF-8 encoding
                            $cleanData = $query->get()->map(function ($order) {
                                return [
                                    'so_number' => mb_convert_encoding($order->so_number ?? '', 'UTF-8', 'UTF-8'),
                                    'created_at' => $order->created_at,
                                    'customer_code' => mb_convert_encoding($order->customer->code ?? '-', 'UTF-8', 'UTF-8'),
                                    'customer_name' => mb_convert_encoding($order->customer->name ?? '-', 'UTF-8', 'UTF-8'),
                                    'total_amount' => $order->total_amount ?? 0,
                                    'status' => mb_convert_encoding($order->status ?? '', 'UTF-8', 'UTF-8'),
                                ];
                            });

                            $pdf = Pdf::loadView('reports.sales_report', [
                                'data' => $cleanData,
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
                        'asc' => 'Tertinggi ke Terendah',
                        'desc' => 'Terendah ke Tertinggi',
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

    public function updateFilters(): void
    {
        $formData = $this->form->getState();
        $this->start_date = $formData['start_date'] ?? null;
        $this->end_date = $formData['end_date'] ?? null;
        $this->customer_id = $formData['customer_id'] ?? null;
        $this->so_number = $formData['so_number'] ?? null;
        $this->sort_by_total = $formData['sort_by_total'] ?? null;
        $this->status = $formData['status'] ?? null;

        // Reset table pagination when filters change
        $this->resetTable();
    }

    private function getFilteredQuery()
    {
        $query = SaleOrder::query()
            ->when($this->start_date, fn($q) => $q->whereDate('created_at', '>=', $this->start_date))
            ->when($this->end_date, fn($q) => $q->whereDate('created_at', '<=', $this->end_date))
            ->when($this->customer_id, fn($q) => $q->where('customer_id', $this->customer_id))
            ->when($this->so_number, fn($q) => $q->where('so_number', 'like', '%' . $this->so_number . '%'))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->with(['customer', 'saleOrderItem.product']);

        // Apply branch scoping
        $user = Auth::user();
        if ($user && !in_array('all', $user->manage_type ?? [])) {
            $query->where('cabang_id', $user->cabang_id);
        }

        // Apply sorting
        if ($this->sort_by_total === 'asc') {
            $query->orderBy('total_amount', 'asc');
        } elseif ($this->sort_by_total === 'desc') {
            $query->orderBy('total_amount', 'desc');
        }

        return $query;
    }
}