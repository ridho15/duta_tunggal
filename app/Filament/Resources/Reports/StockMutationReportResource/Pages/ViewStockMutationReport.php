<?php

namespace App\Filament\Resources\Reports\StockMutationReportResource\Pages;

use App\Filament\Resources\Reports\StockMutationReportResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use App\Exports\GenericViewExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;

class ViewStockMutationReport extends Page
{
    protected static string $resource = StockMutationReportResource::class;

    protected static string $view = 'filament.pages.reports.stock-mutation-report';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public array $productIds = [];

    public array $warehouseIds = [];

    public function mount(): void
    {
        // Handle export request
        if (request('export') === 'excel') {
            // Export will be handled by the exportExcel method called from the view
            // This is just to set the context
        }

        // Set default values
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Filter Laporan')
                ->columns(2)
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Tanggal Mulai')
                        ->default(now()->startOfMonth())
                        ->reactive(),
                    DatePicker::make('endDate')
                        ->label('Tanggal Selesai')
                        ->default(now()->endOfMonth())
                        ->reactive(),
                    Select::make('productIds')
                        ->label('Produk')
                        ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->helperText('Kosongkan untuk menampilkan semua produk'),
                    Select::make('warehouseIds')
                        ->label('Gudang')
                        ->options(fn () => Warehouse::query()->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->helperText('Kosongkan untuk menampilkan semua gudang'),
                ]),
        ];
    }

    public function getReportData(): array
    {
        // Support filter manual dari form blade (GET parameter 'start' dan 'end')
        $startDate = request('start', $this->startDate);
        $endDate = request('end', $this->endDate);

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfMonth()->endOfDay();

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
                    ]
                ];
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

    public function exportExcel()
    {
        try {
            $data = $this->getReportData();

            $filename = 'Laporan_Mutasi_Barang_' . date('Y-m-d_His') . '.xlsx';

            $view = view('filament.exports.stock-mutation-excel', [
                'report' => $data,
                'generated_at' => now(),
            ]);

            return Excel::download(
                new GenericViewExport($view),
                $filename
            );

        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Excel Gagal')
                ->danger()
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();
        }
    }
}