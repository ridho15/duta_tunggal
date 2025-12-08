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

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PurchaseOrder::query()
                    ->when($this->start_date, fn($q) => $q->whereDate('order_date', '>=', $this->start_date))
                    ->when($this->end_date, fn($q) => $q->whereDate('order_date', '<=', $this->end_date))
                    ->when($this->supplier_id, fn($q) => $q->where('supplier_id', $this->supplier_id))
                    ->with(['supplier', 'purchaseOrderItem'])
            )
            ->columns([
                TextColumn::make('po_number')->label('No. PO')->sortable(),
                TextColumn::make('order_date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('supplier.name')->label('Supplier')->sortable(),
                TextColumn::make('total_amount')->label('Total')->money('IDR')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->actions([
                Action::make('view')
                    ->label('Lihat Detail')
                    ->url(fn($record) => route('filament.admin.resources.purchase-orders.view', $record))
                    ->icon('heroicon-o-eye'),
            ])
            ->headerActions([
                // Temporarily disabled due to Filament bug
                // Action::make('export_excel')
                //     ->label('Export Excel')
                //     ->icon('heroicon-o-document')
                //     ->action(fn() => Excel::download(new PurchaseReportExport($this->start_date, $this->end_date, $this->supplier_id), 'purchase_report.xlsx')),
                // Action::make('export_pdf')
                //     ->label('Export PDF')
                //     ->icon('heroicon-o-document')
                //     ->action(fn() => Pdf::loadView('reports.purchase_report', [
                //         'data' => $this->getTableQuery()->get(),
                //         'start_date' => $this->start_date,
                //         'end_date' => $this->end_date,
                //     ])->download('purchase_report.pdf')),
            ]);
    }

    public function filterForm(Form $form): Form
    {
        return $form
            ->schema([
                // Temporarily disabled due to Filament bug
                // DatePicker::make('start_date')->label('Tanggal Mulai'),
                // DatePicker::make('end_date')->label('Tanggal Akhir'),
                // Select::make('supplier_id')
                //     ->label('Supplier')
                //     ->options(Supplier::pluck('name', 'id'))
                //     ->placeholder('Semua Supplier'),
            ]);
    }
}