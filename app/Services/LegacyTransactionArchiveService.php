<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyTransactionArchiveService
{
    private const SOURCES = [
        'inventory' => [
            'name' => 'inventory',
            'database' => 'inventory',
            'prefix' => 'knr',
            'label' => 'Legacy Inventory KNR',
        ],
        'inventory_cab' => [
            'name' => 'inventory_cab',
            'database' => 'inventory_cab',
            'prefix' => 'dtm',
            'label' => 'Legacy Inventory CAB DTM',
        ],
    ];

    private const TABLES = [
        ['table' => 'sales', 'group' => 'sales', 'transaction_type' => 'sale', 'row_kind' => 'document'],
        ['table' => 'sales_detail', 'group' => 'sales', 'transaction_type' => 'sale', 'row_kind' => 'detail', 'parent_table' => 'sales', 'parent_column' => 'sale_id'],
        ['table' => 'sales_payment', 'group' => 'sales', 'transaction_type' => 'sale', 'row_kind' => 'payment', 'parent_table' => 'sales', 'parent_column' => 'sale_id'],
        ['table' => 'sales_cost', 'group' => 'sales', 'transaction_type' => 'sale', 'row_kind' => 'cost', 'parent_table' => 'sales', 'parent_column' => 'sale_id'],
        ['table' => 'sales_delivery_info', 'group' => 'sales', 'transaction_type' => 'sale', 'row_kind' => 'delivery_info', 'parent_table' => 'sales', 'parent_column' => 'sale_id'],
        ['table' => 'sales_inventory', 'group' => 'sales', 'transaction_type' => 'sale', 'row_kind' => 'inventory_link', 'parent_table' => 'sales', 'parent_column' => 'sale_id'],
        ['table' => 'sales_photo', 'group' => 'sales', 'transaction_type' => 'sale', 'row_kind' => 'photo', 'parent_table' => 'sales', 'parent_column' => 'sale_id'],
        ['table' => 'sales_retur_payment', 'group' => 'sales', 'transaction_type' => 'sale', 'row_kind' => 'retur_payment', 'parent_table' => 'sales', 'parent_column' => 'sale_id'],
        ['table' => 'purchases', 'group' => 'purchases', 'transaction_type' => 'purchase', 'row_kind' => 'document'],
        ['table' => 'purchases_detail', 'group' => 'purchases', 'transaction_type' => 'purchase', 'row_kind' => 'detail', 'parent_table' => 'purchases', 'parent_column' => 'purchase_id'],
        ['table' => 'purchases_payment', 'group' => 'purchases', 'transaction_type' => 'purchase', 'row_kind' => 'payment', 'parent_table' => 'purchases', 'parent_column' => 'purchase_id'],
        ['table' => 'purchases_cost', 'group' => 'purchases', 'transaction_type' => 'purchase', 'row_kind' => 'cost', 'parent_table' => 'purchases', 'parent_column' => 'purchase_id'],
        ['table' => 'purchases_photo', 'group' => 'purchases', 'transaction_type' => 'purchase', 'row_kind' => 'photo', 'parent_table' => 'purchases', 'parent_column' => 'purchase_id'],
        ['table' => 'purchases_retur_payment', 'group' => 'purchases', 'transaction_type' => 'purchase', 'row_kind' => 'retur_payment', 'parent_table' => 'purchases', 'parent_column' => 'purchase_id'],
        ['table' => 'quotations', 'group' => 'quotations', 'transaction_type' => 'quotation', 'row_kind' => 'document'],
        ['table' => 'quotations_detail', 'group' => 'quotations', 'transaction_type' => 'quotation', 'row_kind' => 'detail', 'parent_table' => 'quotations', 'parent_column' => 'quotation_id'],
        ['table' => 'delivery_letters', 'group' => 'delivery_history', 'transaction_type' => 'delivery', 'row_kind' => 'document'],
        ['table' => 'delivery_letters_detail', 'group' => 'delivery_history', 'transaction_type' => 'delivery', 'row_kind' => 'detail', 'parent_table' => 'delivery_letters', 'parent_column' => 'delivery_letter_id'],
        ['table' => 'mutations', 'group' => 'mutations', 'transaction_type' => 'mutation', 'row_kind' => 'document'],
        ['table' => 'mutations_detail', 'group' => 'mutations', 'transaction_type' => 'mutation', 'row_kind' => 'detail', 'parent_table' => 'mutations', 'parent_column' => 'mutation_id'],
        ['table' => 'mutations_photo', 'group' => 'mutations', 'transaction_type' => 'mutation', 'row_kind' => 'photo', 'parent_table' => 'mutations', 'parent_column' => 'mutation_id'],
        ['table' => 'stock_adjustment', 'group' => 'adjustments', 'transaction_type' => 'stock_adjustment', 'row_kind' => 'adjustment'],
        ['table' => 'stockflows', 'group' => 'stockflows', 'transaction_type' => 'stockflow', 'row_kind' => 'movement'],
        ['table' => 'stock_modification', 'group' => 'modifications', 'transaction_type' => 'stock_modification', 'row_kind' => 'document'],
        ['table' => 'stock_modification_detail', 'group' => 'modifications', 'transaction_type' => 'stock_modification', 'row_kind' => 'detail', 'parent_table' => 'stock_modification', 'parent_column' => 'modification_id'],
        ['table' => 'stock_opname', 'group' => 'opnames', 'transaction_type' => 'stock_opname', 'row_kind' => 'document'],
        ['table' => 'stock_opname_results', 'group' => 'opnames', 'transaction_type' => 'stock_opname', 'row_kind' => 'detail', 'parent_table' => 'stock_opname', 'parent_column' => 'opname_id'],
        ['table' => 'cashflows', 'group' => 'cashflows', 'transaction_type' => 'cashflow', 'row_kind' => 'document'],
        ['table' => 'fund_mutations', 'group' => 'fund_mutations', 'transaction_type' => 'fund_mutation', 'row_kind' => 'document'],
    ];

    private array $tableExistsCache = [];
    private array $lookupCache = [];

    public function import(array $sourceNames, array $options = []): array
    {
        $sources = $this->normalizeSources($sourceNames);
        $tables = $this->normalizeTables($options['only'] ?? null);
        $execute = (bool) ($options['execute'] ?? false);
        $truncate = (bool) ($options['truncate'] ?? false);
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $chunkSize = $this->safeChunkSize(max(100, (int) ($options['chunk'] ?? 1000)));

        $notes = [];
        if (! $execute) {
            $notes[] = 'Command berjalan dalam mode dry-run. Tambahkan --execute untuk menulis arsip transaksi ke ERP.';
        }
        $notes[] = 'Import ini mengarsipkan histori transaksi legacy ke tabel ERP terpisah dan tidak mem-posting ulang efek stok atau jurnal.';

        $summary = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'sources' => $sources,
            'tables' => array_map(fn (array $config) => $config['table'], $tables),
            'limit' => $limit,
            'chunk' => $chunkSize,
            'notes' => $notes,
            'rows' => [],
        ];

        foreach ($sources as $sourceName) {
            $source = $this->resolveSource($sourceName);

            foreach ($tables as $config) {
                $qualifiedTable = $this->legacyTable($source, $config['table']);
                if (! $this->tableExists($qualifiedTable)) {
                    $summary['rows'][] = [
                        'source' => $sourceName,
                        'table' => $config['table'],
                        'group' => $config['group'],
                        'source_rows' => 0,
                        'processed_rows' => 0,
                        'upserted_rows' => 0,
                        'skipped' => 1,
                        'notes' => 'table_missing',
                    ];
                    continue;
                }

                $sourceRows = $this->tableCount($qualifiedTable, $limit);
                $processedRows = 0;
                $upsertedRows = 0;

                if ($execute) {
                    if ($truncate) {
                        DB::table('legacy_transaction_archives')
                            ->where('source_name', $sourceName)
                            ->where('table_name', $config['table'])
                            ->delete();
                    }

                    $buffer = [];
                    foreach (DB::cursor('SELECT * FROM ' . $qualifiedTable . $this->limitClause($limit)) as $row) {
                        $buffer[] = $this->buildArchiveRow($source, $config, (array) $row);
                        $processedRows++;

                        if (count($buffer) >= $chunkSize) {
                            DB::table('legacy_transaction_archives')->upsert(
                                $buffer,
                                ['source_name', 'table_name', 'legacy_id'],
                                $this->updatableColumns()
                            );
                            $upsertedRows += count($buffer);
                            $buffer = [];
                        }
                    }

                    if ($buffer !== []) {
                        DB::table('legacy_transaction_archives')->upsert(
                            $buffer,
                            ['source_name', 'table_name', 'legacy_id'],
                            $this->updatableColumns()
                        );
                        $upsertedRows += count($buffer);
                    }
                }

                $summary['rows'][] = [
                    'source' => $sourceName,
                    'table' => $config['table'],
                    'group' => $config['group'],
                    'source_rows' => $sourceRows,
                    'processed_rows' => $execute ? $processedRows : $sourceRows,
                    'upserted_rows' => $execute ? $upsertedRows : 0,
                    'skipped' => 0,
                    'notes' => '',
                ];
            }
        }

        return $summary;
    }

    private function normalizeSources(array $sourceNames): array
    {
        $sourceNames = array_values(array_filter(array_map('trim', $sourceNames)));
        if ($sourceNames === []) {
            return array_keys(self::SOURCES);
        }

        foreach ($sourceNames as $sourceName) {
            if (! isset(self::SOURCES[$sourceName])) {
                throw new InvalidArgumentException('Source tidak valid. Gunakan inventory atau inventory_cab.');
            }
        }

        return array_values(array_unique($sourceNames));
    }

    private function normalizeTables(?string $only): array
    {
        if ($only === null || trim($only) === '') {
            return self::TABLES;
        }

        $filters = array_values(array_filter(array_map('trim', explode(',', $only))));
        if ($filters === []) {
            return self::TABLES;
        }

        $tables = array_values(array_filter(
            self::TABLES,
            fn (array $config) => in_array($config['group'], $filters, true) || in_array($config['table'], $filters, true)
        ));

        if ($tables === []) {
            throw new InvalidArgumentException('Filter --only tidak cocok dengan group atau table transaksi legacy yang didukung.');
        }

        return $tables;
    }

    private function resolveSource(string $sourceName): array
    {
        return self::SOURCES[$sourceName] ?? throw new InvalidArgumentException('Source legacy tidak dikenal.');
    }

    private function legacyTable(array $source, string $table): string
    {
        return $source['database'] . '.' . $source['prefix'] . '_' . $table;
    }

    private function tableExists(string $qualifiedTable): bool
    {
        return $this->tableExistsCache[$qualifiedTable] ??= DB::scalar('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [
            explode('.', $qualifiedTable, 2)[0],
            explode('.', $qualifiedTable, 2)[1],
        ]) > 0;
    }

    private function tableCount(string $qualifiedTable, int $limit): int
    {
        if ($limit > 0) {
            return (int) DB::table(DB::raw('(SELECT id FROM ' . $qualifiedTable . ' LIMIT ' . $limit . ') AS limited_rows'))->count();
        }

        return (int) DB::scalar('SELECT COUNT(*) FROM ' . $qualifiedTable);
    }

    private function buildArchiveRow(array $source, array $config, array $row): array
    {
        $now = now();
        $party = $this->resolveParty($source, $row);
        $product = $this->resolveProduct($source, $row);
        $location = $this->resolveStore($source, $row['store_id'] ?? $row['location_id'] ?? $row['place_id'] ?? null);
        $origin = $this->resolveStore($source, $row['origin_id'] ?? null);
        $dest = $this->resolveStore($source, $row['dest_id'] ?? null);

        return [
            'source_name' => $source['name'],
            'table_name' => $config['table'],
            'row_kind' => $config['row_kind'],
            'legacy_id' => (int) ($row['id'] ?? 0),
            'transaction_type' => $config['transaction_type'],
            'parent_table_name' => $config['parent_table'] ?? null,
            'parent_legacy_id' => $this->nullableInt($row[$config['parent_column'] ?? ''] ?? $this->detectParentId($row)),
            'document_number' => $this->pickString($row, ['invoice_no', 'purchase_no', 'quotation_no', 'mutation_no', 'adjustment_no', 'reference_no', 'request_no', 'stockflow_no']),
            'reference_number' => $this->pickString($row, ['reference_no', 'quotation_no', 'invoice_no', 'purchase_no', 'mutation_no', 'adjustment_no', 'request_no']),
            'document_date' => $this->pickDateTime($row, ['sale_date', 'purchase_date', 'quotation_date', 'request_date', 'delivery_date', 'created_date', 'invoice_date', 'cashier_date', 'receive_date', 'lunas_date', 'payment_date', 'date']),
            'status' => $this->pickString($row, ['quotation_status', 'request_status', 'sale_status', 'purchase_status', 'origin_status', 'dest_status', 'process_status', 'status']),
            'payment_status' => $this->pickString($row, ['payment_status', 'payment_retur_status']),
            'delivery_status' => $this->pickString($row, ['delivery_status', 'deliver_status']),
            'receive_status' => $this->pickString($row, ['receive_status']),
            'party_type' => $party['type'],
            'party_legacy_id' => $party['legacy_id'],
            'party_code' => $party['code'],
            'party_name' => $party['name'],
            'product_legacy_id' => $this->nullableInt($row['product_id'] ?? null),
            'product_code' => $product['code'] ?? $this->pickString($row, ['product_code']),
            'location_type' => $this->pickString($row, ['location', 'place_type']),
            'location_legacy_id' => $this->nullableInt($row['location_id'] ?? $row['store_id'] ?? $row['place_id'] ?? null),
            'location_name' => $location['name'],
            'origin_type' => $this->pickString($row, ['origin_place']),
            'origin_legacy_id' => $this->nullableInt($row['origin_id'] ?? null),
            'origin_name' => $origin['name'],
            'dest_type' => $this->pickString($row, ['dest_place']),
            'dest_legacy_id' => $this->nullableInt($row['dest_id'] ?? null),
            'dest_name' => $dest['name'],
            'currency_code' => $this->pickString($row, ['currency']),
            'quantity' => $this->pickDecimal($row, ['qty', 'old_qty', 'new_qty']),
            'processed_quantity' => $this->pickDecimal($row, ['qty_deliver', 'qty_receive', 'receive_qty', 'qty_retur']),
            'unit_price' => $this->pickDecimal($row, ['price', 'sell_price', 'limit_price', 'currency_value']),
            'amount' => $this->pickAmount($row),
            'tax_amount' => $this->pickDecimal($row, ['total_tax_sales', 'total_tax_purchases', 'tax_value', 'sales_tax_value', 'purchase_tax_value']),
            'cost_amount' => $this->pickDecimal($row, ['total_cost_sales', 'cost', 'net_cost']),
            'notes' => $this->pickString($row, ['description', 'retur_description', 'receive_note']),
            'payload' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function resolveParty(array $source, array $row): array
    {
        if (isset($row['customer_id']) && $row['customer_id'] !== null && (int) $row['customer_id'] > 0) {
            $party = $this->legacyLookup($source, 'customers')[(string) $row['customer_id']] ?? null;

            return [
                'type' => 'customer',
                'legacy_id' => $this->nullableInt($row['customer_id']),
                'code' => $party['code'] ?? null,
                'name' => $party['name'] ?? $this->pickString($row, ['customer_name', 'customer_invname']),
            ];
        }

        $customerName = $this->pickString($row, ['customer_name', 'customer_invname', 'recipient_name', 'receiver_name']);
        $customerCode = $this->pickString($row, ['customer_code', 'customer_invcode', 'customer_no']);

        if ($customerName !== null || $customerCode !== null) {
            return [
                'type' => 'customer',
                'legacy_id' => $this->nullableInt($row['customer_id'] ?? null),
                'code' => $customerCode,
                'name' => $customerName,
            ];
        }

        if (isset($row['supplier_id']) && $row['supplier_id'] !== null) {
            $party = $this->legacyLookup($source, 'suppliers')[(string) $row['supplier_id']] ?? null;

            return [
                'type' => 'supplier',
                'legacy_id' => $this->nullableInt($row['supplier_id']),
                'code' => $party['code'] ?? null,
                'name' => $party['name'] ?? null,
            ];
        }

        return ['type' => null, 'legacy_id' => null, 'code' => null, 'name' => null];
    }

    private function resolveProduct(array $source, array $row): array
    {
        $legacyProductId = $row['product_id'] ?? null;

        if ($legacyProductId === null || $legacyProductId === '') {
            return [
                'code' => $this->filledString($row['product_code'] ?? null),
                'name' => null,
            ];
        }

        $product = $this->legacyLookup($source, 'products')[(string) $legacyProductId] ?? null;

        return [
            'code' => $product['code'] ?? $this->filledString($row['product_code'] ?? null),
            'name' => $product['name'] ?? null,
        ];
    }

    private function resolveStore(array $source, mixed $legacyStoreId): array
    {
        if ($legacyStoreId === null || $legacyStoreId === '') {
            return ['code' => null, 'name' => null];
        }

        $store = $this->legacyLookup($source, 'stores')[(string) $legacyStoreId] ?? null;

        return [
            'code' => $store['code'] ?? null,
            'name' => $store['name'] ?? null,
        ];
    }

    private function legacyLookup(array $source, string $entity): array
    {
        $cacheKey = $source['name'] . ':' . $entity;
        if (isset($this->lookupCache[$cacheKey])) {
            return $this->lookupCache[$cacheKey];
        }

        $table = match ($entity) {
            'customers' => $this->legacyTable($source, 'customers'),
            'suppliers' => $this->legacyTable($source, 'suppliers'),
            'products' => $this->legacyTable($source, 'products'),
            'stores' => $this->legacyTable($source, 'stores'),
        };

        if (! $this->tableExists($table)) {
            return $this->lookupCache[$cacheKey] = [];
        }

        $lookup = [];
        foreach (DB::cursor('SELECT * FROM ' . $table) as $row) {
            $data = (array) $row;
            $lookup[(string) ($data['id'] ?? '')] = match ($entity) {
                'customers' => [
                    'code' => $this->filledString($data['customer_code'] ?? null),
                    'name' => $this->filledString($data['customer_name'] ?? null),
                ],
                'suppliers' => [
                    'code' => $this->filledString($data['supplier_code'] ?? null),
                    'name' => $this->filledString($data['supplier_company'] ?? null) ?: $this->filledString($data['supplier_name'] ?? null),
                ],
                'products' => [
                    'code' => $this->filledString($data['product_code'] ?? null),
                    'name' => $this->filledString($data['product_name'] ?? null),
                ],
                'stores' => [
                    'code' => $this->filledString($data['store_code'] ?? null),
                    'name' => $this->filledString($data['store_name'] ?? null),
                ],
            };
        }

        return $this->lookupCache[$cacheKey] = $lookup;
    }

    private function detectParentId(array $row): mixed
    {
        foreach (['sale_id', 'purchase_id', 'quotation_id', 'request_id', 'mutation_id', 'opname_id', 'stock_adjustment_id', 'modification_id'] as $column) {
            if (isset($row[$column]) && $row[$column] !== null && $row[$column] !== '') {
                return $row[$column];
            }
        }

        foreach (['delivery_letter_id', 'delivery_id', 'surat_jalan_id', 'sj_id'] as $column) {
            if (isset($row[$column]) && $row[$column] !== null && $row[$column] !== '') {
                return $row[$column];
            }
        }

        return null;
    }

    private function pickAmount(array $row): ?float
    {
        $direct = $this->pickDecimal($row, ['total_quotation', 'total_sales', 'total_purchases', 'total_delivery', 'total_delivery_letters', 'total_payment', 'amount', 'nominal']);
        if ($direct !== null) {
            return $direct;
        }

        $qty = $this->pickDecimal($row, ['qty']);
        $price = $this->pickDecimal($row, ['price', 'sell_price', 'limit_price']);

        if ($qty !== null && $price !== null) {
            return round($qty * $price, 2);
        }

        return null;
    }

    private function pickString(array $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function pickDecimal(array $row, array $columns): ?float
    {
        foreach ($columns as $column) {
            if (! array_key_exists($column, $row) || $row[$column] === null || $row[$column] === '') {
                continue;
            }

            return round((float) $row[$column], 2);
        }

        return null;
    }

    private function pickDateTime(array $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                continue;
            }

            return (string) $value;
        }

        return null;
    }

    private function updatableColumns(): array
    {
        return [
            'row_kind',
            'transaction_type',
            'parent_table_name',
            'parent_legacy_id',
            'document_number',
            'reference_number',
            'document_date',
            'status',
            'payment_status',
            'delivery_status',
            'receive_status',
            'party_type',
            'party_legacy_id',
            'party_code',
            'party_name',
            'product_legacy_id',
            'product_code',
            'location_type',
            'location_legacy_id',
            'location_name',
            'origin_type',
            'origin_legacy_id',
            'origin_name',
            'dest_type',
            'dest_legacy_id',
            'dest_name',
            'currency_code',
            'quantity',
            'processed_quantity',
            'unit_price',
            'amount',
            'tax_amount',
            'cost_amount',
            'notes',
            'payload',
            'updated_at',
        ];
    }

    private function insertableColumns(): array
    {
        return [
            'source_name',
            'table_name',
            'row_kind',
            'legacy_id',
            'transaction_type',
            'parent_table_name',
            'parent_legacy_id',
            'document_number',
            'reference_number',
            'document_date',
            'status',
            'payment_status',
            'delivery_status',
            'receive_status',
            'party_type',
            'party_legacy_id',
            'party_code',
            'party_name',
            'product_legacy_id',
            'product_code',
            'location_type',
            'location_legacy_id',
            'location_name',
            'origin_type',
            'origin_legacy_id',
            'origin_name',
            'dest_type',
            'dest_legacy_id',
            'dest_name',
            'currency_code',
            'quantity',
            'processed_quantity',
            'unit_price',
            'amount',
            'tax_amount',
            'cost_amount',
            'notes',
            'payload',
            'created_at',
            'updated_at',
        ];
    }

    private function safeChunkSize(int $requestedChunkSize): int
    {
        $maxChunkSize = max(100, intdiv(60000, count($this->insertableColumns())));

        return min($requestedChunkSize, $maxChunkSize);
    }

    private function limitClause(int $limit): string
    {
        return $limit > 0 ? ' LIMIT ' . $limit : '';
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
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