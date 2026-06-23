<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LegacyInventoryMigrationService
{
    private const DEFAULT_STEPS = ['categories', 'uoms', 'customers', 'suppliers', 'products', 'stocks'];

    private array $legacyColumnCache = [];

    public function supportedSources(): array
    {
        return [
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
    }

    public function importMasterData(string $sourceName, array $options = []): array
    {
        $source = $this->resolveSource($sourceName);
        $steps = $this->normalizeSteps($options['only'] ?? null);
        $execute = (bool) ($options['execute'] ?? false);
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $keyPrefix = $this->filledString($options['key_prefix'] ?? null);
        $cabangId = $options['cabang_id'] !== null ? (int) $options['cabang_id'] : null;
        $warehouseId = $options['warehouse_id'] !== null ? (int) $options['warehouse_id'] : null;

        if ($execute && ! $cabangId) {
            throw new InvalidArgumentException('Option --cabang_id wajib diisi saat menjalankan import eksekusi.');
        }

        if ($execute && in_array('stocks', $steps, true) && ! $warehouseId) {
            throw new InvalidArgumentException('Option --warehouse_id wajib diisi saat mengimpor stocks pada mode eksekusi.');
        }

        $resolvedCabangId = $cabangId ?: Cabang::query()->value('id');
        $resolvedWarehouseId = $warehouseId;

        if (in_array('stocks', $steps, true) && ! $resolvedWarehouseId) {
            $resolvedWarehouseId = Warehouse::query()
                ->when($resolvedCabangId, fn ($query) => $query->where('cabang_id', $resolvedCabangId))
                ->value('id') ?: Warehouse::query()->value('id');
        }

        $notes = [];

        if (! $execute) {
            $notes[] = 'Command berjalan dalam mode dry-run. Tambahkan --execute untuk menulis ke database ERP.';
        }

        if ($keyPrefix) {
            $notes[] = "Import memakai prefix key '{$keyPrefix}' agar kode legacy tidak menimpa data yang sudah ada.";
        }

        if (in_array('stocks', $steps, true)) {
            $notes[] = 'Import stocks mengagregasi seluruh saldo legacy per SKU ke satu warehouse ERP yang dipilih.';
        }

        $categoryResult = ['summary' => $this->emptyStepSummary(), 'map' => []];
        $uomResult = ['summary' => $this->emptyStepSummary(), 'map' => [], 'fallback_id' => null];
        $productState = $this->buildProductState();

        $summary = [
            'source' => $source,
            'mode' => $execute ? 'execute' : 'dry-run',
            'steps' => $steps,
            'cabang_id' => $resolvedCabangId,
            'warehouse_id' => $resolvedWarehouseId,
            'notes' => $notes,
            'entities' => [],
        ];

        foreach ($steps as $step) {
            if ($step === 'categories') {
                $categoryResult = $this->importCategories($source, $execute, $limit, $keyPrefix);
                $summary['entities']['categories'] = $categoryResult['summary'];
                continue;
            }

            if ($step === 'uoms') {
                $uomResult = $this->importUoms($source, $execute, $limit);
                $summary['entities']['uoms'] = $uomResult['summary'];
                continue;
            }

            if ($step === 'customers') {
                $summary['entities']['customers'] = $this->importCustomers($source, $resolvedCabangId, $execute, $limit, $keyPrefix);
                continue;
            }

            if ($step === 'suppliers') {
                $summary['entities']['suppliers'] = $this->importSuppliers($source, $resolvedCabangId, $execute, $limit, $keyPrefix);
                continue;
            }

            if ($step === 'products') {
                $productResult = $this->importProducts(
                    $source,
                    $categoryResult['map'],
                    $uomResult['map'],
                    $uomResult['fallback_id'],
                    $resolvedCabangId,
                    $execute,
                    $limit,
                    $keyPrefix,
                    $productState,
                );

                $summary['entities']['products'] = $productResult['summary'];
                $productState = $productResult['product_state'];
                continue;
            }

            if ($step === 'stocks') {
                $summary['entities']['stocks'] = $this->importStocks(
                    $source,
                    $resolvedWarehouseId,
                    $execute,
                    $limit,
                    $productState,
                    $productResult['legacy_sku_map'] ?? [],
                );
            }
        }

        return $summary;
    }

    public function buildImportWorkbookData(string $sourceName, array $options = []): array
    {
        $source = $this->resolveSource($sourceName);
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $keyPrefix = $this->filledString($options['key_prefix'] ?? null);
        $cabangId = $options['cabang_id'] !== null ? (int) $options['cabang_id'] : null;
        $warehouseId = $options['warehouse_id'] !== null ? (int) $options['warehouse_id'] : null;

        $resolvedCabangId = $cabangId ?: Cabang::query()->value('id');
        $resolvedWarehouseId = $warehouseId;

        if (! $resolvedWarehouseId) {
            $resolvedWarehouseId = Warehouse::query()
                ->when($resolvedCabangId, fn ($query) => $query->where('cabang_id', $resolvedCabangId))
                ->value('id') ?: Warehouse::query()->value('id');
        }

        $categoryRows = [];
        $categoryMap = [];
        $sequenceByCategoryCode = [];
        $categorySql = "SELECT id, category_name, category_code, price_increment_percent FROM {$this->legacyTable($source, 'product_categories')} ORDER BY id" . $this->limitClause($limit);

        foreach (DB::cursor($categorySql) as $row) {
            $baseCode = $this->filledString($row->category_code) ?: $this->generatedCode($source['name'], 'CAT', $row->id);
            $code = $this->resolveEntityKey($baseCode, $keyPrefix, $row->id, $sequenceByCategoryCode);

            $categoryRows[] = [
                'legacy_id' => (string) $row->id,
                'kode' => $code,
                'name' => $this->filledString($row->category_name) ?: 'Legacy Category ' . $row->id,
                'kenaikan_harga' => $this->decimal($row->price_increment_percent),
            ];

            $categoryMap[(string) $row->id] = $code;
        }

        if (! collect($categoryRows)->contains(fn (array $row) => $row['kode'] === 'LEGACY-UNCAT')) {
            $categoryRows[] = [
                'legacy_id' => '__fallback__',
                'kode' => 'LEGACY-UNCAT',
                'name' => 'Legacy Uncategorized',
                'kenaikan_harga' => 0,
            ];
        }

        $categoryMap['__fallback__'] = 'LEGACY-UNCAT';

        $uomRows = [];
        $uomMap = [];
        $uomSql = "SELECT DISTINCT satuan FROM {$this->legacyTable($source, 'products')} WHERE COALESCE(TRIM(satuan), '') <> '' ORDER BY satuan" . $this->limitClause($limit);

        foreach (DB::cursor($uomSql) as $row) {
            $name = trim((string) $row->satuan);
            $uomRows[] = [
                'legacy_satuan' => $name,
                'name' => $name,
                'abbreviation' => $this->uomAbbreviation($name),
            ];

            $uomMap[$this->normalizeLabel($name)] = $name;
        }

        if (! collect($uomRows)->contains(fn (array $row) => $this->normalizeLabel($row['name']) === 'PCS')) {
            $uomRows[] = [
                'legacy_satuan' => '__fallback__',
                'name' => 'PCS',
                'abbreviation' => 'PCS',
            ];
        }

        $uomMap['__fallback__'] = 'PCS';

        $customerRows = [];
        $sequenceByCustomerCode = [];
        $customerSql = "SELECT id, customer_name, customer_type, customer_address, customer_phone, customer_special, customer_hp, customer_company, customer_email, customer_tempo, customer_credit, customer_code, customer_fax, customer_desc, customer_nik, customer_paytype FROM {$this->legacyTable($source, 'customers')} ORDER BY id" . $this->limitClause($limit);

        foreach (DB::cursor($customerSql) as $row) {
            $originalCode = $this->filledString($row->customer_code) ?: $this->generatedCode($source['name'], 'CUST', $row->id);
            $code = $this->resolveEntityKey($originalCode, $keyPrefix, $row->id, $sequenceByCustomerCode);

            $customerRows[] = [
                'legacy_id' => (string) $row->id,
                'code' => $code,
                'name' => $this->filledString($row->customer_name) ?: $code,
                'address' => $this->filledString($row->customer_address) ?: '-',
                'phone' => $this->filledString($row->customer_phone) ?: $this->filledString($row->customer_hp) ?: '-',
                'telephone' => $this->filledString($row->customer_phone) ?: $this->filledString($row->customer_hp) ?: '-',
                'email' => $this->filledString($row->customer_email) ?: '',
                'perusahaan' => $this->filledString($row->customer_company) ?: $this->filledString($row->customer_name) ?: '-',
                'tipe' => $this->mapCustomerType($row->customer_type),
                'fax' => $this->filledString($row->customer_fax) ?: '',
                'isSpecial' => $this->mapFlag($row->customer_special),
                'tempo_kredit' => (int) ($row->customer_tempo ?: 0),
                'kredit_limit' => (int) round($this->decimal($row->customer_credit)),
                'tipe_pembayaran' => $this->mapPaymentType($row->customer_paytype),
                'nik_npwp' => $this->filledString($row->customer_nik) ?: '',
                'keterangan' => $this->mergeLegacyNote(
                    $this->filledString($row->customer_desc),
                    $source['name'],
                    'customer_code',
                    $originalCode,
                    $row->id,
                    $keyPrefix,
                ),
                'cabang_id' => $resolvedCabangId,
            ];
        }

        $supplierRows = [];
        $sequenceBySupplierCode = [];
        $supplierSql = "SELECT id, supplier_name, supplier_address, supplier_phone, supplier_hp, supplier_company, supplier_email, supplier_npwp, supplier_tempo, supplier_code, supplier_fax, supplier_contact, supplier_desc FROM {$this->legacyTable($source, 'suppliers')} ORDER BY id" . $this->limitClause($limit);

        foreach (DB::cursor($supplierSql) as $row) {
            $originalCode = $this->filledString($row->supplier_code) ?: $this->generatedCode($source['name'], 'SUP', $row->id);
            $code = $this->resolveEntityKey($originalCode, $keyPrefix, $row->id, $sequenceBySupplierCode);

            $supplierRows[] = [
                'legacy_id' => (string) $row->id,
                'code' => $code,
                'perusahaan' => $this->filledString($row->supplier_company) ?: $this->filledString($row->supplier_name) ?: $code,
                'address' => $this->filledString($row->supplier_address) ?: '-',
                'phone' => $this->filledString($row->supplier_phone) ?: '-',
                'email' => $this->filledString($row->supplier_email) ?: '',
                'handphone' => $this->filledString($row->supplier_hp) ?: '-',
                'fax' => $this->filledString($row->supplier_fax) ?: '',
                'npwp' => $this->filledString($row->supplier_npwp) ?: '',
                'tempo_hutang' => (int) ($row->supplier_tempo ?: 0),
                'kontak_person' => $this->filledString($row->supplier_contact) ?: '',
                'keterangan' => $this->mergeLegacyNote(
                    $this->filledString($row->supplier_desc),
                    $source['name'],
                    'supplier_code',
                    $originalCode,
                    $row->id,
                    $keyPrefix,
                ),
                'cabang_id' => $resolvedCabangId,
            ];
        }

        $productColumns = $this->legacyColumns($source, 'products');
        $productSelectColumns = [
            'id',
            'product_code',
            'product_name',
            'limit_price',
            'tax_value',
            'tax_type',
            'product_category_id',
            'big_capacity',
            'big_sell_qty',
            'cost',
            'satuan',
            'kd_merk',
            'real_cost',
            isset($productColumns['item_value']) ? 'item_value' : '0 AS item_value',
        ];

        $productRows = [];
        $legacySkuMap = [];
        $sequenceBySku = [];
        $productSql = 'SELECT ' . implode(', ', $productSelectColumns) . " FROM {$this->legacyTable($source, 'products')} ORDER BY id" . $this->limitClause($limit);

        foreach (DB::cursor($productSql) as $row) {
            $originalSku = $this->filledString($row->product_code) ?: $this->generatedCode($source['name'], 'PRD', $row->id);
            $sku = $this->resolveEntityKey($originalSku, $keyPrefix, $row->id, $sequenceBySku);
            $legacySkuMap[(string) $row->id] = $sku;
            $limitPrice = $this->decimal($row->limit_price);

            $productRows[] = [
                'legacy_id' => (string) $row->id,
                'sku' => $sku,
                'name' => $this->filledString($row->product_name) ?: $sku,
                'product_category_kode' => $categoryMap[(string) $row->product_category_id] ?? $categoryMap['__fallback__'],
                'cabang_id' => $resolvedCabangId,
                'cost_price' => $this->decimal($row->real_cost ?: $row->cost),
                'sell_price' => $limitPrice,
                'description' => $this->mergeLegacyNote(
                    null,
                    $source['name'],
                    'product_code',
                    $originalSku,
                    $row->id,
                    $keyPrefix,
                ),
                'uom_name' => $uomMap[$this->normalizeLabel($row->satuan)] ?? $uomMap['__fallback__'],
                'supplier_code' => '',
                'harga_batas' => (int) round($limitPrice),
                'item_value' => $this->decimal($row->item_value),
                'tipe_pajak' => $this->mapTaxType($row->tax_type),
                'pajak' => $this->decimal($row->tax_value),
                'jumlah_kelipatan_gudang_besar' => (int) ($row->big_capacity ?: 0),
                'jumlah_jual_kategori_banyak' => (int) ($row->big_sell_qty ?: 0),
                'kode_merk' => $this->filledString($row->kd_merk) ?: '',
                'biaya' => $this->decimal($row->cost),
                'is_manufacture' => 0,
                'is_raw_material' => 0,
                'is_active' => 1,
            ];
        }

        $stockRows = [];
        $stockSql = "SELECT p.id AS legacy_product_id, p.product_code AS sku, COALESCE(inv.qty_available, 0) AS qty_available, COALESCE(inv.qty_reserved, 0) AS qty_reserved, COALESCE(mins.qty_min, 0) AS qty_min FROM {$this->legacyTable($source, 'products')} p LEFT JOIN (SELECT product_id, SUM(COALESCE(qty, 0)) AS qty_available, SUM(COALESCE(qty_booking_mutation, 0) + COALESCE(qty_booking_sale, 0)) AS qty_reserved FROM {$this->legacyTable($source, 'inventories')} GROUP BY product_id) inv ON inv.product_id = p.id LEFT JOIN (SELECT product_id, MAX(COALESCE(min_qty, 0)) AS qty_min FROM {$this->legacyTable($source, 'product_stocks')} GROUP BY product_id) mins ON mins.product_id = p.id ORDER BY p.id" . $this->limitClause($limit);

        foreach (DB::cursor($stockSql) as $row) {
            $sku = $legacySkuMap[(string) $row->legacy_product_id] ?? $this->filledString($row->sku);

            if (! $sku) {
                continue;
            }

            $stockRows[] = [
                'legacy_product_id' => (string) $row->legacy_product_id,
                'sku' => $sku,
                'warehouse_id' => $resolvedWarehouseId,
                'rak_id' => '',
                'qty_available' => $this->decimal($row->qty_available),
                'qty_reserved' => $this->decimal($row->qty_reserved),
                'qty_min' => $this->decimal($row->qty_min),
            ];
        }

        $generatedAt = now();
        $metaRows = [
            ['key' => 'source', 'value' => $source['name']],
            ['key' => 'source_label', 'value' => $source['label']],
            ['key' => 'generated_at', 'value' => $generatedAt->toDateTimeString()],
            ['key' => 'cabang_id', 'value' => (string) $resolvedCabangId],
            ['key' => 'warehouse_id', 'value' => (string) $resolvedWarehouseId],
            ['key' => 'key_prefix', 'value' => $keyPrefix ?: ''],
            ['key' => 'import_command', 'value' => 'php artisan legacy:import-workbook <file.xlsx> --execute'],
            ['key' => 'import_order', 'value' => 'categories,uoms,customers,suppliers,products,stocks'],
            ['key' => 'notes', 'value' => 'Workbook ini memakai business key agar aman di-upsert ke ERP tanpa bergantung pada ID auto increment.'],
        ];

        $summaryRows = [
            ['sheet' => 'categories', 'rows' => count($categoryRows)],
            ['sheet' => 'uoms', 'rows' => count($uomRows)],
            ['sheet' => 'customers', 'rows' => count($customerRows)],
            ['sheet' => 'suppliers', 'rows' => count($supplierRows)],
            ['sheet' => 'products', 'rows' => count($productRows)],
            ['sheet' => 'stocks', 'rows' => count($stockRows)],
        ];

        return [
            'meta' => [
                'columns' => ['key', 'value'],
                'rows' => $metaRows,
            ],
            'summary' => [
                'columns' => ['sheet', 'rows'],
                'rows' => $summaryRows,
            ],
            'categories' => [
                'columns' => ['legacy_id', 'kode', 'name', 'kenaikan_harga'],
                'rows' => $categoryRows,
            ],
            'uoms' => [
                'columns' => ['legacy_satuan', 'name', 'abbreviation'],
                'rows' => $uomRows,
            ],
            'customers' => [
                'columns' => ['legacy_id', 'code', 'name', 'address', 'phone', 'telephone', 'email', 'perusahaan', 'tipe', 'fax', 'isSpecial', 'tempo_kredit', 'kredit_limit', 'tipe_pembayaran', 'nik_npwp', 'keterangan', 'cabang_id'],
                'rows' => $customerRows,
            ],
            'suppliers' => [
                'columns' => ['legacy_id', 'code', 'perusahaan', 'address', 'phone', 'email', 'handphone', 'fax', 'npwp', 'tempo_hutang', 'kontak_person', 'keterangan', 'cabang_id'],
                'rows' => $supplierRows,
            ],
            'products' => [
                'columns' => ['legacy_id', 'sku', 'name', 'product_category_kode', 'cabang_id', 'cost_price', 'sell_price', 'description', 'uom_name', 'supplier_code', 'harga_batas', 'item_value', 'tipe_pajak', 'pajak', 'jumlah_kelipatan_gudang_besar', 'jumlah_jual_kategori_banyak', 'kode_merk', 'biaya', 'is_manufacture', 'is_raw_material', 'is_active'],
                'rows' => $productRows,
            ],
            'stocks' => [
                'columns' => ['legacy_product_id', 'sku', 'warehouse_id', 'rak_id', 'qty_available', 'qty_reserved', 'qty_min'],
                'rows' => $stockRows,
            ],
        ];
    }

    public function auditMerge(int $sampleLimit = 10): array
    {
        $left = $this->resolveSource('inventory');
        $right = $this->resolveSource('inventory_cab');
        $sampleLimit = max(1, $sampleLimit);

        $entities = [
            'customers' => [
                'table' => 'customers',
                'code_column' => 'customer_code',
                'name_column' => 'customer_name',
                'target_table' => 'customers',
                'target_code_column' => 'code',
            ],
            'products' => [
                'table' => 'products',
                'code_column' => 'product_code',
                'name_column' => 'product_name',
                'target_table' => 'products',
                'target_code_column' => 'sku',
            ],
            'suppliers' => [
                'table' => 'suppliers',
                'code_column' => 'supplier_code',
                'name_column' => 'supplier_name',
                'target_table' => 'suppliers',
                'target_code_column' => 'code',
            ],
        ];

        $report = [
            'sources' => [
                $left['name'] => [
                    'label' => $left['label'],
                    'stores' => $this->fetchStores($left),
                ],
                $right['name'] => [
                    'label' => $right['label'],
                    'stores' => $this->fetchStores($right),
                ],
            ],
            'entities' => [],
            'recommendations' => [
                'Gabungkan customer dan supplier berdasarkan code, tetapi review manual untuk nama yang berbeda signifikan.',
                'Jangan gabungkan product berdasarkan nama. Gunakan SKU/code dan audit manual untuk source inventory_cab karena duplikasi internal tinggi.',
                'Impor opening stock per source ke warehouse terpisah jika kedua database legacy akan dibawa bersamaan.',
            ],
        ];

        foreach ($entities as $entity => $config) {
            $leftDuplicates = $this->auditDuplicateCodes($left, $config['table'], $config['code_column'], $config['name_column'], $sampleLimit);
            $rightDuplicates = $this->auditDuplicateCodes($right, $config['table'], $config['code_column'], $config['name_column'], $sampleLimit);
            $overlap = $this->auditOverlap($left, $right, $config['table'], $config['code_column'], $config['name_column'], $sampleLimit);

            $report['entities'][$entity] = [
                'left_rows' => $this->scalar("SELECT COUNT(*) FROM {$this->legacyTable($left, $config['table'])}"),
                'right_rows' => $this->scalar("SELECT COUNT(*) FROM {$this->legacyTable($right, $config['table'])}"),
                'left_duplicate_codes' => $leftDuplicates['duplicate_codes'],
                'left_duplicate_conflicts' => $leftDuplicates['conflicting_duplicates'],
                'left_duplicate_samples' => $leftDuplicates['samples'],
                'right_duplicate_codes' => $rightDuplicates['duplicate_codes'],
                'right_duplicate_conflicts' => $rightDuplicates['conflicting_duplicates'],
                'right_duplicate_samples' => $rightDuplicates['samples'],
                'overlap_codes' => $overlap['overlap_codes'],
                'overlap_name_conflicts' => $overlap['name_conflicts'],
                'overlap_samples' => $overlap['samples'],
                'existing_in_erp_from_left' => $this->countExistingTargetCodes($left, $config),
                'existing_in_erp_from_right' => $this->countExistingTargetCodes($right, $config),
            ];
        }

        return $report;
    }

    private function importCategories(array $source, bool $execute, int $limit, ?string $keyPrefix = null): array
    {
        $summary = $this->emptyStepSummary();
        $map = [];
        $sequenceByCode = [];

        $sql = "SELECT id, category_name, category_code, price_increment_percent FROM {$this->legacyTable($source, 'product_categories')} ORDER BY id" . $this->limitClause($limit);

        foreach (DB::cursor($sql) as $row) {
            $summary['source_rows']++;

            $name = $this->filledString($row->category_name) ?: 'Legacy Category ' . $row->id;
            $baseCode = $this->filledString($row->category_code) ?: $this->generatedCode($source['name'], 'CAT', $row->id);
            $code = $this->resolveEntityKey($baseCode, $keyPrefix, $row->id, $sequenceByCode);
            $markup = $this->decimal($row->price_increment_percent);

            $existing = ProductCategory::withTrashed()->where('kode', $code)->first();
            if (! $existing) {
                $existing = ProductCategory::withTrashed()->where('name', $name)->first();
            }

            $payload = [
                'name' => $name,
                'kode' => $code,
                'kenaikan_harga' => $markup,
            ];

            $status = $this->modelStatus($existing, $payload);
            $summary[$status]++;

            if ($execute) {
                $category = $existing ?: new ProductCategory();
                $category->forceFill($payload);
                if ($category->trashed()) {
                    $category->restore();
                }
                $category->save();
                $map[(string) $row->id] = $category->id;
            } else {
                $map[(string) $row->id] = $existing?->id ?: -1 * $summary['created'];
            }
        }

        if (! $summary['source_rows']) {
            $fallback = $this->ensureFallbackCategory($execute);
            $map['__fallback__'] = $fallback;
        }

        $map['__fallback__'] = $map['__fallback__'] ?? $this->ensureFallbackCategory($execute);

        return ['summary' => $summary, 'map' => $map];
    }

    private function importUoms(array $source, bool $execute, int $limit): array
    {
        $summary = $this->emptyStepSummary();
        $map = [];

        $sql = "SELECT DISTINCT satuan FROM {$this->legacyTable($source, 'products')} WHERE COALESCE(TRIM(satuan), '') <> '' ORDER BY satuan" . $this->limitClause($limit);

        foreach (DB::cursor($sql) as $row) {
            $summary['source_rows']++;

            $name = trim((string) $row->satuan);
            $abbreviation = $this->uomAbbreviation($name);
            $existing = UnitOfMeasure::withTrashed()
                ->where('name', $name)
                ->orWhere('abbreviation', $abbreviation)
                ->first();

            $payload = [
                'name' => $name,
                'abbreviation' => $abbreviation,
            ];

            $status = $this->modelStatus($existing, $payload);
            $summary[$status]++;

            if ($execute) {
                $uom = $existing ?: new UnitOfMeasure();
                $uom->forceFill($payload);
                if ($uom->trashed()) {
                    $uom->restore();
                }
                $uom->save();
                $map[$this->normalizeLabel($name)] = $uom->id;
            } else {
                $map[$this->normalizeLabel($name)] = $existing?->id ?: -1 * $summary['created'];
            }
        }

        $fallbackId = $this->ensureFallbackUom($execute);
        $map['__fallback__'] = $fallbackId;

        return [
            'summary' => $summary,
            'map' => $map,
            'fallback_id' => $fallbackId,
        ];
    }

    private function importCustomers(array $source, ?int $cabangId, bool $execute, int $limit, ?string $keyPrefix = null): array
    {
        $summary = $this->emptyStepSummary();
        $target = DB::table('customers')->select([
            'id', 'code', 'name', 'address', 'phone', 'email', 'perusahaan', 'tipe', 'fax',
            'isSpecial', 'tempo_kredit', 'kredit_limit', 'tipe_pembayaran', 'nik_npwp',
            'keterangan', 'telephone', 'cabang_id', 'deleted_at',
        ])->get()->keyBy('code');

        $sql = "SELECT id, customer_name, customer_type, customer_address, customer_phone, customer_special, customer_hp, customer_company, customer_email, customer_tempo, customer_credit, customer_code, customer_fax, customer_desc, customer_nik, customer_paytype FROM {$this->legacyTable($source, 'customers')} ORDER BY id" . $this->limitClause($limit);

        $batch = [];
        $timestamp = now();
        $sequenceByCode = [];

        foreach (DB::cursor($sql) as $row) {
            $summary['source_rows']++;

            $originalCode = $this->filledString($row->customer_code) ?: $this->generatedCode($source['name'], 'CUST', $row->id);
            $code = $this->resolveEntityKey($originalCode, $keyPrefix, $row->id, $sequenceByCode);
            $payload = [
                'code' => $code,
                'name' => $this->filledString($row->customer_name) ?: $code,
                'address' => $this->filledString($row->customer_address) ?: '-',
                'phone' => $this->filledString($row->customer_phone) ?: $this->filledString($row->customer_hp) ?: '-',
                'telephone' => $this->filledString($row->customer_phone) ?: $this->filledString($row->customer_hp) ?: '-',
                'email' => $this->filledString($row->customer_email) ?: '',
                'perusahaan' => $this->filledString($row->customer_company) ?: $this->filledString($row->customer_name) ?: '-',
                'tipe' => $this->mapCustomerType($row->customer_type),
                'fax' => $this->filledString($row->customer_fax) ?: '',
                'isSpecial' => $this->mapFlag($row->customer_special),
                'tempo_kredit' => (int) ($row->customer_tempo ?: 0),
                'kredit_limit' => (int) round($this->decimal($row->customer_credit)),
                'tipe_pembayaran' => $this->mapPaymentType($row->customer_paytype),
                'nik_npwp' => $this->filledString($row->customer_nik) ?: '',
                'keterangan' => $this->mergeLegacyNote(
                    $this->filledString($row->customer_desc),
                    $source['name'],
                    'customer_code',
                    $originalCode,
                    $row->id,
                    $keyPrefix,
                ),
                'cabang_id' => $cabangId,
                'deleted_at' => null,
            ];

            $existing = $target[$code] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if (! $execute) {
                continue;
            }

            $batch[] = array_merge($payload, [
                'created_at' => $existing->created_at ?? $timestamp,
                'updated_at' => $timestamp,
            ]);

            if (count($batch) >= 500) {
                $this->flushUpsertBatch('customers', $batch, ['code'], array_keys($payload));
                $batch = [];
            }
        }

        if ($execute && $batch) {
            $this->flushUpsertBatch('customers', $batch, ['code'], [
                'name', 'address', 'phone', 'telephone', 'email', 'perusahaan', 'tipe', 'fax', 'isSpecial',
                'tempo_kredit', 'kredit_limit', 'tipe_pembayaran', 'nik_npwp', 'keterangan', 'cabang_id',
                'deleted_at', 'updated_at',
            ]);
        }

        return $summary;
    }

    private function importSuppliers(array $source, ?int $cabangId, bool $execute, int $limit, ?string $keyPrefix = null): array
    {
        $summary = $this->emptyStepSummary();
        $target = DB::table('suppliers')->select([
            'id', 'code', 'perusahaan', 'address', 'phone', 'email', 'handphone', 'fax',
            'npwp', 'tempo_hutang', 'kontak_person', 'keterangan', 'cabang_id', 'deleted_at', 'created_at',
        ])->get()->keyBy('code');

        $sql = "SELECT id, supplier_name, supplier_address, supplier_phone, supplier_hp, supplier_company, supplier_email, supplier_npwp, supplier_tempo, supplier_code, supplier_fax, supplier_contact, supplier_desc FROM {$this->legacyTable($source, 'suppliers')} ORDER BY id" . $this->limitClause($limit);

        $batch = [];
        $timestamp = now();
        $sequenceByCode = [];

        foreach (DB::cursor($sql) as $row) {
            $summary['source_rows']++;

            $originalCode = $this->filledString($row->supplier_code) ?: $this->generatedCode($source['name'], 'SUP', $row->id);
            $code = $this->resolveEntityKey($originalCode, $keyPrefix, $row->id, $sequenceByCode);
            $payload = [
                'code' => $code,
                'perusahaan' => $this->filledString($row->supplier_company) ?: $this->filledString($row->supplier_name) ?: $code,
                'address' => $this->filledString($row->supplier_address) ?: '-',
                'phone' => $this->filledString($row->supplier_phone) ?: '-',
                'email' => $this->filledString($row->supplier_email) ?: '',
                'handphone' => $this->filledString($row->supplier_hp) ?: '-',
                'fax' => $this->filledString($row->supplier_fax) ?: '',
                'npwp' => $this->filledString($row->supplier_npwp) ?: '',
                'tempo_hutang' => (int) ($row->supplier_tempo ?: 0),
                'kontak_person' => $this->filledString($row->supplier_contact),
                'keterangan' => $this->mergeLegacyNote(
                    $this->filledString($row->supplier_desc),
                    $source['name'],
                    'supplier_code',
                    $originalCode,
                    $row->id,
                    $keyPrefix,
                ),
                'cabang_id' => $cabangId,
                'deleted_at' => null,
            ];

            $existing = $target[$code] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if (! $execute) {
                continue;
            }

            $batch[] = array_merge($payload, [
                'created_at' => $existing->created_at ?? $timestamp,
                'updated_at' => $timestamp,
            ]);

            if (count($batch) >= 500) {
                $this->flushUpsertBatch('suppliers', $batch, ['code'], [
                    'perusahaan', 'address', 'phone', 'email', 'handphone', 'fax', 'npwp',
                    'tempo_hutang', 'kontak_person', 'keterangan', 'cabang_id', 'deleted_at', 'updated_at',
                ]);
                $batch = [];
            }
        }

        if ($execute && $batch) {
            $this->flushUpsertBatch('suppliers', $batch, ['code'], [
                'perusahaan', 'address', 'phone', 'email', 'handphone', 'fax', 'npwp',
                'tempo_hutang', 'kontak_person', 'keterangan', 'cabang_id', 'deleted_at', 'updated_at',
            ]);
        }

        return $summary;
    }

    private function importProducts(
        array $source,
        array $categoryMap,
        array $uomMap,
        ?int $fallbackUomId,
        ?int $cabangId,
        bool $execute,
        int $limit,
        ?string $keyPrefix,
        array $productState,
    ): array {
        $summary = $this->emptyStepSummary();
        $fallbackCategoryId = $categoryMap['__fallback__'] ?? $this->ensureFallbackCategory($execute);
        $fallbackUomId = $fallbackUomId ?: ($uomMap['__fallback__'] ?? $this->ensureFallbackUom($execute));

        $productColumns = $this->legacyColumns($source, 'products');
        $selectColumns = [
            'id',
            'product_code',
            'product_name',
            'limit_price',
            'tax_value',
            'tax_type',
            'product_category_id',
            'big_capacity',
            'big_sell_qty',
            'cost',
            'percent_limit',
            'satuan',
            'kd_merk',
            'real_cost',
            isset($productColumns['item_value']) ? 'item_value' : '0 AS item_value',
        ];

        $sql = 'SELECT ' . implode(', ', $selectColumns) . " FROM {$this->legacyTable($source, 'products')} ORDER BY id" . $this->limitClause($limit);

        $batch = [];
        $timestamp = now();
        $nextVirtualId = -1;
        $sequenceByCode = [];
        $legacySkuMap = [];

        foreach (DB::cursor($sql) as $row) {
            $summary['source_rows']++;

            $originalSku = $this->filledString($row->product_code) ?: $this->generatedCode($source['name'], 'PRD', $row->id);
            $sku = $this->resolveEntityKey($originalSku, $keyPrefix, $row->id, $sequenceByCode);
            $legacySkuMap[(string) $row->id] = $sku;
            $categoryId = $categoryMap[(string) $row->product_category_id] ?? $fallbackCategoryId;
            $uomId = $uomMap[$this->normalizeLabel($row->satuan)] ?? $fallbackUomId;
            $limitPrice = $this->decimal($row->limit_price);
            $costPrice = $this->decimal($row->real_cost ?: $row->cost);

            $payload = [
                'sku' => $sku,
                'name' => $this->filledString($row->product_name) ?: $sku,
                'product_category_id' => $categoryId,
                'cabang_id' => $cabangId,
                'cost_price' => $costPrice,
                'sell_price' => $limitPrice,
                'description' => $this->mergeLegacyNote(
                    null,
                    $source['name'],
                    'product_code',
                    $originalSku,
                    $row->id,
                    $keyPrefix,
                ),
                'uom_id' => $uomId,
                'supplier_id' => null,
                'harga_batas' => (int) round($limitPrice),
                'item_value' => $this->decimal($row->item_value),
                'tipe_pajak' => $this->mapTaxType($row->tax_type),
                'pajak' => $this->decimal($row->tax_value),
                'jumlah_kelipatan_gudang_besar' => (int) ($row->big_capacity ?: 0),
                'jumlah_jual_kategori_banyak' => (int) ($row->big_sell_qty ?: 0),
                'kode_merk' => $this->filledString($row->kd_merk) ?: '',
                'biaya' => $this->decimal($row->cost),
                'is_manufacture' => 0,
                'is_raw_material' => 0,
                'is_active' => 1,
                'deleted_at' => null,
            ];

            $existing = $productState[$sku] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if ($execute) {
                $batch[] = array_merge($payload, [
                    'created_at' => $existing->created_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ]);

                if (count($batch) >= 500) {
                    $this->flushUpsertBatch('products', $batch, ['sku'], [
                        'name', 'product_category_id', 'cabang_id', 'cost_price', 'sell_price', 'description',
                        'uom_id', 'supplier_id', 'harga_batas', 'item_value', 'tipe_pajak', 'pajak',
                        'jumlah_kelipatan_gudang_besar', 'jumlah_jual_kategori_banyak', 'kode_merk', 'biaya',
                        'is_manufacture', 'is_raw_material', 'is_active', 'deleted_at', 'updated_at',
                    ]);
                    $batch = [];
                }

                continue;
            }

            $productState[$sku] = (object) array_merge($payload, [
                'id' => $existing->id ?? $nextVirtualId--,
                'created_at' => $existing->created_at ?? $timestamp,
            ]);
        }

        if ($execute && $batch) {
            $this->flushUpsertBatch('products', $batch, ['sku'], [
                'name', 'product_category_id', 'cabang_id', 'cost_price', 'sell_price', 'description',
                'uom_id', 'supplier_id', 'harga_batas', 'item_value', 'tipe_pajak', 'pajak',
                'jumlah_kelipatan_gudang_besar', 'jumlah_jual_kategori_banyak', 'kode_merk', 'biaya',
                'is_manufacture', 'is_raw_material', 'is_active', 'deleted_at', 'updated_at',
            ]);
        }

        if ($execute) {
            $productState = $this->buildProductState();
        }

        return [
            'summary' => $summary,
            'product_state' => $productState,
            'legacy_sku_map' => $legacySkuMap,
        ];
    }

    private function importStocks(array $source, ?int $warehouseId, bool $execute, int $limit, array $productState, array $legacySkuMap = []): array
    {
        if (! $warehouseId) {
            throw new InvalidArgumentException('Warehouse target tidak ditemukan. Isi --warehouse_id atau buat warehouse ERP terlebih dahulu.');
        }

        $summary = $this->emptyStepSummary();
        $existingStocks = DB::table('inventory_stocks')
            ->select('id', 'product_id', 'warehouse_id', 'rak_id', 'qty_available', 'qty_reserved', 'qty_min', 'deleted_at')
            ->where('warehouse_id', $warehouseId)
            ->whereNull('rak_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_id . ':' . $row->warehouse_id . ':0');

        $sql = "SELECT p.id AS legacy_product_id, p.product_code AS sku, COALESCE(inv.qty_available, 0) AS qty_available, COALESCE(inv.qty_reserved, 0) AS qty_reserved, COALESCE(mins.qty_min, 0) AS qty_min FROM {$this->legacyTable($source, 'products')} p LEFT JOIN (SELECT product_id, SUM(COALESCE(qty, 0)) AS qty_available, SUM(COALESCE(qty_booking_mutation, 0) + COALESCE(qty_booking_sale, 0)) AS qty_reserved FROM {$this->legacyTable($source, 'inventories')} GROUP BY product_id) inv ON inv.product_id = p.id LEFT JOIN (SELECT product_id, MAX(COALESCE(min_qty, 0)) AS qty_min FROM {$this->legacyTable($source, 'product_stocks')} GROUP BY product_id) mins ON mins.product_id = p.id ORDER BY p.id" . $this->limitClause($limit);

        foreach (DB::cursor($sql) as $row) {
            $summary['source_rows']++;

            $sku = $legacySkuMap[(string) $row->legacy_product_id] ?? $this->filledString($row->sku);
            if (! $sku || ! isset($productState[$sku])) {
                $summary['skipped']++;
                continue;
            }

            $product = $productState[$sku];
            $payload = [
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'rak_id' => null,
                'qty_available' => (float) $row->qty_available,
                'qty_reserved' => (float) $row->qty_reserved,
                'qty_min' => (float) $row->qty_min,
                'deleted_at' => null,
            ];

            $key = $product->id . ':' . $warehouseId . ':0';
            $existing = $existingStocks[$key] ?? null;
            $status = $this->rowStatus($existing, $payload);
            $summary[$status]++;

            if (! $execute) {
                continue;
            }

            $query = DB::table('inventory_stocks')
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->whereNull('rak_id');

            if ($existing) {
                $query->update([
                    'qty_available' => $payload['qty_available'],
                    'qty_reserved' => $payload['qty_reserved'],
                    'qty_min' => $payload['qty_min'],
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('inventory_stocks')->insert([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'rak_id' => null,
                    'qty_available' => $payload['qty_available'],
                    'qty_reserved' => $payload['qty_reserved'],
                    'qty_min' => $payload['qty_min'],
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            }
        }

        return $summary;
    }

    private function auditDuplicateCodes(array $source, string $tableSuffix, string $codeColumn, string $nameColumn, int $sampleLimit): array
    {
        $sql = "SELECT {$codeColumn} AS code, {$nameColumn} AS name FROM {$this->legacyTable($source, $tableSuffix)} WHERE COALESCE({$codeColumn}, '') <> '' ORDER BY {$codeColumn}";
        $duplicateCodes = 0;
        $conflictingDuplicates = 0;
        $samples = [];
        $activeCode = null;
        $bucket = [];

        foreach (DB::cursor($sql) as $row) {
            if ($activeCode !== null && $row->code !== $activeCode) {
                [$duplicateCodes, $conflictingDuplicates, $samples] = $this->finalizeDuplicateBucket(
                    $activeCode,
                    $bucket,
                    $duplicateCodes,
                    $conflictingDuplicates,
                    $samples,
                    $sampleLimit,
                );
                $bucket = [];
            }

            $activeCode = $row->code;
            $bucket[] = $row->name;
        }

        if ($activeCode !== null) {
            [$duplicateCodes, $conflictingDuplicates, $samples] = $this->finalizeDuplicateBucket(
                $activeCode,
                $bucket,
                $duplicateCodes,
                $conflictingDuplicates,
                $samples,
                $sampleLimit,
            );
        }

        return [
            'duplicate_codes' => $duplicateCodes,
            'conflicting_duplicates' => $conflictingDuplicates,
            'samples' => $samples,
        ];
    }

    private function auditOverlap(array $left, array $right, string $tableSuffix, string $codeColumn, string $nameColumn, int $sampleLimit): array
    {
        $sql = "SELECT a.{$codeColumn} AS code, a.{$nameColumn} AS left_name, b.{$nameColumn} AS right_name FROM {$this->legacyTable($left, $tableSuffix)} a INNER JOIN {$this->legacyTable($right, $tableSuffix)} b ON a.{$codeColumn} = b.{$codeColumn} WHERE COALESCE(a.{$codeColumn}, '') <> '' ORDER BY a.{$codeColumn}";
        $overlapCodes = 0;
        $nameConflicts = 0;
        $samples = [];
        $seenCodes = [];

        foreach (DB::cursor($sql) as $row) {
            if (isset($seenCodes[$row->code])) {
                continue;
            }

            $seenCodes[$row->code] = true;
            $overlapCodes++;

            if ($this->normalizeLabel($row->left_name) === $this->normalizeLabel($row->right_name)) {
                continue;
            }

            $nameConflicts++;

            if (count($samples) < $sampleLimit) {
                $samples[] = [
                    'code' => $row->code,
                    'left_name' => $row->left_name,
                    'right_name' => $row->right_name,
                ];
            }
        }

        return [
            'overlap_codes' => $overlapCodes,
            'name_conflicts' => $nameConflicts,
            'samples' => $samples,
        ];
    }

    private function countExistingTargetCodes(array $source, array $config): int
    {
        $sql = "SELECT COUNT(DISTINCT legacy.{$config['code_column']}) FROM {$this->legacyTable($source, $config['table'])} legacy INNER JOIN {$config['target_table']} target ON target.{$config['target_code_column']} = legacy.{$config['code_column']} WHERE COALESCE(legacy.{$config['code_column']}, '') <> ''";

        return $this->scalar($sql);
    }

    private function fetchStores(array $source): array
    {
        $stores = [];
        $sql = "SELECT id, store_code, store_name, status FROM {$this->legacyTable($source, 'stores')} ORDER BY id";

        foreach (DB::cursor($sql) as $row) {
            $stores[] = [
                'id' => $row->id,
                'code' => $row->store_code,
                'name' => $row->store_name,
                'status' => $row->status,
            ];
        }

        return $stores;
    }

    private function buildProductState(): array
    {
        return DB::table('products')->select([
            'id', 'sku', 'name', 'product_category_id', 'cabang_id', 'cost_price', 'sell_price',
            'description', 'uom_id', 'supplier_id', 'harga_batas', 'item_value', 'tipe_pajak',
            'pajak', 'jumlah_kelipatan_gudang_besar', 'jumlah_jual_kategori_banyak', 'kode_merk',
            'biaya', 'is_manufacture', 'is_raw_material', 'is_active', 'deleted_at', 'created_at',
        ])->get()->keyBy('sku')->all();
    }

    private function ensureFallbackCategory(bool $execute): int
    {
        $existing = ProductCategory::withTrashed()->where('kode', 'LEGACY-UNCAT')->first();
        if (! $existing) {
            $existing = ProductCategory::withTrashed()->where('name', 'Legacy Uncategorized')->first();
        }

        if ($existing) {
            if ($execute) {
                $existing->forceFill([
                    'name' => 'Legacy Uncategorized',
                    'kode' => 'LEGACY-UNCAT',
                    'kenaikan_harga' => 0,
                ]);
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->save();
            }

            return (int) $existing->id;
        }

        if (! $execute) {
            return -999001;
        }

        $category = ProductCategory::create([
            'name' => 'Legacy Uncategorized',
            'kode' => 'LEGACY-UNCAT',
            'kenaikan_harga' => 0,
        ]);

        return (int) $category->id;
    }

    private function ensureFallbackUom(bool $execute): int
    {
        $existing = UnitOfMeasure::withTrashed()->where('name', 'PCS')->orWhere('abbreviation', 'PCS')->first();

        if ($existing) {
            if ($execute) {
                $existing->forceFill([
                    'name' => 'PCS',
                    'abbreviation' => 'PCS',
                ]);
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->save();
            }

            return (int) $existing->id;
        }

        if (! $execute) {
            return -999002;
        }

        $uom = UnitOfMeasure::create([
            'name' => 'PCS',
            'abbreviation' => 'PCS',
        ]);

        return (int) $uom->id;
    }

    private function flushUpsertBatch(string $table, array $batch, array $uniqueBy, array $updateColumns): void
    {
        DB::table($table)->upsert($batch, $uniqueBy, $updateColumns);
    }

    private function emptyStepSummary(): array
    {
        return [
            'source_rows' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
        ];
    }

    private function resolveSource(string $sourceName): array
    {
        $sources = $this->supportedSources();

        if (! isset($sources[$sourceName])) {
            throw new InvalidArgumentException('Source legacy tidak dikenal. Gunakan inventory atau inventory_cab.');
        }

        return $sources[$sourceName];
    }

    private function normalizeSteps(?string $only): array
    {
        if (! $only) {
            return self::DEFAULT_STEPS;
        }

        $steps = array_values(array_filter(array_map('trim', explode(',', $only))));
        $allowed = array_flip(self::DEFAULT_STEPS);

        foreach ($steps as $step) {
            if (! isset($allowed[$step])) {
                throw new InvalidArgumentException("Langkah import tidak dikenali: {$step}");
            }
        }

        if (in_array('products', $steps, true)) {
            if (! in_array('categories', $steps, true)) {
                array_unshift($steps, 'categories');
            }
            if (! in_array('uoms', $steps, true)) {
                array_splice($steps, 1, 0, ['uoms']);
            }
        }

        return array_values(array_unique($steps));
    }

    private function generatedCode(string $sourceName, string $prefix, int|string $id): string
    {
        return strtoupper($prefix . '-' . str_replace('_', '-', $sourceName) . '-' . $id);
    }

    private function resolveEntityKey(string $baseCode, ?string $keyPrefix, int|string $rowId, array &$sequenceByCode): string
    {
        $candidate = $keyPrefix ? $keyPrefix . $baseCode : $baseCode;

        if (! $keyPrefix) {
            return $candidate;
        }

        $bucket = strtolower($candidate);
        $sequenceByCode[$bucket] = ($sequenceByCode[$bucket] ?? 0) + 1;
        $sequence = $sequenceByCode[$bucket];

        if ($sequence === 1) {
            return $candidate;
        }

        return $candidate . '-DUP' . $sequence . '-R' . $rowId;
    }

    private function mergeLegacyNote(?string $text, string $sourceName, string $keyName, string $originalCode, int|string $rowId, ?string $keyPrefix): ?string
    {
        if (! $keyPrefix) {
            return $text;
        }

        $note = sprintf('[legacy:%s %s=%s row_id=%s]', $sourceName, $keyName, $originalCode, $rowId);

        return $text ? trim($text . ' ' . $note) : $note;
    }

    private function mapCustomerType(?string $value): string
    {
        return strtoupper(trim((string) $value)) === 'PKP' ? 'PKP' : 'PRI';
    }

    private function mapPaymentType(?string $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'CREDIT', 'KREDIT' => 'Kredit',
            'COD', 'CASH' => 'COD (Bayar Lunas)',
            default => 'Bebas',
        };
    }

    private function mapTaxType(?string $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'INKLUSIF' => 'Inklusif',
            'EKSKLUSIF' => 'Eksklusif',
            default => 'Non Pajak',
        };
    }

    private function mapFlag(mixed $value): int
    {
        $normalized = strtoupper(trim((string) $value));
        return in_array($normalized, ['Y', 'YES', 'YA', '1', 'TRUE'], true) ? 1 : 0;
    }

    private function scalar(string $sql): int
    {
        return (int) DB::scalar($sql);
    }

    private function legacyTable(array $source, string $tableSuffix): string
    {
        return sprintf('`%s`.`%s_%s`', $source['database'], $source['prefix'], $tableSuffix);
    }

    private function legacyColumns(array $source, string $tableSuffix): array
    {
        $cacheKey = $source['name'] . ':' . $tableSuffix;

        if (isset($this->legacyColumnCache[$cacheKey])) {
            return $this->legacyColumnCache[$cacheKey];
        }

        $tableName = $source['prefix'] . '_' . $tableSuffix;
        $columns = array_map(
            fn ($row) => $row->name,
            DB::select(
                'SELECT COLUMN_NAME AS name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
                [$source['database'], $tableName],
            )
        );

        return $this->legacyColumnCache[$cacheKey] = array_fill_keys($columns, true);
    }

    private function filledString(mixed $value): ?string
    {
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }

    private function decimal(mixed $value): float
    {
        return round((float) ($value ?: 0), 2);
    }

    private function normalizeLabel(mixed $value): string
    {
        $normalized = strtoupper((string) $value);
        $normalized = preg_replace('/\b(PT|CV|TBK)\b/u', '', $normalized);
        $normalized = preg_replace('/[^A-Z0-9]+/u', '', $normalized);
        return trim((string) $normalized);
    }

    private function uomAbbreviation(string $name): string
    {
        $abbreviation = strtoupper(preg_replace('/\s+/', ' ', trim($name)));
        return substr($abbreviation, 0, 20) ?: 'PCS';
    }

    private function modelStatus(mixed $existing, array $payload): string
    {
        if (! $existing) {
            return 'created';
        }

        if ($existing->deleted_at ?? null) {
            return 'updated';
        }

        foreach ($payload as $column => $value) {
            if ($this->valuesDiffer($existing->{$column} ?? null, $value)) {
                return 'updated';
            }
        }

        return 'unchanged';
    }

    private function rowStatus(mixed $existing, array $payload): string
    {
        if (! $existing) {
            return 'created';
        }

        if ($existing->deleted_at ?? null) {
            return 'updated';
        }

        foreach ($payload as $column => $value) {
            if ($this->valuesDiffer($existing->{$column} ?? null, $value)) {
                return 'updated';
            }
        }

        return 'unchanged';
    }

    private function valuesDiffer(mixed $current, mixed $incoming): bool
    {
        if ($current === null || $incoming === null) {
            return $current !== $incoming;
        }

        if (is_numeric($current) && is_numeric($incoming)) {
            return round((float) $current, 4) !== round((float) $incoming, 4);
        }

        return trim((string) $current) !== trim((string) $incoming);
    }

    private function limitClause(int $limit): string
    {
        return $limit > 0 ? ' LIMIT ' . $limit : '';
    }

    private function finalizeDuplicateBucket(
        string $code,
        array $names,
        int $duplicateCodes,
        int $conflictingDuplicates,
        array $samples,
        int $sampleLimit,
    ): array {
        if (count($names) <= 1) {
            return [$duplicateCodes, $conflictingDuplicates, $samples];
        }

        $duplicateCodes++;
        $normalized = array_values(array_unique(array_filter(array_map(fn ($name) => $this->normalizeLabel($name), $names))));

        if (count($normalized) > 1) {
            $conflictingDuplicates++;

            if (count($samples) < $sampleLimit) {
                $samples[] = [
                    'code' => $code,
                    'names' => implode(' | ', array_slice(array_values(array_unique(array_map(fn ($name) => trim((string) $name), $names))), 0, 3)),
                ];
            }
        }

        return [$duplicateCodes, $conflictingDuplicates, $samples];
    }
}