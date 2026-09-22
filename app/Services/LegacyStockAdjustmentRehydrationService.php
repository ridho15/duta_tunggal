<?php

namespace App\Services;

use App\Models\LegacyTransactionArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rehidrasi stock adjustment dari legacy stock_adjustment ke ERP stock_adjustments.
 *
 * Catatan: Adjustment diimpor sebagai referensi historis saja.
 * Current stock levels sudah benar dari opening stock import — service ini
 * TIDAK mengubah inventory_stocks, hanya membuat rekam jejak di ERP.
 *
 * Setiap baris legacy adjustment (1 produk) → 1 dokumen ERP stock_adjustment dengan 1 item.
 */
class LegacyStockAdjustmentRehydrationService
{
    private array $productMap = []; // 'sourceName:skuKey' → ERP product_id

    public function rehydrate(array $options = []): array
    {
        $sourceNames  = $this->normalizeSources($options['source'] ?? []);
        $execute      = (bool) ($options['execute'] ?? false);
        $limit        = max(0, (int) ($options['limit'] ?? 0));
        $chunkSize    = max(100, (int) ($options['chunk_size'] ?? 1000));
        $dateFrom     = $this->normalizeDate($options['from'] ?? null, false);
        $dateTo       = $this->normalizeDate($options['to'] ?? null, true);
        $createdBy    = $this->resolveCreatedBy($options['created_by'] ?? null);
        $onProgress   = $options['on_progress'] ?? null;
        $inventoryWarehouseId    = (int) ($options['inventory_warehouse_id'] ?? 2);
        $inventoryCabWarehouseId = (int) ($options['inventory_cab_warehouse_id'] ?? 3);

        $summary = [
            'mode'      => $execute ? 'execute' : 'dry-run',
            'sources'   => $sourceNames,
            'date_from' => $dateFrom?->toDateString(),
            'date_to'   => $dateTo?->toDateString(),
            'limit'     => $limit,
            'rows'      => [],
            'notes'     => [
                'Stock adjustment diimpor sebagai referensi historis — tidak mengubah inventory_stocks.',
                'Idempotensi: skip jika adjustment_number LEGACY-SA-{source}-{legacy_id} sudah ada.',
                'Setiap baris legacy (1 produk) menjadi 1 dokumen stock_adjustment ERP dengan 1 item.',
            ],
        ];

        $this->preloadProductMap();

        foreach ($sourceNames as $sourceName) {
            $warehouseId = $sourceName === 'inventory_cab' ? $inventoryCabWarehouseId : $inventoryWarehouseId;

            $totalDocuments = $this->adjustmentQuery($sourceName, $dateFrom, $dateTo, 0)->count();
            if ($limit > 0) {
                $totalDocuments = min($totalDocuments, $limit);
            }

            $processed = 0;
            $created         = 0;
            $skippedDuplicate = 0;
            $skippedNoProduct = 0;

            $this->adjustmentQuery($sourceName, $dateFrom, $dateTo, 0)
                ->chunkById($chunkSize, function ($rows) use (
                    $sourceName, $warehouseId, $execute, $createdBy, $limit, $onProgress,
                    &$processed, &$created, &$skippedDuplicate, &$skippedNoProduct
                ) {
                    $stopRequested = false;

                    foreach ($rows as $row) {
                        if ($limit > 0 && $processed >= $limit) {
                            $stopRequested = true;
                            break;
                        }

                        $processed++;

                        $payload = is_array($row->payload) ? $row->payload : [];
                        $adjNumber = 'LEGACY-SA-' . $sourceName . '-' . (int) $row->legacy_id;

                        // Idempotency
                        $existingId = DB::table('stock_adjustments')
                            ->where('adjustment_number', $adjNumber)
                            ->value('id');

                        if ($existingId) {
                            $skippedDuplicate++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        // Resolve product
                        $legacyProductId = (int) ($payload['product_id'] ?? 0);
                        $productCode     = (string) ($row->product_code ?? '');
                        $productId = $this->resolveProduct($sourceName, $legacyProductId, $productCode);

                        if (! $productId) {
                            $skippedNoProduct++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        $oldQty  = (float) ($payload['old_qty'] ?? 0);
                        $newQty  = (float) ($payload['new_qty'] ?? 0);
                        $diffQty = $newQty - $oldQty;
                        $adjType = $diffQty >= 0 ? 'increase' : 'decrease';

                        $adjDate = $this->parseDate($payload['created_date'] ?? null)
                            ?? Carbon::parse($row->document_date ?? now());

                        if ($execute) {
                            $now = now();

                            $adjId = (int) DB::table('stock_adjustments')->insertGetId([
                                'adjustment_number' => $adjNumber,
                                'adjustment_date'   => $adjDate->toDateString(),
                                'warehouse_id'      => $warehouseId,
                                'adjustment_type'   => $adjType,
                                'reason'            => $payload['description'] ?? 'Legacy stock adjustment',
                                'notes'             => 'Migrated from ' . $sourceName . ' (legacy ID: ' . $row->legacy_id . ')',
                                'status'            => 'approved',
                                'created_by'        => $createdBy,
                                'approved_by'       => $createdBy,
                                'approved_at'       => $adjDate,
                                'created_at'        => $adjDate,
                                'updated_at'        => $now,
                            ]);

                            DB::table('stock_adjustment_items')->insert([
                                'stock_adjustment_id' => $adjId,
                                'product_id'          => $productId,
                                'rak_id'              => null,
                                'current_qty'         => round($oldQty, 2),
                                'adjusted_qty'        => round($newQty, 2),
                                'difference_qty'      => round(abs($diffQty), 2),
                                'unit_cost'           => 0,
                                'difference_value'    => 0,
                                'notes'               => null,
                                'created_at'          => $adjDate,
                                'updated_at'          => $now,
                            ]);

                            $created++;
                        } else {
                            $created++;
                        }

                        if ($onProgress) {
                            ($onProgress)($processed);
                        }
                    }

                    return ! $stopRequested;
                });

            $summary['rows'][] = [
                'source'           => $sourceName,
                'warehouse_id'     => $warehouseId,
                'documents'        => $totalDocuments,
                'processed'        => $processed,
                'created'          => $created,
                'skipped_duplicate' => $skippedDuplicate,
                'skipped_no_product' => $skippedNoProduct,
            ];
        }

        return $summary;
    }

    // ─── Private Helpers ────────────────────────────────────────────────────────

    private function preloadProductMap(): void
    {
        $this->productMap = [];
        $rows = DB::table('products')->whereNull('deleted_at')->get(['id', 'sku']);

        foreach ($rows as $row) {
            $sku = strtolower((string) $row->sku);
            $id  = (int) $row->id;
            $this->productMap[$sku] = $id;
        }
    }

    private function resolveProduct(string $sourceName, int $legacyProductId, string $productCode): ?int
    {
        // Try product by code from archive
        if ($productCode !== '') {
            $candidates = $sourceName === 'inventory_cab'
                ? ['cab-' . strtolower($productCode), strtolower($productCode)]
                : [strtolower($productCode)];

            foreach ($candidates as $c) {
                if (isset($this->productMap[$c])) {
                    return $this->productMap[$c];
                }
            }
        }

        return null;
    }

    private function adjustmentQuery(string $sourceName, ?Carbon $dateFrom, ?Carbon $dateTo, int $limit)
    {
        $query = LegacyTransactionArchive::query()
            ->where('source_name', $sourceName)
            ->where('table_name', 'stock_adjustment')
            ->where('row_kind', 'adjustment')
            ->orderBy('id');

        if ($dateFrom) {
            $query->whereDate('document_date', '>=', $dateFrom->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('document_date', '<=', $dateTo->toDateString());
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizeDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $date = Carbon::parse((string) $value);
        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    private function normalizeSources(array $sourceNames): array
    {
        $sourceNames = array_values(array_filter(array_map('trim', $sourceNames)));

        if ($sourceNames === []) {
            return ['inventory', 'inventory_cab'];
        }

        foreach ($sourceNames as $sourceName) {
            if (! in_array($sourceName, ['inventory', 'inventory_cab'], true)) {
                throw new InvalidArgumentException('Source harus inventory atau inventory_cab.');
            }
        }

        return array_values(array_unique($sourceNames));
    }

    private function resolveCreatedBy(mixed $createdBy): int
    {
        if ($createdBy !== null && $createdBy !== '') {
            return (int) $createdBy;
        }

        return (int) DB::table('users')->orderBy('id')->value('id');
    }
}
