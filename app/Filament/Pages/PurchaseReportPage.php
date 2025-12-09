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
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Exports\PurchaseReportExport;
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

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $supplier_id = null;
    public ?string $status = null;
    public ?string $sort_by_total = null;

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'supplier_id' => null,
            'status' => null,
            'sort_by_total' => null,
        ]);

        $this->updateFilters();
    }

    public function table(Table $table): Table
    {
        $query = $this->getFilteredQuery();

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('po_number')->label('No. PO')->sortable(),
                TextColumn::make('order_date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('supplier.name')->label('Supplier')->sortable(),
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
                    ->url(fn($record) => route('filament.admin.resources.purchase-orders.view', $record))
                    ->icon('heroicon-o-eye'),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        $this->updateFilters();
                        $query = $this->getFilteredQuery();
                        return Excel::download(new PurchaseReportExport($query), 'purchase_report.xlsx');
                    }),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        $this->updateFilters();
                        $query = $this->getFilteredQuery();

                        $pdf = Pdf::loadView('reports.purchase_report', [
                            'data' => $query->get(),
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
                            return [$supplier->id => $supplier->code . ' - ' . $supplier->name];
                        });
                    })
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Supplier::where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function ($supplier) {
                                return [$supplier->id => $supplier->code . ' - ' . $supplier->name];
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
                        'asc' => 'Tertinggi ke Terendah',
                        'desc' => 'Terendah ke Tertinggi',
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
        $formData = $this->form->getState();
        $this->start_date = $formData['start_date'] ?? null;
        $this->end_date = $formData['end_date'] ?? null;
        $this->supplier_id = $formData['supplier_id'] ?? null;
        $this->status = $formData['status'] ?? null;
        $this->sort_by_total = $formData['sort_by_total'] ?? null;

        // Reset table pagination when filters change
        $this->resetTable();
    }

    private function getFilteredQuery()
    {
        $query = PurchaseOrder::query()
            ->when($this->start_date, fn($q) => $q->whereDate('order_date', '>=', $this->start_date))
            ->when($this->end_date, fn($q) => $q->whereDate('order_date', '<=', $this->end_date))
            ->when($this->supplier_id, fn($q) => $q->where('supplier_id', $this->supplier_id))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->with(['supplier', 'purchaseOrderItem.product']);

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