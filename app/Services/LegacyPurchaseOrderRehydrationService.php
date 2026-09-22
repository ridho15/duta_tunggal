<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\LegacyTransactionArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyPurchaseOrderRehydrationService
{
    public function rehydrate(array $options = []): array
    {
        $sourceNames = $this->normalizeSources($options['source'] ?? []);
        $execute = (bool) ($options['execute'] ?? false);
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $dateFrom = $this->normalizeDate($options['from'] ?? null, false);
        $dateTo = $this->normalizeDate($options['to'] ?? null, true);
        $createdBy = $this->resolveCreatedBy($options['created_by'] ?? null);
        $currencyId = $this->resolveRupiahCurrencyId();

        $summary = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'sources' => $sourceNames,
            'date_from' => $dateFrom?->toDateString(),
            'date_to' => $dateTo?->toDateString(),
            'limit' => $limit,
            'created_by' => $createdBy,
            'currency_id' => $currencyId,
            'rows' => [],
            'notes' => [
                'Rehidrasi purchase membuat purchase_orders dan purchase_order_items aktif dari arsip legacy tanpa men-trigger observer model karena memakai query builder.',
                'Idempotensi dijaga lewat pasangan legacy_source_name + legacy_legacy_id pada purchase_orders.',
            ],
        ];

        foreach ($sourceNames as $sourceName) {
            $target = $this->sourceTarget($sourceName, $options);
            $documents = $this->documentsQuery($sourceName, $dateFrom, $dateTo, $limit)->get();

            $processed = 0;
            $upserted = 0;
            $skippedSupplier = 0;
            $skippedItems = 0;
            $missingProducts = 0;

            foreach ($documents as $document) {
                $processed++;
                $supplierId = $this->resolveSupplierId($sourceName, (string) $document->party_code);

                if (! $supplierId) {
                    $skippedSupplier++;
                    continue;
                }

                $detailRows = LegacyTransactionArchive::query()
                    ->where('source_name', $sourceName)
                    ->where('table_name', 'purchases_detail')
                    ->where('parent_legacy_id', $document->legacy_id)
                    ->orderBy('legacy_id')
                    ->get();

                $items = [];
                $documentMissingProducts = 0;

                foreach ($detailRows as $detailRow) {
                    $productId = $this->resolveProductId($sourceName, (string) $detailRow->product_code, $target['cabang_id']);
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
                        'currency_id' => $currencyId,
                        'unit_price' => round((float) ($detailRow->unit_price ?? 0), 2),
                        'discount' => round((float) ($payload['discount_value'] ?? 0), 2),
                        'tax' => round((float) ($payload['purchase_tax_value'] ?? 0), 2),
                        'tipe_pajak' => $this->mapTaxType($payload['purchase_tax_type'] ?? $payload['tax_type'] ?? null),
                    ];
                }

                $missingProducts += $documentMissingProducts;

                if ($items === []) {
                    $skippedItems++;
                    continue;
                }

                if ($execute) {
                    $this->upsertPurchaseOrder($document, $items, $supplierId, $target, $createdBy, $currencyId);
                    $upserted++;
                }
            }

            $summary['rows'][] = [
                'source' => $sourceName,
                'target_cabang_id' => $target['cabang_id'],
                'target_warehouse_id' => $target['warehouse_id'],
                'documents' => $documents->count(),
                'processed' => $processed,
                'upserted' => $upserted,
                'skipped_missing_supplier' => $skippedSupplier,
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
            ->where('table_name', 'purchases')
            ->where('row_kind', 'document')
            ->orderBy('document_date')
            ->orderBy('legacy_id');

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

    private function upsertPurchaseOrder(LegacyTransactionArchive $document, array $items, int $supplierId, array $target, int $createdBy, int $currencyId): void
    {
        $payload = is_array($document->payload) ? $document->payload : [];
        $documentDate = $document->document_date ? Carbon::parse($document->document_date) : now();
        $deliveryDate = $this->normalizeDate($payload['delivery_date'] ?? null, true) ?: $documentDate;
        $expectedDate = $deliveryDate;
        $tempoHutang = $this->extractTempoHutang($payload['purchase_status'] ?? null, $payload['payment_status'] ?? null, $payload['lunas_date'] ?? null, $documentDate);
        $status = $this->mapStatus((string) ($document->status ?? ''), (string) ($document->payment_status ?? ''));
        $now = now();

        DB::transaction(function () use ($document, $items, $supplierId, $target, $createdBy, $currencyId, $documentDate, $deliveryDate, $expectedDate, $tempoHutang, $status, $payload, $now) {
            $existingId = DB::table('purchase_orders')
                ->where('legacy_source_name', $document->source_name)
                ->where('legacy_legacy_id', $document->legacy_id)
                ->value('id');

            $purchaseOrderData = [
                'supplier_id' => $supplierId,
                'po_number' => $this->legacyPoNumber($document->source_name, (int) $document->legacy_id),
                'order_date' => $documentDate,
                'status' => $status,
                'expected_date' => $expectedDate,
                'total_amount' => round((float) ($document->amount ?? 0), 2),
                'is_asset' => 0,
                'warehouse_id' => $target['warehouse_id'],
                'tempo_hutang' => $tempoHutang,
                'note' => $this->filledString($payload['description'] ?? null) ?: $this->filledString($payload['retur_description'] ?? null),
                'created_by' => $createdBy,
                'is_import' => 0,
                'ppn_option' => 'standard',
                'cabang_id' => $target['cabang_id'],
                'legacy_source_name' => $document->source_name,
                'legacy_legacy_id' => (int) $document->legacy_id,
                'legacy_reference_number' => $document->reference_number,
                'updated_at' => $now,
            ];

            if ($status === 'completed') {
                $purchaseOrderData['completed_at'] = $deliveryDate;
            }

            if ($existingId) {
                DB::table('purchase_orders')->where('id', $existingId)->update($purchaseOrderData);
                $purchaseOrderId = (int) $existingId;
            } else {
                $purchaseOrderId = (int) DB::table('purchase_orders')->insertGetId(array_merge($purchaseOrderData, [
                    'created_at' => $documentDate,
                ]));
            }

            DB::table('purchase_order_items')->where('purchase_order_id', $purchaseOrderId)->delete();

            $itemRows = array_map(fn (array $item) => array_merge($item, [
                'purchase_order_id' => $purchaseOrderId,
                'created_at' => $documentDate,
                'updated_at' => $now,
                'deleted_at' => null,
            ]), $items);

            DB::table('purchase_order_items')->insert($itemRows);
        });
    }

    private function resolveSupplierId(string $sourceName, string $partyCode): ?int
    {
        if ($partyCode === '') {
            return null;
        }

        $candidates = $sourceName === 'inventory_cab'
            ? ['CAB-' . $partyCode, $partyCode]
            : [$partyCode];

        return $this->resolveByCodes('suppliers', 'code', $candidates);
    }

    private function resolveProductId(string $sourceName, string $productCode, int $targetCabangId): ?int
    {
        $productCode = trim($productCode);

        if ($productCode === '') {
            return null;
        }

        $candidates = $sourceName === 'inventory_cab'
            ? ['CAB-' . $productCode, $productCode]
            : [$productCode];

        $candidates = array_values(array_unique(array_merge(
            $candidates,
            array_map(static fn (string $candidate) => strtolower($candidate), $candidates)
        )));

        $products = DB::table('products')
            ->whereNull('deleted_at')
            ->whereIn('sku', $candidates)
            ->orderByRaw('CASE WHEN cabang_id = ? THEN 0 ELSE 1 END', [$targetCabangId])
            ->get(['id', 'sku']);

        foreach ($candidates as $candidate) {
            $match = $products->first(function ($row) use ($candidate) {
                return strtolower((string) $row->sku) === strtolower($candidate);
            });
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

        $normalizedCandidates = array_values(array_unique(array_merge(
            $candidates,
            array_map(static fn (string $candidate) => strtolower($candidate), $candidates)
        )));

        $rows = DB::table($table)
            ->whereNull('deleted_at')
            ->whereIn($column, $normalizedCandidates)
            ->get(['id', $column]);

        foreach ($normalizedCandidates as $candidate) {
            $match = $rows->first(function ($row) use ($column, $candidate) {
                return strtolower((string) $row->{$column}) === strtolower($candidate);
            });
            if ($match) {
                return (int) $match->id;
            }
        }

        return null;
    }

    private function legacyPoNumber(string $sourceName, int $legacyId): string
    {
        $prefix = $sourceName === 'inventory_cab' ? 'CAB' : 'INV';

        return sprintf('LEGACY-PO-%s-%08d', $prefix, $legacyId);
    }

    private function mapStatus(string $status, string $paymentStatus): string
    {
        $combined = strtolower(trim($status . ' ' . $paymentStatus));

        return match (true) {
            str_contains($combined, 'cancel'), str_contains($combined, 'reject') => 'closed',
            str_contains($combined, 'lunas'), str_contains($combined, 'paid') => 'paid',
            str_contains($combined, 'selesai'), str_contains($combined, 'completed') => 'completed',
            str_contains($combined, 'process'), str_contains($combined, 'partial') => 'partially_received',
            str_contains($combined, 'approve') => 'approved',
            default => 'draft',
        };
    }

    private function mapTaxType(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'INKLUSIF' => 'Inklusif',
            'EKSKLUSIF', 'EKLUSIF' => 'Eklusif',
            'NON PAJAK', 'NON-PAJAK', 'NONE' => 'Non Pajak',
            default => 'Non Pajak',
        };
    }

    private function extractTempoHutang(mixed ...$values): int
    {
        foreach ($values as $value) {
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return 0;
    }

    private function normalizeSources(array $sourceNames): array
    {
        $sourceNames = array_values(array_filter(array_map('trim', $sourceNames)));

        if ($sourceNames === []) {
            return ['inventory', 'inventory_cab'];
        }

        foreach ($sourceNames as $sourceName) {
            if (! in_array($sourceName, ['inventory', 'inventory_cab'], true)) {
                throw new InvalidArgumentException('Source rehidrasi purchase harus inventory atau inventory_cab.');
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

    private function resolveRupiahCurrencyId(): int
    {
        $id = Currency::withoutGlobalScopes()->where('name', 'Rupiah')->value('id')
            ?? Currency::withoutGlobalScopes()->where('code', 'IDR')->value('id')
            ?? Currency::withoutGlobalScopes()->orderBy('id')->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('currencies')->insertGetId([
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'code' => 'IDR',
            'to_rupiah' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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