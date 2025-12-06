<?php

namespace App\Filament\Resources\Reports\InventoryCardResource\Pages;

use App\Filament\Resources\Reports\InventoryCardResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\Cabang;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ViewInventoryCard extends Page
{
    protected static string $resource = InventoryCardResource::class;

    protected static string $view = 'filament.pages.reports.inventory-card';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public array $productIds = [];

    public array $warehouseIds = [];

    public array $cabangIds = [];

    private const IN_TYPES = [
        'purchase_in',
        'manufacture_in',
        'transfer_in',
        'adjustment_in',
    ];

    private const OUT_TYPES = [
        'sales',
        'transfer_out',
        'manufacture_out',
        'adjustment_out',
    ];

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Filter')
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
                    Select::make('cabangIds')
                        ->label('Cabang')
                        ->options(function () {
                            return Cabang::all()->mapWithKeys(function ($cabang) {
                                return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                            });
                        })
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->getSearchResultsUsing(function (string $search) {
                            return Cabang::where('nama', 'like', "%{$search}%")
                                ->orWhere('kode', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(function ($cabang) {
                                    return [$cabang->id => "({$cabang->kode}) {$cabang->nama}"];
                                });
                        })
                        ->helperText('Kosongkan bila ingin menampilkan semua cabang'),
                    Select::make('productIds')
                        ->label('Produk')
                        ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->helperText('Kosongkan bila ingin menampilkan semua produk'),
                    Select::make('warehouseIds')
                        ->label('Gudang')
                        ->options(fn () => Warehouse::query()->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->helperText('Kosongkan bila ingin menampilkan semua gudang'),
                ]),
        ];
    }

    public function getReportData(): array
    {
        // Support filter manual dari form blade (GET parameter 'start' dan 'end')
        // Prioritize GET parameters over Filament form properties
        $startDate = request('start', $this->startDate);
        $endDate = request('end', $this->endDate);

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfMonth()->endOfDay();

        $productIds = array_filter($this->productIds);
        $warehouseIds = array_filter($this->warehouseIds);
        $cabangIds = array_filter($this->cabangIds);

        $openingData = $this->buildAggregateQuery($productIds, $warehouseIds, $cabangIds)
            ->where('date', '<', $start->toDateTimeString())
            ->get()
            ->keyBy(fn ($row) => $row->product_id . '-' . $row->warehouse_id);

        $periodData = $this->buildAggregateQuery($productIds, $warehouseIds, $cabangIds)
            ->whereBetween('date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->get()
            ->keyBy(fn ($row) => $row->product_id . '-' . $row->warehouse_id);

        $keys = $openingData->keys()->merge($periodData->keys())->unique()->values();

        if ($keys->isEmpty()) {
            return [
                'period' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ],
                'rows' => [],
                'totals' => [
                    'opening_qty' => 0.0,
                    'opening_value' => 0.0,
                    'qty_in' => 0.0,
                    'value_in' => 0.0,
                    'qty_out' => 0.0,
                    'value_out' => 0.0,
                    'closing_qty' => 0.0,
                    'closing_value' => 0.0,
                ],
            ];
        }

        $productMap = Product::query()
            ->whereIn('id', $keys->map(fn ($key) => (int) explode('-', $key)[0])->unique())
            ->get()
            ->keyBy('id');

        $warehouseMap = Warehouse::query()
            ->whereIn('id', $keys->map(fn ($key) => (int) explode('-', $key)[1])->unique())
            ->get()
            ->keyBy('id');

        $rows = [];
        $totals = [
            'opening_qty' => 0.0,
            'opening_value' => 0.0,
            'qty_in' => 0.0,
            'value_in' => 0.0,
            'qty_out' => 0.0,
            'value_out' => 0.0,
            'closing_qty' => 0.0,
            'closing_value' => 0.0,
        ];

        foreach ($keys as $key) {
            [$productId, $warehouseId] = array_map('intval', explode('-', $key));

            $opening = $openingData[$key] ?? null;
            $movement = $periodData[$key] ?? null;

            $openingQty = ($opening->qty_in ?? 0.0) - ($opening->qty_out ?? 0.0);
            $openingValue = ($opening->value_in ?? 0.0) - ($opening->value_out ?? 0.0);
            $qtyIn = $movement->qty_in ?? 0.0;
            $valueIn = $movement->value_in ?? 0.0;
            $qtyOut = $movement->qty_out ?? 0.0;
            $valueOut = $movement->value_out ?? 0.0;
            $closingQty = $openingQty + $qtyIn - $qtyOut;
            $closingValue = $openingValue + $valueIn - $valueOut;

            $product = $productMap->get($productId);
            $warehouse = $warehouseMap->get($warehouseId);

            // Hanya tampilkan jika ada transaksi pada periode filter
            $hasMovement = ($qtyIn != 0) || ($qtyOut != 0) || ($valueIn != 0) || ($valueOut != 0);
            if ($hasMovement) {
                $rows[] = [
                    'product_id' => $productId,
                    'product_name' => $product->name ?? 'Produk Tidak Ditemukan',
                    'product_sku' => $product->sku ?? null,
                    'warehouse_id' => $warehouseId,
                    'warehouse_name' => $warehouse->name ?? 'Tanpa Gudang',
                    'warehouse_code' => $warehouse->kode ?? null,
                    'opening_qty' => $openingQty,
                    'opening_value' => $openingValue,
                    'qty_in' => $qtyIn,
                    'value_in' => $valueIn,
                    'qty_out' => $qtyOut,
                    'value_out' => $valueOut,
                    'closing_qty' => $closingQty,
                    'closing_value' => $closingValue,
                ];

                $totals['opening_qty'] += $openingQty;
                $totals['opening_value'] += $openingValue;
                $totals['qty_in'] += $qtyIn;
                $totals['value_in'] += $valueIn;
                $totals['qty_out'] += $qtyOut;
                $totals['value_out'] += $valueOut;
                $totals['closing_qty'] += $closingQty;
                $totals['closing_value'] += $closingValue;
            }
        }

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    protected function buildAggregateQuery(array $productIds, array $warehouseIds, array $cabangIds = []): Builder
    {
        $inList = "'" . implode("','", self::IN_TYPES) . "'";
        $outList = "'" . implode("','", self::OUT_TYPES) . "'";

        $query = StockMovement::query()
            ->selectRaw(
                'product_id, warehouse_id, '
                . "SUM(CASE WHEN type IN ($inList) THEN quantity ELSE 0 END) AS qty_in, "
                . "SUM(CASE WHEN type IN ($outList) THEN quantity ELSE 0 END) AS qty_out, "
                . "SUM(CASE WHEN type IN ($inList) THEN COALESCE(value, 0) ELSE 0 END) AS value_in, "
                . "SUM(CASE WHEN type IN ($outList) THEN COALESCE(value, 0) ELSE 0 END) AS value_out"
            )
            ->groupBy('product_id', 'warehouse_id');

        if (!empty($productIds)) {
            $query->whereIn('product_id', $productIds);
        }

        if (!empty($warehouseIds)) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        if (!empty($cabangIds)) {
            $query->whereHas('warehouse', function (Builder $builder) use ($cabangIds) {
                $builder->whereIn('cabang_id', $cabangIds);
            });
        }

        return $query;
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

    public function getSelectedCabangNames(): array
    {
        if (empty($this->cabangIds)) {
            return [];
        }

        return Cabang::query()
            ->whereIn('id', array_filter($this->cabangIds))
            ->orderBy('nama')
            ->get()
            ->map(fn ($cabang) => "({$cabang->kode}) {$cabang->nama}")
            ->toArray();
    }
}
