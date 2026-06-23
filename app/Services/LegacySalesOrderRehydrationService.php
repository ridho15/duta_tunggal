<?php

namespace App\Services;

use App\Models\LegacyTransactionArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacySalesOrderRehydrationService
{
    private array $customerMap = [];
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

        $summary = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'sources' => $sourceNames,
            'date_from' => $dateFrom?->toDateString(),
            'date_to' => $dateTo?->toDateString(),
            'limit' => $limit,
            'created_by' => $createdBy,
            'rows' => [],
            'notes' => [
                'Rehydrasi sales membuat dokumen aktif ke sale_orders dan sale_order_items dari arsip legacy tanpa men-trigger observer model karena memakai query builder.',
                'Idempotensi dijaga lewat pasangan legacy_source_name + legacy_legacy_id pada sale_orders.',
            ],
        ];

        foreach ($sourceNames as $sourceName) {
            $target = $this->sourceTarget($sourceName, $options);

            $totalDocuments = $this->documentsQuery($sourceName, $dateFrom, $dateTo, 0)->count();
            if ($limit > 0) {
                $totalDocuments = min($totalDocuments, $limit);
            }

            $processed = 0;
            $upserted = 0;
            $skippedCustomer = 0;
            $skippedItems = 0;
            $missingProducts = 0;

            // Pre-load lookup maps for fast in-memory resolution (avoids per-record DB queries)
            $this->preloadMaps($sourceName, $target['cabang_id']);

            $this->documentsQuery($sourceName, $dateFrom, $dateTo, 0)
                ->chunkById($chunkSize, function ($documents) use (
                    $sourceName, $target, $execute, $createdBy, $limit, $onProgress,
                    &$processed, &$upserted, &$skippedCustomer, &$skippedItems, &$missingProducts
                ) {
                    $stopRequested = false;

                    // Batch-load all detail rows for this chunk in one query
                    $docIds = $documents->pluck('legacy_id')->all();
                    $detailByParent = LegacyTransactionArchive::query()
                        ->where('source_name', $sourceName)
                        ->where('table_name', 'sales_detail')
                        ->whereIn('parent_legacy_id', $docIds)
                        ->orderBy('legacy_id')
                        ->get()
                        ->groupBy('parent_legacy_id');

                    foreach ($documents as $document) {
                        if ($limit > 0 && $processed >= $limit) {
                            $stopRequested = true;
                            break;
                        }

                        $customerId = $this->resolveCustomerIdFromMap($sourceName, (string) $document->party_code);

                        if (! $customerId) {
                            $skippedCustomer++;
                            $processed++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        $detailRows = $detailByParent->get((string) $document->legacy_id, collect());

                        $items = [];
                        $documentMissingProducts = 0;

                        foreach ($detailRows as $detailRow) {
                            $productId = $this->resolveProductIdFromMap($sourceName, (string) $detailRow->product_code);
                            if (! $productId) {
                                $documentMissingProducts++;
                                continue;
                            }

                            $payload = is_array($detailRow->payload) ? $detailRow->payload : [];
                            $quantity = (float) ($detailRow->quantity ?? 0);

                            if ($quantity <= 0) {
                                continue;
                            }

                            $items[] = [
                                'product_id' => $productId,
                                'quantity' => $quantity,
                                'delivered_quantity' => min((float) ($detailRow->processed_quantity ?? 0), $quantity),
                                'unit_price' => round((float) ($detailRow->unit_price ?? 0), 2),
                                'discount' => (int) round((float) ($payload['discount_value'] ?? 0)),
                                'tax' => (int) round((float) ($payload['tax_value'] ?? 0)),
                                'tipe_pajak' => $this->mapTaxType($payload['tax_type'] ?? null),
                                'warehouse_id' => $target['warehouse_id'],
                                'rak_id' => null,
                            ];
                        }

                        $missingProducts += $documentMissingProducts;

                        if ($items === []) {
                            $skippedItems++;
                            $processed++;
                            if ($onProgress) {
                                ($onProgress)($processed);
                            }
                            continue;
                        }

                        if ($execute) {
                            $this->upsertSaleOrder($document, $items, $customerId, $target, $createdBy);
                            $upserted++;
                        }

                        $processed++;
                        if ($onProgress) {
                            ($onProgress)($processed);
                        }
                    }

                    return ! $stopRequested;
                });

            $summary['rows'][] = [
                'source' => $sourceName,
                'target_cabang_id' => $target['cabang_id'],
                'target_warehouse_id' => $target['warehouse_id'],
                'documents' => $totalDocuments,
                'processed' => $processed,
                'upserted' => $upserted,
                'skipped_missing_customer' => $skippedCustomer,
                'skipped_without_items' => $skippedItems,
                'missing_products' => $missingProducts,
            ];
        }

        return $summary;
    }

    private function documentsQuery(string $sourceName, ?Carbon $dateFrom, ?Carbon $dateTo, int $limit)
    {
        $query = LegacyTransactionArchive::query()
            ->where('source_name', $sourceName)
            ->where('table_name', 'sales')
            ->where('row_kind', 'document')
            ->orderBy('id');

        if ($dateFrom) {
            $query->whereDate('document_date', '>=', $dateFrom->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('document_date', '<=', $dateTo->toDateString());
        }

        // Note: limit is handled in the chunk callback to avoid breaking chunkById
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query;
    }

    private function upsertSaleOrder(LegacyTransactionArchive $document, array $items, int $customerId, array $target, int $createdBy): void
    {
        $payload = is_array($document->payload) ? $document->payload : [];
        $documentDate = $document->document_date ? Carbon::parse($document->document_date) : now();
        $deliveryDate = $this->normalizeDate($payload['delivery_date'] ?? null, true) ?: $documentDate;
        $paymentDueDate = $this->normalizeDate($payload['payment_due_date'] ?? null, true);
        $tempoPembayaran = $paymentDueDate ? max(0, $documentDate->diffInDays($paymentDueDate, false)) : 0;
        $status = $this->mapStatus((string) ($document->status ?? ''), (string) ($document->payment_status ?? ''), (string) ($document->delivery_status ?? ''), (string) ($document->receive_status ?? ''));
        $now = now();

        DB::transaction(function () use ($document, $items, $customerId, $target, $createdBy, $documentDate, $deliveryDate, $tempoPembayaran, $status, $payload, $now) {
            $existingId = DB::table('sale_orders')
                ->where('legacy_source_name', $document->source_name)
                ->where('legacy_legacy_id', $document->legacy_id)
                ->value('id');

            $saleOrderData = [
                'customer_id' => $customerId,
                'quotation_id' => null,
                'so_number' => $this->legacySoNumber($document->source_name, (int) $document->legacy_id),
                'order_date' => $documentDate,
                'status' => $status,
                'delivery_date' => $deliveryDate,
                'tempo_pembayaran' => $tempoPembayaran,
                'total_amount' => round((float) ($document->amount ?? 0), 2),
                'created_by' => $createdBy,
                'shipped_to' => $this->filledString($payload['customer_invaddress'] ?? null) ?: $this->filledString($payload['description'] ?? null) ?: '-',
                'tipe_pengiriman' => 'Kirim Langsung',
                'cabang_id' => $target['cabang_id'],
                'legacy_source_name' => $document->source_name,
                'legacy_legacy_id' => (int) $document->legacy_id,
                'legacy_reference_number' => $document->reference_number,
                'updated_at' => $now,
            ];

            if ($status === 'completed') {
                $saleOrderData['completed_at'] = $deliveryDate;
            }

            if ($existingId) {
                DB::table('sale_orders')->where('id', $existingId)->update($saleOrderData);
                $saleOrderId = (int) $existingId;
            } else {
                $saleOrderId = (int) DB::table('sale_orders')->insertGetId(array_merge($saleOrderData, [
                    'created_at' => $documentDate,
                ]));
            }

            DB::table('sale_order_items')->where('sale_order_id', $saleOrderId)->delete();

            $itemRows = array_map(fn (array $item) => array_merge($item, [
                'sale_order_id' => $saleOrderId,
                'created_at' => $documentDate,
                'updated_at' => $now,
                'deleted_at' => null,
            ]), $items);

            DB::table('sale_order_items')->insert($itemRows);
        });
    }

    private function preloadMaps(string $sourceName, int $targetCabangId): void
    {
        // Pre-load all customer codes → id
        $this->customerMap = [];
        $customerRows = DB::table('customers')->whereNull('deleted_at')->get(['id', 'code']);
        foreach ($customerRows as $row) {
            $this->customerMap[strtolower((string) $row->code)] = (int) $row->id;
        }

        // Pre-load all product skus → id, preferring target cabang
        $this->productMap = [];
        $productRows = DB::table('products')
            ->whereNull('deleted_at')
            ->orderByRaw('CASE WHEN cabang_id = ? THEN 0 ELSE 1 END', [$targetCabangId])
            ->get(['id', 'sku']);
        foreach ($productRows as $row) {
            $key = strtolower((string) $row->sku);
            if (! isset($this->productMap[$key])) {
                $this->productMap[$key] = (int) $row->id;
            }
        }
    }

    private function resolveCustomerIdFromMap(string $sourceName, string $partyCode): ?int
    {
        if ($partyCode === '') {
            return null;
        }

        $candidates = $sourceName === 'inventory_cab'
            ? ['CAB-' . $partyCode, $partyCode]
            : [$partyCode];

        foreach ($candidates as $candidate) {
            $id = $this->customerMap[strtolower($candidate)] ?? null;
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    private function resolveProductIdFromMap(string $sourceName, string $productCode): ?int
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

    private function resolveCustomerId(string $sourceName, string $partyCode): ?int
    {
        if ($partyCode === '') {
            return null;
        }

        $candidates = $sourceName === 'inventory_cab'
            ? ['CAB-' . $partyCode, $partyCode]
            : [$partyCode];

        return $this->resolveByCodes('customers', 'code', $candidates);
    }

    private function resolveProductId(string $sourceName, string $productCode, int $targetCabangId): ?int
    {
        if ($productCode === '') {
            return null;
        }

        $candidates = $sourceName === 'inventory_cab'
            ? ['CAB-' . $productCode, $productCode]
            : [$productCode];

        $products = DB::table('products')
            ->whereNull('deleted_at')
            ->whereIn('sku', $candidates)
            ->orderByRaw('CASE WHEN cabang_id = ? THEN 0 ELSE 1 END', [$targetCabangId])
            ->get(['id', 'sku']);

        foreach ($candidates as $candidate) {
            $match = $products->firstWhere('sku', $candidate);
            if ($match) {
                return (int) $match->id;
            }
        }

        return null;
    }

    private function resolveByCodes(string $table, string $column, array $candidates): ?int
    {
        $candidates = array_values(array_unique(array_filter($candidates, fn ($value) => trim((string) $value) !== '')));
        if ($candidates === []) {
            return null;
        }

        $rows = DB::table($table)
            ->whereNull('deleted_at')
            ->whereIn($column, $candidates)
            ->get(['id', $column]);

        foreach ($candidates as $candidate) {
            $match = $rows->firstWhere($column, $candidate);
            if ($match) {
                return (int) $match->id;
            }
        }

        return null;
    }

    private function legacySoNumber(string $sourceName, int $legacyId): string
    {
        $prefix = $sourceName === 'inventory_cab' ? 'CAB' : 'INV';

        return sprintf('LEGACY-SO-%s-%08d', $prefix, $legacyId);
    }

    private function mapStatus(string $status, string $paymentStatus, string $deliveryStatus, string $receiveStatus): string
    {
        $combined = strtolower(trim($status . ' ' . $paymentStatus . ' ' . $deliveryStatus . ' ' . $receiveStatus));

        return match (true) {
            str_contains($combined, 'batal'), str_contains($combined, 'cancel'), str_contains($combined, 'reject') => 'canceled',
            str_contains($combined, 'selesai'), str_contains($combined, 'lunas'), str_contains($combined, 'sudah diterima semua'), str_contains($combined, 'sudah dikirim semua') => 'completed',
            str_contains($combined, 'sebagian'), str_contains($combined, 'partial') => 'confirmed',
            str_contains($combined, 'approve') => 'approved',
            default => 'draft',
        };
    }

    private function mapTaxType(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'INKLUSIF' => 'Inklusif',
            'EKSKLUSIF', 'EKLUSIF' => 'Eksklusif',
            default => 'Non Pajak',
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
                throw new InvalidArgumentException('Source rehydrasi sales harus inventory atau inventory_cab.');
            }
        }

        return array_values(array_unique($sourceNames));
    }

    private function sourceTarget(string $sourceName, array $options): array
    {
        if ($sourceName === 'inventory_cab') {
            return [
                'cabang_id' => (int) ($options['inventory_cab_cabang_id'] ?? 3),
                'warehouse_id' => (int) ($options['inventory_cab_warehouse_id'] ?? 3),
            ];
        }

        return [
            'cabang_id' => (int) ($options['inventory_cabang_id'] ?? 2),
            'warehouse_id' => (int) ($options['inventory_warehouse_id'] ?? 2),
        ];
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

    private function filledString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}