<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rehidrasi legacy request permintaan barang (knr_requests / dtm_requests)
 * ke ERP order_requests dan order_request_items.
 *
 * Data legacy ini tidak ada di archive legacy_transaction_archives, jadi
 * service membaca langsung database legacy inventory dan inventory_cab.
 *
 * Setiap request legacy menjadi satu order_request ERP dengan item-itemnya.
 */
class LegacyOrderRequestRehydrationService
{
    private array $productMap = [];

    public function rehydrate(array $options = []): array
    {
        $sourceNames = $this->normalizeSources($options['source'] ?? []);
        $execute = (bool) ($options['execute'] ?? false);
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $chunkSize = max(50, (int) ($options['chunk_size'] ?? 500));
        $dateFrom = $this->normalizeDate($options['from'] ?? null, false);
        $dateTo = $this->normalizeDate($options['to'] ?? null, true);
        $createdBy = $this->resolveCreatedBy($options['created_by'] ?? null);
        $onProgress = $options['on_progress'] ?? null;
        $inventoryWarehouseId = (int) ($options['inventory_warehouse_id'] ?? 2);
        $inventoryCabWarehouseId = (int) ($options['inventory_cab_warehouse_id'] ?? 3);

        $summary = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'sources' => $sourceNames,
            'date_from' => $dateFrom?->toDateString(),
            'date_to' => $dateTo?->toDateString(),
            'limit' => $limit,
            'created_by' => $createdBy,
            'rows' => [],
            'notes' => [
                'Legacy request diimpor langsung dari database inventory / inventory_cab karena tidak masuk ke archive transaksi.',
                'Idempotensi dijaga lewat request_number deterministik LEGACY-OR-{source}-{legacy_id}.',
                'Nilai unit_price/original_price diset 0 karena legacy request tidak menyimpan harga per item.',
                'fulfilled_quantity diisi dari qty_process jika ada, lalu status di-mapping dari status legacy.',
            ],
        ];

        $this->preloadProductMap();

