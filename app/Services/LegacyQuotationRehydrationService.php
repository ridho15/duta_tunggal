<?php

namespace App\Services;

use App\Models\LegacyTransactionArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyQuotationRehydrationService
{
    public function rehydrate(array $options = []): array
    {
        $sourceNames = $this->normalizeSources($options['source'] ?? []);
        $execute = (bool) ($options['execute'] ?? false);
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $dateFrom = $this->normalizeDate($options['from'] ?? null, false);
        $dateTo = $this->normalizeDate($options['to'] ?? null, true);
        $createdBy = $this->resolveCreatedBy($options['created_by'] ?? null);

        $summary = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'sources' => $sourceNames,
            'date_from' => $dateFrom?->toDateString(),
            'date_to' => $dateTo?->toDateString(),
            'limit' => $limit,
            'created_by' => $createdBy,
            'rows' => [],
            'notes' => [
                'Rehidrasi quotation membuat quotations dan quotation_items aktif dari arsip legacy tanpa men-trigger observer model karena memakai query builder.',
                'Idempotensi dijaga lewat pasangan legacy_source_name + legacy_legacy_id pada quotations.',
                'Jika customer quotation hanya ada di header legacy dan belum ada di master ERP, service ini membuat placeholder customer cabang tujuan saat mode execute.',
            ],
        ];

        foreach ($sourceNames as $sourceName) {
            $target = $this->sourceTarget($sourceName, $options);
            $documents = $this->documentsQuery($sourceName, $dateFrom, $dateTo, $limit)->get();

            $processed = 0;
            $upserted = 0;
            $skippedCustomer = 0;
            $skippedItems = 0;
            $missingProducts = 0;
            $autoCreatedCustomers = 0;

            foreach ($documents as $document) {
                $processed++;
                $customerResult = $this->resolveCustomerId(
                    $sourceName,
                    (string) ($document->party_code ?? ''),
                    (string) ($document->party_name ?? ''),
                    $target['cabang_id'],
                    $document,
                    $execute,
                );

                if ($customerResult['created']) {
                    $autoCreatedCustomers++;
                }

                if (! $customerResult['id'] && ! $customerResult['creatable']) {
                    $skippedCustomer++;
                    continue;
                }

                $detailRows = LegacyTransactionArchive::query()
                    ->where('source_name', $sourceName)
                    ->where('table_name', 'quotations_detail')
                    ->where('parent_legacy_id', $document->legacy_id)
                    ->orderBy('legacy_id')
                    ->get();

                $items = [];
                $documentMissingProducts = 0;

                foreach ($detailRows as $detailRow) {
                    $productId = $this->resolveProductId($sourceName, (string) ($detailRow->product_code ?? ''), $target['cabang_id']);
                    if (! $productId) {
                        $documentMissingProducts++;
                        continue;
                    }

                    $payload = is_array($detailRow->payload) ? $detailRow->payload : [];
                    $quantity = (float) ($detailRow->quantity ?? 0);

                    if ($quantity <= 0) {
                        continue;
                    }

                    $unitPrice = round((float) ($detailRow->unit_price ?? 0), 2);
                    $items[] = [
                        'product_id' => $productId,
                        'notes' => $this->filledString($payload['description'] ?? null),
                        'quantity' => (int) round($quantity),
                        'unit_price' => $unitPrice,
                        'total_price' => round($quantity * $unitPrice, 2),
                        'discount' => 0,
                        'tax' => (int) round((float) ($detailRow->tax_amount ?? ($payload['tax_value'] ?? 0))),
                        'tax_type' => $this->mapTaxType($payload['tax_type'] ?? null),
                    ];
                }

                $missingProducts += $documentMissingProducts;

                if ($items === []) {
                    $skippedItems++;
                    continue;
                }

                if ($execute) {
                    $this->upsertQuotation($document, $items, (int) $customerResult['id'], $target, $createdBy);
                    $upserted++;
                }
            }

            $summary['rows'][] = [
                'source' => $sourceName,
                'target_cabang_id' => $target['cabang_id'],
                'documents' => $documents->count(),
                'processed' => $processed,
                'upserted' => $upserted,
                'skipped_missing_customer' => $skippedCustomer,
                'skipped_without_items' => $skippedItems,
                'missing_products' => $missingProducts,
                'auto_created_customers' => $autoCreatedCustomers,
            ];
        }

        return $summary;
    }

    private function documentsQuery(string $sourceName, ?Carbon $dateFrom, ?Carbon $dateTo, int $limit)
    {
        $query = LegacyTransactionArchive::query()
            ->where('source_name', $sourceName)
            ->where('table_name', 'quotations')
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

    private function upsertQuotation(LegacyTransactionArchive $document, array $items, int $customerId, array $target, int $createdBy): void
    {
        $payload = is_array($document->payload) ? $document->payload : [];
        $documentDate = $document->document_date ? Carbon::parse($document->document_date) : now();
        $validUntil = $this->normalizeDate($payload['valid_until'] ?? null, true);
        $tempoPembayaran = $this->extractTempoPembayaran($payload, $documentDate, $validUntil);
        $status = $this->mapStatus((string) ($document->status ?? ''));
        $now = now();

        DB::transaction(function () use ($document, $items, $customerId, $target, $createdBy, $payload, $documentDate, $validUntil, $tempoPembayaran, $status, $now) {
            $existingId = DB::table('quotations')
                ->where('legacy_source_name', $document->source_name)
                ->where('legacy_legacy_id', $document->legacy_id)
                ->value('id');

            $quotationData = [
                'quotation_number' => $this->resolveQuotationNumber($document, $existingId ? (int) $existingId : null),
                'customer_id' => $customerId,
                'date' => $documentDate,
                'valid_until' => $validUntil,
                'tempo_pembayaran' => $tempoPembayaran,
                'total_amount' => round((float) ($document->amount ?? array_sum(array_column($items, 'total_price'))), 2),
                'status_payment' => 'Belum Bayar',
                'po_file_path' => null,
                'notes' => $this->buildNotes($document, $payload),
                'status' => $status,
                'created_by' => $createdBy,
                'request_approve_by' => $status !== 'draft' ? $createdBy : null,
                'request_approve_at' => $status !== 'draft' ? $documentDate : null,
                'reject_by' => $status === 'reject' ? $createdBy : null,
                'reject_at' => $status === 'reject' ? $documentDate : null,
                'approve_by' => $status === 'approve' ? $createdBy : null,
                'approve_at' => $status === 'approve' ? $documentDate : null,
                'cabang_id' => $target['cabang_id'],
                'legacy_source_name' => $document->source_name,
                'legacy_legacy_id' => (int) $document->legacy_id,
                'legacy_reference_number' => $document->reference_number,
                'updated_at' => $now,
            ];

            if ($existingId) {
                DB::table('quotations')->where('id', $existingId)->update($quotationData);
                $quotationId = (int) $existingId;
            } else {
                $quotationId = (int) DB::table('quotations')->insertGetId(array_merge($quotationData, [
                    'created_at' => $documentDate,
                ]));
            }

            DB::table('quotation_items')->where('quotation_id', $quotationId)->delete();

            $itemRows = array_map(fn (array $item) => array_merge($item, [
                'quotation_id' => $quotationId,
                'created_at' => $documentDate,
                'updated_at' => $now,
                'deleted_at' => null,
            ]), $items);

            DB::table('quotation_items')->insert($itemRows);
        });
    }

    private function resolveCustomerId(string $sourceName, string $partyCode, string $partyName, int $targetCabangId, LegacyTransactionArchive $document, bool $execute): array
    {
        $customerId = $this->resolveCustomerByCode($sourceName, $partyCode);
        if ($customerId) {
            return ['id' => $customerId, 'created' => false, 'creatable' => false];
        }

        $customerId = $this->resolveCustomerByName($partyName, $targetCabangId);
        if ($customerId) {
            return ['id' => $customerId, 'created' => false, 'creatable' => false];
        }

        if ($this->filledString($partyName) === null) {
            return ['id' => null, 'created' => false, 'creatable' => false];
        }

        if (! $execute) {
            return ['id' => 0, 'created' => false, 'creatable' => true];
        }

        $payload = is_array($document->payload) ? $document->payload : [];

        return [
            'id' => $this->createLegacyQuotationCustomer($sourceName, $partyName, $targetCabangId, $document, $payload),
            'created' => true,
            'creatable' => true,
        ];
    }

    private function resolveCustomerByCode(string $sourceName, string $partyCode): ?int
    {
        $partyCode = trim($partyCode);
        if ($partyCode === '') {
            return null;
        }

        $candidates = $sourceName === 'inventory_cab'
            ? ['CAB-' . $partyCode, $partyCode]
            : [$partyCode];

        return $this->resolveByCodes('customers', 'code', $candidates);
    }

    private function resolveCustomerByName(string $partyName, int $targetCabangId): ?int
    {
        $partyName = trim($partyName);
        if ($partyName === '') {
            return null;
        }

        return DB::table('customers')
            ->whereNull('deleted_at')
            ->where('name', $partyName)
            ->orderByRaw('CASE WHEN cabang_id = ? THEN 0 ELSE 1 END', [$targetCabangId])
            ->value('id');
    }

    private function createLegacyQuotationCustomer(string $sourceName, string $partyName, int $targetCabangId, LegacyTransactionArchive $document, array $payload): int
    {
        $existingId = $this->resolveCustomerByName($partyName, $targetCabangId);
        if ($existingId) {
            return (int) $existingId;
        }

        $baseCode = sprintf(
            'LEGACY-QC-%s-%05d',
            $sourceName === 'inventory_cab' ? 'CAB' : 'INV',
            (int) $document->legacy_id,
        );

        $code = $baseCode;
        $suffix = 1;
        while (DB::table('customers')->where('code', $code)->exists()) {
            $code = $baseCode . '-' . $suffix;
            $suffix++;
        }

        $address = $this->filledString($payload['customer_address'] ?? null) ?: '-';
        $note = 'Auto-created from legacy quotation ' . ($document->document_number ?: ('ID ' . $document->legacy_id));

        return (int) DB::table('customers')->insertGetId([
            'name' => $partyName,
            'address' => $address,
            'phone' => '-',
            'email' => '',
            'code' => $code,
            'perusahaan' => $partyName,
            'tipe' => 'PRI',
            'fax' => '',
            'isSpecial' => 0,
            'tempo_kredit' => 0,
            'kredit_limit' => 0,
            'tipe_pembayaran' => 'Bebas',
            'nik_npwp' => '',
            'keterangan' => $note,
            'telephone' => '-',
            'cabang_id' => $targetCabangId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    private function resolveQuotationNumber(LegacyTransactionArchive $document, ?int $existingId): string
    {
        if ($existingId) {
            $existingNumber = DB::table('quotations')->where('id', $existingId)->value('quotation_number');
            if ($this->filledString($existingNumber) !== null) {
                return (string) $existingNumber;
            }
        }

        $preferred = $this->filledString($document->document_number) ?: $this->legacyQuotationNumber($document->source_name, (int) $document->legacy_id);
        $available = DB::table('quotations')
            ->where('quotation_number', $preferred)
            ->when($existingId, fn ($query) => $query->where('id', '!=', $existingId))
            ->doesntExist();

        if ($available) {
            return $preferred;
        }

        return $this->legacyQuotationNumber($document->source_name, (int) $document->legacy_id);
    }

    private function legacyQuotationNumber(string $sourceName, int $legacyId): string
    {
        $prefix = $sourceName === 'inventory_cab' ? 'CAB' : 'INV';

        return sprintf('LEGACY-QO-%s-%08d', $prefix, $legacyId);
    }

    private function buildNotes(LegacyTransactionArchive $document, array $payload): ?string
    {
        $notes = [];

        $description = $this->filledString($document->notes ?? null);
        if ($description) {
            $notes[] = $description;
        }

        $address = $this->filledString($payload['customer_address'] ?? null);
        if ($address && $address !== '-') {
            $notes[] = 'Alamat legacy: ' . $address;
        }

        $notes[] = 'Legacy source: ' . $document->source_name . ' / ' . ($document->document_number ?: ('ID ' . $document->legacy_id));

        return implode("\n", array_values(array_unique($notes)));
    }

    private function extractTempoPembayaran(array $payload, Carbon $documentDate, ?Carbon $validUntil): int
    {
        foreach (['tempo_pembayaran', 'payment_due_days', 'customer_tempo'] as $field) {
            $value = $payload[$field] ?? null;
            if ($value !== null && $value !== '' && is_numeric($value)) {
                return max(0, (int) $value);
            }
        }

        if ($validUntil) {
            return max(0, $documentDate->diffInDays($validUntil, false));
        }

        return 0;
    }

    private function mapStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match (true) {
            str_contains($status, 'reject'), str_contains($status, 'cancel'), str_contains($status, 'batal') => 'reject',
            str_contains($status, 'request') => 'request_approve',
            str_contains($status, 'final'), str_contains($status, 'approve'), str_contains($status, 'approved') => 'approve',
            default => 'draft',
        };
    }

    private function mapTaxType(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'INKLUSIF' => 'PPN Included',
            'EKSKLUSIF', 'EKLUSIF' => 'PPN Excluded',
            default => 'None',
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
                throw new InvalidArgumentException('Source rehidrasi quotation harus inventory atau inventory_cab.');
            }
        }

        return array_values(array_unique($sourceNames));
    }

    private function sourceTarget(string $sourceName, array $options): array
    {
        if ($sourceName === 'inventory_cab') {
            return [
                'cabang_id' => (int) ($options['inventory_cab_cabang_id'] ?? 3),
            ];
        }

        return [
            'cabang_id' => (int) ($options['inventory_cabang_id'] ?? 2),
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