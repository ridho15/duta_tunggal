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
use Filament\Forms\Components\Toggle;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Exports\InventoryReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class InventoryReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.inventory-report-page';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Laporan Inventori';

    protected static ?int $navigationSort = 3;

    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $warehouse_id = null;
    public ?int $product_id = null;
    public bool $show_movement_history = false;
    public bool $show_aging_stock = false;

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->format('Y-m-d');
    }

    public function exportExcel()
    {
        $type = $this->show_movement_history ? 'movement' : ($this->show_aging_stock ? 'aging' : 'stock');
        return Excel::download(new InventoryReportExport($this->warehouse_id, $this->product_id, $type, $this->start_date, $this->end_date), 'inventory_report.xlsx');
    }

    public function exportPdf()
    {
        $type = $this->show_movement_history ? 'movement' : ($this->show_aging_stock ? 'aging' : 'stock');
        return Pdf::loadView('reports.inventory_report', [
            'data' => $this->getCurrentTableQuery()->get(),
            'type' => $type,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'warehouse' => $this->warehouse_id ? Warehouse::find($this->warehouse_id) : null,
            'product' => $this->product_id ? Product::find($this->product_id) : null,
        ])->download('inventory_report.pdf');
    }

    private function getCurrentTableQuery()
    {
        if ($this->show_movement_history) {
            return StockMovement::query()
                ->when($this->start_date, fn($q) => $q->whereDate('date', '>=', $this->start_date))
                ->when($this->end_date, fn($q) => $q->whereDate('date', '<=', $this->end_date))
                ->when($this->warehouse_id, fn($q) => $q->where('warehouse_id', $this->warehouse_id))
                ->when($this->product_id, fn($q) => $q->where('product_id', $this->product_id))
                ->with(['product', 'warehouse', 'rak'])
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc');
        } elseif ($this->show_aging_stock) {
            return InventoryStock::query()
                ->when($this->warehouse_id, fn($q) => $q->where('warehouse_id', $this->warehouse_id))
                ->when($this->product_id, fn($q) => $q->where('product_id', $this->product_id))
                ->with(['product', 'warehouse', 'rak'])
                ->orderBy('warehouse_id')
                ->orderBy('product_id');
        } else {
            return InventoryStock::query()
                ->when($this->warehouse_id, fn($q) => $q->where('warehouse_id', $this->warehouse_id))
                ->when($this->product_id, fn($q) => $q->where('product_id', $this->product_id))
                ->with(['product', 'warehouse', 'rak'])
                ->orderBy('warehouse_id')
                ->orderBy('product_id');
        }
    }

    public function table(Table $table): Table
    {
        if ($this->show_movement_history) {
            return $this->getMovementHistoryTable($table);
        } elseif ($this->show_aging_stock) {
            return $this->getAgingStockTable($table);
        } else {
            return $this->getStockByWarehouseTable($table);
        }
    }

    private function getStockByWarehouseTable(Table $table): Table
    {
        return $table
            ->query(
                InventoryStock::query()
                    ->when($this->warehouse_id, fn($q) => $q->where('warehouse_id', $this->warehouse_id))
                    ->when($this->product_id, fn($q) => $q->where('product_id', $this->product_id))
                    ->with(['product', 'warehouse', 'rak'])
                    ->orderBy('warehouse_id')
                    ->orderBy('product_id')
            )
            ->columns([
                TextColumn::make('warehouse.name')->label('Gudang')->sortable(),
                TextColumn::make('product.name')->label('Produk')->sortable(),
                TextColumn::make('product.code')->label('Kode Produk')->sortable(),
                TextColumn::make('rak.name')->label('Rak')->sortable(),
                TextColumn::make('qty_available')->label('Qty Tersedia')->sortable(),
                TextColumn::make('qty_reserved')->label('Qty Dipesan')->sortable(),
                TextColumn::make('qty_min')->label('Qty Minimum')->sortable(),
                TextColumn::make('qty_on_hand')
                    ->label('Qty On Hand')
                    ->getStateUsing(fn($record) => $record->qty_available - $record->qty_reserved)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(function ($record) {
                        $onHand = $record->qty_available - $record->qty_reserved;
                        if ($onHand <= 0) return 'Habis';
                        if ($onHand <= $record->qty_min) return 'Minimum';
                        return 'Normal';
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Habis' => 'danger',
                        'Minimum' => 'warning',
                        'Normal' => 'success',
                    }),
            ])
            ->actions([
                Action::make('view_movements')
                    ->label('Lihat Movement')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => route('filament.admin.resources.inventory-stocks.view', $record)),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document')
                    ->action(fn() => Excel::download(new InventoryReportExport($this->warehouse_id, $this->product_id, 'stock'), 'inventory_stock_report.xlsx')),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->action(fn() => Pdf::loadView('reports.inventory_report', [
                        'data' => $this->getTableQuery()->get(),
                        'type' => 'stock',
                        'warehouse' => $this->warehouse_id ? Warehouse::find($this->warehouse_id) : null,
                        'product' => $this->product_id ? Product::find($this->product_id) : null,
                    ])->download('inventory_stock_report.pdf')),
            ]);
    }

    private function getMovementHistoryTable(Table $table): Table
    {
        return $table
            ->query(
                StockMovement::query()
                    ->when($this->start_date, fn($q) => $q->whereDate('date', '>=', $this->start_date))
                    ->when($this->end_date, fn($q) => $q->whereDate('date', '<=', $this->end_date))
                    ->when($this->warehouse_id, fn($q) => $q->where('warehouse_id', $this->warehouse_id))
                    ->when($this->product_id, fn($q) => $q->where('product_id', $this->product_id))
                    ->with(['product', 'warehouse', 'rak'])
                    ->orderBy('date', 'desc')
                    ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('product.name')->label('Produk')->sortable(),
                TextColumn::make('product.code')->label('Kode Produk')->sortable(),
                TextColumn::make('warehouse.name')->label('Gudang')->sortable(),
                TextColumn::make('rak.name')->label('Rak')->sortable(),
                TextColumn::make('type')->label('Tipe Movement')->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'transfer' => 'warning',
                        'adjustment' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('quantity')->label('Quantity')->sortable(),
                TextColumn::make('value')->label('Nilai')->money('IDR')->sortable(),
                TextColumn::make('from_model_type')->label('Referensi')->getStateUsing(function ($record) {
                    if ($record->from_model_type && $record->from_model_id) {
                        $modelName = class_basename($record->from_model_type);
                        return $modelName . ' #' . $record->from_model_id;
                    }
                    return '-';
                }),
                TextColumn::make('notes')->label('Catatan')->limit(50),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document')
                    ->action(fn() => Excel::download(new InventoryReportExport($this->warehouse_id, $this->product_id, 'movement', $this->start_date, $this->end_date), 'inventory_movement_report.xlsx')),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->action(fn() => Pdf::loadView('reports.inventory_report', [
                        'data' => $this->getTableQuery()->get(),
                        'type' => 'movement',
                        'start_date' => $this->start_date,
                        'end_date' => $this->end_date,
                        'warehouse' => $this->warehouse_id ? Warehouse::find($this->warehouse_id) : null,
                        'product' => $this->product_id ? Product::find($this->product_id) : null,
                    ])->download('inventory_movement_report.pdf')),
            ]);
    }

    private function getAgingStockTable(Table $table): Table
    {
        return $table
            ->query(
                InventoryStock::query()
                    ->when($this->warehouse_id, fn($q) => $q->where('warehouse_id', $this->warehouse_id))
                    ->when($this->product_id, fn($q) => $q->where('product_id', $this->product_id))
                    ->with(['product', 'warehouse', 'rak'])
                    ->orderBy('warehouse_id')
                    ->orderBy('product_id')
            )
            ->columns([
                TextColumn::make('warehouse.name')->label('Gudang')->sortable(),
                TextColumn::make('product.name')->label('Produk')->sortable(),
                TextColumn::make('product.code')->label('Kode Produk')->sortable(),
                TextColumn::make('rak.name')->label('Rak')->sortable(),
                TextColumn::make('qty_available')->label('Qty Tersedia')->sortable(),
                TextColumn::make('qty_reserved')->label('Qty Dipesan')->sortable(),
                TextColumn::make('qty_on_hand')
                    ->label('Qty On Hand')
                    ->getStateUsing(fn($record) => $record->qty_available - $record->qty_reserved)
                    ->sortable(),
                TextColumn::make('last_movement_date')
                    ->label('Terakhir Movement')
                    ->getStateUsing(function ($record) {
                        $lastMovement = StockMovement::where('product_id', $record->product_id)
                            ->where('warehouse_id', $record->warehouse_id)
                            ->orderBy('date', 'desc')
                            ->first();
                        return $lastMovement ? $lastMovement->date : null;
                    })
                    ->date()
                    ->sortable(),
                TextColumn::make('aging_days')
                    ->label('Hari Aging')
                    ->getStateUsing(function ($record) {
                        $lastMovement = StockMovement::where('product_id', $record->product_id)
                            ->where('warehouse_id', $record->warehouse_id)
                            ->orderBy('date', 'desc')
                            ->first();
                        if (!$lastMovement) {
                            return 999; // Very old if no movement
                        }
                        return Carbon::parse($lastMovement->date)->diffInDays(now());
                    })
                    ->sortable(),
                TextColumn::make('aging_category')
                    ->label('Kategori Aging')
                    ->getStateUsing(function ($record) {
                        $lastMovement = StockMovement::where('product_id', $record->product_id)
                            ->where('warehouse_id', $record->warehouse_id)
                            ->orderBy('date', 'desc')
                            ->first();
                        if (!$lastMovement) {
                            return 'Tidak Ada Movement';
                        }
                        $days = Carbon::parse($lastMovement->date)->diffInDays(now());
                        if ($days <= 30) return 'Aktif';
                        if ($days <= 90) return 'Slow Moving';
                        if ($days <= 180) return 'Stagnan';
                        return 'Dead Stock';
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Slow Moving' => 'warning',
                        'Stagnan' => 'danger',
                        'Dead Stock' => 'gray',
                        'Tidak Ada Movement' => 'gray',
                    }),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document')
                    ->action(fn() => Excel::download(new InventoryReportExport($this->warehouse_id, $this->product_id, 'aging'), 'inventory_aging_report.xlsx')),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->action(fn() => Pdf::loadView('reports.inventory_report', [
                        'data' => $this->getCurrentTableQuery()->get(),
                        'type' => 'aging',
                        'warehouse' => $this->warehouse_id ? Warehouse::find($this->warehouse_id) : null,
                        'product' => $this->product_id ? Product::find($this->product_id) : null,
                    ])->download('inventory_aging_report.pdf')),
            ]);
    }

    public function filterForm(Form $form): Form
    {
        return $form
            ->schema([
                // Temporarily disabled due to Filament bug
                // DatePicker::make('start_date')->label('Tanggal Mulai'),
                // DatePicker::make('end_date')->label('Tanggal Akhir'),
                // Select::make('warehouse_id')
                //     ->label('Gudang')
                //     ->options(Warehouse::pluck('name', 'id'))
                //     ->placeholder('Semua Gudang'),
                // Select::make('product_id')
                //     ->label('Produk')
                //     ->options(Product::pluck('name', 'id'))
                //     ->placeholder('Semua Produk'),
                // Toggle::make('show_movement_history')->label('Tampilkan History Movement'),
                // Toggle::make('show_aging_stock')->label('Tampilkan Aging Stock'),
            ]);
    }
}