        foreach ($sourceNames as $sourceName) {
            $target = $this->resolveTarget($sourceName, $inventoryWarehouseId, $inventoryCabWarehouseId);
            $requestsQuery = $this->requestsQuery($sourceName, $dateFrom, $dateTo, 0);
            $totalDocuments = $requestsQuery->count();
            if ($limit > 0) {
                $totalDocuments = min($totalDocuments, $limit);
            }

            $processed = 0;
            $requestsCreated = 0;
            $itemsCreated = 0;
            $skippedDuplicate = 0;
            $skippedNoItems = 0;
            $skippedNoProduct = 0;

            $this->requestsQuery($sourceName, $dateFrom, $dateTo, 0)
                ->chunkById($chunkSize, function ($requests) use (
                    $sourceName,
                    $target,
                    $execute,
                    $createdBy,
                    $limit,
                    $onProgress,
                    &$processed,
                    &$requestsCreated,
                    &$itemsCreated,
                    &$skippedDuplicate,
                    &$skippedNoItems,
                    &$skippedNoProduct
                ) {
                    $stopRequested = false;
                    $requestIds = $requests->pluck('id')->all();

                    $detailsByRequest = DB::table($this->detailTable($sourceName))
                        ->whereIn('request_id', $requestIds)
                        ->orderBy('id')
                        ->get()
                        ->groupBy('request_id');

                    foreach ($requests as $request) {
                        if ($limit > 0 && $processed >= $limit) {
                            $stopRequested = true;
                            break;
                        }

                        $processed++;

                        $requestNumber = 'LEGACY-OR-' . $sourceName . '-' . (int) $request->id;
                        $existingId = DB::table('order_requests')
                            ->where('request_number', $requestNumber)
                            ->value('id');

                        if ($existingId) {
                            $skippedDuplicate++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        $requestPayload = $this->legacyRequestPayload($request);
                        $status = $this->mapStatus($requestPayload);
                        $requestDate = $this->parseDate($request->request_date) ?? now();
                        $createdAt = $this->parseDate($request->created_date) ?? $requestDate;
                        $updatedAt = $this->parseDate($request->updated_date) ?? now();

                        $detailRows = $detailsByRequest->get((string) $request->id, collect());
                        $itemRows = [];
                        $documentMissingProducts = 0;

                        foreach ($detailRows as $detail) {
                            $productId = $this->resolveProductId($sourceName, (string) ($detail->product_code ?? ''), $target['cabang_id']);

                            if (! $productId) {
                                $documentMissingProducts++;
                                continue;
                            }

                            $quantity = (int) max(0, round((float) ($detail->qty ?? 0)));
                            if ($quantity <= 0) {
                                continue;
                            }

                            $fulfilledQuantity = (float) max(0, round((float) ($detail->qty_process ?? 0), 2));
                            if ($fulfilledQuantity > $quantity) {
                                $fulfilledQuantity = (float) $quantity;
                            }

                            $itemRows[] = [
                                'product_id' => $productId,
                                'supplier_id' => null,
                                'quantity' => $quantity,
                                'fulfilled_quantity' => $fulfilledQuantity,
                                'unit_price' => 0,
                                'original_price' => 0,
                                'discount' => 0,
                                'tax' => 0,
                                'subtotal' => 0,
                                'note' => $this->buildItemNote($detail),
                            ];
                        }

                        $skippedNoProduct += $documentMissingProducts;

                        if ($itemRows === []) {
                            $skippedNoItems++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        if ($execute) {
                            $now = now();
                            $legacyNote = $this->buildRequestNote($requestPayload);

                            $orderRequestId = (int) DB::table('order_requests')->insertGetId([
                                'request_number' => $requestNumber,
                                'warehouse_id' => $target['warehouse_id'],
                                'cabang_id' => $target['cabang_id'],
                                'request_date' => $requestDate->toDateString(),
                                'status' => $status,
                                'note' => $legacyNote,
                                'tax_type' => 'None',
                                'created_by' => $createdBy,
                                'created_at' => $createdAt,
                                'updated_at' => $updatedAt,
                            ]);

                            foreach ($itemRows as &$row) {
                                $row['order_request_id'] = $orderRequestId;
                                $row['created_at'] = $createdAt;
                                $row['updated_at'] = $updatedAt;
                            }
                            unset($row);

                            DB::table('order_request_items')->insert($itemRows);
                            $requestsCreated++;
                            $itemsCreated += count($itemRows);
                        } else {
                            $requestsCreated++;
                            $itemsCreated += count($itemRows);
                        }

                        if ($onProgress) {
                            ($onProgress)($processed);
                        }
                    }

                    return ! $stopRequested;
                }, 'id');

            $summary['rows'][] = [
                'source' => $sourceName,
                'warehouse_id' => $target['warehouse_id'],
                'cabang_id' => $target['cabang_id'],
                'documents' => $totalDocuments,
                'processed' => $processed,
                'requests_created' => $requestsCreated,
                'items_created' => $itemsCreated,
                'skipped_duplicate' => $skippedDuplicate,
                'skipped_no_items' => $skippedNoItems,
                'skipped_no_product' => $skippedNoProduct,
            ];
        }

        return $summary;
    }

    private function preloadProductMap(): void
    {
        $this->productMap = [];
        $rows = DB::table('products')
            ->whereNull('deleted_at')
            ->orderByRaw('CASE WHEN cabang_id = ? THEN 0 ELSE 1 END', [2])
            ->get(['id', 'sku']);

        foreach ($rows as $row) {
            $sku = strtolower((string) $row->sku);
            if (! isset($this->productMap[$sku])) {
                $this->productMap[$sku] = (int) $row->id;
            }
        }
    }

    private function resolveProductId(string $sourceName, string $productCode, int $targetCabangId): ?int
    {
        if ($productCode === '') {
            return null;
        }

        $candidates = $sourceName === 'inventory_cab'
            ? ['CAB-' . $productCode, $productCode]
            : [$productCode];

        foreach ($candidates as $candidate) {
            $id = $this->productMap[strtolower($candidate)] ?? null;
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    private function requestsQuery(string $sourceName, ?Carbon $dateFrom, ?Carbon $dateTo, int $limit)
    {
        $query = DB::table($this->requestTable($sourceName))
            ->orderBy('id');

        if ($dateFrom) {
            $query->whereDate('request_date', '>=', $dateFrom->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('request_date', '<=', $dateTo->toDateString());
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query;
    }

    private function requestTable(string $sourceName): string
    {
        return match ($sourceName) {
            'inventory' => 'inventory.knr_requests',
            'inventory_cab' => 'inventory_cab.dtm_requests',
            default => throw new InvalidArgumentException('Source tidak valid.'),
        };
    }

    private function detailTable(string $sourceName): string
    {
        return match ($sourceName) {
            'inventory' => 'inventory.knr_requests_detail',
            'inventory_cab' => 'inventory_cab.dtm_requests_detail',
            default => throw new InvalidArgumentException('Source tidak valid.'),
        };
    }

    private function resolveTarget(string $sourceName, int $inventoryWarehouseId, int $inventoryCabWarehouseId): array
    {
        if ($sourceName === 'inventory_cab') {
            return [
                'warehouse_id' => $inventoryCabWarehouseId,
                'cabang_id' => 3,
            ];
        }

        return [
            'warehouse_id' => $inventoryWarehouseId,
            'cabang_id' => 2,
        ];
    }

    private function legacyRequestPayload(object $request): array
    {
        return [
            'request_no' => $request->request_no ?? null,
            'request_place' => $request->request_place ?? null,
            'request_place_name' => $request->request_place_name ?? null,
            'dest_place' => $request->dest_place ?? null,
            'dest_place_name' => $request->dest_place_name ?? null,
            'process_document_no' => $request->process_document_no ?? null,
            'result_document_no' => $request->result_document_no ?? null,
            'description' => $request->description ?? null,
            'created_by' => $request->created_by ?? null,
            'created_date' => $request->created_date ?? null,
            'updated_by' => $request->updated_by ?? null,
            'updated_date' => $request->updated_date ?? null,
            'request_status' => $request->request_status ?? null,
            'delivery_status' => $request->delivery_status ?? null,
            'process_status' => $request->process_status ?? null,
        ];
    }

    private function buildRequestNote(array $payload): string
    {
        $parts = [
            'Legacy request_no: ' . ($payload['request_no'] ?? '-'),
            'request_place: ' . trim((string) ($payload['request_place_name'] ?? $payload['request_place'] ?? '-')),
            'dest_place: ' . trim((string) ($payload['dest_place_name'] ?? $payload['dest_place'] ?? '-')),
            'process_document_no: ' . ($payload['process_document_no'] ?? '-'),
            'result_document_no: ' . ($payload['result_document_no'] ?? '-'),
            'legacy_created_by: ' . ($payload['created_by'] ?? '-'),
        ];

        if (! empty($payload['description'])) {
            $parts[] = 'description: ' . trim((string) $payload['description']);
        }

        if (! empty($payload['updated_by'])) {
            $parts[] = 'legacy_updated_by: ' . $payload['updated_by'];
        }

        return implode(' | ', $parts);
    }

    private function buildItemNote(object $detail): string
    {
        $parts = [
            'Legacy product_code: ' . ($detail->product_code ?? '-'),
            'qty: ' . (string) ($detail->qty ?? 0),
            'qty_deliver: ' . (string) ($detail->qty_deliver ?? 0),
            'qty_process: ' . (string) ($detail->qty_process ?? 0),
        ];

        if (! empty($detail->deliver_by)) {
            $parts[] = 'deliver_by: ' . $detail->deliver_by;
        }

        if (! empty($detail->process_by)) {
            $parts[] = 'process_by: ' . $detail->process_by;
        }

        return implode(' | ', $parts);
    }

    private function mapStatus(array $payload): string
    {
        $combined = strtolower(implode(' ', array_filter([
            (string) ($payload['request_status'] ?? ''),
            (string) ($payload['delivery_status'] ?? ''),
            (string) ($payload['process_status'] ?? ''),
        ])));

        return match (true) {
            str_contains($combined, 'batal'), str_contains($combined, 'reject'), str_contains($combined, 'tolak') => 'rejected',
            str_contains($combined, 'selesai'), str_contains($combined, 'diterima semua') => 'complete',
            str_contains($combined, 'sebagian') => 'partial',
            str_contains($combined, 'diproses') => 'approved',
            str_contains($combined, 'request') => 'request_approve',
            default => 'approved',
        };
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

    private function normalizeDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $date = Carbon::parse((string) $value);

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
