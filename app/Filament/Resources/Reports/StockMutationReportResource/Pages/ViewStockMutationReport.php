<?php

namespace App\Filament\Resources\Reports\StockMutationReportResource\Pages;

use App\Filament\Resources\Reports\StockMutationReportResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use App\Exports\GenericViewExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class ViewStockMutationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = StockMutationReportResource::class;

    protected static string $view = 'filament.pages.reports.stock-mutation-report';

    public ?string $startDate = null;
    public ?string $endDate = null;
    public array $productIds = [];
    public array $warehouseIds = [];

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => request('start', now()->startOfMonth()->format('Y-m-d')),
            'endDate' => request('end', now()->endOfMonth()->format('Y-m-d')),
            'productIds' => [],
            'warehouseIds' => [],
        ]);

        $this->updateFilters();
    }

    public function table(Table $table): Table
    {
        $query = StockMovement::query()
            ->with(['product', 'warehouse', 'rak'])
            ->when($this->startDate, fn($q) => $q->whereDate('date', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->whereDate('date', '<=', $this->endDate))
            ->when(!empty($this->productIds), fn($q) => $q->whereIn('product_id', $this->productIds))
            ->when(!empty($this->warehouseIds), fn($q) => $q->whereIn('warehouse_id', $this->warehouseIds))
            ->orderBy('warehouse_id')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->sortable(),
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->formatStateUsing(fn (string $state): string => $this->getMovementTypeLabel($state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'purchase_in', 'manufacture_in', 'transfer_in', 'adjustment_in', 'return_in' => 'success',
                        'sales', 'transfer_out', 'manufacture_out', 'adjustment_out', 'return_out' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('value')
                    ->label('Nilai')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('reference_id')
                    ->label('Referensi')
                    ->copyable(),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(30),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        $this->updateFilters();
                        $data = $this->getReportData();

                        $filename = 'laporan_mutasi_barang_' . date('Y-m-d_His') . '.xlsx';

                        $view = view('filament.exports.stock-mutation-excel', [
                            'report' => $data,
                            'generated_at' => now(),
                        ]);

                        return Excel::download(
                            new GenericViewExport($view),
                            $filename
                        );
                    }),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document')
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $data = $this->getReportData();

                            $pdf = Pdf::loadView('reports.stock_mutation_report', [
                                'report' => $data,
                                'start_date' => $data['period']['start'],
                                'end_date' => $data['period']['end'],
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
                        }, 'laporan_mutasi_barang_' . now()->format('Ymd_His') . '.pdf');
                    }),
            ])
            ->defaultSort('date', 'desc');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('startDate')
                    ->label('Tanggal Mulai')
                    ->default(now()->startOfMonth())
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                DatePicker::make('endDate')
                    ->label('Tanggal Selesai')
                    ->default(now()->endOfMonth())
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                Select::make('productIds')
                    ->label('Produk')
                    ->options(fn () => Product::query()->orderBy('name')->get()->mapWithKeys(fn ($product) => [
                        $product->id => "{$product->name} ({$product->sku})"
                    ]))
                    ->multiple()
                    ->searchable()
                    ->placeholder('Semua Produk')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),

                Select::make('warehouseIds')
                    ->label('Gudang')
                    ->options(fn () => Warehouse::query()->orderBy('name')->get()->mapWithKeys(fn ($warehouse) => [
                        $warehouse->id => "{$warehouse->name} ({$warehouse->kode})"
                    ]))
                    ->multiple()
                    ->searchable()
                    ->placeholder('Semua Gudang')
                    ->live()
                    ->afterStateUpdated(fn () => $this->updateFilters()),
            ])
            ->columns(2);
    }

    public function updateFilters(): void
    {
        $formData = $this->form->getState();
        $this->startDate = $formData['startDate'] ?? null;
        $this->endDate = $formData['endDate'] ?? null;
        $this->productIds = array_filter($formData['productIds'] ?? []);
        $this->warehouseIds = array_filter($formData['warehouseIds'] ?? []);

        // Reset table pagination when filters change
        $this->resetTable();
    }

    private function getMovementTypeLabel(string $type): string
    {
        return match ($type) {
            'purchase_in' => 'Pembelian Masuk',
            'purchase_out' => 'Pembelian Keluar',
            'sales' => 'Penjualan',
            'manufacture_in' => 'Produksi Masuk',
            'manufacture_out' => 'Produksi Keluar',
            'transfer_in' => 'Transfer Masuk',
            'transfer_out' => 'Transfer Keluar',
            'adjustment_in' => 'Penyesuaian Masuk',
            'adjustment_out' => 'Penyesuaian Keluar',
            'return_in' => 'Retur Masuk',
            'return_out' => 'Retur Keluar',
            default => ucfirst(str_replace('_', ' ', $type))
        };
    }

    private function getReportData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfMonth()->startOfDay();
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : now()->endOfMonth()->endOfDay();

        $productIds = array_filter($this->productIds);
        $warehouseIds = array_filter($this->warehouseIds);

        // Query stock movements dengan filter
        $query = StockMovement::query()
            ->with(['product', 'warehouse', 'rak'])
            ->whereBetween('date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('warehouse_id')
            ->orderBy('date')
            ->orderBy('created_at');

        if (!empty($productIds)) {
            $query->whereIn('product_id', $productIds);
        }

        if (!empty($warehouseIds)) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        $movements = $query->get();

        // Calculate opening balances for each product-warehouse combination
        $openingBalances = [];
        $openingQuery = StockMovement::query()
            ->selectRaw('product_id, warehouse_id, SUM(CASE WHEN type IN ("purchase_in", "manufacture_in", "transfer_in", "adjustment_in", "return_in") THEN quantity ELSE 0 END) - SUM(CASE WHEN type IN ("sales", "transfer_out", "manufacture_out", "adjustment_out", "return_out") THEN quantity ELSE 0 END) as opening_balance')
            ->where('date', '<', $start->toDateTimeString())
            ->groupBy('product_id', 'warehouse_id');

        if (!empty($productIds)) {
            $openingQuery->whereIn('product_id', $productIds);
        }

        if (!empty($warehouseIds)) {
            $openingQuery->whereIn('warehouse_id', $warehouseIds);
        }

        $openingResults = $openingQuery->get();
        foreach ($openingResults as $result) {
            $openingBalances[$result->warehouse_id][$result->product_id] = $result->opening_balance;
        }

        // Group by warehouse
        $warehouseData = [];
        $totals = [
            'total_movements' => 0,
            'total_qty_in' => 0,
            'total_qty_out' => 0,
            'total_value_in' => 0,
            'total_value_out' => 0,
        ];

        foreach ($movements as $movement) {
            $warehouseId = $movement->warehouse_id;
            $productId = $movement->product_id;
            $warehouseName = $movement->warehouse->name ?? 'Tanpa Gudang';

            if (!isset($warehouseData[$warehouseId])) {
                $warehouseData[$warehouseId] = [
                    'warehouse_name' => $warehouseName,
                    'warehouse_code' => $movement->warehouse->kode ?? null,
                    'movements' => [],
                    'summary' => [
                        'qty_in' => 0,
                        'qty_out' => 0,
                        'value_in' => 0,
                        'value_out' => 0,
                        'net_qty' => 0,
                        'net_value' => 0,
                    ],
                    'running_balance' => [], // Change to array per product
                ];
            }

            // Initialize running balance for this product if not exists
            if (!isset($warehouseData[$warehouseId]['running_balance'][$productId])) {
                $openingBalance = $openingBalances[$warehouseId][$productId] ?? 0;
                $warehouseData[$warehouseId]['running_balance'][$productId] = $openingBalance;
            }

            // Determine if it's in or out movement
            $isInMovement = in_array($movement->type, [
                'purchase_in',
                'manufacture_in',
                'transfer_in',
                'adjustment_in',
                'return_in'
            ]);

            $isOutMovement = in_array($movement->type, [
                'sales',
                'transfer_out',
                'manufacture_out',
                'adjustment_out',
                'return_out'
            ]);

            $qtyIn = $isInMovement ? $movement->quantity : 0;
            $qtyOut = $isOutMovement ? $movement->quantity : 0;
            $valueIn = $isInMovement ? ($movement->value ?? 0) : 0;
            $valueOut = $isOutMovement ? ($movement->value ?? 0) : 0;

            // Calculate running balance for this product in this warehouse
            $warehouseData[$warehouseId]['running_balance'][$productId] += ($qtyIn - $qtyOut);

            $warehouseData[$warehouseId]['movements'][] = [
                'id' => $movement->id,
                'date' => $movement->date,
                'product_name' => $movement->product->name ?? 'Produk Tidak Ditemukan',
                'product_sku' => $movement->product->sku ?? null,
                'type' => $this->getMovementTypeLabel($movement->type),
                'quantity' => $movement->quantity,
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'value' => $movement->value,
                'value_in' => $valueIn,
                'value_out' => $valueOut,
                'reference' => $movement->reference_id,
                'notes' => $movement->notes,
                'rak_name' => $movement->rak->name ?? null,
                'balance' => $warehouseData[$warehouseId]['running_balance'][$productId], // Balance per product
            ];

            // Update warehouse summary
            $warehouseData[$warehouseId]['summary']['qty_in'] += $qtyIn;
            $warehouseData[$warehouseId]['summary']['qty_out'] += $qtyOut;
            $warehouseData[$warehouseId]['summary']['value_in'] += $valueIn;
            $warehouseData[$warehouseId]['summary']['value_out'] += $valueOut;
            $warehouseData[$warehouseId]['summary']['net_qty'] += ($qtyIn - $qtyOut);
            $warehouseData[$warehouseId]['summary']['net_value'] += ($valueIn - $valueOut);

            // Update totals
            $totals['total_movements']++;
            $totals['total_qty_in'] += $qtyIn;
            $totals['total_qty_out'] += $qtyOut;
            $totals['total_value_in'] += $valueIn;
            $totals['total_value_out'] += $valueOut;
        }

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'warehouseData' => array_values($warehouseData),
            'totals' => $totals,
            'filters' => [
                'products' => $this->getSelectedProductNames(),
                'warehouses' => $this->getSelectedWarehouseNames(),
            ]
        ];
    }

    public function getSelectedProductNames(): array
    {
        if (empty($this->productIds)) {
            return [];
        }

        return Product::query()
            ->whereIn('id', array_filter($this->productIds))
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }

    public function getSelectedWarehouseNames(): array
    {
        if (empty($this->warehouseIds)) {
            return [];
        }

        return Warehouse::query()
            ->whereIn('id', array_filter($this->warehouseIds))
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }
}