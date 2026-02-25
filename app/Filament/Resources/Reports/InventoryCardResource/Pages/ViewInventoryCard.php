<?php

namespace App\Filament\Resources\Reports\InventoryCardResource\Pages;

use App\Exports\InventoryCardExport;
use App\Filament\Resources\Reports\InventoryCardResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ViewInventoryCard extends Page
{
    protected static string $resource = InventoryCardResource::class;

    protected static string $view = 'filament.pages.reports.inventory-card';

    // Filter state
    public ?string $startDate   = null;
    public ?string $endDate     = null;
    public ?int    $productId   = null;
    public ?int    $warehouseId = null;

    // Preview state
    public bool  $showPreview = false;
    public array $reportData  = [];

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate   = now()->endOfMonth()->format('Y-m-d');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(function () {
                    if (!$this->startDate || !$this->endDate) {
                        Notification::make()
                            ->title('Tanggal wajib diisi')
                            ->danger()
                            ->send();
                        return;
                    }
                    $this->generatePreview();
                }),

            Action::make('reset')
                ->label('Reset')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible($this->showPreview)
                ->action(function () {
                    $this->resetPreview();
                }),
        ];
    }

    /**
     * Dipanggil saat user klik "Preview" — membangun data & menampilkan tabel.
     */
    public function generatePreview(): void
    {
        $this->reportData  = $this->buildReportData();
        $this->showPreview = true;
    }

    /**
     * Reset ke tampilan filter saja.
     */
    public function resetPreview(): void
    {
        $this->showPreview = false;
        $this->reportData  = [];
    }

    public function buildQueryParams(): array
    {
        return array_filter([
            'start'        => $this->startDate,
            'end'          => $this->endDate,
            'product_id'   => $this->productId,
            'warehouse_id' => $this->warehouseId,
        ]);
    }

    public function getPrintUrl(): string
    {
        return route('inventory-card.print', $this->buildQueryParams());
    }

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
            Forms\Components\Section::make('Filter Kartu Persediaan')
                ->columns(2)
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Tanggal Mulai')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(now()->startOfMonth()),

                    DatePicker::make('endDate')
                        ->label('Tanggal Akhir')
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->default(now()->endOfMonth()),

                    Select::make('productId')
                        ->label('Item (Produk)')
                        ->options(fn () => Product::query()->orderBy('name')->get()->mapWithKeys(fn ($product) => [
                            $product->id => $product->name . ($product->sku ? ' (' . $product->sku . ')' : '')
                        ]))
                        ->searchable()
                        ->preload()
                        ->placeholder('— Semua Produk —')
                        ->columnSpanFull(),

                    Select::make('warehouseId')
                        ->label('Gudang')
                        ->options(fn () => Warehouse::query()->orderBy('name')->get()->mapWithKeys(fn ($warehouse) => [
                            $warehouse->id => $warehouse->name . ($warehouse->kode ? ' (' . $warehouse->kode . ')' : '')
                        ]))
                        ->searchable()
                        ->preload()
                        ->placeholder('— Semua Gudang —')
                        ->columnSpanFull(),
                ]),
        ];
    }

    public function buildReportData(): array
    {
        $start = $this->startDate
            ? Carbon::parse($this->startDate)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $end = $this->endDate
            ? Carbon::parse($this->endDate)->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $productIds   = $this->productId   ? [$this->productId]   : [];
        $warehouseIds = $this->warehouseId ? [$this->warehouseId] : [];

        // Filter labels
        $productLabel   = $this->productId   ? (Product::find($this->productId)?->name ?? '-')   : 'Semua Produk';
        $warehouseLabel = $this->warehouseId ? (Warehouse::find($this->warehouseId)?->name ?? '-') : 'Semua Gudang';

        $openingData = $this->buildAggregateQuery($productIds, $warehouseIds)
            ->where('date', '<', $start->toDateTimeString())
            ->get()
            ->keyBy(fn ($row) => $row->product_id . '-' . $row->warehouse_id);

        $periodData = $this->buildAggregateQuery($productIds, $warehouseIds)
            ->whereBetween('date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->get()
            ->keyBy(fn ($row) => $row->product_id . '-' . $row->warehouse_id);

        $keys = $openingData->keys()->merge($periodData->keys())->unique()->values();

        $emptyTotals = [
            'opening_qty'   => 0.0,
            'opening_value' => 0.0,
            'qty_in'        => 0.0,
            'value_in'      => 0.0,
            'qty_out'       => 0.0,
            'value_out'     => 0.0,
            'closing_qty'   => 0.0,
            'closing_value' => 0.0,
        ];

        if ($keys->isEmpty()) {
            return [
                'period'          => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                'product_label'   => $productLabel,
                'warehouse_label' => $warehouseLabel,
                'rows'            => [],
                'totals'          => $emptyTotals,
            ];
        }

        $productMap = Product::query()
            ->whereIn('id', $keys->map(fn ($key) => (int) explode('-', $key)[0])->unique())
            ->get()->keyBy('id');

        $warehouseMap = Warehouse::query()
            ->whereIn('id', $keys->map(fn ($key) => (int) explode('-', $key)[1])->unique())
            ->get()->keyBy('id');

        $rows   = [];
        $totals = $emptyTotals;

        foreach ($keys as $key) {
            [$pid, $wid] = array_map('intval', explode('-', $key));

            $opening  = $openingData[$key]  ?? null;
            $movement = $periodData[$key]   ?? null;

            $openingQty   = ($opening->qty_in   ?? 0.0) - ($opening->qty_out   ?? 0.0);
            $openingValue = ($opening->value_in  ?? 0.0) - ($opening->value_out ?? 0.0);
            $qtyIn        = $movement->qty_in    ?? 0.0;
            $valueIn      = $movement->value_in  ?? 0.0;
            $qtyOut       = $movement->qty_out   ?? 0.0;
            $valueOut     = $movement->value_out ?? 0.0;
            $closingQty   = $openingQty + $qtyIn - $qtyOut;
            $closingValue = $openingValue + $valueIn - $valueOut;

            $hasMovement = ($qtyIn != 0) || ($qtyOut != 0) || ($valueIn != 0) || ($valueOut != 0);
            if (! $hasMovement) {
                continue;
            }

            $product   = $productMap->get($pid);
            $warehouse = $warehouseMap->get($wid);

            $rows[] = [
                'product_id'     => $pid,
                'product_name'   => $product->name  ?? 'Produk Tidak Ditemukan',
                'product_sku'    => $product->sku   ?? null,
                'warehouse_id'   => $wid,
                'warehouse_name' => $warehouse->name ?? 'Tanpa Gudang',
                'warehouse_code' => $warehouse->kode ?? null,
                'opening_qty'    => $openingQty,
                'opening_value'  => $openingValue,
                'qty_in'         => $qtyIn,
                'value_in'       => $valueIn,
                'qty_out'        => $qtyOut,
                'value_out'      => $valueOut,
                'closing_qty'    => $closingQty,
                'closing_value'  => $closingValue,
            ];

            $totals['opening_qty']   += $openingQty;
            $totals['opening_value'] += $openingValue;
            $totals['qty_in']        += $qtyIn;
            $totals['value_in']      += $valueIn;
            $totals['qty_out']       += $qtyOut;
            $totals['value_out']     += $valueOut;
            $totals['closing_qty']   += $closingQty;
            $totals['closing_value'] += $closingValue;
        }

        return [
            'period'          => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'product_label'   => $productLabel,
            'warehouse_label' => $warehouseLabel,
            'rows'            => $rows,
            'totals'          => $totals,
        ];
    }

    public function getProductOptions(): array
    {
        return Product::query()->orderBy('name')->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->name}" . ($p->sku ? " [{$p->sku}]" : '')])
            ->toArray();
    }

    public function getWarehouseOptions(): array
    {
        return Warehouse::query()->orderBy('name')->pluck('name', 'id')->toArray();
    }

    protected function buildAggregateQuery(array $productIds, array $warehouseIds): Builder
    {
        $inList  = "'" . implode("','", self::IN_TYPES)  . "'";
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

        if (! empty($productIds)) {
            $query->whereIn('product_id', $productIds);
        }

        if (! empty($warehouseIds)) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        return $query;
    }

    public function export(string $format = 'excel')
    {
        $report = $this->buildReportData();
        $filename = 'kartu-persediaan-' . now()->format('Ymd_His');

        if ($format === 'pdf') {
            $report['isPdf'] = true;
            $pdf = Pdf::loadView('reports.inventory-card-print', ['data' => $report])
                ->setPaper('a4', 'landscape');

            $pdfBinary = $pdf->output();

            // Ensure export directory exists
            $exportsDir = storage_path('app/exports');
            if (! is_dir($exportsDir)) {
                @mkdir($exportsDir, 0755, true);
            }

            $tmpFilename = $filename . '.pdf';
            $tmpPath = $exportsDir . '/' . $tmpFilename;
            file_put_contents($tmpPath, $pdfBinary);

            // If caller expects JSON (Livewire/Filament XHR), return a safe download URL instead
            if (request()->wantsJson() || request()->ajax()) {
                $url = route('exports.download', ['filename' => $tmpFilename]);
                return response()->json(['download_url' => $url]);
            }

            return response()->download($tmpPath, $tmpFilename)->deleteFileAfterSend(true);
        } else {
            // Excel export
            $export = new InventoryCardExport(
                startDate: $this->startDate,
                endDate: $this->endDate,
                productId: $this->productId,
                warehouseId: $this->warehouseId,
            );

            // Ensure export directory exists
            $exportsDir = storage_path('app/exports');
            if (! is_dir($exportsDir)) {
                @mkdir($exportsDir, 0755, true);
            }

            $tmpFilename = $filename . '.xlsx';
            $tmpPath = $exportsDir . '/' . $tmpFilename;

            $excelBinary = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
            file_put_contents($tmpPath, $excelBinary);

            if (request()->wantsJson() || request()->ajax()) {
                $url = route('exports.download', ['filename' => $tmpFilename]);
                return response()->json(['download_url' => $url]);
            }

            return response()->download($tmpPath, $tmpFilename)->deleteFileAfterSend(true);
        }
    }
}
