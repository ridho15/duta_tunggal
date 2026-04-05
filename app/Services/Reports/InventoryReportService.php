<?php

namespace App\Services\Reports;

use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InventoryReportService
{
    public function stockQuery(array $filters = []): Builder
    {
        return InventoryStock::query()
            ->when($filters['warehouse_id'] ?? null, fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->where('product_id', $productId))
            ->with(['product', 'warehouse', 'rak'])
            ->orderBy('warehouse_id')
            ->orderBy('product_id');
    }

    public function movementQuery(array $filters = []): Builder
    {
        return StockMovement::query()
            ->when($filters['start_date'] ?? null, fn ($query, $startDate) => $query->whereDate('date', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn ($query, $endDate) => $query->whereDate('date', '<=', $endDate))
            ->when($filters['warehouse_id'] ?? null, fn ($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->where('product_id', $productId))
            ->with(['product', 'warehouse', 'rak'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');
    }

    public function agingQuery(array $filters = []): Builder
    {
        return $this->stockQuery($filters);
    }

    public function stockRows(array $filters = []): Collection
    {
        return $this->stockQuery($filters)
            ->get()
            ->map(function (InventoryStock $stock) {
                $qtyOnHand = $this->qtyOnHand($stock);

                return [
                    'Gudang' => $stock->warehouse->name ?? '-',
                    'Kode Produk' => $stock->product->code ?? '-',
                    'Nama Produk' => $stock->product->name ?? '-',
                    'Rak' => $stock->rak->name ?? '-',
                    'Qty Fisik' => $stock->qty_available,
                    'Qty Reserved' => $stock->qty_reserved,
                    'Qty Minimum' => $stock->qty_min,
                    'Qty Tersedia Bebas' => $qtyOnHand,
                    'Status' => $this->stockStatus($qtyOnHand, (float) ($stock->qty_min ?? 0)),
                ];
            });
    }

    public function movementRows(array $filters = []): Collection
    {
        return $this->movementQuery($filters)
            ->get()
            ->map(function (StockMovement $movement) {
                return [
                    'Tanggal' => $movement->date,
                    'Kode Produk' => $movement->product->code ?? '-',
                    'Nama Produk' => $movement->product->name ?? '-',
                    'Gudang' => $movement->warehouse->name ?? '-',
                    'Rak' => $movement->rak->name ?? '-',
                    'Tipe Movement' => $movement->type,
                    'Quantity' => $movement->quantity,
                    'Nilai' => $movement->value,
                    'Referensi' => $this->movementReference($movement),
                    'Catatan' => $movement->notes ?? '-',
                ];
            });
    }

    public function agingRows(array $filters = []): Collection
    {
        $asOf = $this->resolveAsOfDate($filters['as_of_date'] ?? null);

        return $this->agingQuery($filters)
            ->get()
            ->map(function (InventoryStock $stock) use ($asOf) {
                $qtyOnHand = $this->qtyOnHand($stock);
                $lastMovement = $this->lastMovement($stock);
                $agingDays = $this->agingDays($stock, $asOf, $lastMovement);

                return [
                    'Gudang' => $stock->warehouse->name ?? '-',
                    'Kode Produk' => $stock->product->code ?? '-',
                    'Nama Produk' => $stock->product->name ?? '-',
                    'Rak' => $stock->rak->name ?? '-',
                    'Qty Fisik' => $stock->qty_available,
                    'Qty Reserved' => $stock->qty_reserved,
                    'Qty Tersedia Bebas' => $qtyOnHand,
                    'Terakhir Movement' => $lastMovement?->date,
                    'Hari Aging' => $agingDays === null ? 'Tidak Ada Data' : $agingDays,
                    'Kategori Aging' => $this->agingCategory($agingDays),
                ];
            });
    }

    public function pdfPayload(array $filters = []): array
    {
        $type = $filters['type'] ?? 'stock';

        $data = match ($type) {
            'movement' => $this->movementRows($filters),
            'aging' => $this->agingRows($filters),
            default => $this->stockRows($filters),
        };

        return [
            'data' => $data,
            'type' => $type,
            'start_date' => $filters['start_date'] ?? null,
            'end_date' => $filters['end_date'] ?? null,
            'warehouse' => ! empty($filters['warehouse_id']) ? Warehouse::find($filters['warehouse_id']) : null,
            'product' => ! empty($filters['product_id']) ? Product::find($filters['product_id']) : null,
        ];
    }

    public function qtyOnHand(InventoryStock $stock): float
    {
        return (float) $stock->qty_available - (float) $stock->qty_reserved;
    }

    public function stockStatusForRecord(InventoryStock $stock): string
    {
        return $this->stockStatus($this->qtyOnHand($stock), (float) ($stock->qty_min ?? 0));
    }

    public function lastMovementDateForRecord(InventoryStock $stock): ?Carbon
    {
        return $this->lastMovement($stock)?->date;
    }

    public function agingDaysForRecord(InventoryStock $stock, Carbon|string|null $asOfDate = null): ?int
    {
        return $this->agingDays($stock, $this->resolveAsOfDate($asOfDate), $this->lastMovement($stock));
    }

    public function agingCategoryForRecord(InventoryStock $stock, Carbon|string|null $asOfDate = null): string
    {
        return $this->agingCategory($this->agingDaysForRecord($stock, $asOfDate));
    }

    public function movementReference(StockMovement $movement): string
    {
        if ($movement->from_model_type && $movement->from_model_id) {
            return class_basename($movement->from_model_type) . ' #' . $movement->from_model_id;
        }

        return '-';
    }

    private function stockStatus(float $qtyOnHand, float $qtyMin): string
    {
        if ($qtyOnHand <= 0) {
            return 'Habis';
        }

        if ($qtyOnHand <= $qtyMin) {
            return 'Minimum';
        }

        return 'Normal';
    }

    private function lastMovement(InventoryStock $stock): ?StockMovement
    {
        return StockMovement::query()
            ->where('product_id', $stock->product_id)
            ->where('warehouse_id', $stock->warehouse_id)
            ->orderBy('date', 'desc')
            ->first();
    }

    private function agingDays(InventoryStock $stock, Carbon $asOfDate, ?StockMovement $lastMovement = null): ?int
    {
        $movement = $lastMovement ?? $this->lastMovement($stock);

        if (! $movement?->date) {
            return null;
        }

        return Carbon::parse($movement->date)->diffInDays($asOfDate);
    }

    private function agingCategory(?int $days): string
    {
        if ($days === null) {
            return 'Tidak Ada Movement';
        }

        if ($days <= 30) {
            return 'Aktif';
        }

        if ($days <= 90) {
            return 'Slow Moving';
        }

        if ($days <= 180) {
            return 'Stagnan';
        }

        return 'Dead Stock';
    }

    private function resolveAsOfDate(Carbon|string|null $asOfDate): Carbon
    {
        if ($asOfDate instanceof Carbon) {
            return $asOfDate->copy();
        }

        if (is_string($asOfDate) && $asOfDate !== '') {
            return Carbon::parse($asOfDate);
        }

        return now();
    }
}