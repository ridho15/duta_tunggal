<?php

namespace App\Services;

use App\Models\LegacyTransactionArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rehidrasi stock transfer dari legacy mutations ke ERP stock_transfers.
 *
 * Catatan: Stock transfer diimpor sebagai referensi historis saja.
 * Current stock levels sudah benar dari opening stock import — service ini
 * TIDAK mengubah inventory_stocks, hanya membuat rekam jejak mutasi di ERP.
 */
class LegacyStockTransferRehydrationService
{
    private array $productMap = []; // 'sourceName:legacyProductId' → ERP product_id

    public function rehydrate(array $options = []): array
    {
        $sourceNames  = $this->normalizeSources($options['source'] ?? []);
        $execute      = (bool) ($options['execute'] ?? false);
        $limit        = max(0, (int) ($options['limit'] ?? 0));
        $chunkSize    = max(50, (int) ($options['chunk_size'] ?? 500));
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
                'Stock transfer diimpor sebagai referensi historis saja — tidak mengubah inventory_stocks.',
                'Idempotensi: skip jika transfer_number LEGACY-MT-{source}-{legacy_id} sudah ada.',
                'from_warehouse_id dan to_warehouse_id keduanya dipetakan ke warehouse ERP yang sama karena original_id/dest_id adalah ID internal legacy.',
            ],
        ];

        $this->preloadProductMap();

        foreach ($sourceNames as $sourceName) {
            $warehouseId = $sourceName === 'inventory_cab' ? $inventoryCabWarehouseId : $inventoryWarehouseId;

            $totalDocuments = $this->documentsQuery($sourceName, $dateFrom, $dateTo, 0)->count();
            if ($limit > 0) {
                $totalDocuments = min($totalDocuments, $limit);
            }

            $processed = 0;
            $transfersCreated    = 0;
            $skippedDuplicate    = 0;
            $skippedMissingItems = 0;

            $this->documentsQuery($sourceName, $dateFrom, $dateTo, 0)
                ->chunkById($chunkSize, function ($documents) use (
                    $sourceName, $warehouseId, $execute, $limit, $onProgress,
                    &$processed, &$transfersCreated, &$skippedDuplicate, &$skippedMissingItems
                ) {
                    $stopRequested = false;

                    // Batch-load detail rows for this chunk
                    $docIds = $documents->pluck('legacy_id')->all();
                    $detailsByParent = LegacyTransactionArchive::query()
                        ->where('source_name', $sourceName)
                        ->where('table_name', 'mutations_detail')
                        ->whereIn('parent_legacy_id', $docIds)
                        ->orderBy('id')
                        ->get()
                        ->groupBy('parent_legacy_id');

                    foreach ($documents as $document) {
                        if ($limit > 0 && $processed >= $limit) {
                            $stopRequested = true;
                            break;
                        }

                        $processed++;

                        $payload = is_array($document->payload) ? $document->payload : [];
                        $transferNumber = 'LEGACY-MT-' . $sourceName . '-' . (int) $document->legacy_id;

                        // Idempotency
                        $existingId = DB::table('stock_transfers')
                            ->where('transfer_number', $transferNumber)
                            ->value('id');

                        if ($existingId) {
                            $skippedDuplicate++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        $details = $detailsByParent->get((string) $document->legacy_id, collect());
                        $items = [];

                        foreach ($details as $detail) {
                            $dp = is_array($detail->payload) ? $detail->payload : [];
                            $legacyProductId = (int) ($dp['product_id'] ?? 0);

                            $productId = $this->productMap[$sourceName . ':' . $legacyProductId] ?? null;
                            if (! $productId) {
                                // Try by product_code in the archive
                                $productId = $this->productMap[$sourceName . ':code:' . ($detail->product_code ?? '')] ?? null;
                            }
                            if (! $productId) {
                                continue;
                            }

                            $qty = max(1, (int) ($dp['qty_deliver'] ?? $dp['qty'] ?? 1));
                            $items[] = [
                                'product_id'       => $productId,
                                'quantity'         => $qty,
                                'from_warehouse_id' => $warehouseId,
                                'from_rak_id'      => 0,
                                'to_warehouse_id'  => $warehouseId,
                                'to_rak_id'        => 0,
                            ];
                        }

                        if ($items === []) {
                            $skippedMissingItems++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        if ($execute) {
                            $transferDate = $document->document_date
                                ? Carbon::parse($document->document_date)
                                : now();
                            $now = now();

                            $transferId = (int) DB::table('stock_transfers')->insertGetId([
                                'transfer_number'   => $transferNumber,
                                'from_warehouse_id' => $warehouseId,
                                'to_warehouse_id'   => $warehouseId,
                                'transfer_date'     => $transferDate,
                                'status'            => 'Completed',
                                'created_at'        => $transferDate,
                                'updated_at'        => $now,
                            ]);

                            $itemRows = array_map(fn (array $item) => array_merge($item, [
                                'stock_transfer_id' => $transferId,
                                'created_at'        => $transferDate,
                                'updated_at'        => $now,
                            ]), $items);

                            DB::table('stock_transfer_items')->insert($itemRows);
                            $transfersCreated++;
                        } else {
                            $transfersCreated++;
                        }

                        if ($onProgress) {
                            ($onProgress)($processed);
                        }
                    }

                    return ! $stopRequested;
                });

            $summary['rows'][] = [
                'source'              => $sourceName,
                'warehouse_id'        => $warehouseId,
                'documents'           => $totalDocuments,
                'processed'           => $processed,
                'transfers_created'   => $transfersCreated,
                'skipped_duplicate'   => $skippedDuplicate,
                'skipped_no_items'    => $skippedMissingItems,
            ];
        }

        return $summary;
    }

    // ─── Private Helpers ────────────────────────────────────────────────────────

    private function preloadProductMap(): void
    {
        $this->productMap = [];

        // Load all products keyed by (sourceName:legacyProductId) — using sku prefix
        // inventory_cab SKUs have 'CAB-' prefix, inventory don't
        $rows = DB::table('products')->whereNull('deleted_at')->get(['id', 'sku']);

        foreach ($rows as $row) {
            $sku = (string) $row->sku;
            $id  = (int) $row->id;

            // inventory_cab: CAB-XXXXX → key = 'inventory_cab:code:XXXXX'
            if (str_starts_with($sku, 'CAB-')) {
                $this->productMap['inventory_cab:code:' . substr($sku, 4)] = $id;
                $this->productMap['inventory_cab:code:' . $sku] = $id;
            } else {
                // inventory
                $this->productMap['inventory:code:' . $sku] = $id;
            }
        }
    }

    private function documentsQuery(string $sourceName, ?Carbon $dateFrom, ?Carbon $dateTo, int $limit)
    {
        $query = LegacyTransactionArchive::query()
            ->where('source_name', $sourceName)
            ->where('table_name', 'mutations')
            ->where('row_kind', 'document')
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
